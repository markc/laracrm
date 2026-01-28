<?php

namespace App\Filament\Resources\CRM;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Filament\Resources\CRM\CustomerResource\Pages;
use App\Filament\Resources\CRM\CustomerResource\RelationManagers;
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
use Illuminate\Support\Str;
use UnitEnum;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|UnitEnum|null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'display_name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->options(CustomerType::class)
                            ->required()
                            ->live()
                            ->default('individual'),
                        Forms\Components\TextInput::make('customer_number')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->default(fn () => 'CUS-'.strtoupper(Str::random(8)))
                            ->disabled(fn ($operation) => $operation === 'edit'),
                        Forms\Components\TextInput::make('company_name')
                            ->visible(fn ($get) => $get('type') === 'company')
                            ->required(fn ($get) => $get('type') === 'company')
                            ->maxLength(255)
                            ->columnSpan(2),
                        Forms\Components\TextInput::make('first_name')
                            ->required(fn ($get) => $get('type') === 'individual')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('last_name')
                            ->required(fn ($get) => $get('type') === 'individual')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('tax_id')
                            ->label('ABN')
                            ->maxLength(11)
                            ->helperText('Australian Business Number'),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),

                Section::make('Account Settings')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options(CustomerStatus::class)
                            ->default('active')
                            ->required(),
                        Forms\Components\TextInput::make('payment_terms')
                            ->numeric()
                            ->default(30)
                            ->suffix('days'),
                        Forms\Components\TextInput::make('credit_limit')
                            ->numeric()
                            ->prefix('$'),
                        Forms\Components\Select::make('currency')
                            ->options([
                                'AUD' => 'AUD - Australian Dollar',
                                'USD' => 'USD - US Dollar',
                                'EUR' => 'EUR - Euro',
                                'GBP' => 'GBP - British Pound',
                            ])
                            ->default('AUD'),
                        Forms\Components\Select::make('assigned_to')
                            ->label('Assigned To')
                            ->relationship('assignedUser', 'name')
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(5)
                    ->columnSpanFull(),

                Section::make('Billing Address')
                    ->schema([
                        Forms\Components\TextInput::make('billing_address.street')
                            ->label('Street')
                            ->columnSpan(2),
                        Forms\Components\TextInput::make('billing_address.city')
                            ->label('City'),
                        Forms\Components\TextInput::make('billing_address.state')
                            ->label('State'),
                        Forms\Components\TextInput::make('billing_address.postcode')
                            ->label('Postcode'),
                        Forms\Components\TextInput::make('billing_address.country')
                            ->label('Country')
                            ->default('Australia'),
                    ])
                    ->columns(6)
                    ->collapsible()
                    ->columnSpanFull(),

                Section::make('Shipping Address')
                    ->schema([
                        Forms\Components\TextInput::make('shipping_address.street')
                            ->label('Street')
                            ->columnSpan(2),
                        Forms\Components\TextInput::make('shipping_address.city')
                            ->label('City'),
                        Forms\Components\TextInput::make('shipping_address.state')
                            ->label('State'),
                        Forms\Components\TextInput::make('shipping_address.postcode')
                            ->label('Postcode'),
                        Forms\Components\TextInput::make('shipping_address.country')
                            ->label('Country')
                            ->default('Australia'),
                    ])
                    ->columns(6)
                    ->collapsible()
                    ->collapsed()
                    ->columnSpanFull(),

                Section::make('Notes')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
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
                Columns\TextColumn::make('customer_number')
                    ->searchable()
                    ->sortable(),
                Columns\TextColumn::make('display_name')
                    ->label('Name')
                    ->searchable(['company_name', 'first_name', 'last_name'])
                    ->sortable(),
                Columns\TextColumn::make('type')
                    ->badge(),
                Columns\TextColumn::make('email')
                    ->searchable()
                    ->copyable(),
                Columns\TextColumn::make('phone'),
                Columns\TextColumn::make('status')
                    ->badge(),
                Columns\TextColumn::make('invoices_sum_balance_due')
                    ->sum('invoices', 'balance_due')
                    ->money('AUD')
                    ->label('Outstanding'),
                Columns\TextColumn::make('assignedUser.name')
                    ->label('Assigned To')
                    ->placeholder('-'),
            ])
            ->filters([
                Filters\SelectFilter::make('status')
                    ->options(CustomerStatus::class),
                Filters\SelectFilter::make('type')
                    ->options(CustomerType::class),
                Filters\SelectFilter::make('assigned_to')
                    ->relationship('assignedUser', 'name'),
                Filters\TrashedFilter::make(),
            ])
            ->recordActions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                    Actions\ForceDeleteBulkAction::make(),
                    Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('customer_number', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ContactsRelationManager::class,
            RelationManagers\InvoicesRelationManager::class,
            RelationManagers\ActivitiesRelationManager::class,
            RelationManagers\OpportunitiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'view' => Pages\ViewCustomer::route('/{record}'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                \Illuminate\Database\Eloquent\SoftDeletingScope::class,
            ]);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['customer_number', 'company_name', 'first_name', 'last_name', 'email'];
    }
}
