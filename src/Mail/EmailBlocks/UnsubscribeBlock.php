<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Mail\EmailBlocks;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Builder\Block;
use Dashed\DashedCore\Mail\EmailBlocks\EmailBlock;

/**
 * Het afmeldblok dat een redacteur zelf in de footer van een lijst kan zetten.
 *
 * Dit blok bestaat alleen zodat CampaignRenderer iets heeft om te renderen
 * als iemand er zelf voor kiest de afmeldregel op te maken. Zonder dit blok
 * is 'unsubscribe' als footer-blocktype wel een herkende vlag (CampaignRenderer
 * ziet 'm en onderdrukt dan de standaardregel), maar zonder registratie in
 * cms()->emailBlocks() rendert hij niets: dan verdwijnt de afmeldlink
 * volledig in plaats van dat hij verplaatst wordt. Dat mag nooit gebeuren.
 *
 * De url blijft bewust de letterlijke plaatshouder :unsubscribe_url:. Die
 * wordt pas per ontvanger ingevuld, net als bij de standaardregel in
 * shell.blade.php.
 */
class UnsubscribeBlock extends EmailBlock
{
    public static function contexts(): array
    {
        return [self::CONTEXT_NEWSLETTER];
    }

    public static function key(): string
    {
        return 'unsubscribe';
    }

    public static function label(): string
    {
        return 'Afmeldlink';
    }

    public static function filamentBlock(): Block
    {
        return Block::make(self::key())
            ->label(self::label())
            ->icon('heroicon-o-arrow-right-on-rectangle')
            ->schema([
                TextInput::make('label')->label('Tekst van de link')->default('Afmelden'),
            ]);
    }

    public static function render(array $blockData, array $context): string
    {
        return view('dashed-newsletter::emails.blocks.unsubscribe', [
            'label' => self::substitute($blockData['label'] ?? 'Afmelden', $context),
        ])->render();
    }
}
