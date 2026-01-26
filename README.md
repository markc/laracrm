# LaraCRM

A modern CRM and Accounting system built with Laravel 12 and Filament 5. Designed as a lightweight alternative to WHMCS for managing customers, invoicing, payments, and double-entry bookkeeping.

## Features

### CRM
- **Customer Management** - Individual and company customers with billing/shipping addresses
- **Contact Management** - Multiple contacts per customer with primary contact designation
- **Opportunity Tracking** - Sales pipeline with stages (Lead → Qualified → Proposal → Negotiation → Won/Lost)
- **Activity Logging** - Calls, emails, meetings, tasks, and notes linked to customers

### Accounting
- **Chart of Accounts** - Hierarchical account structure (Assets, Liabilities, Equity, Revenue, Expenses)
- **Double-Entry Bookkeeping** - Proper journal entries with balanced debits/credits
- **Invoicing** - Create, send, and track invoices with line items and tax calculations
- **Payments** - Record payments and allocate to invoices
- **Quotes** - Generate quotes that can be converted to invoices
- **Products/Services** - Product catalog with pricing and tax rates

### Dashboard
- Accounting stats (revenue, outstanding, overdue)
- CRM stats (customers, opportunities, pipeline value)
- Revenue chart (12-month trend)
- Opportunity pipeline chart
- Latest invoices table
- Upcoming activities table

## Requirements

- PHP 8.2+
- Composer
- Node.js & npm
- SQLite (development) or MySQL/PostgreSQL (production)

## Installation

```bash
# Clone the repository
git clone https://github.com/markc/laracrm.git
cd laracrm

# Install dependencies
composer install
npm install

# Setup environment
cp .env.example .env
php artisan key:generate

# Create database and run migrations
touch database/database.sqlite
php artisan migrate

# Seed sample data (Chart of Accounts + Tax Rates)
php artisan db:seed

# Build frontend assets
npm run build

# Create admin user
php artisan make:filament-user

# Start development server
php artisan serve
```

Or use the setup script:
```bash
composer run setup
php artisan make:filament-user
composer run dev
```

## Usage

Access the admin panel at `http://localhost:8000/admin` after creating a user.

### Quick Start
1. **Setup Chart of Accounts** - Pre-seeded with standard accounts
2. **Add Customers** - Create individual or company customers
3. **Add Products** - Define your products/services with pricing
4. **Create Invoices** - Generate invoices from products
5. **Record Payments** - Track payments and allocate to invoices

## Tech Stack

| Component | Version |
|-----------|---------|
| PHP | 8.5 |
| Laravel | 12 |
| Filament | 5 |
| Livewire | 4 |
| Pest | 4 |

### Key Packages
- `filament/filament` - Admin panel framework
- `spatie/laravel-permission` - Role-based access control
- `spatie/laravel-activitylog` - Activity logging
- `spatie/laravel-medialibrary` - File attachments
- `barryvdh/laravel-dompdf` - PDF generation
- `maatwebsite/excel` - Excel import/export
- `brick/money` - Money handling

## Development

```bash
# Start all services (server, queue, logs, vite)
composer run dev

# Run tests
./vendor/bin/pest

# Run tests in parallel
./vendor/bin/pest --parallel

# Format code
vendor/bin/pint

# Generate IDE helpers
php artisan ide-helper:generate
```

## Architecture

```
app/
├── Enums/                 # Status/type enums (InvoiceStatus, CustomerType, etc.)
├── Exceptions/            # Custom exceptions (UnbalancedEntryException)
├── Filament/
│   ├── Resources/
│   │   ├── Accounting/    # Account, Invoice, Payment, Quote, Product
│   │   └── CRM/           # Customer, Contact, Opportunity, Activity
│   └── Widgets/           # Dashboard widgets
├── Models/
│   ├── Accounting/        # Account, Invoice, Payment, JournalEntry, etc.
│   └── CRM/               # Customer, Contact, Opportunity, Activity
└── Services/
    └── Accounting/        # Business logic (InvoiceService, PaymentService, etc.)
```

### Double-Entry Bookkeeping

The system uses proper double-entry accounting:

```php
// Creating a journal entry
$journalService->createEntry([
    'entry_date' => now(),
    'description' => 'Invoice payment',
    'lines' => [
        ['account_id' => $cashAccount, 'debit_amount' => 100],
        ['account_id' => $revenueAccount, 'credit_amount' => 100],
    ],
]);
```

All entries are validated to ensure debits equal credits before saving.

## Testing

```bash
# Run all tests
./vendor/bin/pest

# Run specific test file
./vendor/bin/pest tests/Feature/InvoiceTest.php

# Run with coverage
./vendor/bin/pest --coverage
```

## License

MIT License. See [LICENSE](LICENSE) for details.

## Credits

Built with [Laravel](https://laravel.com) and [Filament](https://filamentphp.com).
