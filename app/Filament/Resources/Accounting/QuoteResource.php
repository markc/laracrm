<?php

namespace App\Filament\Resources\Accounting;

use App\Enums\QuoteStatus;
use App\Filament\Resources\Accounting\QuoteResource\Pages;
use App\Filament\Resources\Accounting\QuoteResource\RelationManagers;
use App\Models\Accounting\Quote;
use App\Services\Accounting\QuoteService;
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
use UnitEnum;

class QuoteResource extends Resource
{
    protected static ?string $model = Quote::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|UnitEnum|null $navigationGroup = 'Accounting';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'quote_number';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Quote Details')
                    ->schema([
                        Forms\Components\TextInput::make('quote_number')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->default(fn () => app(QuoteService::class)->generateQuoteNumber()),
                        Forms\Components\Select::make('customer_id')
                            ->relationship('customer')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->display_name)
                            ->searchable(['company_name', 'first_name', 'last_name', 'email'])
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('opportunity_id')
                            ->label('Opportunity')
                            ->relationship('opportunity', 'name')
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('status')
                            ->options(QuoteStatus::class)
                            ->required()
                            ->default('draft'),
                        Forms\Components\DatePicker::make('quote_date')
                            ->required()
                            ->default(now()),
                        Forms\Components\DatePicker::make('valid_until')
                            ->required()
                            ->default(now()->addDays(30)),
                        Forms\Components\DateTimePicker::make('sent_at')
                            ->label('Sent At'),
                        Forms\Components\DateTimePicker::make('approved_at')
                            ->label('Approved At'),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),

                Section::make('Totals')
                    ->schema([
                        Forms\Components\TextInput::make('subtotal')
                            ->numeric()
                            ->prefix('$')
                            ->default(0)
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\TextInput::make('discount_amount')
                            ->label('Discount')
                            ->numeric()
                            ->prefix('$')
                            ->default(0)
                            ->minValue(0),
                        Forms\Components\TextInput::make('tax_amount')
                            ->label('Tax')
                            ->numeric()
                            ->prefix('$')
                            ->default(0)
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\TextInput::make('total_amount')
                            ->label('Total')
                            ->numeric()
                            ->prefix('$')
                            ->default(0)
                            ->disabled()
                            ->dehydrated(),
                    ])
                    ->columns(4)
                    ->visible(fn ($operation) => $operation === 'edit' || $operation === 'view')
                    ->columnSpanFull(),

                Section::make('Notes')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes (visible on quote)')
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
                Columns\TextColumn::make('quote_number')
                    ->searchable()
                    ->sortable(),
                Columns\TextColumn::make('customer.display_name')
                    ->label('Customer')
                    ->searchable(['customer.company_name', 'customer.first_name', 'customer.last_name'])
                    ->sortable(),
                Columns\TextColumn::make('quote_date')
                    ->date()
                    ->sortable(),
                Columns\TextColumn::make('valid_until')
                    ->date()
                    ->sortable(),
                Columns\TextColumn::make('total_amount')
                    ->money('AUD')
                    ->sortable(),
                Columns\TextColumn::make('status')
                    ->badge(),
                Columns\TextColumn::make('opportunity.name')
                    ->label('Opportunity')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filters\SelectFilter::make('status')
                    ->options(QuoteStatus::class),
                Filters\SelectFilter::make('customer_id')
                    ->relationship('customer', 'id')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->display_name)
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                Actions\Action::make('send')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === QuoteStatus::Draft)
                    ->action(function ($record) {
                        app(QuoteService::class)->sendQuote($record);
                        Notification::make()->title('Quote sent')->success()->send();
                    }),
                Actions\Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === QuoteStatus::Sent)
                    ->action(function ($record) {
                        app(QuoteService::class)->approveQuote($record);
                        Notification::make()->title('Quote approved')->success()->send();
                    }),
                Actions\Action::make('convert')
                    ->label('Convert to Invoice')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Convert Quote to Invoice')
                    ->modalDescription('This will create a new draft invoice with all line items from this quote.')
                    ->visible(fn ($record) => $record->status === QuoteStatus::Approved)
                    ->action(function ($record) {
                        $invoice = app(QuoteService::class)->convertToInvoice($record);
                        Notification::make()
                            ->title('Invoice created: '.$invoice->invoice_number)
                            ->success()
                            ->send();

                        return redirect(InvoiceResource::getUrl('view', ['record' => $invoice]));
                    }),
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('quote_date', 'desc');
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
            'index' => Pages\ListQuotes::route('/'),
            'create' => Pages\CreateQuote::route('/create'),
            'view' => Pages\ViewQuote::route('/{record}'),
            'edit' => Pages\EditQuote::route('/{record}/edit'),
        ];
    }
}
