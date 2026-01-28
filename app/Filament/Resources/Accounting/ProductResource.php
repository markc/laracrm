<?php

namespace App\Filament\Resources\Accounting;

use App\Enums\ProductType;
use App\Filament\Resources\Accounting\ProductResource\Pages;
use App\Models\Accounting\Product;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns;
use Filament\Tables\Filters;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static string|UnitEnum|null $navigationGroup = 'Accounting';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Product Information')
                    ->schema([
                        Forms\Components\TextInput::make('sku')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->default(fn () => 'SKU-'.strtoupper(Str::random(8))),
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),
                        Forms\Components\Select::make('type')
                            ->options(ProductType::class)
                            ->required()
                            ->default('service'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->inline(false),
                        Forms\Components\TextInput::make('unit_price')
                            ->numeric()
                            ->prefix('$')
                            ->required()
                            ->minValue(0),
                        Forms\Components\TextInput::make('cost_price')
                            ->numeric()
                            ->prefix('$')
                            ->minValue(0),
                        Forms\Components\TextInput::make('tax_rate')
                            ->numeric()
                            ->suffix('%')
                            ->default(10)
                            ->minValue(0)
                            ->maxValue(100),
                    ])
                    ->columns(5)
                    ->columnSpanFull(),

                Section::make('Accounting')
                    ->schema([
                        Forms\Components\Select::make('income_account_id')
                            ->label('Income Account')
                            ->relationship('incomeAccount', 'name')
                            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->code} - {$record->name}")
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('expense_account_id')
                            ->label('Expense Account')
                            ->relationship('expenseAccount', 'name')
                            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->code} - {$record->name}")
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Description')
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->hiddenLabel()
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Columns\TextColumn::make('sku')
                    ->searchable()
                    ->sortable(),
                Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Columns\TextColumn::make('type')
                    ->badge(),
                Columns\TextColumn::make('unit_price')
                    ->money('AUD')
                    ->sortable(),
                Columns\TextColumn::make('tax_rate')
                    ->suffix('%'),
                Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
            ])
            ->filters([
                Filters\SelectFilter::make('type')
                    ->options(ProductType::class),
                Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'view' => Pages\ViewProduct::route('/{record}'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
