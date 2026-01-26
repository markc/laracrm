# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

LaraCRM is a CRM and Accounting system built with Laravel 12 and Filament 5. It provides customer management, invoicing, payments, quotes, and double-entry bookkeeping.

## Tech Stack

- PHP 8.5, Laravel 12, Filament 5, Livewire 4
- Pest 4 for testing, Laravel Pint for formatting
- SQLite (dev), spatie/laravel-permission for RBAC

## Commands

```bash
# Development
composer run dev              # Start all services (server, queue, logs, vite)
php artisan serve --port=8080 # Start server only

# Testing (Pest)
./vendor/bin/pest                              # Run all tests
./vendor/bin/pest --filter="test name"         # Run specific test
./vendor/bin/pest tests/Feature/ExampleTest.php  # Run test file
./vendor/bin/pest --parallel                   # Run tests in parallel

# Code Quality
vendor/bin/pint --dirty       # Format changed files (run before commits)

# Database
php artisan migrate:fresh --seed  # Reset and seed database
php artisan db:seed --class=ChartOfAccountsSeeder  # Seed chart of accounts

# Filament
php artisan make:filament-resource ModelName  # Create new resource
```

## Architecture

### Domain Structure

Models and resources are organized by domain:

```
app/
├── Models/
│   ├── Accounting/    # Account, Invoice, Payment, Quote, Product, JournalEntry
│   └── CRM/           # Customer, Contact, Opportunity, Activity
├── Filament/Resources/
│   ├── Accounting/    # Resources mirror model structure
│   └── CRM/
├── Services/Accounting/   # Business logic layer
└── Enums/             # Status/type enums with Filament interfaces
```

### Double-Entry Bookkeeping

The accounting system uses proper double-entry bookkeeping:

- `JournalEntry` + `JournalEntryLine` for all financial transactions
- `JournalEntryService` validates debit/credit balance before saving
- `Account` has types (Asset, Liability, Equity, Revenue, Expense) with normal balance rules
- Invoices and Payments automatically create journal entries via their services

### Key Services

- `JournalEntryService`: Create/post/reverse journal entries, validates balance
- `InvoiceService`: Send invoices, void with reversing entries, recalculate totals
- `PaymentService`: Allocate payments to invoices, create journal entries
- `ReportingService`: P&L, Balance Sheet, Trial Balance, AR Aging reports

### Filament 5 Patterns

**Important**: Filament 5 moved layout components:
- `Section`, `Grid`, `Tabs` are in `Filament\Schemas\Components\*` (not Forms)
- Form fields remain in `Filament\Forms\Components\*`
- Actions are in `Filament\Actions\*` (not Tables\Actions)
- Use `->recordActions()` for row actions, `->toolbarActions()` for bulk actions

Resources use union types for navigation properties:
```php
protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cube';
protected static string|UnitEnum|null $navigationGroup = 'Accounting';
```

### Enums

All enums implement Filament interfaces for badges/selects:
```php
enum InvoiceStatus: string implements HasLabel, HasColor, HasIcon
```

### Customer Model

Customer has conditional fields based on type (individual vs company):
- `display_name` accessor returns company_name or "first_name last_name"
- `billing_address` and `shipping_address` are JSON cast arrays

### Dashboard Widgets

Six widgets in `app/Filament/Widgets/`: stats overviews, revenue chart, pipeline chart, latest invoices table, upcoming activities table.

## Database Schema

Key relationships:
- Customer hasMany: Invoices, Quotes, Payments, Contacts, Opportunities, Activities
- Invoice hasMany: InvoiceItems, PaymentAllocations
- Payment hasMany: PaymentAllocations (links payments to invoices)
- JournalEntry hasMany: JournalEntryLines (each line references an Account)
- Account belongsTo: parent Account (hierarchical chart of accounts)

## Testing

Use Pest for all tests. Create tests with:
```bash
php artisan make:test FeatureNameTest           # Feature test (uses Pest)
php artisan make:test UnitNameTest --unit       # Unit test
```

Pest syntax:
```php
it('creates an invoice', function () {
    $customer = Customer::factory()->create();

    $response = $this->post('/invoices', [...]);

    expect($response->status())->toBe(201);
});
```
