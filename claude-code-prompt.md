# Accounting + CRM System - Claude Code Development Prompt

## Project Overview

Build a comprehensive Accounting and CRM system using Laravel 12 and Filament 5. This is a hosting business management tool that will eventually replace WHMCS, handling customer management, invoicing, payments, and double-entry bookkeeping.

## Technology Stack

- **Framework**: Laravel 12.x (latest)
- **Admin Panel**: Filament 5.x (latest)
- **Database**: SQLite initially (structured for easy migration to PostgreSQL)
- **PHP Version**: 8.4+
- **Frontend Build**: Bun (not npm)
- **Queue/Cache**: Database driver initially (Redis-ready)

## Initial Setup Instructions

```bash
# Create new Laravel project
composer create-project laravel/laravel accounting-crm
cd accounting-crm

# Install Filament 5
composer require filament/filament:"^5.0"
php artisan filament:install --panels

# Install required packages
composer require spatie/laravel-permission
composer require spatie/laravel-activitylog
composer require spatie/laravel-medialibrary
composer require maatwebsite/excel
composer require barryvdh/laravel-dompdf
composer require brick/money

# Filament plugins
composer require filament/spatie-laravel-media-library-plugin:"^5.0"
composer require bezhansalleh/filament-shield:"^5.0"
composer require pxlrbt/filament-excel

# Use Bun for frontend
bun install
```

## Directory Structure

Create the following organized structure:

```
app/
├── Filament/
│   ├── Resources/
│   │   ├── Accounting/
│   │   │   ├── AccountResource.php
│   │   │   ├── JournalEntryResource.php
│   │   │   ├── InvoiceResource.php
│   │   │   ├── PaymentResource.php
│   │   │   └── ExpenseResource.php
│   │   └── CRM/
│   │       ├── CustomerResource.php
│   │       ├── ContactResource.php
│   │       ├── OpportunityResource.php
│   │       └── ActivityResource.php
│   ├── Widgets/
│   │   ├── RevenueChart.php
│   │   ├── OutstandingInvoices.php
│   │   ├── CashFlowWidget.php
│   │   └── SalesPipelineWidget.php
│   └── Pages/
│       ├── Reports/
│       │   ├── ProfitLossReport.php
│       │   ├── BalanceSheetReport.php
│       │   └── AgingReport.php
│       └── Dashboard.php
├── Models/
│   ├── Accounting/
│   │   ├── Account.php
│   │   ├── JournalEntry.php
│   │   ├── JournalEntryLine.php
│   │   ├── Invoice.php
│   │   ├── InvoiceItem.php
│   │   ├── Payment.php
│   │   ├── PaymentAllocation.php
│   │   ├── Expense.php
│   │   ├── TaxRate.php
│   │   └── BankAccount.php
│   └── CRM/
│       ├── Customer.php
│       ├── Contact.php
│       ├── Opportunity.php
│       ├── Activity.php
│       ├── Quote.php
│       └── QuoteItem.php
├── Services/
│   ├── Accounting/
│   │   ├── JournalEntryService.php
│   │   ├── InvoiceService.php
│   │   ├── PaymentService.php
│   │   └── ReportingService.php
│   └── CRM/
│       ├── CustomerService.php
│       └── PipelineService.php
├── Actions/
│   ├── CreateInvoiceAction.php
│   ├── RecordPaymentAction.php
│   ├── ConvertQuoteToInvoiceAction.php
│   ├── PostJournalEntryAction.php
│   └── ReverseJournalEntryAction.php
├── Enums/
│   ├── AccountType.php
│   ├── InvoiceStatus.php
│   ├── PaymentMethod.php
│   ├── OpportunityStage.php
│   └── ActivityType.php
├── Events/
│   ├── InvoiceCreated.php
│   ├── PaymentReceived.php
│   └── JournalEntryPosted.php
├── Observers/
│   ├── InvoiceObserver.php
│   └── PaymentObserver.php
└── Exceptions/
    ├── UnbalancedEntryException.php
    └── InsufficientFundsException.php
```

---

## Database Schema

### Migration Order (create in this sequence):

#### 1. Accounting Core Tables

**accounts** (Chart of Accounts):
```php
Schema::create('accounts', function (Blueprint $table) {
    $table->id();
    $table->string('code', 20)->unique();
    $table->string('name');
    $table->string('type'); // asset, liability, equity, revenue, expense
    $table->string('normal_balance'); // debit, credit
    $table->foreignId('parent_id')->nullable()->constrained('accounts')->nullOnDelete();
    $table->string('currency', 3)->default('AUD');
    $table->text('description')->nullable();
    $table->boolean('is_active')->default(true);
    $table->boolean('is_system')->default(false); // prevent deletion of system accounts
    $table->timestamps();
    $table->softDeletes();
    
    $table->index(['type', 'is_active']);
});
```

**journal_entries**:
```php
Schema::create('journal_entries', function (Blueprint $table) {
    $table->id();
    $table->string('entry_number')->unique();
    $table->date('entry_date');
    $table->text('description');
    $table->string('reference_type')->nullable(); // Invoice::class, Payment::class, etc.
    $table->unsignedBigInteger('reference_id')->nullable();
    $table->foreignId('created_by')->constrained('users');
    $table->foreignId('approved_by')->nullable()->constrained('users');
    $table->timestamp('posted_at')->nullable();
    $table->boolean('is_posted')->default(false);
    $table->boolean('is_locked')->default(false);
    $table->foreignId('reversed_by_id')->nullable()->constrained('journal_entries');
    $table->timestamps();
    $table->softDeletes();
    
    $table->index(['entry_date', 'is_posted']);
    $table->index(['reference_type', 'reference_id']);
});
```

**journal_entry_lines**:
```php
Schema::create('journal_entry_lines', function (Blueprint $table) {
    $table->id();
    $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
    $table->foreignId('account_id')->constrained();
    $table->decimal('debit_amount', 15, 2)->default(0);
    $table->decimal('credit_amount', 15, 2)->default(0);
    $table->string('description')->nullable();
    $table->timestamps();
    
    $table->index('account_id');
    
    // Ensure only debit OR credit is non-zero
    // Note: SQLite doesn't support CHECK constraints well, enforce in application
});
```

#### 2. CRM Tables

**customers**:
```php
Schema::create('customers', function (Blueprint $table) {
    $table->id();
    $table->string('customer_number')->unique();
    $table->string('company_name')->nullable();
    $table->string('first_name')->nullable();
    $table->string('last_name')->nullable();
    $table->string('email')->nullable();
    $table->string('phone')->nullable();
    $table->string('tax_id')->nullable(); // ABN for Australian businesses
    $table->string('type')->default('individual'); // individual, company
    $table->json('billing_address')->nullable();
    $table->json('shipping_address')->nullable();
    $table->integer('payment_terms')->default(30); // days
    $table->decimal('credit_limit', 15, 2)->nullable();
    $table->string('currency', 3)->default('AUD');
    $table->string('status')->default('active'); // active, inactive, blocked
    $table->foreignId('assigned_to')->nullable()->constrained('users');
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->softDeletes();
    
    $table->index(['status', 'assigned_to']);
});
```

**contacts**:
```php
Schema::create('contacts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
    $table->string('first_name');
    $table->string('last_name');
    $table->string('email')->nullable();
    $table->string('phone')->nullable();
    $table->string('mobile')->nullable();
    $table->string('position')->nullable();
    $table->boolean('is_primary')->default(false);
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->softDeletes();
    
    $table->index('customer_id');
});
```

**opportunities**:
```php
Schema::create('opportunities', function (Blueprint $table) {
    $table->id();
    $table->foreignId('customer_id')->constrained();
    $table->string('name');
    $table->decimal('value', 15, 2)->nullable();
    $table->integer('probability')->default(50); // percentage
    $table->string('stage')->default('lead'); // lead, qualified, proposal, negotiation, won, lost
    $table->date('expected_close_date')->nullable();
    $table->foreignId('assigned_to')->nullable()->constrained('users');
    $table->string('lost_reason')->nullable();
    $table->timestamp('won_at')->nullable();
    $table->timestamp('lost_at')->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->softDeletes();
    
    $table->index(['stage', 'assigned_to']);
    $table->index('expected_close_date');
});
```

**activities**:
```php
Schema::create('activities', function (Blueprint $table) {
    $table->id();
    $table->foreignId('customer_id')->nullable()->constrained();
    $table->foreignId('contact_id')->nullable()->constrained();
    $table->foreignId('opportunity_id')->nullable()->constrained();
    $table->string('type'); // call, email, meeting, task, note
    $table->string('subject');
    $table->text('description')->nullable();
    $table->datetime('activity_date');
    $table->datetime('due_date')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->foreignId('assigned_to')->nullable()->constrained('users');
    $table->foreignId('created_by')->constrained('users');
    $table->timestamps();
    $table->softDeletes();
    
    $table->index(['customer_id', 'type']);
    $table->index('activity_date');
});
```

#### 3. Products & Invoicing Tables

**products**:
```php
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->string('sku')->unique();
    $table->string('name');
    $table->text('description')->nullable();
    $table->decimal('unit_price', 15, 2);
    $table->decimal('cost_price', 15, 2)->nullable();
    $table->decimal('tax_rate', 5, 2)->default(10.00); // GST default
    $table->foreignId('income_account_id')->nullable()->constrained('accounts');
    $table->foreignId('expense_account_id')->nullable()->constrained('accounts');
    $table->string('type')->default('service'); // service, product
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->softDeletes();
    
    $table->index('is_active');
});
```

**tax_rates**:
```php
Schema::create('tax_rates', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('code')->unique(); // GST, GST-FREE, etc.
    $table->decimal('rate', 5, 2);
    $table->string('type')->default('sales'); // sales, purchase
    $table->foreignId('account_id')->nullable()->constrained('accounts');
    $table->boolean('is_active')->default(true);
    $table->boolean('is_default')->default(false);
    $table->timestamps();
});
```

**invoices**:
```php
Schema::create('invoices', function (Blueprint $table) {
    $table->id();
    $table->string('invoice_number')->unique();
    $table->foreignId('customer_id')->constrained();
    $table->foreignId('quote_id')->nullable()->constrained('quotes');
    $table->date('invoice_date');
    $table->date('due_date');
    $table->decimal('subtotal', 15, 2)->default(0);
    $table->decimal('tax_amount', 15, 2)->default(0);
    $table->decimal('discount_amount', 15, 2)->default(0);
    $table->decimal('total_amount', 15, 2)->default(0);
    $table->decimal('paid_amount', 15, 2)->default(0);
    $table->decimal('balance_due', 15, 2)->default(0);
    $table->string('status')->default('draft'); // draft, sent, partial, paid, overdue, void
    $table->string('currency', 3)->default('AUD');
    $table->decimal('exchange_rate', 10, 6)->default(1);
    $table->text('notes')->nullable();
    $table->text('terms')->nullable();
    $table->timestamp('sent_at')->nullable();
    $table->timestamp('paid_at')->nullable();
    $table->timestamp('voided_at')->nullable();
    $table->string('void_reason')->nullable();
    $table->foreignId('created_by')->constrained('users');
    $table->timestamps();
    $table->softDeletes();
    
    $table->index(['customer_id', 'status']);
    $table->index(['due_date', 'status']);
});
```

**invoice_items**:
```php
Schema::create('invoice_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
    $table->foreignId('product_id')->nullable()->constrained();
    $table->string('description');
    $table->decimal('quantity', 10, 2)->default(1);
    $table->decimal('unit_price', 15, 2);
    $table->decimal('discount_percent', 5, 2)->default(0);
    $table->decimal('discount_amount', 15, 2)->default(0);
    $table->decimal('tax_rate', 5, 2)->default(10);
    $table->decimal('tax_amount', 15, 2)->default(0);
    $table->decimal('total_amount', 15, 2);
    $table->integer('sort_order')->default(0);
    $table->timestamps();
    
    $table->index('invoice_id');
});
```

**payments**:
```php
Schema::create('payments', function (Blueprint $table) {
    $table->id();
    $table->string('payment_number')->unique();
    $table->foreignId('customer_id')->constrained();
    $table->date('payment_date');
    $table->decimal('amount', 15, 2);
    $table->decimal('allocated_amount', 15, 2)->default(0);
    $table->decimal('unallocated_amount', 15, 2)->default(0);
    $table->string('payment_method'); // cash, cheque, card, bank_transfer, direct_debit
    $table->string('reference_number')->nullable();
    $table->foreignId('bank_account_id')->nullable()->constrained();
    $table->text('notes')->nullable();
    $table->foreignId('created_by')->constrained('users');
    $table->timestamps();
    $table->softDeletes();
    
    $table->index(['customer_id', 'payment_date']);
});
```

**payment_allocations**:
```php
Schema::create('payment_allocations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
    $table->foreignId('invoice_id')->constrained();
    $table->decimal('amount', 15, 2);
    $table->timestamps();
    
    $table->unique(['payment_id', 'invoice_id']);
});
```

**bank_accounts**:
```php
Schema::create('bank_accounts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('account_id')->constrained('accounts'); // Links to chart of accounts
    $table->string('bank_name');
    $table->string('account_name');
    $table->string('account_number');
    $table->string('bsb')->nullable(); // Australian bank code
    $table->string('currency', 3)->default('AUD');
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

#### 4. Quotes Tables

**quotes**:
```php
Schema::create('quotes', function (Blueprint $table) {
    $table->id();
    $table->string('quote_number')->unique();
    $table->foreignId('customer_id')->constrained();
    $table->foreignId('opportunity_id')->nullable()->constrained();
    $table->date('quote_date');
    $table->date('valid_until');
    $table->decimal('subtotal', 15, 2)->default(0);
    $table->decimal('tax_amount', 15, 2)->default(0);
    $table->decimal('discount_amount', 15, 2)->default(0);
    $table->decimal('total_amount', 15, 2)->default(0);
    $table->string('status')->default('draft'); // draft, sent, approved, rejected, converted, expired
    $table->text('notes')->nullable();
    $table->text('terms')->nullable();
    $table->timestamp('sent_at')->nullable();
    $table->timestamp('approved_at')->nullable();
    $table->foreignId('invoice_id')->nullable()->constrained(); // When converted
    $table->foreignId('created_by')->constrained('users');
    $table->timestamps();
    $table->softDeletes();
    
    $table->index(['customer_id', 'status']);
});
```

**quote_items**:
```php
Schema::create('quote_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
    $table->foreignId('product_id')->nullable()->constrained();
    $table->string('description');
    $table->decimal('quantity', 10, 2)->default(1);
    $table->decimal('unit_price', 15, 2);
    $table->decimal('discount_percent', 5, 2)->default(0);
    $table->decimal('discount_amount', 15, 2)->default(0);
    $table->decimal('tax_rate', 5, 2)->default(10);
    $table->decimal('tax_amount', 15, 2)->default(0);
    $table->decimal('total_amount', 15, 2);
    $table->integer('sort_order')->default(0);
    $table->timestamps();
});
```

#### 5. Expenses Table

**expenses**:
```php
Schema::create('expenses', function (Blueprint $table) {
    $table->id();
    $table->string('expense_number')->unique();
    $table->foreignId('vendor_id')->nullable()->constrained('customers'); // Vendors are also in customers
    $table->foreignId('account_id')->constrained('accounts'); // Expense account
    $table->foreignId('bank_account_id')->nullable()->constrained();
    $table->date('expense_date');
    $table->decimal('amount', 15, 2);
    $table->decimal('tax_amount', 15, 2)->default(0);
    $table->decimal('total_amount', 15, 2);
    $table->string('payment_method')->nullable();
    $table->string('reference_number')->nullable();
    $table->text('description')->nullable();
    $table->boolean('is_billable')->default(false);
    $table->foreignId('customer_id')->nullable()->constrained(); // If billable to customer
    $table->string('status')->default('recorded'); // recorded, reconciled
    $table->foreignId('created_by')->constrained('users');
    $table->timestamps();
    $table->softDeletes();
    
    $table->index(['expense_date', 'account_id']);
});
```

---

## Enums

Create PHP 8.4 backed enums:

**app/Enums/AccountType.php**:
```php
<?php

namespace App\Enums;

enum AccountType: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Revenue = 'revenue';
    case Expense = 'expense';
    
    public function label(): string
    {
        return match($this) {
            self::Asset => 'Asset',
            self::Liability => 'Liability',
            self::Equity => 'Equity',
            self::Revenue => 'Revenue',
            self::Expense => 'Expense',
        };
    }
    
    public function normalBalance(): string
    {
        return match($this) {
            self::Asset, self::Expense => 'debit',
            self::Liability, self::Equity, self::Revenue => 'credit',
        };
    }
}
```

**app/Enums/InvoiceStatus.php**:
```php
<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Partial = 'partial';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Void = 'void';
    
    public function label(): string
    {
        return match($this) {
            self::Draft => 'Draft',
            self::Sent => 'Sent',
            self::Partial => 'Partially Paid',
            self::Paid => 'Paid',
            self::Overdue => 'Overdue',
            self::Void => 'Void',
        };
    }
    
    public function color(): string
    {
        return match($this) {
            self::Draft => 'gray',
            self::Sent => 'info',
            self::Partial => 'warning',
            self::Paid => 'success',
            self::Overdue => 'danger',
            self::Void => 'gray',
        };
    }
}
```

Create similar enums for: `PaymentMethod`, `OpportunityStage`, `ActivityType`, `CustomerStatus`, `QuoteStatus`

---

## Core Services

### JournalEntryService

```php
<?php

namespace App\Services\Accounting;

use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\Account;
use App\Exceptions\UnbalancedEntryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class JournalEntryService
{
    public function createEntry(array $data): JournalEntry
    {
        $this->validateBalance($data['lines']);
        
        return DB::transaction(function () use ($data) {
            $entry = JournalEntry::create([
                'entry_number' => $this->generateEntryNumber(),
                'entry_date' => $data['entry_date'],
                'description' => $data['description'],
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'created_by' => auth()->id(),
            ]);
            
            foreach ($data['lines'] as $line) {
                $entry->lines()->create([
                    'account_id' => $line['account_id'],
                    'debit_amount' => $line['debit_amount'] ?? 0,
                    'credit_amount' => $line['credit_amount'] ?? 0,
                    'description' => $line['description'] ?? null,
                ]);
            }
            
            return $entry->fresh(['lines.account']);
        });
    }
    
    public function postEntry(JournalEntry $entry): bool
    {
        if ($entry->is_posted) {
            return false;
        }
        
        return DB::transaction(function () use ($entry) {
            $entry->update([
                'is_posted' => true,
                'posted_at' => now(),
                'approved_by' => auth()->id(),
                'is_locked' => true,
            ]);
            
            return true;
        });
    }
    
    public function reverseEntry(JournalEntry $entry, string $reason): JournalEntry
    {
        if (!$entry->is_posted) {
            throw new \Exception('Cannot reverse unposted entry');
        }
        
        return DB::transaction(function () use ($entry, $reason) {
            $reversingLines = $entry->lines->map(fn ($line) => [
                'account_id' => $line->account_id,
                'debit_amount' => $line->credit_amount,
                'credit_amount' => $line->debit_amount,
                'description' => "Reversal: {$line->description}",
            ])->toArray();
            
            $reversingEntry = $this->createEntry([
                'entry_date' => now(),
                'description' => "Reversal of {$entry->entry_number}: {$reason}",
                'lines' => $reversingLines,
            ]);
            
            $entry->update(['reversed_by_id' => $reversingEntry->id]);
            $this->postEntry($reversingEntry);
            
            return $reversingEntry;
        });
    }
    
    public function getAccountBalance(Account $account, ?\Carbon\Carbon $asOfDate = null): float
    {
        $query = $account->journalEntryLines()
            ->whereHas('journalEntry', fn ($q) => $q->where('is_posted', true));
        
        if ($asOfDate) {
            $query->whereHas('journalEntry', fn ($q) => $q->where('entry_date', '<=', $asOfDate));
        }
        
        $debits = (clone $query)->sum('debit_amount');
        $credits = (clone $query)->sum('credit_amount');
        
        return $account->normal_balance === 'debit' 
            ? $debits - $credits 
            : $credits - $debits;
    }
    
    protected function validateBalance(array $lines): void
    {
        $totalDebits = collect($lines)->sum('debit_amount');
        $totalCredits = collect($lines)->sum('credit_amount');
        
        if (bccomp((string) $totalDebits, (string) $totalCredits, 2) !== 0) {
            throw new UnbalancedEntryException(
                "Entry is unbalanced: Debits ({$totalDebits}) != Credits ({$totalCredits})"
            );
        }
    }
    
    protected function generateEntryNumber(): string
    {
        $prefix = 'JE-' . now()->format('Ym') . '-';
        $lastEntry = JournalEntry::where('entry_number', 'like', $prefix . '%')
            ->orderBy('entry_number', 'desc')
            ->first();
        
        $nextNumber = $lastEntry 
            ? (int) substr($lastEntry->entry_number, -4) + 1 
            : 1;
        
        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
```

### InvoiceService

```php
<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Invoice;
use App\Models\Accounting\Account;
use App\Models\CRM\Customer;
use App\Enums\InvoiceStatus;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function __construct(
        protected JournalEntryService $journalService
    ) {}
    
    public function createInvoice(Customer $customer, array $items, array $data = []): Invoice
    {
        return DB::transaction(function () use ($customer, $items, $data) {
            $totals = $this->calculateTotals($items);
            
            $invoice = Invoice::create([
                'invoice_number' => $this->generateInvoiceNumber(),
                'customer_id' => $customer->id,
                'invoice_date' => $data['invoice_date'] ?? now(),
                'due_date' => $data['due_date'] ?? now()->addDays($customer->payment_terms),
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax'],
                'discount_amount' => $totals['discount'],
                'total_amount' => $totals['total'],
                'balance_due' => $totals['total'],
                'status' => InvoiceStatus::Draft,
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
                'created_by' => auth()->id(),
            ]);
            
            foreach ($items as $index => $item) {
                $itemTotal = $this->calculateItemTotal($item);
                $invoice->items()->create([
                    'product_id' => $item['product_id'] ?? null,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount_percent' => $item['discount_percent'] ?? 0,
                    'discount_amount' => $itemTotal['discount'],
                    'tax_rate' => $item['tax_rate'] ?? 10,
                    'tax_amount' => $itemTotal['tax'],
                    'total_amount' => $itemTotal['total'],
                    'sort_order' => $index,
                ]);
            }
            
            return $invoice->fresh(['items', 'customer']);
        });
    }
    
    public function sendInvoice(Invoice $invoice): Invoice
    {
        if ($invoice->status === InvoiceStatus::Draft) {
            $invoice->update([
                'status' => InvoiceStatus::Sent,
                'sent_at' => now(),
            ]);
            
            // Create journal entry: Debit AR, Credit Revenue
            $this->createInvoiceJournalEntry($invoice);
            
            // TODO: Send email notification
        }
        
        return $invoice->fresh();
    }
    
    public function updateInvoiceStatus(Invoice $invoice): void
    {
        if ($invoice->status === InvoiceStatus::Void) {
            return;
        }
        
        $newStatus = match (true) {
            $invoice->balance_due <= 0 => InvoiceStatus::Paid,
            $invoice->paid_amount > 0 => InvoiceStatus::Partial,
            $invoice->due_date < now() && $invoice->status === InvoiceStatus::Sent => InvoiceStatus::Overdue,
            default => $invoice->status,
        };
        
        if ($newStatus !== $invoice->status) {
            $invoice->update([
                'status' => $newStatus,
                'paid_at' => $newStatus === InvoiceStatus::Paid ? now() : null,
            ]);
        }
    }
    
    public function voidInvoice(Invoice $invoice, string $reason): Invoice
    {
        if ($invoice->paid_amount > 0) {
            throw new \Exception('Cannot void invoice with payments. Refund payments first.');
        }
        
        return DB::transaction(function () use ($invoice, $reason) {
            $invoice->update([
                'status' => InvoiceStatus::Void,
                'voided_at' => now(),
                'void_reason' => $reason,
            ]);
            
            // Reverse journal entry if exists
            if ($invoice->journalEntry) {
                $this->journalService->reverseEntry($invoice->journalEntry, "Invoice voided: {$reason}");
            }
            
            return $invoice->fresh();
        });
    }
    
    public function calculateTotals(array $items): array
    {
        $subtotal = 0;
        $tax = 0;
        $discount = 0;
        
        foreach ($items as $item) {
            $itemTotal = $this->calculateItemTotal($item);
            $subtotal += $itemTotal['subtotal'];
            $tax += $itemTotal['tax'];
            $discount += $itemTotal['discount'];
        }
        
        return [
            'subtotal' => round($subtotal, 2),
            'tax' => round($tax, 2),
            'discount' => round($discount, 2),
            'total' => round($subtotal + $tax - $discount, 2),
        ];
    }
    
    protected function calculateItemTotal(array $item): array
    {
        $lineTotal = $item['quantity'] * $item['unit_price'];
        $discountPercent = $item['discount_percent'] ?? 0;
        $discount = $lineTotal * ($discountPercent / 100);
        $taxableAmount = $lineTotal - $discount;
        $taxRate = $item['tax_rate'] ?? 10;
        $tax = $taxableAmount * ($taxRate / 100);
        
        return [
            'subtotal' => round($lineTotal, 2),
            'discount' => round($discount, 2),
            'tax' => round($tax, 2),
            'total' => round($taxableAmount + $tax, 2),
        ];
    }
    
    protected function createInvoiceJournalEntry(Invoice $invoice): void
    {
        $arAccount = Account::where('code', '1200')->firstOrFail(); // Accounts Receivable
        $revenueAccount = Account::where('code', '4000')->firstOrFail(); // Sales Revenue
        $taxAccount = Account::where('code', '2100')->firstOrFail(); // GST Collected
        
        $lines = [
            [
                'account_id' => $arAccount->id,
                'debit_amount' => $invoice->total_amount,
                'credit_amount' => 0,
                'description' => "AR - Invoice {$invoice->invoice_number}",
            ],
            [
                'account_id' => $revenueAccount->id,
                'debit_amount' => 0,
                'credit_amount' => $invoice->subtotal,
                'description' => "Revenue - Invoice {$invoice->invoice_number}",
            ],
        ];
        
        if ($invoice->tax_amount > 0) {
            $lines[] = [
                'account_id' => $taxAccount->id,
                'debit_amount' => 0,
                'credit_amount' => $invoice->tax_amount,
                'description' => "GST - Invoice {$invoice->invoice_number}",
            ];
        }
        
        $entry = $this->journalService->createEntry([
            'entry_date' => $invoice->invoice_date,
            'description' => "Invoice {$invoice->invoice_number} - {$invoice->customer->display_name}",
            'reference_type' => Invoice::class,
            'reference_id' => $invoice->id,
            'lines' => $lines,
        ]);
        
        $this->journalService->postEntry($entry);
    }
    
    protected function generateInvoiceNumber(): string
    {
        $prefix = 'INV-' . now()->format('Ym') . '-';
        $lastInvoice = Invoice::where('invoice_number', 'like', $prefix . '%')
            ->orderBy('invoice_number', 'desc')
            ->first();
        
        $nextNumber = $lastInvoice 
            ? (int) substr($lastInvoice->invoice_number, -4) + 1 
            : 1;
        
        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
```

---

## Filament Resources

### Example: CustomerResource

```php
<?php

namespace App\Filament\Resources\CRM;

use App\Filament\Resources\CRM\CustomerResource\Pages;
use App\Filament\Resources\CRM\CustomerResource\RelationManagers;
use App\Models\CRM\Customer;
use App\Enums\CustomerStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'CRM';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->options([
                                'individual' => 'Individual',
                                'company' => 'Company',
                            ])
                            ->required()
                            ->live(),
                        Forms\Components\TextInput::make('company_name')
                            ->visible(fn ($get) => $get('type') === 'company')
                            ->required(fn ($get) => $get('type') === 'company'),
                        Forms\Components\TextInput::make('first_name')
                            ->required(fn ($get) => $get('type') === 'individual'),
                        Forms\Components\TextInput::make('last_name')
                            ->required(fn ($get) => $get('type') === 'individual'),
                        Forms\Components\TextInput::make('email')
                            ->email(),
                        Forms\Components\TextInput::make('phone')
                            ->tel(),
                        Forms\Components\TextInput::make('tax_id')
                            ->label('ABN')
                            ->maxLength(11),
                    ])
                    ->columns(2),
                    
                Forms\Components\Section::make('Billing Address')
                    ->schema([
                        Forms\Components\TextInput::make('billing_address.street'),
                        Forms\Components\TextInput::make('billing_address.city'),
                        Forms\Components\TextInput::make('billing_address.state'),
                        Forms\Components\TextInput::make('billing_address.postcode'),
                        Forms\Components\TextInput::make('billing_address.country')
                            ->default('Australia'),
                    ])
                    ->columns(2),
                    
                Forms\Components\Section::make('Account Settings')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options(CustomerStatus::class)
                            ->default('active'),
                        Forms\Components\TextInput::make('payment_terms')
                            ->numeric()
                            ->default(30)
                            ->suffix('days'),
                        Forms\Components\TextInput::make('credit_limit')
                            ->numeric()
                            ->prefix('$'),
                        Forms\Components\Select::make('assigned_to')
                            ->relationship('assignedUser', 'name'),
                    ])
                    ->columns(2),
                    
                Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer_number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('display_name')
                    ->label('Name')
                    ->searchable(['company_name', 'first_name', 'last_name']),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone'),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'active',
                        'warning' => 'inactive',
                        'danger' => 'blocked',
                    ]),
                Tables\Columns\TextColumn::make('invoices_sum_balance_due')
                    ->sum('invoices', 'balance_due')
                    ->money('AUD')
                    ->label('Outstanding'),
                Tables\Columns\TextColumn::make('assignedUser.name')
                    ->label('Assigned To'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(CustomerStatus::class),
                Tables\Filters\SelectFilter::make('assigned_to')
                    ->relationship('assignedUser', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ContactsRelationManager::class,
            RelationManagers\InvoicesRelationManager::class,
            RelationManagers\PaymentsRelationManager::class,
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
}
```

---

## Database Seeders

Create a seeder for the default Chart of Accounts (Australian standard):

```php
<?php

namespace Database\Seeders;

use App\Models\Accounting\Account;
use App\Enums\AccountType;
use Illuminate\Database\Seeder;

class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            // Assets (1000-1999)
            ['code' => '1000', 'name' => 'Cash on Hand', 'type' => AccountType::Asset],
            ['code' => '1010', 'name' => 'Bank Account - Operating', 'type' => AccountType::Asset],
            ['code' => '1020', 'name' => 'Bank Account - Savings', 'type' => AccountType::Asset],
            ['code' => '1200', 'name' => 'Accounts Receivable', 'type' => AccountType::Asset, 'is_system' => true],
            ['code' => '1300', 'name' => 'Prepaid Expenses', 'type' => AccountType::Asset],
            ['code' => '1500', 'name' => 'Computer Equipment', 'type' => AccountType::Asset],
            ['code' => '1510', 'name' => 'Accumulated Depreciation - Equipment', 'type' => AccountType::Asset],
            
            // Liabilities (2000-2999)
            ['code' => '2000', 'name' => 'Accounts Payable', 'type' => AccountType::Liability, 'is_system' => true],
            ['code' => '2100', 'name' => 'GST Collected', 'type' => AccountType::Liability, 'is_system' => true],
            ['code' => '2110', 'name' => 'GST Paid', 'type' => AccountType::Liability, 'is_system' => true],
            ['code' => '2200', 'name' => 'PAYG Withholding Payable', 'type' => AccountType::Liability],
            ['code' => '2300', 'name' => 'Superannuation Payable', 'type' => AccountType::Liability],
            ['code' => '2400', 'name' => 'Credit Cards Payable', 'type' => AccountType::Liability],
            ['code' => '2500', 'name' => 'Loans Payable', 'type' => AccountType::Liability],
            
            // Equity (3000-3999)
            ['code' => '3000', 'name' => 'Owner\'s Equity', 'type' => AccountType::Equity],
            ['code' => '3100', 'name' => 'Owner\'s Drawings', 'type' => AccountType::Equity],
            ['code' => '3200', 'name' => 'Retained Earnings', 'type' => AccountType::Equity, 'is_system' => true],
            
            // Revenue (4000-4999)
            ['code' => '4000', 'name' => 'Sales Revenue', 'type' => AccountType::Revenue, 'is_system' => true],
            ['code' => '4100', 'name' => 'Service Revenue', 'type' => AccountType::Revenue],
            ['code' => '4200', 'name' => 'Hosting Revenue', 'type' => AccountType::Revenue],
            ['code' => '4300', 'name' => 'Domain Revenue', 'type' => AccountType::Revenue],
            ['code' => '4900', 'name' => 'Other Income', 'type' => AccountType::Revenue],
            
            // Expenses (5000-5999)
            ['code' => '5000', 'name' => 'Cost of Goods Sold', 'type' => AccountType::Expense],
            ['code' => '5100', 'name' => 'Server Costs', 'type' => AccountType::Expense],
            ['code' => '5200', 'name' => 'Domain Costs', 'type' => AccountType::Expense],
            ['code' => '5300', 'name' => 'Bandwidth Costs', 'type' => AccountType::Expense],
            ['code' => '6000', 'name' => 'Advertising & Marketing', 'type' => AccountType::Expense],
            ['code' => '6100', 'name' => 'Bank Fees', 'type' => AccountType::Expense],
            ['code' => '6200', 'name' => 'Depreciation Expense', 'type' => AccountType::Expense],
            ['code' => '6300', 'name' => 'Insurance', 'type' => AccountType::Expense],
            ['code' => '6400', 'name' => 'Interest Expense', 'type' => AccountType::Expense],
            ['code' => '6500', 'name' => 'Office Supplies', 'type' => AccountType::Expense],
            ['code' => '6600', 'name' => 'Professional Fees', 'type' => AccountType::Expense],
            ['code' => '6700', 'name' => 'Rent', 'type' => AccountType::Expense],
            ['code' => '6800', 'name' => 'Software Subscriptions', 'type' => AccountType::Expense],
            ['code' => '6900', 'name' => 'Telephone & Internet', 'type' => AccountType::Expense],
            ['code' => '7000', 'name' => 'Travel & Entertainment', 'type' => AccountType::Expense],
            ['code' => '7100', 'name' => 'Utilities', 'type' => AccountType::Expense],
            ['code' => '7200', 'name' => 'Wages & Salaries', 'type' => AccountType::Expense],
            ['code' => '7300', 'name' => 'Superannuation Expense', 'type' => AccountType::Expense],
        ];
        
        foreach ($accounts as $account) {
            Account::create([
                'code' => $account['code'],
                'name' => $account['name'],
                'type' => $account['type'],
                'normal_balance' => $account['type']->normalBalance(),
                'is_system' => $account['is_system'] ?? false,
                'is_active' => true,
            ]);
        }
    }
}
```

---

## Configuration Notes

### SQLite Configuration (config/database.php)
```php
'sqlite' => [
    'driver' => 'sqlite',
    'url' => env('DB_URL'),
    'database' => env('DB_DATABASE', database_path('database.sqlite')),
    'prefix' => '',
    'foreign_key_constraints' => true,
    // Enable WAL mode for better concurrent access
],
```

### Model Configuration

All models should use:
- `SoftDeletes` trait
- Appropriate `$casts` for enums and JSON fields
- Scopes for common queries
- Accessors for computed values (e.g., `display_name` on Customer)

### Activity Logging

Configure Spatie Activity Log to automatically log changes on all financial models.

---

## Development Phases

### Phase 1: Foundation (Week 1)
1. Set up Laravel 12 + Filament 5
2. Create all migrations
3. Create all models with relationships
4. Set up enums
5. Seed Chart of Accounts
6. Create basic Filament resources for Account and Customer

### Phase 2: CRM Core (Week 2)
1. Complete CustomerResource with all relation managers
2. ContactResource
3. OpportunityResource with pipeline/kanban view
4. ActivityResource
5. Dashboard widgets for CRM

### Phase 3: Accounting Core (Week 3)
1. JournalEntryService
2. JournalEntryResource
3. InvoiceService
4. InvoiceResource with line items
5. Auto journal entry creation

### Phase 4: Payments & Quotes (Week 4)
1. PaymentService
2. PaymentResource with allocation
3. QuoteResource
4. Quote to Invoice conversion
5. ExpenseResource

### Phase 5: Reporting (Week 5)
1. ReportingService
2. Profit & Loss report page
3. Balance Sheet report page
4. Aging reports (AR/AP)
5. PDF export

### Phase 6: Polish (Week 6)
1. Dashboard with all widgets
2. Permissions with Filament Shield
3. Activity log viewing
4. Settings management
5. Testing and bug fixes

---

## Key Implementation Rules

1. **Always use database transactions** for multi-model operations
2. **Never delete posted journal entries** - only reverse them
3. **Validate double-entry balance** before saving journal entries
4. **Auto-generate document numbers** with prefix and sequence
5. **Use Money library** (brick/money) for all currency calculations
6. **Log all financial operations** using Spatie Activity Log
7. **Use Enums** for all status fields - no magic strings
8. **Apply soft deletes** on all models to preserve audit trail
9. **Create journal entries** automatically when invoices are sent and payments recorded
10. **Update invoice status** automatically when payments are allocated
