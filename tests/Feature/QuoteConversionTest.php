<?php

use App\Enums\InvoiceStatus;
use App\Enums\QuoteStatus;
use App\Models\Accounting\Account;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Quote;
use App\Models\Accounting\QuoteItem;
use App\Models\CRM\Customer;
use App\Models\User;
use App\Services\Accounting\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    // Ensure required GL accounts exist
    Account::firstOrCreate(['code' => '1200'], [
        'name' => 'Accounts Receivable',
        'type' => 'asset',
        'normal_balance' => 'debit',
        'is_system' => true,
        'is_active' => true,
    ]);
    Account::firstOrCreate(['code' => '4000'], [
        'name' => 'Sales Revenue',
        'type' => 'revenue',
        'normal_balance' => 'credit',
        'is_system' => true,
        'is_active' => true,
    ]);
    Account::firstOrCreate(['code' => '2100'], [
        'name' => 'GST Collected',
        'type' => 'liability',
        'normal_balance' => 'credit',
        'is_system' => true,
        'is_active' => true,
    ]);
});

it('converts an approved quote to an invoice', function () {
    $customer = Customer::factory()->create();
    $quote = Quote::factory()->create([
        'customer_id' => $customer->id,
        'status' => QuoteStatus::Approved,
        'subtotal' => 1000.00,
        'tax_amount' => 100.00,
        'total_amount' => 1100.00,
        'created_by' => $this->user->id,
    ]);

    QuoteItem::factory()->create([
        'quote_id' => $quote->id,
        'description' => 'Web Development',
        'quantity' => 10,
        'unit_price' => 100.00,
        'discount_percent' => 0,
        'tax_rate' => 10,
        'tax_amount' => 100.00,
        'total_amount' => 1100.00,
    ]);

    $service = app(QuoteService::class);
    $invoice = $service->convertToInvoice($quote);

    expect($invoice)->toBeInstanceOf(Invoice::class)
        ->and($invoice->customer_id)->toBe($customer->id)
        ->and($invoice->status)->toBe(InvoiceStatus::Draft)
        ->and($invoice->items)->toHaveCount(1);

    // Verify quote was updated
    $quote->refresh();
    expect($quote->status)->toBe(QuoteStatus::Converted)
        ->and($quote->invoice_id)->toBe($invoice->id);
});

it('copies all line items with correct amounts', function () {
    $customer = Customer::factory()->create();
    $quote = Quote::factory()->create([
        'customer_id' => $customer->id,
        'status' => QuoteStatus::Approved,
        'created_by' => $this->user->id,
    ]);

    QuoteItem::factory()->create([
        'quote_id' => $quote->id,
        'description' => 'Service A',
        'quantity' => 5,
        'unit_price' => 200.00,
        'discount_percent' => 10,
        'tax_rate' => 10,
    ]);

    QuoteItem::factory()->create([
        'quote_id' => $quote->id,
        'description' => 'Service B',
        'quantity' => 2,
        'unit_price' => 50.00,
        'discount_percent' => 0,
        'tax_rate' => 10,
    ]);

    $service = app(QuoteService::class);
    $invoice = $service->convertToInvoice($quote);

    expect($invoice->items)->toHaveCount(2);

    $itemA = $invoice->items->where('description', 'Service A')->first();
    expect($itemA->quantity)->toBe('5.00')
        ->and($itemA->unit_price)->toBe('200.00')
        ->and($itemA->discount_percent)->toBe('10.00')
        ->and($itemA->tax_rate)->toBe('10.00');

    $itemB = $invoice->items->where('description', 'Service B')->first();
    expect($itemB->quantity)->toBe('2.00')
        ->and($itemB->unit_price)->toBe('50.00');
});

it('prevents converting a draft quote', function () {
    $customer = Customer::factory()->create();
    $quote = Quote::factory()->create([
        'customer_id' => $customer->id,
        'status' => QuoteStatus::Draft,
        'created_by' => $this->user->id,
    ]);

    $service = app(QuoteService::class);

    expect(fn () => $service->convertToInvoice($quote))
        ->toThrow(Exception::class, 'Only approved quotes can be converted');
});

it('prevents converting an already converted quote', function () {
    $customer = Customer::factory()->create();
    $quote = Quote::factory()->create([
        'customer_id' => $customer->id,
        'status' => QuoteStatus::Converted,
        'created_by' => $this->user->id,
    ]);

    $service = app(QuoteService::class);

    expect(fn () => $service->convertToInvoice($quote))
        ->toThrow(Exception::class, 'Only approved quotes can be converted');
});

it('sends a draft quote', function () {
    $customer = Customer::factory()->create();
    $quote = Quote::factory()->create([
        'customer_id' => $customer->id,
        'status' => QuoteStatus::Draft,
        'created_by' => $this->user->id,
    ]);

    $service = app(QuoteService::class);
    $quote = $service->sendQuote($quote);

    expect($quote->status)->toBe(QuoteStatus::Sent)
        ->and($quote->sent_at)->not->toBeNull();
});

it('approves a sent quote', function () {
    $customer = Customer::factory()->create();
    $quote = Quote::factory()->create([
        'customer_id' => $customer->id,
        'status' => QuoteStatus::Sent,
        'created_by' => $this->user->id,
    ]);

    $service = app(QuoteService::class);
    $quote = $service->approveQuote($quote);

    expect($quote->status)->toBe(QuoteStatus::Approved)
        ->and($quote->approved_at)->not->toBeNull();
});

it('rejects a sent quote', function () {
    $customer = Customer::factory()->create();
    $quote = Quote::factory()->create([
        'customer_id' => $customer->id,
        'status' => QuoteStatus::Sent,
        'created_by' => $this->user->id,
    ]);

    $service = app(QuoteService::class);
    $quote = $service->rejectQuote($quote, 'Too expensive');

    expect($quote->status)->toBe(QuoteStatus::Rejected);
});

it('generates sequential quote numbers', function () {
    $service = app(QuoteService::class);

    $num1 = $service->generateQuoteNumber();
    $num2 = $service->generateQuoteNumber();

    $prefix = 'QTE-'.now()->format('Ym').'-';
    expect($num1)->toBe($prefix.'0001');

    // Create a quote with num1 so num2 increments
    Quote::factory()->create([
        'quote_number' => $num1,
        'created_by' => User::factory()->create()->id,
    ]);

    $num2 = $service->generateQuoteNumber();
    expect($num2)->toBe($prefix.'0002');
});
