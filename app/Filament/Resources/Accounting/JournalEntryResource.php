<?php

namespace App\Filament\Resources\Accounting;

use App\Filament\Resources\Accounting\JournalEntryResource\Pages;
use App\Filament\Resources\Accounting\JournalEntryResource\RelationManagers;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\JournalEntryService;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns;
use Filament\Tables\Filters;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class JournalEntryResource extends Resource
{
    protected static ?string $model = JournalEntry::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static string|UnitEnum|null $navigationGroup = 'Accounting';

    protected static ?int $navigationSort = 7;

    protected static ?string $recordTitleAttribute = 'entry_number';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Entry Details')
                    ->schema([
                        Forms\Components\TextInput::make('entry_number')
                            ->disabled(),
                        Forms\Components\DatePicker::make('entry_date')
                            ->disabled(),
                        Forms\Components\TextInput::make('description')
                            ->disabled()
                            ->columnSpan(2),
                        Forms\Components\TextInput::make('reference_type')
                            ->label('Reference')
                            ->disabled(),
                        Forms\Components\Toggle::make('is_posted')
                            ->label('Posted')
                            ->disabled(),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Columns\TextColumn::make('entry_number')
                    ->searchable()
                    ->sortable(),
                Columns\TextColumn::make('entry_date')
                    ->date()
                    ->sortable(),
                Columns\TextColumn::make('description')
                    ->limit(60)
                    ->searchable(),
                Columns\IconColumn::make('is_posted')
                    ->label('Posted')
                    ->boolean(),
                Columns\TextColumn::make('reference_type')
                    ->label('Reference')
                    ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '—')
                    ->toggleable(),
                Columns\TextColumn::make('createdBy.name')
                    ->label('Created By')
                    ->toggleable(),
                Columns\TextColumn::make('posted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filters\Filter::make('posted')
                    ->query(fn (Builder $query) => $query->posted())
                    ->label('Posted Only'),
                Filters\Filter::make('unposted')
                    ->query(fn (Builder $query) => $query->unposted())
                    ->label('Unposted Only'),
                Filters\TrashedFilter::make(),
            ])
            ->recordActions([
                Actions\ViewAction::make(),
                Actions\Action::make('post')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => ! $record->is_posted)
                    ->requiresConfirmation()
                    ->modalHeading('Post Journal Entry')
                    ->modalDescription('This will post the entry to the general ledger. This action cannot be undone (only reversed). Continue?')
                    ->action(function ($record) {
                        $result = app(JournalEntryService::class)->postEntry($record);
                        if ($result) {
                            Notification::make()
                                ->title('Journal entry posted')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Entry is already posted')
                                ->warning()
                                ->send();
                        }
                    }),
                Actions\Action::make('reverse')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(fn ($record) => $record->is_posted && ! $record->reversed_by_id)
                    ->form([
                        Forms\Components\TextInput::make('reason')
                            ->required()
                            ->label('Reason for reversal'),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            app(JournalEntryService::class)->reverseEntry($record, $data['reason']);
                            Notification::make()
                                ->title('Journal entry reversed')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->defaultSort('entry_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\LinesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJournalEntries::route('/'),
            'view' => Pages\ViewJournalEntry::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                \Illuminate\Database\Eloquent\SoftDeletingScope::class,
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
