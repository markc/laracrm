<?php

namespace Database\Seeders;

use App\Enums\ActivityType;
use App\Enums\CustomerStatus;
use App\Enums\InvoiceStatus;
use App\Enums\OpportunityStage;
use App\Enums\PaymentMethod;
use App\Enums\QuoteStatus;
use App\Models\Accounting\Account;
use App\Models\Accounting\BankAccount;
use App\Models\Accounting\Expense;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceItem;
use App\Models\Accounting\Payment;
use App\Models\Accounting\PaymentAllocation;
use App\Models\Accounting\Product;
use App\Models\Accounting\Quote;
use App\Models\Accounting\QuoteItem;
use App\Models\CRM\Activity;
use App\Models\CRM\Contact;
use App\Models\CRM\Customer;
use App\Models\CRM\Opportunity;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoDataSeeder extends Seeder
{
    private array $users = [];

    private array $customers = [];

    private array $products = [];

    private array $bankAccounts = [];

    public function run(): void
    {
        $this->command->info('Starting demo data seeding...');

        DB::transaction(function () {
            $this->seedUsers();
            $this->seedBankAccounts();
            $this->seedProducts();
            $this->seedCustomers();
            $this->seedContacts();
            $this->seedOpportunities();
            $this->seedQuotes();
            $this->seedInvoices();
            $this->seedPayments();
            $this->seedExpenses();
            $this->seedActivities();
        });

        $this->command->info('Demo data seeding completed!');
        $this->printSummary();
    }

    private function seedUsers(): void
    {
        $this->command->info('Seeding users...');

        $usersData = [
            ['name' => 'Admin User', 'email' => 'admin@example.com'],
            ['name' => 'Sarah Johnson', 'email' => 'sarah@example.com'],
            ['name' => 'Michael Chen', 'email' => 'michael@example.com'],
            ['name' => 'Emma Williams', 'email' => 'emma@example.com'],
            ['name' => 'James Brown', 'email' => 'james@example.com'],
        ];

        foreach ($usersData as $userData) {
            $this->users[] = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => bcrypt('password'),
                ]
            );
        }
    }

    private function seedBankAccounts(): void
    {
        $this->command->info('Seeding bank accounts...');

        $operatingAccount = Account::where('code', '1010')->first();
        $savingsAccount = Account::where('code', '1020')->first();

        $bankAccountsData = [
            [
                'account_id' => $operatingAccount?->id,
                'bank_name' => 'Commonwealth Bank',
                'account_name' => 'Business Operating',
                'account_number' => '12345678',
                'bsb' => '062-000',
                'currency' => 'AUD',
                'is_active' => true,
            ],
            [
                'account_id' => $savingsAccount?->id,
                'bank_name' => 'Commonwealth Bank',
                'account_name' => 'Business Savings',
                'account_number' => '87654321',
                'bsb' => '062-000',
                'currency' => 'AUD',
                'is_active' => true,
            ],
            [
                'account_id' => $operatingAccount?->id,
                'bank_name' => 'Westpac',
                'account_name' => 'Merchant Account',
                'account_number' => '11223344',
                'bsb' => '032-000',
                'currency' => 'AUD',
                'is_active' => true,
            ],
        ];

        foreach ($bankAccountsData as $data) {
            $this->bankAccounts[] = BankAccount::firstOrCreate(
                ['account_number' => $data['account_number']],
                $data
            );
        }
    }

    private function seedProducts(): void
    {
        $this->command->info('Seeding products...');

        $salesAccount = Account::where('code', '4000')->first();
        $serviceAccount = Account::where('code', '4100')->first();
        $hostingAccount = Account::where('code', '4200')->first();
        $cogsAccount = Account::where('code', '5000')->first();
        $serverCostsAccount = Account::where('code', '5100')->first();

        $productsData = [
            // Services
            ['sku' => 'DEV-HOUR', 'name' => 'Web Development - Hourly', 'unit_price' => 150.00, 'cost_price' => 0, 'type' => 'service', 'income_account_id' => $serviceAccount?->id],
            ['sku' => 'DEV-DAY', 'name' => 'Web Development - Daily', 'unit_price' => 1100.00, 'cost_price' => 0, 'type' => 'service', 'income_account_id' => $serviceAccount?->id],
            ['sku' => 'CONSULT', 'name' => 'Technical Consulting', 'unit_price' => 200.00, 'cost_price' => 0, 'type' => 'service', 'income_account_id' => $serviceAccount?->id],
            ['sku' => 'DESIGN-HR', 'name' => 'Graphic Design - Hourly', 'unit_price' => 120.00, 'cost_price' => 0, 'type' => 'service', 'income_account_id' => $serviceAccount?->id],
            ['sku' => 'SEO-MON', 'name' => 'SEO Optimization - Monthly', 'unit_price' => 800.00, 'cost_price' => 0, 'type' => 'service', 'income_account_id' => $serviceAccount?->id],
            ['sku' => 'SUPPORT-HR', 'name' => 'IT Support - Hourly', 'unit_price' => 95.00, 'cost_price' => 0, 'type' => 'service', 'income_account_id' => $serviceAccount?->id],
            ['sku' => 'MAINT-MON', 'name' => 'Website Maintenance - Monthly', 'unit_price' => 350.00, 'cost_price' => 0, 'type' => 'service', 'income_account_id' => $serviceAccount?->id],

            // Products (Hosting)
            ['sku' => 'HOST-BASIC', 'name' => 'Web Hosting - Basic', 'unit_price' => 15.00, 'cost_price' => 5.00, 'type' => 'product', 'income_account_id' => $hostingAccount?->id, 'expense_account_id' => $serverCostsAccount?->id],
            ['sku' => 'HOST-PRO', 'name' => 'Web Hosting - Professional', 'unit_price' => 45.00, 'cost_price' => 15.00, 'type' => 'product', 'income_account_id' => $hostingAccount?->id, 'expense_account_id' => $serverCostsAccount?->id],
            ['sku' => 'HOST-BIZ', 'name' => 'Web Hosting - Business', 'unit_price' => 99.00, 'cost_price' => 35.00, 'type' => 'product', 'income_account_id' => $hostingAccount?->id, 'expense_account_id' => $serverCostsAccount?->id],
            ['sku' => 'HOST-ENT', 'name' => 'Web Hosting - Enterprise', 'unit_price' => 299.00, 'cost_price' => 100.00, 'type' => 'product', 'income_account_id' => $hostingAccount?->id, 'expense_account_id' => $serverCostsAccount?->id],
            ['sku' => 'DOMAIN', 'name' => 'Domain Registration - Annual', 'unit_price' => 25.00, 'cost_price' => 12.00, 'type' => 'product', 'income_account_id' => $salesAccount?->id, 'expense_account_id' => $cogsAccount?->id],
            ['sku' => 'SSL-STD', 'name' => 'SSL Certificate - Standard', 'unit_price' => 99.00, 'cost_price' => 30.00, 'type' => 'product', 'income_account_id' => $salesAccount?->id, 'expense_account_id' => $cogsAccount?->id],
            ['sku' => 'SSL-WILD', 'name' => 'SSL Certificate - Wildcard', 'unit_price' => 299.00, 'cost_price' => 100.00, 'type' => 'product', 'income_account_id' => $salesAccount?->id, 'expense_account_id' => $cogsAccount?->id],
            ['sku' => 'EMAIL-10', 'name' => 'Email Hosting - 10 Accounts', 'unit_price' => 50.00, 'cost_price' => 15.00, 'type' => 'product', 'income_account_id' => $hostingAccount?->id, 'expense_account_id' => $serverCostsAccount?->id],
            ['sku' => 'BACKUP-MON', 'name' => 'Cloud Backup - Monthly', 'unit_price' => 25.00, 'cost_price' => 8.00, 'type' => 'product', 'income_account_id' => $hostingAccount?->id, 'expense_account_id' => $serverCostsAccount?->id],
        ];

        foreach ($productsData as $data) {
            $this->products[] = Product::firstOrCreate(
                ['sku' => $data['sku']],
                array_merge($data, [
                    'tax_rate' => 10.00,
                    'is_active' => true,
                ])
            );
        }
    }

    private function seedCustomers(): void
    {
        $this->command->info('Seeding customers (100 companies, 50 individuals)...');

        // Create 100 company customers
        for ($i = 0; $i < 100; $i++) {
            $this->customers[] = Customer::factory()
                ->company()
                ->create([
                    'status' => fake()->randomElement([
                        CustomerStatus::Active,
                        CustomerStatus::Active,
                        CustomerStatus::Active,
                        CustomerStatus::Inactive,
                        CustomerStatus::Blocked,
                    ]),
                    'assigned_to' => fake()->randomElement($this->users)->id,
                ]);
        }

        // Create 50 individual customers
        for ($i = 0; $i < 50; $i++) {
            $this->customers[] = Customer::factory()
                ->individual()
                ->create([
                    'status' => fake()->randomElement([
                        CustomerStatus::Active,
                        CustomerStatus::Active,
                        CustomerStatus::Inactive,
                    ]),
                    'assigned_to' => fake()->randomElement($this->users)->id,
                ]);
        }
    }

    private function seedContacts(): void
    {
        $this->command->info('Seeding contacts...');

        foreach ($this->customers as $customer) {
            // Create 1-4 contacts per customer
            $contactCount = fake()->numberBetween(1, 4);

            for ($i = 0; $i < $contactCount; $i++) {
                Contact::factory()->create([
                    'customer_id' => $customer->id,
                    'is_primary' => $i === 0,
                ]);
            }
        }
    }

    private function seedOpportunities(): void
    {
        $this->command->info('Seeding opportunities (200 total)...');

        $activeCustomers = collect($this->customers)->filter(fn ($c) => $c->status === CustomerStatus::Active);

        // Create opportunities in various stages [stage, count]
        $stageDistribution = [
            [OpportunityStage::Lead, 30],
            [OpportunityStage::Qualified, 25],
            [OpportunityStage::Proposal, 35],
            [OpportunityStage::Negotiation, 20],
            [OpportunityStage::Won, 60],
            [OpportunityStage::Lost, 30],
        ];

        foreach ($stageDistribution as [$stage, $count]) {
            for ($i = 0; $i < $count; $i++) {
                $customer = $activeCustomers->random();

                Opportunity::factory()->create([
                    'customer_id' => $customer->id,
                    'stage' => $stage,
                    'probability' => $stage->probability(),
                    'assigned_to' => fake()->randomElement($this->users)->id,
                    'won_at' => $stage === OpportunityStage::Won ? fake()->dateTimeBetween('-6 months', 'now') : null,
                    'lost_at' => $stage === OpportunityStage::Lost ? fake()->dateTimeBetween('-6 months', 'now') : null,
                    'lost_reason' => $stage === OpportunityStage::Lost
                        ? fake()->randomElement(['Price too high', 'Chose competitor', 'Project cancelled', 'Budget constraints', 'Timing not right'])
                        : null,
                ]);
            }
        }
    }

    private function seedQuotes(): void
    {
        $this->command->info('Seeding quotes (150 total)...');

        $activeCustomers = collect($this->customers)->filter(fn ($c) => $c->status === CustomerStatus::Active);
        $opportunities = Opportunity::whereIn('stage', [OpportunityStage::Proposal, OpportunityStage::Negotiation, OpportunityStage::Won])->get();

        // [status, count]
        $statusDistribution = [
            [QuoteStatus::Draft, 15],
            [QuoteStatus::Sent, 25],
            [QuoteStatus::Approved, 30],
            [QuoteStatus::Rejected, 20],
            [QuoteStatus::Converted, 40],
            [QuoteStatus::Expired, 20],
        ];

        $quoteNumber = 1001;

        foreach ($statusDistribution as [$status, $count]) {
            for ($i = 0; $i < $count; $i++) {
                $customer = $activeCustomers->random();
                $opportunity = $opportunities->where('customer_id', $customer->id)->first();

                $quoteDate = fake()->dateTimeBetween('-6 months', 'now');
                $validUntil = (clone $quoteDate)->modify('+30 days');

                $quote = Quote::create([
                    'quote_number' => 'QUO-'.str_pad($quoteNumber++, 6, '0', STR_PAD_LEFT),
                    'customer_id' => $customer->id,
                    'opportunity_id' => $opportunity?->id,
                    'quote_date' => $quoteDate,
                    'valid_until' => $validUntil,
                    'subtotal' => 0,
                    'tax_amount' => 0,
                    'discount_amount' => 0,
                    'total_amount' => 0,
                    'status' => $status,
                    'notes' => fake()->boolean(40) ? fake()->paragraph() : null,
                    'terms' => 'Quote valid for 30 days. Prices subject to change.',
                    'sent_at' => in_array($status, [QuoteStatus::Sent, QuoteStatus::Approved, QuoteStatus::Rejected, QuoteStatus::Converted])
                        ? fake()->dateTimeBetween($quoteDate, 'now') : null,
                    'approved_at' => in_array($status, [QuoteStatus::Approved, QuoteStatus::Converted])
                        ? fake()->dateTimeBetween($quoteDate, 'now') : null,
                    'created_by' => fake()->randomElement($this->users)->id,
                ]);

                // Add 2-6 line items
                $this->addQuoteItems($quote, fake()->numberBetween(2, 6));
            }
        }
    }

    private function addQuoteItems(Quote $quote, int $count): void
    {
        $subtotal = 0;
        $taxTotal = 0;

        for ($i = 0; $i < $count; $i++) {
            $product = fake()->randomElement($this->products);
            $quantity = fake()->randomFloat(2, 1, 20);
            $unitPrice = $product->unit_price;
            $discountPercent = fake()->boolean(25) ? fake()->randomFloat(2, 5, 15) : 0;

            $lineSubtotal = $quantity * $unitPrice;
            $discountAmount = round($lineSubtotal * ($discountPercent / 100), 2);
            $taxableAmount = $lineSubtotal - $discountAmount;
            $taxAmount = round($taxableAmount * 0.10, 2);
            $totalAmount = $taxableAmount + $taxAmount;

            QuoteItem::create([
                'quote_id' => $quote->id,
                'product_id' => $product->id,
                'description' => $product->name,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_percent' => $discountPercent,
                'discount_amount' => $discountAmount,
                'tax_rate' => 10.00,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'sort_order' => $i,
            ]);

            $subtotal += $lineSubtotal - $discountAmount;
            $taxTotal += $taxAmount;
        }

        $quote->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxTotal,
            'total_amount' => $subtotal + $taxTotal,
        ]);
    }

    private function seedInvoices(): void
    {
        $this->command->info('Seeding invoices (300 total)...');

        $activeCustomers = collect($this->customers)->filter(fn ($c) => $c->status === CustomerStatus::Active);

        // [status, count]
        $statusDistribution = [
            [InvoiceStatus::Draft, 20],
            [InvoiceStatus::Sent, 40],
            [InvoiceStatus::Partial, 30],
            [InvoiceStatus::Paid, 150],
            [InvoiceStatus::Overdue, 45],
            [InvoiceStatus::Void, 15],
        ];

        $invoiceNumber = 10001;

        foreach ($statusDistribution as [$status, $count]) {
            for ($i = 0; $i < $count; $i++) {
                $customer = $activeCustomers->random();
                $paymentTerms = $customer->payment_terms ?? 30;

                $invoiceDate = match ($status) {
                    InvoiceStatus::Overdue => fake()->dateTimeBetween('-4 months', '-2 months'),
                    InvoiceStatus::Paid => fake()->dateTimeBetween('-12 months', '-1 month'),
                    default => fake()->dateTimeBetween('-3 months', 'now'),
                };

                $dueDate = (clone $invoiceDate)->modify("+{$paymentTerms} days");

                $invoice = Invoice::create([
                    'invoice_number' => 'INV-'.str_pad($invoiceNumber++, 6, '0', STR_PAD_LEFT),
                    'customer_id' => $customer->id,
                    'invoice_date' => $invoiceDate,
                    'due_date' => $dueDate,
                    'subtotal' => 0,
                    'tax_amount' => 0,
                    'discount_amount' => 0,
                    'total_amount' => 0,
                    'paid_amount' => 0,
                    'balance_due' => 0,
                    'status' => $status,
                    'currency' => 'AUD',
                    'exchange_rate' => 1.000000,
                    'notes' => fake()->boolean(30) ? fake()->sentence() : null,
                    'terms' => "Payment due within {$paymentTerms} days.",
                    'sent_at' => $status !== InvoiceStatus::Draft
                        ? fake()->dateTimeBetween($invoiceDate, 'now') : null,
                    'paid_at' => $status === InvoiceStatus::Paid
                        ? fake()->dateTimeBetween($invoiceDate, 'now') : null,
                    'voided_at' => $status === InvoiceStatus::Void
                        ? fake()->dateTimeBetween($invoiceDate, 'now') : null,
                    'void_reason' => $status === InvoiceStatus::Void
                        ? fake()->randomElement(['Duplicate invoice', 'Customer cancelled', 'Issued in error']) : null,
                    'created_by' => fake()->randomElement($this->users)->id,
                ]);

                // Add 1-8 line items
                $this->addInvoiceItems($invoice, fake()->numberBetween(1, 8));

                // Update paid amounts based on status
                $this->updateInvoiceAmounts($invoice, $status);
            }
        }
    }

    private function addInvoiceItems(Invoice $invoice, int $count): void
    {
        $subtotal = 0;
        $taxTotal = 0;

        for ($i = 0; $i < $count; $i++) {
            $product = fake()->randomElement($this->products);
            $quantity = fake()->randomFloat(2, 1, 20);
            $unitPrice = $product->unit_price;
            $discountPercent = fake()->boolean(20) ? fake()->randomFloat(2, 5, 15) : 0;

            $lineSubtotal = $quantity * $unitPrice;
            $discountAmount = round($lineSubtotal * ($discountPercent / 100), 2);
            $taxableAmount = $lineSubtotal - $discountAmount;
            $taxAmount = round($taxableAmount * 0.10, 2);
            $totalAmount = $taxableAmount + $taxAmount;

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'product_id' => $product->id,
                'description' => $product->name,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_percent' => $discountPercent,
                'discount_amount' => $discountAmount,
                'tax_rate' => 10.00,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'sort_order' => $i,
            ]);

            $subtotal += $lineSubtotal - $discountAmount;
            $taxTotal += $taxAmount;
        }

        $invoice->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxTotal,
            'total_amount' => $subtotal + $taxTotal,
        ]);
    }

    private function updateInvoiceAmounts(Invoice $invoice, InvoiceStatus $status): void
    {
        $paidAmount = match ($status) {
            InvoiceStatus::Paid => $invoice->total_amount,
            InvoiceStatus::Partial => round($invoice->total_amount * fake()->randomFloat(2, 0.2, 0.8), 2),
            InvoiceStatus::Void => 0,
            default => 0,
        };

        $invoice->update([
            'paid_amount' => $paidAmount,
            'balance_due' => $status === InvoiceStatus::Void ? 0 : $invoice->total_amount - $paidAmount,
        ]);
    }

    private function seedPayments(): void
    {
        $this->command->info('Seeding payments...');

        // Get all paid and partially paid invoices
        $invoices = Invoice::whereIn('status', [InvoiceStatus::Paid, InvoiceStatus::Partial])->get();
        $paymentNumber = 1001;

        foreach ($invoices as $invoice) {
            if ($invoice->paid_amount <= 0) {
                continue;
            }

            // Create 1-3 payments per invoice
            $remainingAmount = $invoice->paid_amount;
            $paymentCount = $invoice->status === InvoiceStatus::Partial ? 1 : fake()->numberBetween(1, 2);

            for ($i = 0; $i < $paymentCount && $remainingAmount > 0; $i++) {
                $amount = $i === $paymentCount - 1
                    ? $remainingAmount
                    : round($remainingAmount * fake()->randomFloat(2, 0.3, 0.7), 2);

                $payment = Payment::create([
                    'payment_number' => 'PAY-'.str_pad($paymentNumber++, 6, '0', STR_PAD_LEFT),
                    'customer_id' => $invoice->customer_id,
                    'payment_date' => fake()->dateTimeBetween($invoice->invoice_date, 'now'),
                    'amount' => $amount,
                    'allocated_amount' => $amount,
                    'unallocated_amount' => 0,
                    'payment_method' => fake()->randomElement(PaymentMethod::cases()),
                    'reference_number' => fake()->boolean(70) ? fake()->bothify('REF-####-????') : null,
                    'bank_account_id' => fake()->randomElement($this->bankAccounts)->id,
                    'notes' => fake()->boolean(20) ? 'Payment for '.$invoice->invoice_number : null,
                    'created_by' => fake()->randomElement($this->users)->id,
                ]);

                // Create payment allocation
                PaymentAllocation::create([
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                    'amount' => $amount,
                ]);

                $remainingAmount -= $amount;
            }
        }

        // Create some unallocated payments (customer credits)
        for ($i = 0; $i < 20; $i++) {
            $customer = fake()->randomElement($this->customers);
            $amount = fake()->randomFloat(2, 100, 2000);

            Payment::create([
                'payment_number' => 'PAY-'.str_pad($paymentNumber++, 6, '0', STR_PAD_LEFT),
                'customer_id' => $customer->id,
                'payment_date' => fake()->dateTimeBetween('-3 months', 'now'),
                'amount' => $amount,
                'allocated_amount' => 0,
                'unallocated_amount' => $amount,
                'payment_method' => fake()->randomElement(PaymentMethod::cases()),
                'reference_number' => fake()->bothify('ADV-####'),
                'bank_account_id' => fake()->randomElement($this->bankAccounts)->id,
                'notes' => 'Advance payment / Customer credit',
                'created_by' => fake()->randomElement($this->users)->id,
            ]);
        }
    }

    private function seedExpenses(): void
    {
        $this->command->info('Seeding expenses (200 total)...');

        $expenseAccounts = Account::whereIn('code', [
            '5100', '5200', '5300', '6000', '6100', '6200', '6300',
            '6400', '6500', '6600', '6700', '6800', '6900', '7000', '7100',
        ])->get();

        $expenseNumber = 1001;

        $expenseTypes = [
            '5100' => ['Server hosting - AWS', 'Server hosting - DigitalOcean', 'Cloud compute fees'],
            '5200' => ['Domain renewal - client domains', 'Domain registration bulk'],
            '5300' => ['Bandwidth overage', 'CDN charges'],
            '6000' => ['Google Ads', 'Facebook Ads', 'LinkedIn Advertising', 'Marketing materials'],
            '6100' => ['Bank transfer fee', 'Merchant fee', 'PayPal fees'],
            '6300' => ['Professional indemnity insurance', 'Public liability insurance'],
            '6500' => ['Printer supplies', 'Stationery', 'Office equipment'],
            '6600' => ['Legal fees', 'Accounting fees', 'Consulting fees'],
            '6700' => ['Office rent', 'Co-working space'],
            '6800' => ['Adobe Creative Cloud', 'JetBrains IDE', 'GitHub subscription', 'Slack subscription'],
            '6900' => ['Internet bill', 'Mobile phone bill', 'VoIP service'],
            '7000' => ['Client lunch', 'Conference travel', 'Accommodation'],
            '7100' => ['Electricity', 'Water'],
        ];

        for ($i = 0; $i < 200; $i++) {
            $account = $expenseAccounts->random();
            $descriptions = $expenseTypes[$account->code] ?? ['General expense'];

            $amount = fake()->randomFloat(2, 20, 3000);
            $taxAmount = round($amount * 0.10, 2);

            // Some expenses are billable to customers
            $isBillable = fake()->boolean(15);
            $customer = $isBillable ? fake()->randomElement($this->customers) : null;

            Expense::create([
                'expense_number' => 'EXP-'.str_pad($expenseNumber++, 6, '0', STR_PAD_LEFT),
                'vendor_id' => fake()->boolean(70) ? fake()->randomElement($this->customers)->id : null,
                'account_id' => $account->id,
                'bank_account_id' => fake()->randomElement($this->bankAccounts)->id,
                'expense_date' => fake()->dateTimeBetween('-12 months', 'now'),
                'amount' => $amount,
                'tax_amount' => $taxAmount,
                'total_amount' => $amount + $taxAmount,
                'payment_method' => fake()->randomElement(PaymentMethod::cases()),
                'reference_number' => fake()->boolean(60) ? fake()->bothify('INV-####') : null,
                'description' => fake()->randomElement($descriptions),
                'is_billable' => $isBillable,
                'customer_id' => $customer?->id,
                'status' => fake()->randomElement(['draft', 'approved', 'paid', 'paid', 'paid']),
                'created_by' => fake()->randomElement($this->users)->id,
            ]);
        }
    }

    private function seedActivities(): void
    {
        $this->command->info('Seeding activities (500 total)...');

        $contacts = Contact::all();
        $opportunities = Opportunity::all();

        for ($i = 0; $i < 500; $i++) {
            $customer = fake()->randomElement($this->customers);
            $contact = $contacts->where('customer_id', $customer->id)->random();
            $opportunity = fake()->boolean(40) ? $opportunities->where('customer_id', $customer->id)->first() : null;

            $type = fake()->randomElement(ActivityType::cases());
            $activityDate = fake()->dateTimeBetween('-6 months', '+1 month');
            $hasDueDate = in_array($type, [ActivityType::Task, ActivityType::Meeting, ActivityType::Call]);

            // Can only be completed if activity date is in the past
            $activityInPast = $activityDate < now();
            $isCompleted = $activityInPast && fake()->boolean(65);

            Activity::create([
                'customer_id' => $customer->id,
                'contact_id' => $contact?->id,
                'opportunity_id' => $opportunity?->id,
                'type' => $type,
                'subject' => $this->getActivitySubject($type),
                'description' => fake()->boolean(60) ? fake()->paragraph() : null,
                'activity_date' => $activityDate,
                'due_date' => $hasDueDate ? fake()->dateTimeBetween($activityDate, (clone $activityDate)->modify('+2 weeks')) : null,
                'completed_at' => $isCompleted ? fake()->dateTimeBetween($activityDate, 'now') : null,
                'assigned_to' => fake()->randomElement($this->users)->id,
                'created_by' => fake()->randomElement($this->users)->id,
            ]);
        }
    }

    private function getActivitySubject(ActivityType $type): string
    {
        return match ($type) {
            ActivityType::Call => fake()->randomElement([
                'Initial discovery call',
                'Follow-up call',
                'Sales call',
                'Support call',
                'Quarterly check-in',
                'Project status call',
                'Renewal discussion',
            ]),
            ActivityType::Email => fake()->randomElement([
                'Sent proposal',
                'Follow-up email',
                'Quote request response',
                'Invoice reminder sent',
                'Project update',
                'Welcome email',
                'Contract sent',
            ]),
            ActivityType::Meeting => fake()->randomElement([
                'Sales presentation',
                'Project kickoff meeting',
                'Quarterly business review',
                'Product demo',
                'Contract negotiation',
                'Requirements workshop',
                'Strategy session',
            ]),
            ActivityType::Task => fake()->randomElement([
                'Prepare proposal',
                'Update CRM records',
                'Send contract',
                'Review project scope',
                'Schedule follow-up',
                'Complete assessment',
                'Process renewal',
            ]),
            ActivityType::Note => fake()->randomElement([
                'Customer feedback received',
                'Meeting notes',
                'Important update',
                'Internal note',
                'Contact preference noted',
                'Special requirements',
                'Pricing discussion',
            ]),
        };
    }

    private function printSummary(): void
    {
        $this->command->newLine();
        $this->command->info('=== Demo Data Summary ===');
        $this->command->line('Users: '.User::count());
        $this->command->line('Bank Accounts: '.BankAccount::count());
        $this->command->line('Products: '.Product::count());
        $this->command->line('Customers: '.Customer::count());
        $this->command->line('Contacts: '.Contact::count());
        $this->command->line('Opportunities: '.Opportunity::count());
        $this->command->line('Quotes: '.Quote::count());
        $this->command->line('Quote Items: '.QuoteItem::count());
        $this->command->line('Invoices: '.Invoice::count());
        $this->command->line('Invoice Items: '.InvoiceItem::count());
        $this->command->line('Payments: '.Payment::count());
        $this->command->line('Payment Allocations: '.PaymentAllocation::count());
        $this->command->line('Expenses: '.Expense::count());
        $this->command->line('Activities: '.Activity::count());
        $this->command->newLine();
    }
}
