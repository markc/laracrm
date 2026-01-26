<?php

namespace App\Filament\Resources\CRM;

use App\Enums\ActivityType;
use App\Filament\Resources\CRM\ActivityResource\Pages;
use App\Models\CRM\Activity;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns;
use Filament\Tables\Filters;
use Filament\Tables\Table;
use UnitEnum;

class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'subject';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Activity Details')
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->options(ActivityType::class)
                            ->required()
                            ->default('note'),
                        Forms\Components\TextInput::make('subject')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),
                        Forms\Components\Select::make('customer_id')
                            ->relationship('customer', 'company_name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn ($set) => $set('contact_id', null)),
                        Forms\Components\Select::make('contact_id')
                            ->relationship(
                                'contact',
                                'first_name',
                                fn ($query, $get) => $query->where('customer_id', $get('customer_id'))
                            )
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name)
                            ->searchable()
                            ->preload()
                            ->visible(fn ($get) => filled($get('customer_id'))),
                        Forms\Components\Select::make('opportunity_id')
                            ->relationship(
                                'opportunity',
                                'name',
                                fn ($query, $get) => $query->where('customer_id', $get('customer_id'))
                            )
                            ->searchable()
                            ->preload()
                            ->visible(fn ($get) => filled($get('customer_id'))),
                    ])
                    ->columns(3),

                Section::make('Schedule')
                    ->schema([
                        Forms\Components\DateTimePicker::make('activity_date')
                            ->label('Activity Date/Time')
                            ->default(now()),
                        Forms\Components\DateTimePicker::make('due_date')
                            ->label('Due Date'),
                        Forms\Components\DateTimePicker::make('completed_at')
                            ->label('Completed At'),
                        Forms\Components\Select::make('assigned_to')
                            ->relationship('assignedUser', 'name')
                            ->searchable()
                            ->preload()
                            ->default(fn () => auth()->id()),
                    ])
                    ->columns(4),

                Forms\Components\Textarea::make('description')
                    ->rows(4)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Columns\TextColumn::make('type')
                    ->badge(),
                Columns\TextColumn::make('subject')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                Columns\TextColumn::make('customer.company_name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),
                Columns\TextColumn::make('activity_date')
                    ->dateTime()
                    ->sortable(),
                Columns\TextColumn::make('due_date')
                    ->dateTime()
                    ->sortable(),
                Columns\IconColumn::make('completed_at')
                    ->boolean()
                    ->label('Done')
                    ->getStateUsing(fn ($record) => $record->completed_at !== null),
                Columns\TextColumn::make('assignedUser.name')
                    ->label('Assigned To'),
            ])
            ->filters([
                Filters\SelectFilter::make('type')
                    ->options(ActivityType::class),
                Filters\TernaryFilter::make('completed')
                    ->label('Completed')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('completed_at'),
                        false: fn ($query) => $query->whereNull('completed_at'),
                    ),
                Filters\SelectFilter::make('assigned_to')
                    ->relationship('assignedUser', 'name')
                    ->searchable()
                    ->preload(),
                Filters\SelectFilter::make('customer_id')
                    ->relationship('customer', 'company_name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                Actions\Action::make('complete')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->completed_at === null)
                    ->action(fn ($record) => $record->update(['completed_at' => now()])),
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('activity_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivities::route('/'),
            'create' => Pages\CreateActivity::route('/create'),
            'view' => Pages\ViewActivity::route('/{record}'),
            'edit' => Pages\EditActivity::route('/{record}/edit'),
        ];
    }
}
