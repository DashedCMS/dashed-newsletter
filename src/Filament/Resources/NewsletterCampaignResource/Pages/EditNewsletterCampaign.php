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
                    // $recipient->exists blijft daardoor false, en
                    // UnsubscribeLink::for() herkent daaraan een proefmail:
                    // die wijst naar een uitlegpagina in plaats van naar een
                    // ondertekende link met een id dat nooit heeft bestaan.
                    //
                    // Bewust geen toets aan NewsletterSuppression of aan de
                    // status van een subscriber, in tegenstelling tot
                    // CampaignSender en CampaignRecipients: een beheerder die
                    // zichzelf een proef stuurt moet die mail ook krijgen, ook
                    // als dat adres toevallig op de blokkadelijst staat. Dit is
                    // een besluit, geen vergeten toets.
                    $recipient = new NewsletterCampaignRecipient([
                        'newsletter_campaign_id' => $campaign->id,
                        'email' => $data['email'],
                        'status' => NewsletterCampaignRecipient::STATUS_PENDING,
                    ]);

                    Mail::to($data['email'])->send(new NewsletterCampaignMail($campaign, $recipient));

                    Notification::make()->title('Testmail verstuurd')->success()->send();
                }),

            Action::make('send')
                ->label('Verzenden')
                ->icon('heroicon-o-paper-airplane')
                // Spiegelbeeld van CampaignGuard::problem() en de claim in
                // StartCampaignJob: die weigeren/claimen op precies 'sent' en
                // 'sending', dus deze knop verbergt zich ook alleen daarvoor.
                // Verder bepaalt de guard, via de Placeholder hieronder en de
                // action() eronder, of het werkelijk mag: een afgebroken of
                // mislukte campagne mag dus best op deze knop klikken, en
                // krijgt vervolgens gewoon te zien wat er nog ontbreekt (of,
                // is er niets meer op aan te merken, gaat gewoon van start).
                // Zonder deze knop is bewerken bij 'cancelled'/'failed'
                // (zie NewsletterCampaignResource::getEditAuthorizationResponse())
                // een doodlopende weg: repareren zonder ooit opnieuw te
                // kunnen versturen.
                ->visible(fn (): bool => ! in_array($this->getRecord()->status, [
                    NewsletterCampaign::STATUS_SENT,
                    NewsletterCampaign::STATUS_SENDING,
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

                    // De overgang naar 'sending' gebeurt bewust niet hier, maar
                    // pas in StartCampaignJob::handle(), en met een
                    // voorwaardelijke update in plaats van een gewone save.
                    // CampaignGuard::problem() hierboven leest de status van
                    // de campagne; zou deze knop die status alvast zetten, dan
                    // zou een volgende guard-check (van deze knop, de planner,
                    // of een tweede job) de campagne altijd als "al aan het
                    // verzenden" zien, ook de aanroep die de vlag zelf net
                    // zette. De knop dispatcht dus enkel; de claim op wie de
                    // campagne echt mag starten ligt in de job.
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
