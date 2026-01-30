<?php

use App\Enums\VendorBillStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
use App\Models\Accounting\Account;
use App\Models\Accounting\VendorBill;
use App\Models\CRM\Customer;
use App\Models\User;
use App\Services\Accounting\VendorBillService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    // Ensure required accounts exist
    Account::firstOrCreate(['code' => '2000'], [
        'name' => 'Accounts Payable',
        'type' => 'liability',
        'normal_balance' => 'credit',
        'is_system' => true,
        'is_active' => true,
    ]);
    Account::firstOrCreate(['code' => '2110'], [
        'name' => 'GST Paid',
        'type' => 'liability',
        'normal_balance' => 'credit',
        'is_system' => true,
        'is_active' => true,
    ]);
    Account::firstOrCreate(['code' => '5000'], [
        'name' => 'Cost of Goods Sold',
        'type' => 'expense',
        'normal_balance' => 'debit',
        'is_active' => true,
    ]);
});

it('can create a vendor bill', function () {
    $vendor = Customer::factory()->create([
        'is_vendor' => true,
        'company_name' => 'Test Vendor',
        'type' => 'company',
    ]);

    $service = app(VendorBillService::class);
    $expenseAccount = Account::where('code', '5000')->first();

    $bill = $service->createBill($vendor, [
        [
            'description' => 'Office Supplies',
            'quantity' => 10,
            'unit_price' => 50.00,
            'tax_rate' => 10,
            'account_id' => $expenseAccount->id,
        ],
    ], [
        'vendor_reference' => 'INV-12345',
    ]);

    expect($bill)->toBeInstanceOf(VendorBill::class)
        ->and($bill->vendor_id)->toBe($vendor->id)
        ->and($bill->vendor_reference)->toBe('INV-12345')
        ->and($bill->subtotal)->toBe('500.00')
        ->and($bill->tax_amount)->toBe('50.00')
        ->and($bill->total_amount)->toBe('550.00')
        ->and($bill->balance_due)->toBe('550.00')
        ->and($bill->status)->toBe(VendorBillStatus::Draft)
        ->and($bill->items)->toHaveCount(1);
});

it('can receive a vendor bill and post to GL', function () {
    $vendor = Customer::factory()->create([
        'is_vendor' => true,
        'company_name' => 'Test Vendor',
        'type' => 'company',
    ]);

    $service = app(VendorBillService::class);
    $expenseAccount = Account::where('code', '5000')->first();

    $bill = $service->createBill($vendor, [
        [
            'description' => 'Services',
            'quantity' => 1,
            'unit_price' => 1000.00,
            'tax_rate' => 10,
            'account_id' => $expenseAccount->id,
        ],
    ]);

    expect($bill->status)->toBe(VendorBillStatus::Draft)
        ->and($bill->journalEntry)->toBeNull();

    $bill = $service->receiveBill($bill);

    expect($bill->status)->toBe(VendorBillStatus::Received)
        ->and($bill->received_at)->not->toBeNull()
        ->and($bill->journalEntry)->not->toBeNull()
        ->and($bill->journalEntry->is_posted)->toBeTrue();

    // Verify journal entry is balanced
    $totalDebits = $bill->journalEntry->lines->sum('debit_amount');
    $totalCredits = $bill->journalEntry->lines->sum('credit_amount');
    expect((float) $totalDebits)->toBe((float) $totalCredits);
});

it('can void a vendor bill and reverse GL entry', function () {
    $vendor = Customer::factory()->create([
        'is_vendor' => true,
        'company_name' => 'Test Vendor',
        'type' => 'company',
    ]);

    $service = app(VendorBillService::class);
    $expenseAccount = Account::where('code', '5000')->first();

    $bill = $service->createBill($vendor, [
        [
            'description' => 'Item',
            'quantity' => 1,
            'unit_price' => 100.00,
            'tax_rate' => 10,
            'account_id' => $expenseAccount->id,
        ],
    ]);

    $bill = $service->receiveBill($bill);
    $originalJournalEntry = $bill->journalEntry;

    $bill = $service->voidBill($bill, 'Duplicate bill');

    expect($bill->status)->toBe(VendorBillStatus::Void)
        ->and($bill->voided_at)->not->toBeNull()
        ->and($bill->void_reason)->toBe('Duplicate bill');

    // Check that the original journal entry was reversed
    $originalJournalEntry->refresh();
    expect($originalJournalEntry->reversed_by_id)->not->toBeNull();
});

it('prevents voiding a bill with payments', function () {
    $vendor = Customer::factory()->create([
        'is_vendor' => true,
        'company_name' => 'Test Vendor',
        'type' => 'company',
    ]);

    $service = app(VendorBillService::class);
    $expenseAccount = Account::where('code', '5000')->first();

    $bill = $service->createBill($vendor, [
        [
            'description' => 'Item',
            'quantity' => 1,
            'unit_price' => 100.00,
            'tax_rate' => 10,
            'account_id' => $expenseAccount->id,
        ],
    ]);

    $bill = $service->receiveBill($bill);

    // Simulate payment
    $bill->update(['paid_amount' => 50]);

    expect(fn () => $service->voidBill($bill, 'Test'))
        ->toThrow(Exception::class, 'Cannot void bill with payments');
});

it('updates status when payment recorded', function () {
    $vendor = Customer::factory()->create([
        'is_vendor' => true,
        'company_name' => 'Test Vendor',
        'type' => 'company',
    ]);

    $service = app(VendorBillService::class);
    $expenseAccount = Account::where('code', '5000')->first();

    $bill = $service->createBill($vendor, [
        [
            'description' => 'Item',
            'quantity' => 1,
            'unit_price' => 100.00,
            'tax_rate' => 10,
            'account_id' => $expenseAccount->id,
        ],
    ]);

    $bill = $service->receiveBill($bill);

    // Partial payment
    $bill = $service->recordPayment($bill, 50.00);
    expect($bill->status)->toBe(VendorBillStatus::Partial)
        ->and($bill->paid_amount)->toBe('50.00')
        ->and($bill->balance_due)->toBe('60.00');

    // Full payment
    $bill = $service->recordPayment($bill, 60.00);
    expect($bill->status)->toBe(VendorBillStatus::Paid)
        ->and($bill->balance_due)->toBe('0.00')
        ->and($bill->paid_at)->not->toBeNull();
});

it('scopes vendors correctly on customer model', function () {
    Customer::factory()->count(3)->create(['is_vendor' => false]);
    Customer::factory()->count(2)->create(['is_vendor' => true]);

    expect(Customer::vendors()->count())->toBe(2);
});
