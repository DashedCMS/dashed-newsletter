<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Filament\Resources\NewsletterCampaignResource\Pages;

use Filament\Actions\Action;
use Illuminate\Support\Facades\Mail;
use Filament\Forms\Components\Radio;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Components\Utilities\Get;
use Dashed\DashedNewsletter\Jobs\StartCampaignJob;
use Dashed\DashedNewsletter\Campaigns\CampaignGuard;
use Dashed\DashedNewsletter\Models\NewsletterCampaign;
use Dashed\DashedNewsletter\Mail\NewsletterCampaignMail;
use Dashed\DashedNewsletter\Filament\Resources\NewsletterCampaignResource;
use Dashed\DashedNewsletter\Models\NewsletterCampaignRecipient;

class EditNewsletterCampaign extends EditRecord
{
    protected static string $resource = NewsletterCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendTest')
                ->label('Testmail sturen')
                ->icon('heroicon-o-beaker')
                ->schema([
                    TextInput::make('email')
                        ->label('Naar welk adres')
                        ->email()
                        ->required()
                        ->default(fn () => auth()->user()?->email),
                ])
                ->action(function (array $data): void {
                    $campaign = $this->getRecord();

                    // Een testmail heeft een ontvangerregel nodig voor de
                    // afmeldlink, maar hoort niet in de telling. Vandaar een
                    // regel die niet wordt opgeslagen: geen aanraking van de
                    // ontvangerstabel, de tellers of de status van de campagne,
                    // dus niets hiervan beinvloedt een latere echte verzending.
                    $recipient = new NewsletterCampaignRecipient([
                        'newsletter_campaign_id' => $campaign->id,
                        'email' => $data['email'],
                        'status' => NewsletterCampaignRecipient::STATUS_PENDING,
                    ]);
                    $recipient->id = 0;

                    Mail::to($data['email'])->send(new NewsletterCampaignMail($campaign, $recipient));

                    Notification::make()->title('Testmail verstuurd')->success()->send();
                }),

            Action::make('send')
                ->label('Verzenden')
                ->icon('heroicon-o-paper-airplane')
                ->visible(fn (): bool => in_array($this->getRecord()->status, [
                    NewsletterCampaign::STATUS_CONCEPT,
                    NewsletterCampaign::STATUS_SCHEDULED,
                ], true))
                ->schema([
                    Placeholder::make('overzicht')
                        ->label('')
                        ->content(function (): string {
                            $campaign = $this->getRecord();
                            $probleem = CampaignGuard::problem($campaign);

                            if ($probleem) {
                                return 'Deze campagne kan nog niet verzonden worden: ' . $probleem;
                            }

                            return 'Klaar om te verzenden naar de gekozen ontvangers. '
                                . 'Uitgeschreven en geblokkeerde adressen vallen automatisch af.';
                        }),
                    Radio::make('when')
                        ->label('Wanneer')
                        ->options(['now' => 'Nu verzenden', 'later' => 'Op een tijdstip'])
                        ->default('now')
                        ->live()
                        ->required(),
                    DateTimePicker::make('scheduled_at')
                        ->label('Tijdstip')
                        ->seconds(false)
                        ->required(fn (Get $get): bool => $get('when') === 'later')
                        ->visible(fn (Get $get): bool => $get('when') === 'later'),
                ])
                ->action(function (array $data): void {
                    $campaign = $this->getRecord();

                    // CampaignGuard::problem() is de enige waarheid over
                    // verzendbaarheid, hier en in de Placeholder hierboven: geen
                    // los oordeel in dit scherm.
                    $probleem = CampaignGuard::problem($campaign);

                    if ($probleem) {
                        Notification::make()->title('Verzenden kan niet')->body($probleem)->danger()->send();

                        return;
                    }

                    if (($data['when'] ?? 'now') === 'later') {
                        $campaign->update([
                            'status' => NewsletterCampaign::STATUS_SCHEDULED,
                            'scheduled_at' => $data['scheduled_at'],
                        ]);

                        Notification::make()->title('Campagne ingepland')->success()->send();

                        return;
                    }

                    // Meteen op 'sending' zetten, vóór het dispatchen van de job.
                    // CampaignGuard::problem() hierboven keek naar de status van
                    // vóór deze klik; zonder deze regel zou een tweede klik (of
                    // een dubbel verzoek) diezelfde 'concept'/'scheduled'-status
                    // aantreffen en StartCampaignJob een tweede keer voor
                    // dezelfde campagne dispatchen. StartCampaignJob::handle()
                    // zet deze status ook zelf, maar dat gebeurt pas als de
                    // wachtrijjob daadwerkelijk draait; met een echte wachtrij
                    // zit daar ruimte tussen. Door de vlag hier al te zetten
                    // ziet een volgende aanroep van de guard, ook binnen
                    // dezelfde paginasessie, meteen de bijgewerkte status.
                    $campaign->update([
                        'status' => NewsletterCampaign::STATUS_SENDING,
                        'started_at' => now(),
                    ]);

                    StartCampaignJob::dispatch($campaign->id);

                    Notification::make()->title('Verzenden gestart')->success()->send();
                }),

            // Zelfde waarschuwing als de verwijderknop in de tabel: de
            // verzendgeschiedenis gaat mee als deze campagne al ontvangers heeft.
            DeleteAction::make()->modalDescription(
                fn (NewsletterCampaign $record): string => NewsletterCampaignResource::deleteWarningDescription($record)
            ),
        ];
    }
}
