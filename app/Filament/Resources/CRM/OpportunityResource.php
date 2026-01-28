<?php

namespace App\Filament\Resources\CRM;

use App\Enums\OpportunityStage;
use App\Filament\Resources\CRM\OpportunityResource\Pages;
use App\Models\CRM\Opportunity;
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

class OpportunityResource extends Resource
{
    protected static ?string $model = Opportunity::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';

    protected static string|UnitEnum|null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Opportunity Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),
                        Forms\Components\Select::make('customer_id')
                            ->relationship('customer')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->display_name)
                            ->searchable(['company_name', 'first_name', 'last_name', 'email'])
                            ->preload()
                            ->required()
                            ->columnSpan(2),
                        Forms\Components\Select::make('stage')
                            ->options(OpportunityStage::class)
                            ->required()
                            ->default('lead')
                            ->live(),
                        Forms\Components\DatePicker::make('expected_close_date')
                            ->label('Expected Close')
                            ->required(),
                        Forms\Components\TextInput::make('value')
                            ->numeric()
                            ->prefix('$')
                            ->required()
                            ->minValue(0),
                        Forms\Components\TextInput::make('probability')
                            ->numeric()
                            ->suffix('%')
                            ->default(10)
                            ->minValue(0)
                            ->maxValue(100),
                        Forms\Components\Select::make('assigned_to')
                            ->label('Assigned To')
                            ->relationship('assignedUser', 'name')
                            ->searchable()
                            ->preload()
                            ->columnSpan(2),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),

                Section::make('Outcome')
                    ->schema([
                        Forms\Components\DateTimePicker::make('won_at')
                            ->visible(fn ($get) => $get('stage') === 'won'),
                        Forms\Components\DateTimePicker::make('lost_at')
                            ->visible(fn ($get) => $get('stage') === 'lost'),
                        Forms\Components\Textarea::make('lost_reason')
                            ->rows(2)
                            ->visible(fn ($get) => $get('stage') === 'lost')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->visible(fn ($get) => in_array($get('stage'), ['won', 'lost']))
                    ->columnSpanFull(),

                Section::make('Notes')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->hiddenLabel()
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Columns\TextColumn::make('customer.display_name')
                    ->label('Customer')
                    ->searchable(['customer.company_name', 'customer.first_name', 'customer.last_name'])
                    ->sortable(),
                Columns\TextColumn::make('value')
                    ->money('AUD')
                    ->sortable(),
                Columns\TextColumn::make('stage')
                    ->badge(),
                Columns\TextColumn::make('probability')
                    ->suffix('%')
                    ->sortable(),
                Columns\TextColumn::make('expected_close_date')
                    ->date()
                    ->sortable(),
                Columns\TextColumn::make('assignedUser.name')
                    ->label('Assigned To'),
            ])
            ->filters([
                Filters\SelectFilter::make('stage')
                    ->options(OpportunityStage::class),
                Filters\SelectFilter::make('assigned_to')
                    ->relationship('assignedUser', 'name')
                    ->searchable()
                    ->preload(),
                Filters\SelectFilter::make('customer_id')
                    ->relationship('customer', 'id')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->display_name)
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('expected_close_date');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOpportunities::route('/'),
            'create' => Pages\CreateOpportunity::route('/create'),
            'view' => Pages\ViewOpportunity::route('/{record}'),
            'edit' => Pages\EditOpportunity::route('/{record}/edit'),
        ];
    }
}
