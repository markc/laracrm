<?php

namespace App\Filament\Resources\Accounting;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Accounting\InvoiceResource\Pages;
use App\Filament\Resources\Accounting\InvoiceResource\RelationManagers;
use App\Models\Accounting\Invoice;
use App\Services\Accounting\InvoiceService;
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

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|UnitEnum|null $navigationGroup = 'Accounting';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'invoice_number';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Invoice Details')
                    ->schema([
                        Forms\Components\TextInput::make('invoice_number')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->default(fn () => 'INV-'.now()->format('Ym').'-'.str_pad(
                                Invoice::whereYear('created_at', now()->year)->count() + 1,
                                4,
                                '0',
                                STR_PAD_LEFT
                            ))
                            ->disabled(fn ($operation) => $operation === 'edit'),
                        Forms\Components\Select::make('customer_id')
                            ->relationship('customer')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->display_name)
                            ->searchable(['company_name', 'first_name', 'last_name', 'email'])
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state) {
                                    $customer = \App\Models\CRM\Customer::find($state);
                                    if ($customer) {
                                        $set('due_date', now()->addDays($customer->payment_terms)->format('Y-m-d'));
                                    }
                                }
                            }),
                        Forms\Components\DatePicker::make('invoice_date')
                            ->required()
                            ->default(now()),
                        Forms\Components\DatePicker::make('due_date')
                            ->required()
                            ->default(now()->addDays(30)),
                        Forms\Components\Select::make('status')
                            ->options(InvoiceStatus::class)
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
                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                        if ($state) {
                                            $product = \App\Models\Accounting\Product::find($state);
                                            if ($product) {
                                                $set('description', $product->name);
                                                $set('unit_price', $product->unit_price);
                                                $set('tax_rate', $product->tax_rate);
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
                                Forms\Components\TextInput::make('discount_percent')
                                    ->label('Disc %')
                                    ->numeric()
                                    ->suffix('%')
                                    ->default(0)
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->live()
                                    ->columnSpan(1),
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
                                        $discount = (float) ($get('discount_percent') ?? 0);
                                        $taxRate = (float) ($get('tax_rate') ?? 0);

                                        $subtotal = $quantity * $unitPrice;
                                        $discountAmount = $subtotal * ($discount / 100);
                                        $taxableAmount = $subtotal - $discountAmount;
                                        $tax = $taxableAmount * ($taxRate / 100);

                                        return '$'.number_format($taxableAmount + $tax, 2);
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
                            ->label('Tax')
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
                            ->label('Notes (visible on invoice)')
                            ->rows(3),
                        Forms\Components\Textarea::make('terms')
                            ->label('Terms & Conditions')
                            ->rows(3),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Columns\TextColumn::make('invoice_number')
                    ->searchable()
                    ->sortable(),
                Columns\TextColumn::make('customer.display_name')
                    ->label('Customer')
                    ->searchable(['customer.company_name', 'customer.first_name', 'customer.last_name'])
                    ->sortable(),
                Columns\TextColumn::make('invoice_date')
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
                    ->options(InvoiceStatus::class),
                Filters\SelectFilter::make('customer_id')
                    ->relationship('customer', 'id')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->display_name)
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
                    ->visible(fn ($record) => $record->status === InvoiceStatus::Draft),
                Actions\Action::make('download_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn ($record) => route('invoices.pdf', $record))
                    ->openUrlInNewTab(),
                Actions\Action::make('send')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === InvoiceStatus::Draft)
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        app(InvoiceService::class)->sendInvoice($record);
                        Notification::make()
                            ->title('Invoice sent successfully')
                            ->success()
                            ->send();
                    }),
                Actions\Action::make('void')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => ! in_array($record->status, [InvoiceStatus::Void, InvoiceStatus::Paid]))
                    ->form([
                        Forms\Components\TextInput::make('void_reason')
                            ->required()
                            ->label('Reason for voiding'),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            app(InvoiceService::class)->voidInvoice($record, $data['void_reason']);
                            Notification::make()
                                ->title('Invoice voided successfully')
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
            ->defaultSort('invoice_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ItemsRelationManager::class,
            RelationManagers\PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'view' => Pages\ViewInvoice::route('/{record}'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
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
