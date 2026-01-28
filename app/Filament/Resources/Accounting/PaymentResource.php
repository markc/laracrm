<?php

namespace App\Filament\Resources\Accounting;

use App\Enums\PaymentMethod;
use App\Filament\Resources\Accounting\PaymentResource\Pages;
use App\Filament\Resources\Accounting\PaymentResource\RelationManagers;
use App\Models\Accounting\Payment;
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

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|UnitEnum|null $navigationGroup = 'Accounting';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'payment_number';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Payment Details')
                    ->schema([
                        Forms\Components\TextInput::make('payment_number')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->default(fn () => 'PAY-'.strtoupper(Str::random(8))),
                        Forms\Components\Select::make('customer_id')
                            ->relationship('customer')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->display_name)
                            ->searchable(['company_name', 'first_name', 'last_name', 'email'])
                            ->preload()
                            ->required(),
                        Forms\Components\DatePicker::make('payment_date')
                            ->required()
                            ->default(now()),
                        Forms\Components\TextInput::make('amount')
                            ->numeric()
                            ->prefix('$')
                            ->required()
                            ->minValue(0.01),
                    ])
                    ->columns(2),

                Section::make('Payment Method')
                    ->schema([
                        Forms\Components\Select::make('payment_method')
                            ->options(PaymentMethod::class)
                            ->required()
                            ->default('bank_transfer'),
                        Forms\Components\TextInput::make('reference_number')
                            ->maxLength(255)
                            ->placeholder('Cheque number, transaction ID, etc.'),
                        Forms\Components\Select::make('bank_account_id')
                            ->relationship('bankAccount', 'name')
                            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->name} ({$record->account_number})")
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(3),

                Section::make('Allocation')
                    ->schema([
                        Forms\Components\Placeholder::make('allocated_amount')
                            ->label('Allocated')
                            ->content(fn ($record) => $record ? '$'.number_format($record->allocated_amount, 2) : '$0.00'),
                        Forms\Components\Placeholder::make('unallocated_amount')
                            ->label('Unallocated')
                            ->content(fn ($record) => $record ? '$'.number_format($record->unallocated_amount, 2) : '$0.00'),
                    ])
                    ->columns(2)
                    ->visible(fn ($record) => $record !== null),

                Forms\Components\Textarea::make('notes')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Columns\TextColumn::make('payment_number')
                    ->searchable()
                    ->sortable(),
                Columns\TextColumn::make('customer.display_name')
                    ->label('Customer')
                    ->searchable(['customer.company_name', 'customer.first_name', 'customer.last_name'])
                    ->sortable(),
                Columns\TextColumn::make('payment_date')
                    ->date()
                    ->sortable(),
                Columns\TextColumn::make('amount')
                    ->money('AUD')
                    ->sortable(),
                Columns\TextColumn::make('payment_method')
                    ->badge(),
                Columns\TextColumn::make('unallocated_amount')
                    ->money('AUD')
                    ->label('Unallocated')
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'success'),
                Columns\TextColumn::make('reference_number')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filters\SelectFilter::make('payment_method')
                    ->options(PaymentMethod::class),
                Filters\SelectFilter::make('customer_id')
                    ->relationship('customer', 'id')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->display_name)
                    ->searchable()
                    ->preload(),
                Filters\TernaryFilter::make('fully_allocated')
                    ->label('Fully Allocated')
                    ->queries(
                        true: fn ($query) => $query->where('unallocated_amount', '<=', 0),
                        false: fn ($query) => $query->where('unallocated_amount', '>', 0),
                    ),
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
            ->defaultSort('payment_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\AllocationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'create' => Pages\CreatePayment::route('/create'),
            'view' => Pages\ViewPayment::route('/{record}'),
            'edit' => Pages\EditPayment::route('/{record}/edit'),
        ];
    }
}
