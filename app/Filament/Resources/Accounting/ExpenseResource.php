<?php

namespace App\Filament\Resources\Accounting;

use App\Enums\PaymentMethod;
use App\Filament\Resources\Accounting\ExpenseResource\Pages;
use App\Models\Accounting\Account;
use App\Models\Accounting\Expense;
use App\Models\CRM\Customer;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns;
use Filament\Tables\Filters;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static string|UnitEnum|null $navigationGroup = 'Accounting';

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'expense_number';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Expense Details')
                    ->schema([
                        Forms\Components\TextInput::make('expense_number')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->default(fn () => 'EXP-'.now()->format('Ym').'-'.str_pad(
                                Expense::whereYear('created_at', now()->year)->count() + 1,
                                4,
                                '0',
                                STR_PAD_LEFT
                            ))
                            ->disabled(fn ($operation) => $operation === 'edit'),
                        Forms\Components\Select::make('vendor_id')
                            ->label('Vendor')
                            ->options(fn () => Customer::vendors()->active()->pluck('company_name', 'id')->filter())
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('account_id')
                            ->label('Expense Account')
                            ->options(fn () => Account::where('type', 'expense')->active()->get()->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('bank_account_id')
                            ->label('Paid From')
                            ->relationship('bankAccount', 'account_name')
                            ->searchable()
                            ->preload(),
                        Forms\Components\DatePicker::make('expense_date')
                            ->required()
                            ->default(now()),
                        Forms\Components\Select::make('payment_method')
                            ->options(PaymentMethod::class)
                            ->default('bank_transfer'),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),

                Section::make('Amounts')
                    ->schema([
                        Forms\Components\TextInput::make('amount')
                            ->numeric()
                            ->prefix('$')
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                $amount = (float) ($state ?? 0);
                                $taxAmount = round($amount * 0.1, 2);
                                $set('tax_amount', $taxAmount);
                                $set('total_amount', $amount + $taxAmount);
                            }),
                        Forms\Components\TextInput::make('tax_amount')
                            ->label('GST')
                            ->numeric()
                            ->prefix('$')
                            ->default(0)
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                $amount = (float) ($get('amount') ?? 0);
                                $tax = (float) ($state ?? 0);
                                $set('total_amount', $amount + $tax);
                            }),
                        Forms\Components\TextInput::make('total_amount')
                            ->numeric()
                            ->prefix('$')
                            ->required()
                            ->default(0),
                        Forms\Components\TextInput::make('reference_number')
                            ->label('Reference #')
                            ->maxLength(255),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),

                Section::make('Billing')
                    ->schema([
                        Forms\Components\Toggle::make('is_billable')
                            ->label('Billable to Customer')
                            ->live(),
                        Forms\Components\Select::make('customer_id')
                            ->label('Bill To Customer')
                            ->relationship('customer')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->display_name)
                            ->searchable(['company_name', 'first_name', 'last_name'])
                            ->preload()
                            ->visible(fn (Forms\Get $get) => $get('is_billable')),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Description')
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Columns\TextColumn::make('expense_number')
                    ->searchable()
                    ->sortable(),
                Columns\TextColumn::make('vendor.display_name')
                    ->label('Vendor')
                    ->placeholder('—')
                    ->searchable(['vendor.company_name'])
                    ->sortable(),
                Columns\TextColumn::make('account.name')
                    ->label('Account')
                    ->sortable(),
                Columns\TextColumn::make('expense_date')
                    ->date()
                    ->sortable(),
                Columns\TextColumn::make('total_amount')
                    ->money('AUD')
                    ->sortable(),
                Columns\TextColumn::make('payment_method')
                    ->badge()
                    ->toggleable(),
                Columns\IconColumn::make('is_billable')
                    ->boolean()
                    ->toggleable(),
            ])
            ->filters([
                Filters\SelectFilter::make('account_id')
                    ->label('Account')
                    ->relationship('account', 'name')
                    ->searchable()
                    ->preload(),
                Filters\SelectFilter::make('vendor_id')
                    ->label('Vendor')
                    ->relationship('vendor', 'company_name')
                    ->searchable()
                    ->preload(),
                Filters\Filter::make('billable')
                    ->query(fn (Builder $query) => $query->where('is_billable', true))
                    ->label('Billable Only'),
                Filters\TrashedFilter::make(),
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
            ->defaultSort('expense_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExpenses::route('/'),
            'create' => Pages\CreateExpense::route('/create'),
            'view' => Pages\ViewExpense::route('/{record}'),
            'edit' => Pages\EditExpense::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                \Illuminate\Database\Eloquent\SoftDeletingScope::class,
            ]);
    }
}
