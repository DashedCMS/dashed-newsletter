<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Filament\Resources\NewsletterCampaignResource\Pages;

use Filament\Schemas\Schema;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Dashed\DashedNewsletter\Models\NewsletterCampaign;
use Dashed\DashedNewsletter\Campaigns\CampaignRenderer;
use Dashed\DashedNewsletter\Campaigns\CampaignStatistics;
use Dashed\DashedNewsletter\Campaigns\UnsubscribeReasons;
use Dashed\DashedNewsletter\Models\NewsletterCampaignRecipient;
use Dashed\DashedNewsletter\Filament\Actions\DuplicateCampaignAction;
use Dashed\DashedNewsletter\Filament\Resources\NewsletterCampaignResource;

/**
 * Bekijken van een campagne die niet meer bewerkt mag worden.
 *
 * De bewerkgrens blijft waar hij was: getEditAuthorizationResponse() weigert
 * een verzonden of verzendende campagne, want de inhoud moet blijven kloppen
 * met wat er de deur uit is. Bekijken mag altijd, en zonder dit scherm was een
 * verzonden campagne een doodlopende weg.
 */
class ViewNewsletterCampaign extends ViewRecord
{
    protected static string $resource = NewsletterCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DuplicateCampaignAction::make(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Campagne')->columnSpanFull()->columns(3)->schema([
                TextEntry::make('name')->label('Naam'),
                TextEntry::make('list.name')->label('Lijst'),
                TextEntry::make('segment.name')->label('Segment')->placeholder('Hele lijst'),
                TextEntry::make('subject')->label('Onderwerp'),
                TextEntry::make('preheader')->label('Preheader')->placeholder('-'),
                TextEntry::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state): string => NewsletterCampaignResource::statusOptions()[$state] ?? $state),
                TextEntry::make('started_at')->label('Gestart')->dateTime()->placeholder('-'),
                TextEntry::make('completed_at')->label('Afgerond')->dateTime()->placeholder('-'),
                TextEntry::make('failure_reason')->label('Reden mislukt')->placeholder('-')
                    ->visible(fn (NewsletterCampaign $record): bool => $record->status === NewsletterCampaign::STATUS_FAILED),
            ]),

            Section::make('Cijfers')->columnSpanFull()->schema([
                ViewEntry::make('statistieken')
                    ->view('dashed-newsletter::filament.campaign-statistics')
                    ->viewData(fn (NewsletterCampaign $record): array => [
                        'cijfers' => (new CampaignStatistics($record))->totals(),
                        'links' => (new CampaignStatistics($record))->links(),
                    ]),
            ]),

            Section::make('Waarom mensen zich afmeldden')->columnSpanFull()->collapsible()->schema([
                ViewEntry::make('afmeldredenen')
                    ->view('dashed-newsletter::filament.unsubscribe-reasons')
                    ->viewData(fn (NewsletterCampaign $record): array => [
                        'totaal' => UnsubscribeReasons::total(campaign: $record),
                        'zonderReden' => UnsubscribeReasons::withoutReason(campaign: $record),
                        'redenen' => UnsubscribeReasons::totals(campaign: $record),
                        'toelichtingen' => UnsubscribeReasons::comments(campaign: $record),
                        'omschrijvingen' => NewsletterCampaignRecipient::unsubscribeReasons(),
                    ]),
            ]),

            Section::make('De mail')->collapsible()->columnSpanFull()->schema([
                ViewEntry::make('mail')
                    ->view('dashed-newsletter::filament.campaign-preview')
                    ->viewData(fn (NewsletterCampaign $record): array => [
                        // rendered_html is de mail zoals hij verstuurd is. Bij
                        // een concept bestaat die nog niet; dan is een verse
                        // render het dichtst bij de waarheid.
                        //
                        // renderTemplate() en niet renderForSending(): dit is
                        // kijken, geen versturen. Zou hier het verzendpad
                        // gebruikt worden, dan telt een beheerder die een
                        // concept bekijkt mee als opening.
                        'html' => $record->rendered_html
                            ?: app(CampaignRenderer::class)->renderTemplate($record),
                        'breedte' => 'breed',
                    ]),
            ]),
        ]);
    }
}
