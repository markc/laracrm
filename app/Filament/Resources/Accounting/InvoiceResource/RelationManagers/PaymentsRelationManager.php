<?php

namespace App\Filament\Resources\Accounting\InvoiceResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('payment.payment_number')
            ->columns([
                Columns\TextColumn::make('payment.payment_number')
                    ->label('Payment #')
                    ->searchable(),
                Columns\TextColumn::make('payment.payment_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                Columns\TextColumn::make('payment.payment_method')
                    ->label('Method')
                    ->badge(),
                Columns\TextColumn::make('amount')
                    ->money('AUD')
                    ->summarize(Columns\Summarizers\Sum::make()->money('AUD')),
            ])
            ->filters([])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
