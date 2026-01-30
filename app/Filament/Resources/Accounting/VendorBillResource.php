<?php

namespace App\Filament\Resources\Accounting;

use App\Enums\VendorBillStatus;
use App\Filament\Resources\Accounting\VendorBillResource\Pages;
use App\Filament\Resources\Accounting\VendorBillResource\RelationManagers;
use App\Models\Accounting\Account;
use App\Models\Accounting\VendorBill;
use App\Models\CRM\Customer;
use App\Services\Accounting\VendorBillService;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns;
use Filament\Tables\Filters;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class VendorBillResource extends Resource
{
    protected static ?string $model = VendorBill::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'Purchasing';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'bill_number';

    protected static ?string $label = 'Vendor Bill';

    protected static ?string $pluralLabel = 'Vendor Bills';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Bill Details')
                    ->schema([
                        Forms\Components\TextInput::make('bill_number')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->default(fn () => 'BILL-'.now()->format('Ym').'-'.str_pad(
                                VendorBill::whereYear('created_at', now()->year)->count() + 1,
                                4,
                                '0',
                                STR_PAD_LEFT
                            ))
                            ->disabled(fn ($operation) => $operation === 'edit'),
                        Forms\Components\Select::make('vendor_id')
                            ->label('Vendor')
                            ->options(fn () => Customer::vendors()->active()->pluck('company_name', 'id')->filter())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('company_name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('email')
                                    ->email()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('phone')
                                    ->tel()
                                    ->maxLength(255),
                                Forms\Components\Hidden::make('is_vendor')
                                    ->default(true),
                                Forms\Components\Hidden::make('type')
                                    ->default('company'),
                                Forms\Components\Hidden::make('status')
                                    ->default('active'),
                            ])
                            ->createOptionUsing(function (array $data): int {
                                $data['customer_number'] = 'VEND-'.str_pad(Customer::count() + 1, 4, '0', STR_PAD_LEFT);

                                return Customer::create($data)->getKey();
                            }),
                        Forms\Components\TextInput::make('vendor_reference')
                            ->label('Vendor Invoice #')
                            ->maxLength(255)
                            ->placeholder("Vendor's reference number"),
                        Forms\Components\DatePicker::make('bill_date')
                            ->required()
                            ->default(now()),
                        Forms\Components\DatePicker::make('due_date')
                            ->required()
                            ->default(now()->addDays(30)),
                        Forms\Components\Select::make('status')
                            ->options(VendorBillStatus::class)
                            ->default('draft')
                            ->disabled(fn ($operation) => $operation === 'create'),
                    ])
                    ->columns(6)
                    ->columnSpanFull(),

                Section::make('Line Items')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('account_id')
                                    ->label('Expense Account')
                                    ->options(fn () => Account::where('type', 'expense')
                                        ->orWhere('type', 'asset')
                                        ->active()
                                        ->pluck('name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->columnSpan(2),
                                Forms\Components\Select::make('product_id')
                                    ->label('Product')
                                    ->relationship('product', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                        if ($state) {
                                            $product = \App\Models\Accounting\Product::find($state);
                                            if ($product) {
                                                $set('description', $product->name);
                                                $set('unit_price', $product->cost_price ?: $product->unit_price);
                                                $set('tax_rate', $product->tax_rate);
                                                if ($product->expense_account_id) {
                                                    $set('account_id', $product->expense_account_id);
                                                }
                                            }
                                        }
                                    })
                                    ->columnSpan(2),
                                Forms\Components\TextInput::make('description')
                                    ->required()
                                    ->columnSpan(4),
                                Forms\Components\TextInput::make('quantity')
                                    ->label('Qty')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(0.01)
                                    ->required()
                                    ->live()
                                    ->columnSpan(1),
                                Forms\Components\TextInput::make('unit_price')
                                    ->label('Price')
                                    ->numeric()
                                    ->prefix('$')
                                    ->required()
                                    ->live()
                                    ->columnSpan(2),
                                Forms\Components\TextInput::make('tax_rate')
                                    ->label('Tax %')
                                    ->numeric()
                                    ->suffix('%')
                                    ->default(10)
                                    ->live()
                                    ->columnSpan(1),
                                Forms\Components\Placeholder::make('line_total')
                                    ->label('Total')
                                    ->content(function ($get) {
                                        $quantity = (float) ($get('quantity') ?? 0);
                                        $unitPrice = (float) ($get('unit_price') ?? 0);
                                        $taxRate = (float) ($get('tax_rate') ?? 0);

                                        $subtotal = $quantity * $unitPrice;
                                        $tax = $subtotal * ($taxRate / 100);

                                        return '$'.number_format($subtotal + $tax, 2);
                                    })
                                    ->columnSpan(2),
                            ])
                            ->columns(12)
                            ->defaultItems(1)
                            ->reorderable()
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['description'] ?? 'New Item')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Totals')
                    ->schema([
                        Forms\Components\Placeholder::make('subtotal_display')
                            ->label('Subtotal')
                            ->content(fn ($record) => $record ? '$'.number_format($record->subtotal, 2) : '$0.00'),
                        Forms\Components\Placeholder::make('tax_display')
                            ->label('GST')
                            ->content(fn ($record) => $record ? '$'.number_format($record->tax_amount, 2) : '$0.00'),
                        Forms\Components\Placeholder::make('total_display')
                            ->label('Total')
                            ->content(fn ($record) => $record ? '$'.number_format($record->total_amount, 2) : '$0.00'),
                        Forms\Components\Placeholder::make('balance_display')
                            ->label('Balance Due')
                            ->content(fn ($record) => $record ? '$'.number_format($record->balance_due, 2) : '$0.00'),
                    ])
                    ->columns(4)
                    ->visible(fn ($operation) => $operation === 'edit' || $operation === 'view')
                    ->columnSpanFull(),

                Section::make('Notes')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('Internal Notes')
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
                Columns\TextColumn::make('bill_number')
                    ->searchable()
                    ->sortable(),
                Columns\TextColumn::make('vendor_reference')
                    ->label('Vendor Ref')
                    ->searchable()
                    ->toggleable(),
                Columns\TextColumn::make('vendor.display_name')
                    ->label('Vendor')
                    ->searchable(['vendor.company_name'])
                    ->sortable(),
                Columns\TextColumn::make('bill_date')
                    ->date()
                    ->sortable(),
                Columns\TextColumn::make('due_date')
                    ->date()
                    ->sortable()
                    ->color(fn ($record) => $record->is_overdue ? 'danger' : null),
                Columns\TextColumn::make('total_amount')
                    ->money('AUD')
                    ->sortable(),
                Columns\TextColumn::make('balance_due')
                    ->money('AUD')
                    ->sortable()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'success'),
                Columns\TextColumn::make('status')
                    ->badge(),
            ])
            ->filters([
                Filters\SelectFilter::make('status')
                    ->options(VendorBillStatus::class),
                Filters\SelectFilter::make('vendor_id')
                    ->label('Vendor')
                    ->relationship('vendor', 'company_name')
                    ->searchable()
                    ->preload(),
                Filters\Filter::make('overdue')
                    ->query(fn (Builder $query) => $query->overdue())
                    ->label('Overdue Only'),
                Filters\TrashedFilter::make(),
            ])
            ->recordActions([
                Actions\ViewAction::make(),
                Actions\EditAction::make()
                    ->visible(fn ($record) => $record->status === VendorBillStatus::Draft),
                Actions\Action::make('receive')
                    ->label('Receive')
                    ->icon('heroicon-o-inbox-arrow-down')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === VendorBillStatus::Draft)
                    ->requiresConfirmation()
                    ->modalHeading('Receive Bill')
                    ->modalDescription('This will post the bill to the general ledger. Continue?')
                    ->action(function ($record) {
                        app(VendorBillService::class)->receiveBill($record);
                        Notification::make()
                            ->title('Bill received and posted to GL')
                            ->success()
                            ->send();
                    }),
                Actions\Action::make('void')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => ! in_array($record->status, [VendorBillStatus::Void, VendorBillStatus::Paid]))
                    ->form([
                        Forms\Components\TextInput::make('void_reason')
                            ->required()
                            ->label('Reason for voiding'),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            app(VendorBillService::class)->voidBill($record, $data['void_reason']);
                            Notification::make()
                                ->title('Bill voided successfully')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('bill_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVendorBills::route('/'),
            'create' => Pages\CreateVendorBill::route('/create'),
            'view' => Pages\ViewVendorBill::route('/{record}'),
            'edit' => Pages\EditVendorBill::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                \Illuminate\Database\Eloquent\SoftDeletingScope::class,
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::unpaid()->count() ?: null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return static::getModel()::overdue()->exists() ? 'danger' : 'warning';
    }
}
