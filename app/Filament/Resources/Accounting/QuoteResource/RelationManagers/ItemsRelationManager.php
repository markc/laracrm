<?php

namespace App\Filament\Resources\Accounting\QuoteResource\RelationManagers;

use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $recordTitleAttribute = 'description';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('product_id')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                        if ($state) {
                            $product = \App\Models\Accounting\Product::find($state);
                            if ($product) {
                                $set('description', $product->name);
                                $set('unit_price', $product->unit_price);
                                $set('tax_rate', $product->tax_rate);
                            }
                        }
                    }),
                Forms\Components\Textarea::make('description')
                    ->required()
                    ->rows(2)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('quantity')
                    ->numeric()
                    ->required()
                    ->default(1)
                    ->minValue(0.01)
                    ->live()
                    ->afterStateUpdated(fn ($state, $get, $set) => self::calculateLineTotal($state, $get, $set)),
                Forms\Components\TextInput::make('unit_price')
                    ->numeric()
                    ->prefix('$')
                    ->required()
                    ->minValue(0)
                    ->live()
                    ->afterStateUpdated(fn ($state, $get, $set) => self::calculateLineTotal($state, $get, $set)),
                Forms\Components\TextInput::make('discount_percent')
                    ->numeric()
                    ->suffix('%')
                    ->default(0)
                    ->minValue(0)
                    ->maxValue(100)
                    ->live()
                    ->afterStateUpdated(fn ($state, $get, $set) => self::calculateLineTotal($state, $get, $set)),
                Forms\Components\TextInput::make('tax_rate')
                    ->numeric()
                    ->suffix('%')
                    ->default(10)
                    ->minValue(0)
                    ->maxValue(100)
                    ->live()
                    ->afterStateUpdated(fn ($state, $get, $set) => self::calculateLineTotal($state, $get, $set)),
                Forms\Components\TextInput::make('line_total')
                    ->numeric()
                    ->prefix('$')
                    ->disabled()
                    ->dehydrated(),
                Forms\Components\Hidden::make('sort_order')
                    ->default(0),
            ]);
    }

    protected static function calculateLineTotal($state, $get, $set): void
    {
        $quantity = (float) ($get('quantity') ?? 0);
        $unitPrice = (float) ($get('unit_price') ?? 0);
        $discountPercent = (float) ($get('discount_percent') ?? 0);
        $taxRate = (float) ($get('tax_rate') ?? 0);

        $subtotal = $quantity * $unitPrice;
        $discount = $subtotal * ($discountPercent / 100);
        $taxableAmount = $subtotal - $discount;
        $tax = $taxableAmount * ($taxRate / 100);
        $lineTotal = $taxableAmount + $tax;

        $set('line_total', round($lineTotal, 2));
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Columns\TextColumn::make('product.name')
                    ->label('Product'),
                Columns\TextColumn::make('description')
                    ->limit(30),
                Columns\TextColumn::make('quantity')
                    ->numeric(),
                Columns\TextColumn::make('unit_price')
                    ->money('AUD'),
                Columns\TextColumn::make('discount_percent')
                    ->suffix('%'),
                Columns\TextColumn::make('tax_rate')
                    ->suffix('%'),
                Columns\TextColumn::make('line_total')
                    ->money('AUD'),
            ])
            ->filters([])
            ->headerActions([
                Actions\CreateAction::make(),
            ])
            ->recordActions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('sort_order');
    }
}
