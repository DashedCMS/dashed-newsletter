<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Actions\DeleteAction;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\RichEditor;
use Filament\Schemas\Components\Utilities\Get;
use Dashed\DashedNewsletter\Models\NewsletterList;
use Dashed\DashedNewsletter\Models\NewsletterSegment;
use Dashed\DashedNewsletter\Models\NewsletterCampaign;
use Dashed\DashedNewsletter\Filament\Resources\NewsletterCampaignResource\Pages\EditNewsletterCampaign;
use Dashed\DashedNewsletter\Filament\Resources\NewsletterCampaignResource\Pages\ListNewsletterCampaigns;
use Dashed\DashedNewsletter\Filament\Resources\NewsletterCampaignResource\Pages\CreateNewsletterCampaign;

class NewsletterCampaignResource extends Resource
{
    protected static ?string $model = NewsletterCampaign::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-paper-airplane';

    protected static string | UnitEnum | null $navigationGroup = 'Communicatie';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Campagnes';

    protected static ?string $label = 'Campagne';

    protected static ?string $pluralLabel = 'Campagnes';

    public static function statusOptions(): array
    {
        return [
            NewsletterCampaign::STATUS_CONCEPT => 'Concept',
            NewsletterCampaign::STATUS_SCHEDULED => 'Ingepland',
            NewsletterCampaign::STATUS_SENDING => 'Bezig met verzenden',
            NewsletterCampaign::STATUS_SENT => 'Verzonden',
            NewsletterCampaign::STATUS_CANCELLED => 'Gestopt',
            NewsletterCampaign::STATUS_FAILED => 'Mislukt',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Campagne')->columnSpanFull()->columns(2)->schema([
                TextInput::make('name')->label('Naam')->required()->maxLength(255)
                    ->helperText('Alleen voor jezelf, dit komt niet in de mail.'),
                Select::make('newsletter_list_id')
                    ->label('Lijst')
                    ->options(fn (): array => NewsletterList::pluck('name', 'id')->all())
                    ->required()
                    ->live(),
                Select::make('newsletter_segment_id')
                    ->label('Segment')
                    ->placeholder('De hele lijst')
                    ->helperText('Laat leeg om naar iedereen op de lijst te sturen.')
                    ->options(fn (Get $get): array => $get('newsletter_list_id')
                        ? NewsletterSegment::where('newsletter_list_id', $get('newsletter_list_id'))->pluck('name', 'id')->all()
                        : []),
                TextInput::make('subject')->label('Onderwerp')->required()->maxLength(255),
                TextInput::make('preheader')->label('Preheader')->maxLength(255)
                    ->helperText('De regel die een mailbox naast het onderwerp toont.'),
                TextInput::make('from_email')->label('Afzenderadres')->email()
                    ->helperText('Laat leeg om dat van de lijst te gebruiken.'),
                TextInput::make('reply_to_email')->label('Antwoordadres')->email(),
            ]),
            Section::make('Inhoud')->columnSpanFull()->schema([
                RichEditor::make('content')->label('')->required()->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Naam')->searchable()->sortable(),
                TextColumn::make('list.name')->label('Lijst'),
                TextColumn::make('segment.name')->label('Segment')->placeholder('Hele lijst'),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state) => self::statusOptions()[$state] ?? $state),
                TextColumn::make('sent_count')->label('Verzonden')
                    ->state(fn (NewsletterCampaign $record): string => $record->sent_count . ' van ' . $record->recipients_count),
                TextColumn::make('scheduled_at')->label('Ingepland')->dateTime()->placeholder('-'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNewsletterCampaigns::route('/'),
            'create' => CreateNewsletterCampaign::route('/create'),
            'edit' => EditNewsletterCampaign::route('/{record}/edit'),
        ];
    }
}
