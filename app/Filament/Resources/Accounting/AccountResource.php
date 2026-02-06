<?php

namespace App\Filament\Resources\Accounting;

use App\Enums\AccountType;
use App\Filament\Resources\Accounting\AccountResource\Pages;
use App\Models\Accounting\Account;
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

class AccountResource extends Resource
{
    protected static ?string $model = Account::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calculator';

    protected static string|UnitEnum|null $navigationGroup = 'Accounting';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account Information')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->required()
                            ->maxLength(20)
                            ->unique(ignoreRecord: true)
                            ->placeholder('e.g., 1000'),
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('type')
                            ->options(AccountType::class)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, $set) {
                                if ($state) {
                                    $type = AccountType::from($state);
                                    $set('normal_balance', $type->normalBalance());
                                }
                            }),
                        Forms\Components\Select::make('normal_balance')
                            ->options([
                                'debit' => 'Debit',
                                'credit' => 'Credit',
                            ])
                            ->required()
                            ->disabled(),
                        Forms\Components\Select::make('parent_id')
                            ->label('Parent Account')
                            ->relationship('parent', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('Select parent account (optional)'),
                        Forms\Components\Select::make('currency')
                            ->options([
                                'AUD' => 'AUD - Australian Dollar',
                                'USD' => 'USD - US Dollar',
                                'EUR' => 'EUR - Euro',
                                'GBP' => 'GBP - British Pound',
                            ])
                            ->default('AUD')
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Additional Details')
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                        Forms\Components\Toggle::make('is_system')
                            ->label('System Account')
                            ->disabled()
                            ->helperText('System accounts cannot be deleted'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Columns\TextColumn::make('code')
                    ->searchable()
                    ->sortable(),
                Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Columns\TextColumn::make('type')
                    ->badge()
                    ->sortable(),
                Columns\TextColumn::make('normal_balance')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'debit' => 'info',
                        'credit' => 'warning',
                    }),
                Columns\TextColumn::make('parent.name')
                    ->label('Parent')
                    ->placeholder('-'),
                Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
                Columns\IconColumn::make('is_system')
                    ->boolean()
                    ->label('System'),
            ])
            ->filters([
                Filters\SelectFilter::make('type')
                    ->options(AccountType::class),
                Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make()
                        ->before(function ($records) {
                            $records = $records->filter(fn ($record) => ! $record->is_system);
                        }),
                ]),
            ])
            ->defaultSort('code');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccounts::route('/'),
            'create' => Pages\CreateAccount::route('/create'),
            'view' => Pages\ViewAccount::route('/{record}'),
            'edit' => Pages\EditAccount::route('/{record}/edit'),
        ];
    }
}
