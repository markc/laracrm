<?php

namespace App\Services\Accounting;

use App\Enums\QuoteStatus;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Quote;
use Illuminate\Support\Facades\DB;

class QuoteService
{
    public function __construct(
        protected InvoiceService $invoiceService
    ) {}

    public function convertToInvoice(Quote $quote): Invoice
    {
        if ($quote->status !== QuoteStatus::Approved) {
            throw new \Exception('Only approved quotes can be converted to invoices.');
        }

        return DB::transaction(function () use ($quote) {
            $quote->loadMissing(['items', 'customer']);

            $items = $quote->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'discount_percent' => $item->discount_percent,
                'tax_rate' => $item->tax_rate,
            ])->toArray();

            $invoice = $this->invoiceService->createInvoice($quote->customer, $items, [
                'notes' => $quote->notes,
                'terms' => $quote->terms,
            ]);

            $quote->update([
                'status' => QuoteStatus::Converted,
                'invoice_id' => $invoice->id,
            ]);

            return $invoice;
        });
    }

    public function sendQuote(Quote $quote): Quote
    {
        if ($quote->status !== QuoteStatus::Draft) {
            throw new \Exception('Only draft quotes can be sent.');
        }

        $quote->update([
            'status' => QuoteStatus::Sent,
            'sent_at' => now(),
        ]);

        return $quote->fresh();
    }

    public function approveQuote(Quote $quote): Quote
    {
        if ($quote->status !== QuoteStatus::Sent) {
            throw new \Exception('Only sent quotes can be approved.');
        }

        $quote->update([
            'status' => QuoteStatus::Approved,
            'approved_at' => now(),
        ]);

        return $quote->fresh();
    }

    public function rejectQuote(Quote $quote, ?string $reason = null): Quote
    {
        if (! in_array($quote->status, [QuoteStatus::Sent, QuoteStatus::Approved])) {
            throw new \Exception('Only sent or approved quotes can be rejected.');
        }

        $quote->update([
            'status' => QuoteStatus::Rejected,
            'notes' => $reason ? $quote->notes."\nRejection reason: {$reason}" : $quote->notes,
        ]);

        return $quote->fresh();
    }

    public function generateQuoteNumber(): string
    {
        $prefix = 'QTE-'.now()->format('Ym').'-';
        $lastQuote = Quote::where('quote_number', 'like', $prefix.'%')
            ->orderBy('quote_number', 'desc')
            ->first();

        $nextNumber = $lastQuote
            ? (int) substr($lastQuote->quote_number, -4) + 1
            : 1;

        return $prefix.str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
