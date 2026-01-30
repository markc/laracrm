<?php

use App\Enums\MovementType;
use App\Models\Accounting\InventoryLocation;
use App\Models\Accounting\Product;
use App\Models\Accounting\StockLevel;
use App\Models\Accounting\StockMovement;
use App\Models\User;
use App\Services\Accounting\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->location = InventoryLocation::create([
        'name' => 'Main Warehouse',
        'code' => 'WH1',
        'is_default' => true,
        'is_active' => true,
    ]);

    $this->product = Product::factory()->create([
        'name' => 'Test Product',
        'track_inventory' => true,
        'is_active' => true,
    ]);
});

it('can receive stock into a location', function () {
    $service = app(InventoryService::class);

    $movement = $service->receiveStock(
        product: $this->product,
        quantity: 100,
        locationId: $this->location->id,
        notes: 'Initial stock'
    );

    expect($movement)->toBeInstanceOf(StockMovement::class)
        ->and($movement->movement_type)->toBe(MovementType::Receipt)
        ->and($movement->quantity)->toBe('100.0000')
        ->and($movement->to_location_id)->toBe($this->location->id)
        ->and($movement->from_location_id)->toBeNull();

    $stockLevel = StockLevel::where('product_id', $this->product->id)
        ->where('location_id', $this->location->id)
        ->first();

    expect($stockLevel->quantity_on_hand)->toBe('100.0000');
});

it('can ship stock from a location', function () {
    $service = app(InventoryService::class);

    // First receive some stock
    $service->receiveStock($this->product, 100, $this->location->id);

    // Then ship some
    $movement = $service->shipStock(
        product: $this->product,
        quantity: 30,
        locationId: $this->location->id,
        notes: 'Order #123'
    );

    expect($movement->movement_type)->toBe(MovementType::Shipment)
        ->and($movement->from_location_id)->toBe($this->location->id)
        ->and($movement->to_location_id)->toBeNull();

    $stockLevel = StockLevel::where('product_id', $this->product->id)
        ->where('location_id', $this->location->id)
        ->first();

    expect($stockLevel->quantity_on_hand)->toBe('70.0000');
});

it('prevents shipping more than available', function () {
    $service = app(InventoryService::class);

    $service->receiveStock($this->product, 50, $this->location->id);

    expect(fn () => $service->shipStock($this->product, 100, $this->location->id))
        ->toThrow(Exception::class, 'Insufficient stock');
});

it('can transfer stock between locations', function () {
    $service = app(InventoryService::class);

    $location2 = InventoryLocation::create([
        'name' => 'Secondary Warehouse',
        'code' => 'WH2',
        'is_active' => true,
    ]);

    // Receive stock at first location
    $service->receiveStock($this->product, 100, $this->location->id);

    // Transfer to second location
    $movement = $service->transferStock(
        product: $this->product,
        quantity: 40,
        fromLocationId: $this->location->id,
        toLocationId: $location2->id
    );

    expect($movement->movement_type)->toBe(MovementType::Transfer);

    $fromLevel = StockLevel::where('product_id', $this->product->id)
        ->where('location_id', $this->location->id)
        ->first();

    $toLevel = StockLevel::where('product_id', $this->product->id)
        ->where('location_id', $location2->id)
        ->first();

    expect($fromLevel->quantity_on_hand)->toBe('60.0000')
        ->and($toLevel->quantity_on_hand)->toBe('40.0000');
});

it('can adjust stock with count', function () {
    $service = app(InventoryService::class);

    $service->receiveStock($this->product, 100, $this->location->id);

    // Physical count shows 95 (5 missing)
    $movement = $service->adjustStock(
        product: $this->product,
        newQuantity: 95,
        locationId: $this->location->id,
        notes: 'Inventory count adjustment'
    );

    expect($movement->movement_type)->toBe(MovementType::Adjustment)
        ->and($movement->quantity)->toBe('5.0000'); // Difference recorded

    $stockLevel = StockLevel::where('product_id', $this->product->id)
        ->where('location_id', $this->location->id)
        ->first();

    expect($stockLevel->quantity_on_hand)->toBe('95.0000')
        ->and($stockLevel->last_counted_at)->not->toBeNull();
});

it('can reserve and release stock', function () {
    $service = app(InventoryService::class);

    $service->receiveStock($this->product, 100, $this->location->id);

    // Reserve some stock
    $service->reserveStock($this->product, 30, $this->location->id);

    $stockLevel = StockLevel::where('product_id', $this->product->id)
        ->where('location_id', $this->location->id)
        ->first();

    expect($stockLevel->quantity_on_hand)->toBe('100.0000')
        ->and($stockLevel->quantity_reserved)->toBe('30.0000')
        ->and((float) $stockLevel->quantity_available)->toBe(70.0);

    // Release reservation
    $service->releaseReservedStock($this->product, 30, $this->location->id);

    $stockLevel->refresh();
    expect($stockLevel->quantity_reserved)->toBe('0.0000');
});

it('prevents reserving more than available', function () {
    $service = app(InventoryService::class);

    $service->receiveStock($this->product, 50, $this->location->id);

    expect(fn () => $service->reserveStock($this->product, 100, $this->location->id))
        ->toThrow(Exception::class, 'Insufficient stock to reserve');
});

it('tracks movement history for product', function () {
    $service = app(InventoryService::class);

    $service->receiveStock($this->product, 100, $this->location->id, notes: 'Initial');
    $service->shipStock($this->product, 20, $this->location->id, notes: 'Order 1');
    $service->shipStock($this->product, 10, $this->location->id, notes: 'Order 2');

    $history = $service->getMovementHistory($this->product);

    expect($history)->toHaveCount(3);
});

it('identifies low stock products', function () {
    $service = app(InventoryService::class);

    // Create stock level with reorder point
    $service->receiveStock($this->product, 5, $this->location->id);

    $stockLevel = StockLevel::where('product_id', $this->product->id)->first();
    $stockLevel->update(['reorder_point' => 10]);

    $lowStock = $service->getLowStockProducts();

    expect($lowStock)->toHaveCount(1)
        ->and($lowStock->first()->id)->toBe($this->product->id);
});
