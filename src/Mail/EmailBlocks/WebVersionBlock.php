<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Mail\EmailBlocks;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Builder\Block;
use Dashed\DashedCore\Mail\EmailBlocks\EmailBlock;

/**
 * Link naar de webversie van de campagne. De url blijft bewust de letterlijke
 * plaatshouder :web_version_url:, net als bij UnsubscribeBlock: die wordt pas
 * per ontvanger ingevuld door CampaignRenderer::substitute(). Zet dit blok
 * hier al een echte url neer, dan krijgt elke ontvanger dezelfde link naar de
 * webversie van iemand anders.
 */
class WebVersionBlock extends EmailBlock
{
    public static function contexts(): array
    {
        return [self::CONTEXT_NEWSLETTER];
    }

    public static function key(): string
    {
        return 'web-version';
    }

    public static function label(): string
    {
        return 'Bekijk in je browser';
    }

    public static function filamentBlock(): Block
    {
        return Block::make(self::key())
            ->label(self::label())
            ->icon('heroicon-o-globe-alt')
            ->schema([
                TextInput::make('label')->label('Tekst')->default('Bekijk deze mail in je browser'),
            ]);
    }

    public static function render(array $blockData, array $context): string
    {
        // Niet "$blockData['label'] ?:": op een lege $blockData (zoals een
        // blok zonder ingevulde tekst) gooit dat een undefined-array-key-fout.
        // blank() dekt zowel de ontbrekende sleutel als een leeggemaakt veld.
        return view('dashed-newsletter::emails.blocks.web-version', [
            'label' => blank($blockData['label'] ?? null) ? 'Bekijk deze mail in je browser' : $blockData['label'],
        ])->render();
    }
}
