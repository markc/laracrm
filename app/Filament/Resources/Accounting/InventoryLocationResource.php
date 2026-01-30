<?php

namespace App\Filament\Resources\Accounting;

use App\Filament\Resources\Accounting\InventoryLocationResource\Pages;
use App\Models\Accounting\InventoryLocation;
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

class InventoryLocationResource extends Resource
{
    protected static ?string $model = InventoryLocation::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Location Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('code')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->helperText('Short code for quick reference (e.g., WH1, MAIN)'),
                        Forms\Components\Textarea::make('address')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('is_default')
                            ->label('Default Location')
                            ->helperText('Default location for new stock movements'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Columns\TextColumn::make('code')
                    ->searchable()
                    ->sortable(),
                Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Columns\IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),
                Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Columns\TextColumn::make('stock_levels_count')
                    ->counts('stockLevels')
                    ->label('Products'),
                Columns\TextColumn::make('stock_levels_sum_quantity_on_hand')
                    ->sum('stockLevels', 'quantity_on_hand')
                    ->label('Total Qty')
                    ->numeric(decimalPlaces: 2),
            ])
            ->filters([
                Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make()
                    ->before(function (InventoryLocation $record) {
                        if ($record->stockLevels()->where('quantity_on_hand', '>', 0)->exists()) {
                            throw new \Exception('Cannot delete location with stock. Transfer or adjust stock first.');
                        }
                    }),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryLocations::route('/'),
            'create' => Pages\CreateInventoryLocation::route('/create'),
            'edit' => Pages\EditInventoryLocation::route('/{record}/edit'),
        ];
    }
}
