<?php

namespace App\Filament\Resources\CRM\CustomerResource\RelationManagers;

use App\Enums\AddressType;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns;
use Filament\Tables\Table;

class AddressesRelationManager extends RelationManager
{
    protected static string $relationship = 'addresses';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('label')
                    ->placeholder('e.g., Warehouse, Head Office')
                    ->maxLength(255),
                Forms\Components\Select::make('type')
                    ->options(AddressType::class)
                    ->default(AddressType::Shipping)
                    ->required(),
                Forms\Components\TextInput::make('street')
                    ->label('Address Line 1')
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('street2')
                    ->label('Address Line 2')
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('street3')
                    ->label('Address Line 3')
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('street4')
                    ->label('Address Line 4')
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('street5')
                    ->label('Address Line 5')
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('city')
                    ->maxLength(255),
                Forms\Components\TextInput::make('state')
                    ->maxLength(255),
                Forms\Components\TextInput::make('postcode')
                    ->maxLength(20),
                Forms\Components\TextInput::make('country')
                    ->default('Australia')
                    ->maxLength(255),
                Forms\Components\TextInput::make('contact_name')
                    ->label('Contact Name')
                    ->maxLength(255),
                Forms\Components\TextInput::make('contact_phone')
                    ->label('Contact Phone')
                    ->tel()
                    ->maxLength(255),
                Forms\Components\Toggle::make('is_default')
                    ->label('Default Address'),
                Forms\Components\Textarea::make('notes')
                    ->rows(2)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                Columns\TextColumn::make('label')
                    ->searchable()
                    ->placeholder('—'),
                Columns\TextColumn::make('type')
                    ->badge(),
                Columns\TextColumn::make('street')
                    ->description(fn ($record) => $record->street2)
                    ->searchable(),
                Columns\TextColumn::make('city')
                    ->searchable(),
                Columns\TextColumn::make('state'),
                Columns\TextColumn::make('postcode'),
                Columns\IconColumn::make('is_default')
                    ->boolean()
                    ->label('Default'),
            ])
            ->defaultSort('is_default', 'desc')
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
            ]);
    }
}
