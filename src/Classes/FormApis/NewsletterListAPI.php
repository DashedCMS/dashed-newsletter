<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Classes\FormApis;

use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Dashed\DashedForms\Models\FormInput;
use Filament\Forms\Components\TextInput;
use Dashed\DashedNewsletter\Facades\Newsletter;
use Dashed\DashedNewsletter\Models\NewsletterList;
use Dashed\DashedNewsletter\Models\NewsletterSubscriber;

class NewsletterListAPI
{
    /**
     * dashed-forms roept dit aan om een formulierinzending op de gekozen
     * CMS-nieuwsbrieflijst te zetten, naast (of in plaats van) de
     * Laposta-koppeling. Volgt exact de vorm van
     * Dashed\DashedLaposta\Classes\FormApis\NewsletterAPI::dispatch(): het
     * e-mailadres en de gemapte velden komen uit $formInput->formFields via
     * de geconfigureerde veld-ids in $api, en de uitkomst wordt net als bij
     * Laposta teruggeschreven op $formInput->api_error / api_send in plaats
     * van als returnwaarde.
     */
    public static function dispatch(FormInput $formInput, $api)
    {
        $email = $formInput->formFields->where('form_field_id', $api['email_field_id'] ?? '')->first()->value ?? null;

        $mappedFields = [];
        foreach ($api['customFields'] ?? [] as $customField) {
            $value = $formInput->formFields->where('form_field_id', $customField['field_id'] ?? '')->first()->value ?? null;
            if ($value) {
                $mappedFields[$customField['newsletter_field_key']] = $value;
            }
        }

        try {
            static::subscribeFromInput(
                email: $email,
                list: NewsletterList::findOrFail($api['newsletter_list_id'] ?? null),
                mappedFields: $mappedFields,
                consentText: $api['consent_text'] ?? null,
                ip: $formInput->ip,
            );
        } catch (\Throwable $e) {
            $formInput->api_error = mb_substr($e->getMessage(), 0, 1000);
            $formInput->save();

            return;
        }

        $formInput->api_error = null;
        $formInput->api_send = 1;
        $formInput->save();
    }

    public static function formFields(): array
    {
        return [
            Select::make('newsletter_list_id')
                ->label('Nieuwsbrieflijst')
                ->options(fn () => static::listOptions())
                // De standaardlijst uit de instellingen staat vooraf gekozen.
                // Blijft die leeg, dan kiest de redacteur zelf, zoals eerst.
                ->default(fn () => Newsletter::defaultList()?->id)
                ->required(),
            Select::make('email_field_id')
                ->label(__('Email veld'))
                ->required()
                ->columnSpanFull()
                ->options(fn ($record) => $record ? $record->fields()->where('type', 'input')->where('input_type', 'email')->pluck('name', 'id') : []),
            Repeater::make('customFields')
                ->label(__('Gekoppelde velden'))
                ->schema([
                    Select::make('field_id')
                        ->label(__('Formulierveld'))
                        ->options(fn ($record) => $record ? $record->fields()->where('type', 'input')->pluck('name', 'id') : []),
                    TextInput::make('newsletter_field_key')
                        ->label(__('Sleutel van het nieuwsbriefveld'))
                        ->required(),
                ])
                ->columnSpanFull(),
            Textarea::make('consent_text')
                ->label('Toestemmingstekst')
                ->helperText('De tekst die naast het vinkje staat. Deze wordt letterlijk bewaard als bewijs.')
                ->rows(2)
                ->required(),
        ];
    }

    /**
     * De keuzelijst met nieuwsbrieflijsten voor de formulierkoppeling.
     *
     * Bewust niet gefilterd op de actieve site: een formulier is in dit CMS niet
     * aan een site gebonden (site_id staat op de inzending, niet op het
     * formulier), dus zou filteren lijsten wegnemen die een redacteur nodig
     * heeft. In plaats daarvan staat de sitenaam erachter zodra er meer dan één
     * site is, zodat twee lijsten met dezelfde naam uit elkaar te houden zijn.
     *
     * @return array<int, string>
     */
    public static function listOptions(): array
    {
        $sites = collect(Sites::getSites())->pluck('name', 'id');

        if ($sites->count() < 2) {
            return NewsletterList::pluck('name', 'id')->all();
        }

        return NewsletterList::get(['id', 'name', 'site_id'])
            ->mapWithKeys(fn (NewsletterList $list): array => [
                $list->id => $list->name . ' (' . ($sites[$list->site_id] ?? $list->site_id ?? 'geen site') . ')',
            ])
            ->all();
    }

    /**
     * Losse ingang zodat dit zonder een volledige FormInput te testen is.
     *
     * @param array<string, mixed> $mappedFields
     */
    public static function subscribeFromInput(
        string $email,
        NewsletterList $list,
        array $mappedFields = [],
        ?string $consentText = null,
        ?string $ip = null,
    ): NewsletterSubscriber {
        return Newsletter::subscribe(
            email: $email,
            list: $list,
            fields: $mappedFields,
            source: 'formulier',
            consentText: $consentText,
            ip: $ip,
        );
    }
}
