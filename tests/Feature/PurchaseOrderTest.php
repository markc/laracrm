<?php

use App\Enums\PurchaseOrderStatus;
use App\Models\Accounting\Account;
use App\Models\Accounting\InventoryLocation;
use App\Models\Accounting\Product;
use App\Models\Accounting\PurchaseOrder;
use App\Models\Accounting\VendorBill;
use App\Models\CRM\Customer;
use App\Models\User;
use App\Services\Accounting\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    // Ensure required GL accounts exist
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

    $this->vendor = Customer::factory()->create([
        'is_vendor' => true,
        'company_name' => 'Test Vendor',
        'type' => 'company',
    ]);
});

it('creates a purchase order', function () {
    $service = app(PurchaseOrderService::class);

    $order = $service->createOrder($this->vendor, [
        [
            'description' => 'Cleaning Solution',
            'quantity' => 100,
            'unit_price' => 25.00,
            'tax_rate' => 10,
        ],
    ]);

    expect($order)->toBeInstanceOf(PurchaseOrder::class)
        ->and($order->vendor_id)->toBe($this->vendor->id)
        ->and($order->subtotal)->toBe('2500.00')
        ->and($order->tax_amount)->toBe('250.00')
        ->and($order->total_amount)->toBe('2750.00')
        ->and($order->status)->toBe(PurchaseOrderStatus::Draft)
        ->and($order->items)->toHaveCount(1);
});

it('follows the full lifecycle: draft -> sent -> confirmed', function () {
    $service = app(PurchaseOrderService::class);

    $order = $service->createOrder($this->vendor, [
        ['description' => 'Item', 'quantity' => 10, 'unit_price' => 50.00, 'tax_rate' => 10],
    ]);

    expect($order->status)->toBe(PurchaseOrderStatus::Draft);

    $order = $service->sendOrder($order);
    expect($order->status)->toBe(PurchaseOrderStatus::Sent)
        ->and($order->sent_at)->not->toBeNull();

    $order = $service->confirmOrder($order);
    expect($order->status)->toBe(PurchaseOrderStatus::Confirmed)
        ->and($order->confirmed_at)->not->toBeNull();
});

it('receives items fully and updates status to received', function () {
    $service = app(PurchaseOrderService::class);

    // Create a default location for inventory
    InventoryLocation::create([
        'name' => 'Main Warehouse',
        'code' => 'MAIN',
        'is_default' => true,
        'is_active' => true,
    ]);

    $product = Product::factory()->create(['track_inventory' => true]);

    $order = $service->createOrder($this->vendor, [
        [
            'product_id' => $product->id,
            'description' => $product->name,
            'quantity' => 50,
            'unit_price' => 10.00,
            'tax_rate' => 10,
        ],
    ]);

    $order = $service->sendOrder($order);
    $order = $service->confirmOrder($order);

    $order = $service->receiveItems($order, [
        ['item_id' => $order->items->first()->id, 'quantity' => 50],
    ]);

    expect($order->status)->toBe(PurchaseOrderStatus::Received)
        ->and($order->received_at)->not->toBeNull()
        ->and($order->items->first()->received_quantity)->toBe('50.0000');
});

it('handles partial receiving', function () {
    $service = app(PurchaseOrderService::class);

    $order = $service->createOrder($this->vendor, [
        ['description' => 'Item A', 'quantity' => 100, 'unit_price' => 10.00, 'tax_rate' => 10],
    ]);

    $order = $service->sendOrder($order);
    $order = $service->confirmOrder($order);

    // Receive half
    $order = $service->receiveItems($order, [
        ['item_id' => $order->items->first()->id, 'quantity' => 50],
    ]);

    expect($order->status)->toBe(PurchaseOrderStatus::PartiallyReceived)
        ->and($order->items->first()->received_quantity)->toBe('50.0000');

    // Receive the rest
    $order = $service->receiveItems($order, [
        ['item_id' => $order->items->first()->id, 'quantity' => 50],
    ]);

    expect($order->status)->toBe(PurchaseOrderStatus::Received);
});

it('prevents receiving more than ordered', function () {
    $service = app(PurchaseOrderService::class);

    $order = $service->createOrder($this->vendor, [
        ['description' => 'Item', 'quantity' => 10, 'unit_price' => 50.00, 'tax_rate' => 10],
    ]);

    $order = $service->sendOrder($order);
    $order = $service->confirmOrder($order);

    expect(fn () => $service->receiveItems($order, [
        ['item_id' => $order->items->first()->id, 'quantity' => 20],
    ]))->toThrow(Exception::class, 'Cannot receive');
});

it('creates a vendor bill from a confirmed order', function () {
    $service = app(PurchaseOrderService::class);

    $order = $service->createOrder($this->vendor, [
        ['description' => 'Supplies', 'quantity' => 20, 'unit_price' => 100.00, 'tax_rate' => 10],
    ]);

    $order = $service->sendOrder($order);
    $order = $service->confirmOrder($order);

    $bill = $service->createBillFromOrder($order);

    expect($bill)->toBeInstanceOf(VendorBill::class)
        ->and($bill->purchase_order_id)->toBe($order->id)
        ->and($bill->vendor_id)->toBe($this->vendor->id)
        ->and($bill->items)->toHaveCount(1);
});

it('cancels a draft order', function () {
    $service = app(PurchaseOrderService::class);

    $order = $service->createOrder($this->vendor, [
        ['description' => 'Item', 'quantity' => 10, 'unit_price' => 50.00, 'tax_rate' => 10],
    ]);

    $order = $service->cancelOrder($order, 'No longer needed');

    expect($order->status)->toBe(PurchaseOrderStatus::Cancelled)
        ->and($order->cancel_reason)->toBe('No longer needed')
        ->and($order->cancelled_at)->not->toBeNull();
});

it('prevents cancelling a partially received order', function () {
    $service = app(PurchaseOrderService::class);

    $order = PurchaseOrder::factory()->create([
        'vendor_id' => $this->vendor->id,
        'status' => PurchaseOrderStatus::PartiallyReceived,
        'created_by' => $this->user->id,
    ]);

    expect(fn () => $service->cancelOrder($order, 'Test'))
        ->toThrow(Exception::class, 'Only draft or sent orders can be cancelled');
});

it('generates sequential PO numbers', function () {
    $service = app(PurchaseOrderService::class);

    $num1 = $service->generatePoNumber();
    $prefix = 'PO-'.now()->format('Ym').'-';
    expect($num1)->toBe($prefix.'0001');

    PurchaseOrder::factory()->create([
        'po_number' => $num1,
        'created_by' => $this->user->id,
    ]);

    $num2 = $service->generatePoNumber();
    expect($num2)->toBe($prefix.'0002');
});
