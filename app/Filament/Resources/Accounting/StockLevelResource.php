<?php

namespace App\Filament\Resources\Accounting;

use App\Filament\Resources\Accounting\StockLevelResource\Pages;
use App\Models\Accounting\StockLevel;
use App\Services\Accounting\InventoryService;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns;
use Filament\Tables\Filters;
use Filament\Tables\Table;
use UnitEnum;

class StockLevelResource extends Resource
{
    protected static ?string $model = StockLevel::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 2;

    protected static ?string $label = 'Stock Level';

    protected static ?string $pluralLabel = 'Stock Levels';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Columns\TextColumn::make('product.sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),
                Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                Columns\TextColumn::make('location.name')
                    ->label('Location')
                    ->sortable(),
                Columns\TextColumn::make('quantity_on_hand')
                    ->label('On Hand')
                    ->numeric(decimalPlaces: 2)
                    ->alignRight()
                    ->sortable(),
                Columns\TextColumn::make('quantity_reserved')
                    ->label('Reserved')
                    ->numeric(decimalPlaces: 2)
                    ->alignRight()
                    ->color(fn ($state) => $state > 0 ? 'warning' : null),
                Columns\TextColumn::make('quantity_available')
                    ->label('Available')
                    ->numeric(decimalPlaces: 2)
                    ->alignRight()
                    ->color(fn ($state) => $state <= 0 ? 'danger' : 'success')
                    ->weight('bold'),
                Columns\TextColumn::make('reorder_point')
                    ->label('Reorder At')
                    ->numeric(decimalPlaces: 2)
                    ->alignRight()
                    ->toggleable(isToggledHiddenByDefault: true),
                Columns\IconColumn::make('needs_reorder')
                    ->label('Low')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('danger')
                    ->falseIcon('')
                    ->getStateUsing(fn ($record) => $record->needs_reorder),
                Columns\TextColumn::make('last_counted_at')
                    ->label('Last Count')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filters\SelectFilter::make('location_id')
                    ->label('Location')
                    ->relationship('location', 'name'),
                Filters\Filter::make('low_stock')
                    ->label('Low Stock')
                    ->query(fn ($query) => $query->lowStock()),
                Filters\Filter::make('out_of_stock')
                    ->label('Out of Stock')
                    ->query(fn ($query) => $query->outOfStock()),
                Filters\Filter::make('in_stock')
                    ->label('In Stock')
                    ->query(fn ($query) => $query->inStock()),
            ])
            ->recordActions([
                Actions\Action::make('adjust')
                    ->label('Adjust')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->color('warning')
                    ->form([
                        Forms\Components\TextInput::make('new_quantity')
                            ->label('New Quantity')
                            ->numeric()
                            ->required()
                            ->default(fn ($record) => $record->quantity_on_hand),
                        Forms\Components\Textarea::make('notes')
                            ->label('Reason for Adjustment')
                            ->required()
                            ->rows(2),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            app(InventoryService::class)->adjustStock(
                                product: $record->product,
                                newQuantity: $data['new_quantity'],
                                locationId: $record->location_id,
                                notes: $data['notes']
                            );

                            Notification::make()
                                ->title('Stock adjusted successfully')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Adjustment failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Actions\Action::make('history')
                    ->label('History')
                    ->icon('heroicon-o-clock')
                    ->color('gray')
                    ->url(fn ($record) => StockMovementResource::getUrl('index', [
                        'tableFilters[product_id][value]' => $record->product_id,
                    ])),
            ])
            ->defaultSort('product.name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockLevels::route('/'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $lowStock = static::getModel()::lowStock()->count();

        return $lowStock > 0 ? (string) $lowStock : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }
}
