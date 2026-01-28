<?php

namespace App\Filament\Resources\Accounting;

use App\Enums\QuoteStatus;
use App\Filament\Resources\Accounting\QuoteResource\Pages;
use App\Filament\Resources\Accounting\QuoteResource\RelationManagers;
use App\Models\Accounting\Quote;
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
                Section::make('Quote Information')
                    ->schema([
                        Forms\Components\TextInput::make('quote_number')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->default(fn () => 'QTE-'.strtoupper(Str::random(8))),
                        Forms\Components\Select::make('customer_id')
                            ->relationship('customer')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->display_name)
                            ->searchable(['company_name', 'first_name', 'last_name', 'email'])
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('opportunity_id')
                            ->relationship('opportunity', 'name')
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('status')
                            ->options(QuoteStatus::class)
                            ->required()
                            ->default('draft'),
                    ])
                    ->columns(2),

                Section::make('Dates')
                    ->schema([
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
                    ->columns(4),

                Section::make('Totals')
                    ->schema([
                        Forms\Components\TextInput::make('subtotal')
                            ->numeric()
                            ->prefix('$')
                            ->default(0)
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\TextInput::make('discount_amount')
                            ->numeric()
                            ->prefix('$')
                            ->default(0)
                            ->minValue(0),
                        Forms\Components\TextInput::make('tax_amount')
                            ->numeric()
                            ->prefix('$')
                            ->default(0)
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\TextInput::make('total_amount')
                            ->numeric()
                            ->prefix('$')
                            ->default(0)
                            ->disabled()
                            ->dehydrated(),
                    ])
                    ->columns(4),

                Forms\Components\Textarea::make('notes')
                    ->rows(2)
                    ->columnSpanFull(),

                Forms\Components\Textarea::make('terms')
                    ->rows(3)
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
                    ->action(fn ($record) => $record->update([
                        'status' => QuoteStatus::Sent,
                        'sent_at' => now(),
                    ])),
                Actions\Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === QuoteStatus::Sent)
                    ->action(fn ($record) => $record->update([
                        'status' => QuoteStatus::Approved,
                        'approved_at' => now(),
                    ])),
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
