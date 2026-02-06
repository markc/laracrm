<?php

namespace App\Filament\Resources\Accounting;

use App\Enums\PurchaseOrderStatus;
use App\Filament\Resources\Accounting\PurchaseOrderResource\Pages;
use App\Filament\Resources\Accounting\PurchaseOrderResource\RelationManagers;
use App\Models\Accounting\PurchaseOrder;
use App\Models\CRM\Customer;
use App\Services\Accounting\PurchaseOrderService;
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

class PurchaseOrderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-cart';

    protected static string|UnitEnum|null $navigationGroup = 'Purchasing';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'po_number';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Order Details')
                    ->schema([
                        Forms\Components\TextInput::make('po_number')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->default(fn () => app(PurchaseOrderService::class)->generatePoNumber())
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
                        Forms\Components\DatePicker::make('order_date')
                            ->required()
                            ->default(now()),
                        Forms\Components\DatePicker::make('expected_delivery_date')
                            ->label('Expected Delivery'),
                        Forms\Components\Select::make('status')
                            ->options(PurchaseOrderStatus::class)
                            ->default('draft')
                            ->disabled(fn ($operation) => $operation === 'create'),
                    ])
                    ->columns(5)
                    ->columnSpanFull(),

                Section::make('Line Items')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('Product')
                                    ->relationship('product', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(function ($state, $set) {
                                        if ($state) {
                                            $product = \App\Models\Accounting\Product::find($state);
                                            if ($product) {
                                                $set('description', $product->name);
                                                $set('unit_price', $product->cost_price ?: $product->unit_price);
                                                $set('tax_rate', $product->tax_rate);
                                            }
                                        }
                                    })
                                    ->columnSpan(3),
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
                                    ->columnSpan(1),
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
                    ])
                    ->columns(3)
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
                Columns\TextColumn::make('po_number')
                    ->searchable()
                    ->sortable(),
                Columns\TextColumn::make('vendor.display_name')
                    ->label('Vendor')
                    ->searchable(['vendor.company_name'])
                    ->sortable(),
                Columns\TextColumn::make('order_date')
                    ->date()
                    ->sortable(),
                Columns\TextColumn::make('expected_delivery_date')
                    ->label('Expected')
                    ->date()
                    ->sortable(),
                Columns\TextColumn::make('total_amount')
                    ->money('AUD')
                    ->sortable(),
                Columns\TextColumn::make('status')
                    ->badge(),
            ])
            ->filters([
                Filters\SelectFilter::make('status')
                    ->options(PurchaseOrderStatus::class),
                Filters\SelectFilter::make('vendor_id')
                    ->label('Vendor')
                    ->relationship('vendor', 'company_name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->display_name)
                    ->searchable()
                    ->preload(),
                Filters\TrashedFilter::make(),
            ])
            ->recordActions([
                Actions\ViewAction::make(),
                Actions\EditAction::make()
                    ->visible(fn ($record) => $record->status === PurchaseOrderStatus::Draft),
                Actions\Action::make('send')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === PurchaseOrderStatus::Draft)
                    ->action(function ($record) {
                        app(PurchaseOrderService::class)->sendOrder($record);
                        Notification::make()->title('Purchase order sent')->success()->send();
                    }),
                Actions\Action::make('confirm')
                    ->icon('heroicon-o-check')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === PurchaseOrderStatus::Sent)
                    ->action(function ($record) {
                        app(PurchaseOrderService::class)->confirmOrder($record);
                        Notification::make()->title('Purchase order confirmed')->success()->send();
                    }),
                Actions\Action::make('receive')
                    ->label('Receive Items')
                    ->icon('heroicon-o-inbox-arrow-down')
                    ->color('success')
                    ->visible(fn ($record) => in_array($record->status, [
                        PurchaseOrderStatus::Confirmed,
                        PurchaseOrderStatus::PartiallyReceived,
                    ]))
                    ->form(fn ($record) => $record->items->map(fn ($item) => Forms\Components\TextInput::make("items.{$item->id}")
                        ->label("{$item->description} (Remaining: {$item->remaining_quantity})")
                        ->numeric()
                        ->default($item->remaining_quantity)
                        ->minValue(0)
                        ->maxValue($item->remaining_quantity)
                    )->toArray())
                    ->action(function ($record, array $data) {
                        $receivedItems = [];
                        foreach ($data['items'] ?? [] as $itemId => $qty) {
                            if ((float) $qty > 0) {
                                $receivedItems[] = [
                                    'item_id' => $itemId,
                                    'quantity' => (float) $qty,
                                ];
                            }
                        }

                        if (empty($receivedItems)) {
                            Notification::make()->title('No items to receive')->warning()->send();

                            return;
                        }

                        app(PurchaseOrderService::class)->receiveItems($record, $receivedItems);
                        Notification::make()->title('Items received successfully')->success()->send();
                    }),
                Actions\Action::make('create_bill')
                    ->label('Create Bill')
                    ->icon('heroicon-o-document-text')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Create Vendor Bill from PO')
                    ->modalDescription('This will create a draft vendor bill with items from this purchase order.')
                    ->visible(fn ($record) => in_array($record->status, [
                        PurchaseOrderStatus::Confirmed,
                        PurchaseOrderStatus::PartiallyReceived,
                        PurchaseOrderStatus::Received,
                    ]))
                    ->action(function ($record) {
                        $bill = app(PurchaseOrderService::class)->createBillFromOrder($record);
                        Notification::make()
                            ->title('Vendor bill created: '.$bill->bill_number)
                            ->success()
                            ->send();

                        return redirect(VendorBillResource::getUrl('view', ['record' => $bill]));
                    }),
                Actions\Action::make('cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => in_array($record->status, [
                        PurchaseOrderStatus::Draft,
                        PurchaseOrderStatus::Sent,
                    ]))
                    ->form([
                        Forms\Components\TextInput::make('cancel_reason')
                            ->required()
                            ->label('Reason for cancellation'),
                    ])
                    ->action(function ($record, array $data) {
                        app(PurchaseOrderService::class)->cancelOrder($record, $data['cancel_reason']);
                        Notification::make()->title('Purchase order cancelled')->success()->send();
                    }),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('order_date', 'desc');
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
            'index' => Pages\ListPurchaseOrders::route('/'),
            'create' => Pages\CreatePurchaseOrder::route('/create'),
            'view' => Pages\ViewPurchaseOrder::route('/{record}'),
            'edit' => Pages\EditPurchaseOrder::route('/{record}/edit'),
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
        return static::getModel()::open()->count() ?: null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'primary';
    }
}
