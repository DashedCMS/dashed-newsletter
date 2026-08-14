<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Mail\EmailBlocks;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Builder\Block;
use Dashed\DashedCore\Mail\EmailBlocks\EmailBlock;

/**
 * Rij met iconen/links naar de sociale kanalen van de lijst. Hoort alleen in
 * een nieuwsbrief thuis, een orderbevestiging heeft hier niets aan.
 */
class SocialBlock extends EmailBlock
{
    /** @var array<string, string> */
    public const KANALEN = [
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'linkedin' => 'LinkedIn',
        'youtube' => 'YouTube',
        'tiktok' => 'TikTok',
        'x' => 'X',
    ];

    public static function contexts(): array
    {
        return [self::CONTEXT_NEWSLETTER];
    }

    public static function key(): string
    {
        return 'social';
    }

    public static function label(): string
    {
        return 'Sociale media';
    }

    public static function filamentBlock(): Block
    {
        return Block::make(self::key())
            ->label(self::label())
            ->icon('heroicon-o-share')
            ->schema([
                Repeater::make('links')
                    ->label('Kanalen')
                    ->schema([
                        Select::make('channel')->label('Kanaal')->options(self::KANALEN)->required(),
                        TextInput::make('url')->label('Link')->url(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function render(array $blockData, array $context): string
    {
        $links = [];

        foreach ($blockData['links'] ?? [] as $link) {
            // Een kanaal zonder link overslaan: een pictogram dat nergens heen
            // gaat is erger dan geen pictogram.
            if (blank($link['url'] ?? null) || blank($link['channel'] ?? null)) {
                continue;
            }

            $links[] = [
                'label' => self::KANALEN[$link['channel']] ?? $link['channel'],
                'url' => $link['url'],
            ];
        }

        return view('dashed-newsletter::emails.blocks.social', ['links' => $links])->render();
    }
}
