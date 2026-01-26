<?php

namespace App\Services\Accounting;

use App\Enums\InvoiceStatus;
use App\Models\Accounting\Account;
use App\Models\Accounting\Invoice;
use App\Models\CRM\Customer;
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

    public function recalculateInvoice(Invoice $invoice): Invoice
    {
        $items = $invoice->items->map(fn ($item) => [
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'discount_percent' => $item->discount_percent,
            'tax_rate' => $item->tax_rate,
        ])->toArray();

        $totals = $this->calculateTotals($items);

        $invoice->update([
            'subtotal' => $totals['subtotal'],
            'tax_amount' => $totals['tax'],
            'discount_amount' => $totals['discount'],
            'total_amount' => $totals['total'],
            'balance_due' => $totals['total'] - $invoice->paid_amount,
        ]);

        return $invoice->fresh();
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

    public function calculateItemTotal(array $item): array
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
        $taxAccount = Account::where('code', '2100')->first(); // GST Collected

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

        if ($invoice->tax_amount > 0 && $taxAccount) {
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
        $prefix = 'INV-'.now()->format('Ym').'-';
        $lastInvoice = Invoice::where('invoice_number', 'like', $prefix.'%')
            ->orderBy('invoice_number', 'desc')
            ->first();

        $nextNumber = $lastInvoice
            ? (int) substr($lastInvoice->invoice_number, -4) + 1
            : 1;

        return $prefix.str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
