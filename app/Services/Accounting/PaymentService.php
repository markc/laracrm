<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Payment;
use App\Models\CRM\Customer;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(
        protected JournalEntryService $journalService,
        protected InvoiceService $invoiceService
    ) {}

    public function createPayment(Customer $customer, array $data): Payment
    {
        return DB::transaction(function () use ($customer, $data) {
            $payment = Payment::create([
                'payment_number' => $this->generatePaymentNumber(),
                'customer_id' => $customer->id,
                'payment_date' => $data['payment_date'] ?? now(),
                'amount' => $data['amount'],
                'allocated_amount' => 0,
                'unallocated_amount' => $data['amount'],
                'payment_method' => $data['payment_method'],
                'reference_number' => $data['reference_number'] ?? null,
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            // Create journal entry: Debit Bank/Cash, Credit AR
            $this->createPaymentJournalEntry($payment);

            // Auto-allocate to oldest invoices if requested
            if ($data['auto_allocate'] ?? false) {
                $this->autoAllocatePayment($payment);
            }

            return $payment->fresh(['allocations.invoice', 'customer']);
        });
    }

    public function allocatePayment(Payment $payment, Invoice $invoice, float $amount): void
    {
        if ($amount > $payment->unallocated_amount) {
            throw new \Exception('Allocation amount exceeds unallocated payment amount');
        }

        if ($amount > $invoice->balance_due) {
            throw new \Exception('Allocation amount exceeds invoice balance due');
        }

        DB::transaction(function () use ($payment, $invoice, $amount) {
            // Create allocation
            $payment->allocations()->create([
                'invoice_id' => $invoice->id,
                'amount' => $amount,
            ]);

            // Update payment amounts
            $payment->update([
                'allocated_amount' => $payment->allocated_amount + $amount,
                'unallocated_amount' => $payment->unallocated_amount - $amount,
            ]);

            // Update invoice amounts
            $invoice->update([
                'paid_amount' => $invoice->paid_amount + $amount,
                'balance_due' => $invoice->balance_due - $amount,
            ]);

            // Update invoice status
            $this->invoiceService->updateInvoiceStatus($invoice);
        });
    }

    public function autoAllocatePayment(Payment $payment): void
    {
        $unpaidInvoices = Invoice::where('customer_id', $payment->customer_id)
            ->unpaid()
            ->orderBy('due_date')
            ->orderBy('invoice_date')
            ->get();

        $remainingAmount = $payment->unallocated_amount;

        foreach ($unpaidInvoices as $invoice) {
            if ($remainingAmount <= 0) {
                break;
            }

            $allocationAmount = min($remainingAmount, $invoice->balance_due);
            $this->allocatePayment($payment, $invoice, $allocationAmount);

            $remainingAmount -= $allocationAmount;
            $payment->refresh();
        }
    }

    public function deallocatePayment(Payment $payment, Invoice $invoice): void
    {
        $allocation = $payment->allocations()->where('invoice_id', $invoice->id)->first();

        if (! $allocation) {
            throw new \Exception('No allocation found for this payment and invoice');
        }

        DB::transaction(function () use ($payment, $invoice, $allocation) {
            $amount = $allocation->amount;

            // Remove allocation
            $allocation->delete();

            // Update payment amounts
            $payment->update([
                'allocated_amount' => $payment->allocated_amount - $amount,
                'unallocated_amount' => $payment->unallocated_amount + $amount,
            ]);

            // Update invoice amounts
            $invoice->update([
                'paid_amount' => $invoice->paid_amount - $amount,
                'balance_due' => $invoice->balance_due + $amount,
            ]);

            // Update invoice status
            $this->invoiceService->updateInvoiceStatus($invoice);
        });
    }

    protected function createPaymentJournalEntry(Payment $payment): void
    {
        $arAccount = Account::where('code', '1200')->firstOrFail(); // Accounts Receivable

        // Determine the debit account (bank or cash)
        $debitAccount = $payment->bankAccount
            ? $payment->bankAccount->account
            : Account::where('code', '1000')->firstOrFail(); // Cash on Hand

        $lines = [
            [
                'account_id' => $debitAccount->id,
                'debit_amount' => $payment->amount,
                'credit_amount' => 0,
                'description' => "Payment {$payment->payment_number}",
            ],
            [
                'account_id' => $arAccount->id,
                'debit_amount' => 0,
                'credit_amount' => $payment->amount,
                'description' => "AR - Payment {$payment->payment_number}",
            ],
        ];

        $entry = $this->journalService->createEntry([
            'entry_date' => $payment->payment_date,
            'description' => "Payment {$payment->payment_number} - {$payment->customer->display_name}",
            'reference_type' => Payment::class,
            'reference_id' => $payment->id,
            'lines' => $lines,
        ]);

        $this->journalService->postEntry($entry);
    }

    protected function generatePaymentNumber(): string
    {
        $prefix = 'PAY-'.now()->format('Ym').'-';
        $lastPayment = Payment::where('payment_number', 'like', $prefix.'%')
            ->orderBy('payment_number', 'desc')
            ->first();

        $nextNumber = $lastPayment
            ? (int) substr($lastPayment->payment_number, -4) + 1
            : 1;

        return $prefix.str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
