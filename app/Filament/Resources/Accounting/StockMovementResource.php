<?php

namespace App\Filament\Resources\Accounting;

use App\Enums\MovementType;
use App\Filament\Resources\Accounting\StockMovementResource\Pages;
use App\Models\Accounting\InventoryLocation;
use App\Models\Accounting\Product;
use App\Models\Accounting\StockMovement;
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

class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'reference_number';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Movement Details')
                    ->schema([
                        Forms\Components\Select::make('movement_type')
                            ->options([
                                'receipt' => 'Receipt (Stock In)',
                                'shipment' => 'Shipment (Stock Out)',
                                'transfer' => 'Transfer',
                                'adjustment' => 'Adjustment',
                                'return' => 'Return',
                            ])
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('from_location_id', null)),
                        Forms\Components\Select::make('product_id')
                            ->label('Product')
                            ->options(fn () => Product::tracksInventory()->active()->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live(),
                        Forms\Components\TextInput::make('quantity')
                            ->numeric()
                            ->required()
                            ->minValue(0.0001)
                            ->step(0.01),
                        Forms\Components\Select::make('from_location_id')
                            ->label('From Location')
                            ->options(fn () => InventoryLocation::active()->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->visible(fn ($get) => in_array($get('movement_type'), ['shipment', 'transfer']))
                            ->required(fn ($get) => in_array($get('movement_type'), ['shipment', 'transfer'])),
                        Forms\Components\Select::make('to_location_id')
                            ->label('To Location')
                            ->options(fn () => InventoryLocation::active()->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->visible(fn ($get) => in_array($get('movement_type'), ['receipt', 'transfer', 'return']))
                            ->required(fn ($get) => in_array($get('movement_type'), ['receipt', 'transfer', 'return'])),
                        Forms\Components\Select::make('adjustment_location_id')
                            ->label('Location')
                            ->options(fn () => InventoryLocation::active()->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->visible(fn ($get) => $get('movement_type') === 'adjustment')
                            ->required(fn ($get) => $get('movement_type') === 'adjustment'),
                        Forms\Components\Textarea::make('notes')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

                Section::make('Current Stock')
                    ->schema([
                        Forms\Components\Placeholder::make('current_stock')
                            ->label('Current Stock Levels')
                            ->content(function ($get) {
                                $productId = $get('product_id');
                                if (! $productId) {
                                    return 'Select a product to view stock levels';
                                }

                                $product = Product::with('stockLevels.location')->find($productId);
                                if (! $product || $product->stockLevels->isEmpty()) {
                                    return 'No stock recorded for this product';
                                }

                                $lines = $product->stockLevels->map(function ($level) {
                                    return "{$level->location->name}: {$level->quantity_on_hand} (Available: {$level->quantity_available})";
                                });

                                return implode("\n", $lines->toArray());
                            }),
                    ])
                    ->visible(fn ($get) => $get('product_id') !== null)
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Columns\TextColumn::make('reference_number')
                    ->searchable()
                    ->sortable(),
                Columns\TextColumn::make('movement_type')
                    ->badge(),
                Columns\TextColumn::make('product.name')
                    ->searchable()
                    ->sortable()
                    ->limit(30),
                Columns\TextColumn::make('quantity')
                    ->numeric(decimalPlaces: 2)
                    ->alignRight(),
                Columns\TextColumn::make('fromLocation.name')
                    ->label('From')
                    ->placeholder('-'),
                Columns\TextColumn::make('toLocation.name')
                    ->label('To')
                    ->placeholder('-'),
                Columns\TextColumn::make('createdBy.name')
                    ->label('By')
                    ->toggleable(isToggledHiddenByDefault: true),
                Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Filters\SelectFilter::make('movement_type')
                    ->options(MovementType::class),
                Filters\SelectFilter::make('product_id')
                    ->label('Product')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),
                Filters\SelectFilter::make('location')
                    ->label('Location')
                    ->options(fn () => InventoryLocation::pluck('name', 'id'))
                    ->query(function ($query, array $data) {
                        if ($data['value']) {
                            $query->where(function ($q) use ($data) {
                                $q->where('from_location_id', $data['value'])
                                    ->orWhere('to_location_id', $data['value']);
                            });
                        }
                    }),
            ])
            ->recordActions([
                Actions\ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockMovements::route('/'),
            'create' => Pages\CreateStockMovement::route('/create'),
            'view' => Pages\ViewStockMovement::route('/{record}'),
        ];
    }
}
