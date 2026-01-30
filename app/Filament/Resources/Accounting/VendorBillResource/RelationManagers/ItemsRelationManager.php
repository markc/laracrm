<?php

namespace App\Filament\Resources\Accounting\VendorBillResource\RelationManagers;

use App\Enums\VendorBillStatus;
use App\Models\Accounting\Account;
use App\Services\Accounting\VendorBillService;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Line Items';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('account_id')
                    ->label('Expense Account')
                    ->options(fn () => Account::where('type', 'expense')
                        ->orWhere('type', 'asset')
                        ->active()
                        ->pluck('name', 'id'))
                    ->searchable()
                    ->preload(),
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
                                $set('unit_price', $product->cost_price ?: $product->unit_price);
                                $set('tax_rate', $product->tax_rate);
                                if ($product->expense_account_id) {
                                    $set('account_id', $product->expense_account_id);
                                }
                            }
                        }
                    }),
                Forms\Components\TextInput::make('description')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('quantity')
                    ->numeric()
                    ->default(1)
                    ->required(),
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
                Columns\TextColumn::make('account.name')
                    ->label('Account')
                    ->wrap(),
                Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->wrap()
                    ->toggleable(),
                Columns\TextColumn::make('description')
                    ->wrap()
                    ->limit(50),
                Columns\TextColumn::make('quantity')
                    ->numeric(decimalPlaces: 2)
                    ->alignRight(),
                Columns\TextColumn::make('unit_price')
                    ->money('AUD')
                    ->alignRight(),
                Columns\TextColumn::make('tax_rate')
                    ->suffix('%')
                    ->alignRight(),
                Columns\TextColumn::make('total_amount')
                    ->money('AUD')
                    ->alignRight()
                    ->weight('bold'),
            ])
            ->headerActions([
                \Filament\Tables\Actions\CreateAction::make()
                    ->visible(fn () => $this->ownerRecord->status === VendorBillStatus::Draft)
                    ->after(fn () => app(VendorBillService::class)->recalculateBill($this->ownerRecord)),
            ])
            ->actions([
                \Filament\Tables\Actions\EditAction::make()
                    ->visible(fn () => $this->ownerRecord->status === VendorBillStatus::Draft)
                    ->after(fn () => app(VendorBillService::class)->recalculateBill($this->ownerRecord)),
                \Filament\Tables\Actions\DeleteAction::make()
                    ->visible(fn () => $this->ownerRecord->status === VendorBillStatus::Draft)
                    ->after(fn () => app(VendorBillService::class)->recalculateBill($this->ownerRecord)),
            ])
            ->bulkActions([
                \Filament\Tables\Actions\DeleteBulkAction::make()
                    ->visible(fn () => $this->ownerRecord->status === VendorBillStatus::Draft)
                    ->after(fn () => app(VendorBillService::class)->recalculateBill($this->ownerRecord)),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order');
    }
}
