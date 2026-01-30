<?php

namespace App\Services\Accounting;

use App\Enums\VendorBillStatus;
use App\Models\Accounting\Account;
use App\Models\Accounting\VendorBill;
use App\Models\CRM\Customer;
use Illuminate\Support\Facades\DB;

class VendorBillService
{
    public function __construct(
        protected JournalEntryService $journalService
    ) {}

    public function createBill(Customer $vendor, array $items, array $data = []): VendorBill
    {
        return DB::transaction(function () use ($vendor, $items, $data) {
            $totals = $this->calculateTotals($items);

            $bill = VendorBill::create([
                'bill_number' => $this->generateBillNumber(),
                'vendor_id' => $vendor->id,
                'vendor_reference' => $data['vendor_reference'] ?? null,
                'bill_date' => $data['bill_date'] ?? now(),
                'due_date' => $data['due_date'] ?? now()->addDays($vendor->payment_terms ?? 30),
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax'],
                'total_amount' => $totals['total'],
                'balance_due' => $totals['total'],
                'status' => VendorBillStatus::Draft,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            foreach ($items as $index => $item) {
                $itemTotal = $this->calculateItemTotal($item);
                $bill->items()->create([
                    'product_id' => $item['product_id'] ?? null,
                    'account_id' => $item['account_id'] ?? null,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'tax_rate' => $item['tax_rate'] ?? 10,
                    'tax_amount' => $itemTotal['tax'],
                    'total_amount' => $itemTotal['total'],
                    'sort_order' => $index,
                ]);
            }

            return $bill->fresh(['items', 'vendor']);
        });
    }

    public function receiveBill(VendorBill $bill): VendorBill
    {
        if ($bill->status === VendorBillStatus::Draft) {
            $bill->update([
                'status' => VendorBillStatus::Received,
                'received_at' => now(),
            ]);

            // Create journal entry: Debit Expense accounts, Credit AP
            $this->createBillJournalEntry($bill);
        }

        return $bill->fresh();
    }

    public function updateBillStatus(VendorBill $bill): void
    {
        if ($bill->status === VendorBillStatus::Void) {
            return;
        }

        $newStatus = match (true) {
            $bill->balance_due <= 0 => VendorBillStatus::Paid,
            $bill->paid_amount > 0 => VendorBillStatus::Partial,
            default => $bill->status,
        };

        if ($newStatus !== $bill->status) {
            $bill->update([
                'status' => $newStatus,
                'paid_at' => $newStatus === VendorBillStatus::Paid ? now() : null,
            ]);
        }
    }

    public function voidBill(VendorBill $bill, string $reason): VendorBill
    {
        if ($bill->paid_amount > 0) {
            throw new \Exception('Cannot void bill with payments. Reverse payments first.');
        }

        return DB::transaction(function () use ($bill, $reason) {
            $bill->update([
                'status' => VendorBillStatus::Void,
                'voided_at' => now(),
                'void_reason' => $reason,
            ]);

            // Reverse journal entry if exists
            if ($bill->journalEntry) {
                $this->journalService->reverseEntry($bill->journalEntry, "Bill voided: {$reason}");
            }

            return $bill->fresh();
        });
    }

    public function recalculateBill(VendorBill $bill): VendorBill
    {
        $items = $bill->items->map(fn ($item) => [
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'tax_rate' => $item->tax_rate,
        ])->toArray();

        $totals = $this->calculateTotals($items);

        $bill->update([
            'subtotal' => $totals['subtotal'],
            'tax_amount' => $totals['tax'],
            'total_amount' => $totals['total'],
            'balance_due' => $totals['total'] - $bill->paid_amount,
        ]);

        return $bill->fresh();
    }

    public function calculateTotals(array $items): array
    {
        $subtotal = 0;
        $tax = 0;

        foreach ($items as $item) {
            $itemTotal = $this->calculateItemTotal($item);
            $subtotal += $itemTotal['subtotal'];
            $tax += $itemTotal['tax'];
        }

        return [
            'subtotal' => round($subtotal, 2),
            'tax' => round($tax, 2),
            'total' => round($subtotal + $tax, 2),
        ];
    }

    public function calculateItemTotal(array $item): array
    {
        $lineTotal = $item['quantity'] * $item['unit_price'];
        $taxRate = $item['tax_rate'] ?? 10;
        $tax = $lineTotal * ($taxRate / 100);

        return [
            'subtotal' => round($lineTotal, 2),
            'tax' => round($tax, 2),
            'total' => round($lineTotal + $tax, 2),
        ];
    }

    protected function createBillJournalEntry(VendorBill $bill): void
    {
        $apAccount = Account::where('code', '2000')->firstOrFail(); // Accounts Payable
        $gstAccount = Account::where('code', '2110')->first(); // GST Paid (Input Tax Credit)
        $defaultExpenseAccount = Account::where('code', '5000')->first(); // Default expense account

        $lines = [];

        // Group items by expense account for cleaner journal entry
        $expensesByAccount = [];
        foreach ($bill->items as $item) {
            $accountId = $item->account_id ?? $item->product?->expense_account_id ?? $defaultExpenseAccount?->id;
            if (! isset($expensesByAccount[$accountId])) {
                $expensesByAccount[$accountId] = 0;
            }
            $expensesByAccount[$accountId] += $item->quantity * $item->unit_price;
        }

        // Debit expense accounts
        foreach ($expensesByAccount as $accountId => $amount) {
            if ($accountId && $amount > 0) {
                $lines[] = [
                    'account_id' => $accountId,
                    'debit_amount' => round($amount, 2),
                    'credit_amount' => 0,
                    'description' => "Expense - Bill {$bill->bill_number}",
                ];
            }
        }

        // Debit GST Paid (if tax amount)
        if ($bill->tax_amount > 0 && $gstAccount) {
            $lines[] = [
                'account_id' => $gstAccount->id,
                'debit_amount' => $bill->tax_amount,
                'credit_amount' => 0,
                'description' => "GST Input - Bill {$bill->bill_number}",
            ];
        }

        // Credit Accounts Payable
        $lines[] = [
            'account_id' => $apAccount->id,
            'debit_amount' => 0,
            'credit_amount' => $bill->total_amount,
            'description' => "AP - Bill {$bill->bill_number}",
        ];

        $entry = $this->journalService->createEntry([
            'entry_date' => $bill->bill_date,
            'description' => "Bill {$bill->bill_number} - {$bill->vendor->display_name}",
            'reference_type' => VendorBill::class,
            'reference_id' => $bill->id,
            'lines' => $lines,
        ]);

        $this->journalService->postEntry($entry);
    }

    public function recordPayment(VendorBill $bill, float $amount): VendorBill
    {
        if ($amount <= 0) {
            throw new \Exception('Payment amount must be positive.');
        }

        if ($amount > $bill->balance_due) {
            throw new \Exception('Payment amount exceeds balance due.');
        }

        $bill->update([
            'paid_amount' => $bill->paid_amount + $amount,
            'balance_due' => $bill->balance_due - $amount,
        ]);

        $this->updateBillStatus($bill);

        return $bill->fresh();
    }

    protected function generateBillNumber(): string
    {
        $prefix = 'BILL-'.now()->format('Ym').'-';
        $lastBill = VendorBill::where('bill_number', 'like', $prefix.'%')
            ->orderBy('bill_number', 'desc')
            ->first();

        $nextNumber = $lastBill
            ? (int) substr($lastBill->bill_number, -4) + 1
            : 1;

        return $prefix.str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
