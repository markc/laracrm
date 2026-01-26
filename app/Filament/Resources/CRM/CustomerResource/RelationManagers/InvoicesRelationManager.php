<?php

namespace App\Filament\Resources\CRM\CustomerResource\RelationManagers;

use App\Enums\InvoiceStatus;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns;
use Filament\Tables\Filters;
use Filament\Tables\Table;

class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('invoice_number')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('invoice_number')
            ->columns([
                Columns\TextColumn::make('invoice_number')
                    ->searchable()
                    ->sortable(),
                Columns\TextColumn::make('invoice_date')
                    ->date()
                    ->sortable(),
                Columns\TextColumn::make('due_date')
                    ->date()
                    ->sortable(),
                Columns\TextColumn::make('total_amount')
                    ->money('AUD')
                    ->sortable(),
                Columns\TextColumn::make('balance_due')
                    ->money('AUD')
                    ->sortable(),
                Columns\TextColumn::make('status')
                    ->badge(),
            ])
            ->filters([
                Filters\SelectFilter::make('status')
                    ->options(InvoiceStatus::class),
            ])
            ->headerActions([
                // Invoices should be created from the Invoice resource
            ])
            ->recordActions([
                Actions\ViewAction::make(),
            ])
            ->toolbarActions([])
            ->defaultSort('invoice_date', 'desc');
    }
}
