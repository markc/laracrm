<?php

namespace App\Filament\Resources\Accounting\InvoiceResource\RelationManagers;

use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

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
                Forms\Components\TextInput::make('description')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('quantity')
                    ->numeric()
                    ->required()
                    ->default(1),
                Forms\Components\TextInput::make('unit_price')
                    ->numeric()
                    ->prefix('$')
                    ->required(),
                Forms\Components\TextInput::make('discount_percent')
                    ->numeric()
                    ->suffix('%')
                    ->default(0),
                Forms\Components\TextInput::make('tax_rate')
                    ->numeric()
                    ->suffix('%')
                    ->default(10),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->placeholder('-'),
                Columns\TextColumn::make('description')
                    ->limit(30),
                Columns\TextColumn::make('quantity')
                    ->numeric(2),
                Columns\TextColumn::make('unit_price')
                    ->money('AUD'),
                Columns\TextColumn::make('discount_percent')
                    ->suffix('%'),
                Columns\TextColumn::make('tax_amount')
                    ->money('AUD'),
                Columns\TextColumn::make('total_amount')
                    ->money('AUD')
                    ->summarize(Columns\Summarizers\Sum::make()->money('AUD')),
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
