<?php

namespace App\Filament\Resources\Accounting;

use App\Filament\Resources\Accounting\BankAccountResource\Pages;
use App\Models\Accounting\Account;
use App\Models\Accounting\BankAccount;
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

class BankAccountResource extends Resource
{
    protected static ?string $model = BankAccount::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-library';

    protected static string|UnitEnum|null $navigationGroup = 'Accounting';

    protected static ?int $navigationSort = 8;

    protected static ?string $recordTitleAttribute = 'account_name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Bank Account Details')
                    ->schema([
                        Forms\Components\Select::make('account_id')
                            ->label('GL Account')
                            ->options(fn () => Account::where('type', 'asset')->active()->get()->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\TextInput::make('bank_name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('account_name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('account_number')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('bsb')
                            ->label('BSB')
                            ->maxLength(10),
                        Forms\Components\TextInput::make('currency')
                            ->default('AUD')
                            ->maxLength(3),
                        Forms\Components\Toggle::make('is_active')
                            ->default(true),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Columns\TextColumn::make('bank_name')
                    ->searchable()
                    ->sortable(),
                Columns\TextColumn::make('account_name')
                    ->searchable()
                    ->sortable(),
                Columns\TextColumn::make('account_number')
                    ->searchable(),
                Columns\TextColumn::make('bsb')
                    ->label('BSB'),
                Columns\TextColumn::make('account.name')
                    ->label('GL Account'),
                Columns\TextColumn::make('currency'),
                Columns\IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->filters([
                Filters\Filter::make('active')
                    ->query(fn ($query) => $query->active())
                    ->label('Active Only')
                    ->default(),
            ])
            ->recordActions([
                Actions\EditAction::make(),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBankAccounts::route('/'),
            'create' => Pages\CreateBankAccount::route('/create'),
            'edit' => Pages\EditBankAccount::route('/{record}/edit'),
        ];
    }
}
