<?php

namespace App\Console\Commands;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Enums\InvoiceStatus;
use App\Enums\ProductType;
use App\Enums\QuoteStatus;
use App\Enums\VendorBillStatus;
use App\Models\Accounting\Account;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceItem;
use App\Models\Accounting\Product;
use App\Models\Accounting\Quote;
use App\Models\Accounting\QuoteItem;
use App\Models\Accounting\VendorBill;
use App\Models\Accounting\VendorBillItem;
use App\Models\CRM\Customer;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportOdooData extends Command
{
    protected $signature = 'import:odoo-data
                            {--path=storage/odoo-import : Path to CSV files}
                            {--dry-run : Preview import without making changes}';

    protected $description = 'Import data from Odoo CSV exports into LaraCRM';

    private string $basePath;

    private bool $dryRun;

    private array $partnerMap = [];

    private array $productMap = [];

    private array $invoiceMap = [];

    private array $quoteMap = [];

    private array $invoiceTypes = [];

    private ?int $incomeAccountId = null;

    private ?int $expenseAccountId = null;

    private ?int $adminUserId = null;

    private array $stats = [
        'products' => 0,
        'customers' => 0,
        'vendors' => 0,
        'quotes' => 0,
        'quote_items' => 0,
        'invoices' => 0,
        'invoice_items' => 0,
        'vendor_bills' => 0,
        'vendor_bill_items' => 0,
    ];

    public function handle(): int
    {
        $this->basePath = base_path($this->option('path'));
        $this->dryRun = (bool) $this->option('dry-run');

        if (! is_dir($this->basePath)) {
            $this->error("Directory not found: {$this->basePath}");

            return Command::FAILURE;
        }

        $this->info('Starting Odoo data import...');
        if ($this->dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        $this->loadGLAccounts();

        try {
            DB::transaction(function () {
                $this->importProducts();
                $this->importPartners();
                $this->importSalesOrders();
                $this->importSaleLines();
                $this->importInvoices();
                $this->importInvoiceLines();

                if ($this->dryRun) {
                    throw new \Exception('Dry run - rolling back');
                }
            });
        } catch (\Exception $e) {
            if ($this->dryRun && $e->getMessage() === 'Dry run - rolling back') {
                $this->info('Dry run completed - all changes rolled back');
            } else {
                $this->error('Import failed: '.$e->getMessage());

                return Command::FAILURE;
            }
        }

        $this->printSummary();

        return Command::SUCCESS;
    }

    private function loadGLAccounts(): void
    {
        $this->incomeAccountId = Account::where('code', '4000')->value('id');
        $this->expenseAccountId = Account::where('code', '5000')->value('id');

        if (! $this->incomeAccountId || ! $this->expenseAccountId) {
            $this->warn('GL accounts 4000 and/or 5000 not found. Run database seeder first.');
        }

        $this->adminUserId = User::first()?->id;
        if (! $this->adminUserId) {
            throw new \RuntimeException('No user found in database. Run database seeder first.');
        }
    }

    private function importProducts(): void
    {
        $this->info('Importing products...');

        $file = $this->basePath.'/odoo_products.csv';
        if (! file_exists($file)) {
            $this->warn("File not found: {$file}");

            return;
        }

        foreach ($this->readCsv($file) as $row) {
            $odooId = (int) $row['id'];

            $sku = ! empty($row['sku']) ? $row['sku'] : 'ODOO-'.str_pad($odooId, 4, '0', STR_PAD_LEFT);

            $type = match ($row['type']) {
                'service' => ProductType::Service,
                default => ProductType::Product,
            };

            $product = Product::create([
                'sku' => $sku,
                'name' => $row['name'],
                'description' => $this->stripHtml($row['description'] ?? ''),
                'unit_price' => (float) ($row['list_price'] ?? 0),
                'type' => $type,
                'is_active' => $row['active'] === 't',
                'track_inventory' => $type === ProductType::Product,
                'income_account_id' => $this->incomeAccountId,
                'expense_account_id' => $this->expenseAccountId,
            ]);

            $this->productMap[$odooId] = $product->id;
            $this->stats['products']++;
        }

        $this->info("  Imported {$this->stats['products']} products");
    }

    private function importPartners(): void
    {
        $this->info('Importing partners (customers/vendors)...');

        $file = $this->basePath.'/odoo_partners.csv';
        if (! file_exists($file)) {
            $this->warn("File not found: {$file}");

            return;
        }

        foreach ($this->readCsv($file) as $row) {
            $odooId = (int) $row['id'];
            $isCompany = $row['is_company'] === 't';
            $isVendor = (int) ($row['supplier_rank'] ?? 0) > 0;

            $billingAddress = $this->buildAddress($row);

            $firstName = null;
            $lastName = null;
            $companyName = null;

            if ($isCompany) {
                $companyName = $row['name'];
            } else {
                $nameParts = $this->splitName($row['name'] ?? '');
                $firstName = $nameParts['first'];
                $lastName = $nameParts['last'];
            }

            $customer = Customer::create([
                'customer_number' => 'ODOO-'.str_pad($odooId, 4, '0', STR_PAD_LEFT),
                'company_name' => $companyName,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $row['email'] ?? null,
                'phone' => $row['phone'] ?: ($row['mobile'] ?? null),
                'tax_id' => $row['vat'] ?? null,
                'type' => $isCompany ? CustomerType::Company : CustomerType::Individual,
                'billing_address' => $billingAddress,
                'status' => $row['active'] === 't' ? CustomerStatus::Active : CustomerStatus::Inactive,
                'is_vendor' => $isVendor,
                'notes' => $row['comment'] ?? null,
            ]);

            $this->partnerMap[$odooId] = $customer->id;

            if ($isVendor) {
                $this->stats['vendors']++;
            } else {
                $this->stats['customers']++;
            }
        }

        $total = $this->stats['customers'] + $this->stats['vendors'];
        $this->info("  Imported {$total} partners ({$this->stats['customers']} customers, {$this->stats['vendors']} vendors)");
    }

    private function importSalesOrders(): void
    {
        $this->info('Importing sales orders as quotes...');

        $file = $this->basePath.'/odoo_sales.csv';
        if (! file_exists($file)) {
            $this->warn("File not found: {$file}");

            return;
        }

        foreach ($this->readCsv($file) as $row) {
            $odooId = (int) $row['id'];
            $partnerId = (int) $row['partner_id'];

            if (! isset($this->partnerMap[$partnerId])) {
                $this->warn("  Skipping quote {$row['order_number']}: partner {$partnerId} not found");

                continue;
            }

            $quoteNumber = ! empty($row['order_number'])
                ? 'QUO-'.$row['order_number']
                : 'QUO-'.str_pad($odooId, 5, '0', STR_PAD_LEFT);

            $status = match ($row['state']) {
                'sale' => QuoteStatus::Approved,
                'sent' => QuoteStatus::Sent,
                'cancel' => QuoteStatus::Rejected,
                default => QuoteStatus::Draft,
            };

            $quoteDate = $this->parseDate($row['date_order']);
            $validUntil = $this->parseDate($row['validity_date']);

            $quote = Quote::create([
                'quote_number' => $quoteNumber,
                'customer_id' => $this->partnerMap[$partnerId],
                'quote_date' => $quoteDate,
                'valid_until' => $validUntil,
                'subtotal' => (float) ($row['amount_untaxed'] ?? 0),
                'tax_amount' => (float) ($row['amount_tax'] ?? 0),
                'total_amount' => (float) ($row['amount_total'] ?? 0),
                'status' => $status,
                'created_by' => $this->adminUserId,
            ]);

            $this->quoteMap[$odooId] = $quote->id;
            $this->stats['quotes']++;
        }

        $this->info("  Imported {$this->stats['quotes']} quotes");
    }

    private function importSaleLines(): void
    {
        $this->info('Importing quote line items...');

        $file = $this->basePath.'/odoo_sale_lines.csv';
        if (! file_exists($file)) {
            $this->warn("File not found: {$file}");

            return;
        }

        foreach ($this->readCsv($file) as $row) {
            $orderId = (int) $row['order_id'];
            $productId = (int) ($row['product_id'] ?? 0);

            if (! isset($this->quoteMap[$orderId])) {
                continue;
            }

            $subtotal = (float) ($row['price_subtotal'] ?? 0);
            $total = (float) ($row['price_total'] ?? 0);
            $taxAmount = $total - $subtotal;
            $quantity = (float) ($row['quantity'] ?? 1);
            $taxRate = $quantity > 0 && $subtotal > 0 ? ($taxAmount / $subtotal) * 100 : 10;

            QuoteItem::create([
                'quote_id' => $this->quoteMap[$orderId],
                'product_id' => $this->productMap[$productId] ?? null,
                'description' => $row['description'] ?? '',
                'quantity' => $quantity,
                'unit_price' => (float) ($row['price_unit'] ?? 0),
                'discount_percent' => (float) ($row['discount'] ?? 0),
                'tax_rate' => round($taxRate, 2),
                'tax_amount' => $taxAmount,
                'total_amount' => $total,
            ]);

            $this->stats['quote_items']++;
        }

        $this->info("  Imported {$this->stats['quote_items']} quote items");
    }

    private function importInvoices(): void
    {
        $this->info('Importing invoices and vendor bills...');

        $file = $this->basePath.'/odoo_invoices.csv';
        if (! file_exists($file)) {
            $this->warn("File not found: {$file}");

            return;
        }

        foreach ($this->readCsv($file) as $row) {
            $odooId = (int) $row['id'];
            $moveType = $row['move_type'];
            $partnerId = (int) $row['partner_id'];

            $this->invoiceTypes[$odooId] = $moveType;

            if (! isset($this->partnerMap[$partnerId])) {
                $this->warn("  Skipping invoice {$odooId}: partner {$partnerId} not found");

                continue;
            }

            if ($moveType === 'out_invoice') {
                $this->createInvoice($odooId, $row, $partnerId);
            } elseif ($moveType === 'in_invoice') {
                $this->createVendorBill($odooId, $row, $partnerId);
            }
        }

        $this->info("  Imported {$this->stats['invoices']} customer invoices, {$this->stats['vendor_bills']} vendor bills");
    }

    private function createInvoice(int $odooId, array $row, int $partnerId): void
    {
        $invoiceNumber = ! empty($row['invoice_number'])
            ? 'INV-'.$row['invoice_number']
            : 'INV-'.str_pad($odooId, 5, '0', STR_PAD_LEFT);

        $residual = (float) ($row['amount_residual'] ?? 0);
        $total = (float) ($row['amount_total'] ?? 0);

        $status = match ($row['state']) {
            'posted' => $residual <= 0 ? InvoiceStatus::Paid : ($residual < $total ? InvoiceStatus::Partial : InvoiceStatus::Sent),
            'cancel' => InvoiceStatus::Void,
            default => InvoiceStatus::Draft,
        };

        $invoiceDate = $this->parseDate($row['invoice_date']) ?? $this->parseDate($row['invoice_date_due']) ?? now()->format('Y-m-d');
        $dueDate = $this->parseDate($row['invoice_date_due']) ?? $invoiceDate;

        $invoice = Invoice::create([
            'invoice_number' => $invoiceNumber,
            'customer_id' => $this->partnerMap[$partnerId],
            'invoice_date' => $invoiceDate,
            'due_date' => $dueDate,
            'subtotal' => (float) ($row['amount_untaxed'] ?? 0),
            'tax_amount' => (float) ($row['amount_tax'] ?? 0),
            'total_amount' => $total,
            'paid_amount' => $total - $residual,
            'balance_due' => $residual,
            'status' => $status,
            'created_by' => $this->adminUserId,
        ]);

        $this->invoiceMap[$odooId] = ['id' => $invoice->id, 'type' => 'invoice'];
        $this->stats['invoices']++;
    }

    private function createVendorBill(int $odooId, array $row, int $partnerId): void
    {
        $customerId = $this->partnerMap[$partnerId];
        $customer = Customer::find($customerId);

        if (! $customer->is_vendor) {
            $customer->update(['is_vendor' => true]);
        }

        $billNumber = $this->generateBillNumber($row);
        $vendorReference = $row['invoice_number'] ?? null;

        $residual = (float) ($row['amount_residual'] ?? 0);
        $total = (float) ($row['amount_total'] ?? 0);

        $status = match ($row['state']) {
            'posted' => $residual <= 0 ? VendorBillStatus::Paid : ($residual < $total ? VendorBillStatus::Partial : VendorBillStatus::Received),
            'cancel' => VendorBillStatus::Void,
            default => VendorBillStatus::Draft,
        };

        $billDate = $this->parseDate($row['invoice_date']) ?? $this->parseDate($row['invoice_date_due']) ?? now()->format('Y-m-d');
        $dueDate = $this->parseDate($row['invoice_date_due']) ?? $billDate;

        $vendorBill = VendorBill::create([
            'bill_number' => $billNumber,
            'vendor_id' => $customerId,
            'vendor_reference' => $vendorReference,
            'bill_date' => $billDate,
            'due_date' => $dueDate,
            'subtotal' => (float) ($row['amount_untaxed'] ?? 0),
            'tax_amount' => (float) ($row['amount_tax'] ?? 0),
            'total_amount' => $total,
            'paid_amount' => $total - $residual,
            'balance_due' => $residual,
            'status' => $status,
            'created_by' => $this->adminUserId,
        ]);

        $this->invoiceMap[$odooId] = ['id' => $vendorBill->id, 'type' => 'vendor_bill'];
        $this->stats['vendor_bills']++;
    }

    private function importInvoiceLines(): void
    {
        $this->info('Importing invoice/bill line items...');

        $file = $this->basePath.'/odoo_invoice_lines.csv';
        if (! file_exists($file)) {
            $this->warn("File not found: {$file}");

            return;
        }

        foreach ($this->readCsv($file) as $row) {
            $invoiceId = (int) $row['invoice_id'];
            $productId = (int) ($row['product_id'] ?? 0);

            if (! isset($this->invoiceMap[$invoiceId])) {
                continue;
            }

            $mapping = $this->invoiceMap[$invoiceId];
            $subtotal = (float) ($row['price_subtotal'] ?? 0);
            $total = (float) ($row['price_total'] ?? 0);
            $taxAmount = $total - $subtotal;
            $quantity = (float) ($row['quantity'] ?? 1);
            $taxRate = $quantity > 0 && $subtotal > 0 ? ($taxAmount / $subtotal) * 100 : 10;

            if ($mapping['type'] === 'invoice') {
                InvoiceItem::create([
                    'invoice_id' => $mapping['id'],
                    'product_id' => $this->productMap[$productId] ?? null,
                    'description' => $row['description'] ?? '',
                    'quantity' => $quantity,
                    'unit_price' => (float) ($row['price_unit'] ?? 0),
                    'discount_percent' => (float) ($row['discount'] ?? 0),
                    'tax_rate' => round($taxRate, 2),
                    'tax_amount' => $taxAmount,
                    'total_amount' => $total,
                ]);
                $this->stats['invoice_items']++;
            } else {
                VendorBillItem::create([
                    'vendor_bill_id' => $mapping['id'],
                    'product_id' => $this->productMap[$productId] ?? null,
                    'description' => $row['description'] ?? '',
                    'quantity' => $quantity,
                    'unit_price' => (float) ($row['price_unit'] ?? 0),
                    'tax_rate' => round($taxRate, 2),
                    'tax_amount' => $taxAmount,
                    'total_amount' => $total,
                ]);
                $this->stats['vendor_bill_items']++;
            }
        }

        $this->info("  Imported {$this->stats['invoice_items']} invoice items, {$this->stats['vendor_bill_items']} vendor bill items");
    }

    private function readCsv(string $file): \Generator
    {
        $handle = fopen($file, 'r');
        if (! $handle) {
            return;
        }

        $headers = fgetcsv($handle);
        if (! $headers) {
            fclose($handle);

            return;
        }

        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) === count($headers)) {
                yield array_combine($headers, $data);
            }
        }

        fclose($handle);
    }

    private function buildAddress(array $row): ?array
    {
        $street = trim(($row['street'] ?? '').($row['street2'] ? ', '.$row['street2'] : ''));

        if (empty($street) && empty($row['city']) && empty($row['zip'])) {
            return null;
        }

        return [
            'street' => $street ?: null,
            'city' => $row['city'] ?? null,
            'state' => null,
            'postcode' => $row['zip'] ?? null,
            'country' => 'Australia',
        ];
    }

    private function splitName(string $name): array
    {
        $name = trim($name);
        if (empty($name)) {
            return ['first' => null, 'last' => null];
        }

        $parts = explode(' ', $name, 2);

        return [
            'first' => $parts[0] ?? null,
            'last' => $parts[1] ?? null,
        ];
    }

    private function parseDate(?string $date): ?string
    {
        if (empty($date)) {
            return null;
        }

        if (str_contains($date, ' ')) {
            $date = explode(' ', $date)[0];
        }

        return $date;
    }

    private function stripHtml(?string $text): ?string
    {
        if (empty($text)) {
            return null;
        }

        return trim(strip_tags($text));
    }

    private function generateBillNumber(array $row): string
    {
        static $billCounter = [];

        $date = $this->parseDate($row['invoice_date']) ?: $this->parseDate($row['invoice_date_due']) ?: now()->format('Y-m-d');
        $yearMonth = substr($date, 0, 4).substr($date, 5, 2);

        if (! isset($billCounter[$yearMonth])) {
            $billCounter[$yearMonth] = 0;
        }
        $billCounter[$yearMonth]++;

        return 'BILL-'.$yearMonth.'-'.str_pad($billCounter[$yearMonth], 4, '0', STR_PAD_LEFT);
    }

    private function printSummary(): void
    {
        $this->newLine();
        $this->info('=== Import Summary ===');
        $this->table(
            ['Entity', 'Count'],
            [
                ['Products', $this->stats['products']],
                ['Customers', $this->stats['customers']],
                ['Vendors', $this->stats['vendors']],
                ['Quotes', $this->stats['quotes']],
                ['Quote Items', $this->stats['quote_items']],
                ['Invoices', $this->stats['invoices']],
                ['Invoice Items', $this->stats['invoice_items']],
                ['Vendor Bills', $this->stats['vendor_bills']],
                ['Vendor Bill Items', $this->stats['vendor_bill_items']],
            ]
        );
    }
}
