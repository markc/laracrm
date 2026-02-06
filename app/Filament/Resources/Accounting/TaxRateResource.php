<?php

namespace App\Filament\Resources\Accounting;

use App\Filament\Resources\Accounting\TaxRateResource\Pages;
use App\Models\Accounting\Account;
use App\Models\Accounting\TaxRate;
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

class TaxRateResource extends Resource
{
    protected static ?string $model = TaxRate::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calculator';

    protected static string|UnitEnum|null $navigationGroup = 'Accounting';

    protected static ?int $navigationSort = 9;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Tax Rate Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('code')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('rate')
                            ->numeric()
                            ->suffix('%')
                            ->required()
                            ->minValue(0)
                            ->maxValue(100),
                        Forms\Components\Select::make('type')
                            ->options([
                                'sales' => 'Sales',
                                'purchase' => 'Purchase',
                                'both' => 'Both',
                            ])
                            ->required()
                            ->default('both'),
                        Forms\Components\Select::make('account_id')
                            ->label('Tax Liability Account')
                            ->options(fn () => Account::where('type', 'liability')->active()->get()->pluck('name', 'id'))
                            ->searchable()
                            ->preload(),
                        Forms\Components\Toggle::make('is_active')
                            ->default(true),
                        Forms\Components\Toggle::make('is_default'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Columns\TextColumn::make('code')
                    ->searchable()
                    ->sortable(),
                Columns\TextColumn::make('rate')
                    ->suffix('%')
                    ->sortable(),
                Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'sales' => 'success',
                        'purchase' => 'warning',
                        'both' => 'info',
                        default => 'gray',
                    }),
                Columns\TextColumn::make('account.name')
                    ->label('Liability Account')
                    ->placeholder('—'),
                Columns\IconColumn::make('is_active')
                    ->boolean(),
                Columns\IconColumn::make('is_default')
                    ->boolean(),
            ])
            ->filters([
                Filters\SelectFilter::make('type')
                    ->options([
                        'sales' => 'Sales',
                        'purchase' => 'Purchase',
                        'both' => 'Both',
                    ]),
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
            'index' => Pages\ListTaxRates::route('/'),
            'create' => Pages\CreateTaxRate::route('/create'),
            'edit' => Pages\EditTaxRate::route('/{record}/edit'),
        ];
    }
}
