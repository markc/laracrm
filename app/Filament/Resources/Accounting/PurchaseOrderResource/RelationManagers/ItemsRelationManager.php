<?php

namespace App\Filament\Resources\Accounting\PurchaseOrderResource\RelationManagers;

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
                    ->preload(),
                Forms\Components\TextInput::make('description')
                    ->required(),
                Forms\Components\TextInput::make('quantity')
                    ->numeric()
                    ->required()
                    ->default(1),
                Forms\Components\TextInput::make('unit_price')
                    ->numeric()
                    ->prefix('$')
                    ->required(),
                Forms\Components\TextInput::make('tax_rate')
                    ->numeric()
                    ->suffix('%')
                    ->default(10),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->placeholder('—'),
                Columns\TextColumn::make('description')
                    ->wrap()
                    ->limit(50),
                Columns\TextColumn::make('quantity')
                    ->numeric(4),
                Columns\TextColumn::make('received_quantity')
                    ->label('Received')
                    ->numeric(4),
                Columns\TextColumn::make('unit_price')
                    ->money('AUD'),
                Columns\TextColumn::make('tax_rate')
                    ->suffix('%'),
                Columns\TextColumn::make('total_amount')
                    ->money('AUD'),
            ]);
    }
}
