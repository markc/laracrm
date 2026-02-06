<?php

namespace App\Filament\Resources\Accounting\JournalEntryResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns;
use Filament\Tables\Table;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Journal Lines';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Columns\TextColumn::make('account.code')
                    ->label('Code')
                    ->sortable(),
                Columns\TextColumn::make('account.name')
                    ->label('Account')
                    ->sortable(),
                Columns\TextColumn::make('description')
                    ->limit(50),
                Columns\TextColumn::make('debit_amount')
                    ->money('AUD')
                    ->alignEnd(),
                Columns\TextColumn::make('credit_amount')
                    ->money('AUD')
                    ->alignEnd(),
            ]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
