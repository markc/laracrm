<?php

namespace App\Services\Accounting;

use App\Enums\MovementType;
use App\Models\Accounting\InventoryLocation;
use App\Models\Accounting\Product;
use App\Models\Accounting\StockLevel;
use App\Models\Accounting\StockMovement;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Receive stock into a location (e.g., from a purchase)
     */
    public function receiveStock(
        Product $product,
        float $quantity,
        ?int $locationId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null
    ): StockMovement {
        $locationId = $locationId ?? InventoryLocation::getDefault()?->id;

        if (! $locationId) {
            throw new \Exception('No inventory location specified and no default location exists.');
        }

        return DB::transaction(function () use ($product, $quantity, $locationId, $referenceType, $referenceId, $notes) {
            // Update stock level
            $stockLevel = StockLevel::getOrCreate($product->id, $locationId);
            $stockLevel->increment('quantity_on_hand', $quantity);

            // Record movement
            return StockMovement::create([
                'reference_number' => $this->generateReferenceNumber('RCV'),
                'product_id' => $product->id,
                'from_location_id' => null,
                'to_location_id' => $locationId,
                'quantity' => $quantity,
                'movement_type' => MovementType::Receipt,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'notes' => $notes,
                'created_by' => auth()->id(),
            ]);
        });
    }

    /**
     * Ship stock from a location (e.g., for an invoice)
     */
    public function shipStock(
        Product $product,
        float $quantity,
        ?int $locationId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null
    ): StockMovement {
        $locationId = $locationId ?? InventoryLocation::getDefault()?->id;

        if (! $locationId) {
            throw new \Exception('No inventory location specified and no default location exists.');
        }

        return DB::transaction(function () use ($product, $quantity, $locationId, $referenceType, $referenceId, $notes) {
            // Check available stock
            $stockLevel = StockLevel::getOrCreate($product->id, $locationId);

            if ($stockLevel->quantity_available < $quantity) {
                throw new \Exception(
                    "Insufficient stock. Available: {$stockLevel->quantity_available}, Requested: {$quantity}"
                );
            }

            // Update stock level
            $stockLevel->decrement('quantity_on_hand', $quantity);

            // Record movement
            return StockMovement::create([
                'reference_number' => $this->generateReferenceNumber('SHP'),
                'product_id' => $product->id,
                'from_location_id' => $locationId,
                'to_location_id' => null,
                'quantity' => $quantity,
                'movement_type' => MovementType::Shipment,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'notes' => $notes,
                'created_by' => auth()->id(),
            ]);
        });
    }

    /**
     * Transfer stock between locations
     */
    public function transferStock(
        Product $product,
        float $quantity,
        int $fromLocationId,
        int $toLocationId,
        ?string $notes = null
    ): StockMovement {
        if ($fromLocationId === $toLocationId) {
            throw new \Exception('Cannot transfer to the same location.');
        }

        return DB::transaction(function () use ($product, $quantity, $fromLocationId, $toLocationId, $notes) {
            // Check source stock
            $fromStockLevel = StockLevel::getOrCreate($product->id, $fromLocationId);

            if ($fromStockLevel->quantity_available < $quantity) {
                throw new \Exception(
                    "Insufficient stock at source. Available: {$fromStockLevel->quantity_available}, Requested: {$quantity}"
                );
            }

            // Update stock levels
            $fromStockLevel->decrement('quantity_on_hand', $quantity);

            $toStockLevel = StockLevel::getOrCreate($product->id, $toLocationId);
            $toStockLevel->increment('quantity_on_hand', $quantity);

            // Record movement
            return StockMovement::create([
                'reference_number' => $this->generateReferenceNumber('TRF'),
                'product_id' => $product->id,
                'from_location_id' => $fromLocationId,
                'to_location_id' => $toLocationId,
                'quantity' => $quantity,
                'movement_type' => MovementType::Transfer,
                'notes' => $notes,
                'created_by' => auth()->id(),
            ]);
        });
    }

    /**
     * Adjust stock (inventory count correction)
     */
    public function adjustStock(
        Product $product,
        float $newQuantity,
        int $locationId,
        ?string $notes = null
    ): StockMovement {
        return DB::transaction(function () use ($product, $newQuantity, $locationId, $notes) {
            $stockLevel = StockLevel::getOrCreate($product->id, $locationId);
            $currentQuantity = (float) $stockLevel->quantity_on_hand;
            $difference = $newQuantity - $currentQuantity;

            if (abs($difference) < 0.0001) {
                throw new \Exception('No adjustment needed - quantity unchanged.');
            }

            // Update stock level
            $stockLevel->update([
                'quantity_on_hand' => $newQuantity,
                'last_counted_at' => now(),
            ]);

            // Record movement (positive or negative quantity based on adjustment direction)
            return StockMovement::create([
                'reference_number' => $this->generateReferenceNumber('ADJ'),
                'product_id' => $product->id,
                'from_location_id' => $difference < 0 ? $locationId : null,
                'to_location_id' => $difference > 0 ? $locationId : null,
                'quantity' => abs($difference),
                'movement_type' => MovementType::Adjustment,
                'notes' => $notes ?? "Adjusted from {$currentQuantity} to {$newQuantity}",
                'created_by' => auth()->id(),
            ]);
        });
    }

    /**
     * Return stock (e.g., customer return)
     */
    public function returnStock(
        Product $product,
        float $quantity,
        ?int $locationId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null
    ): StockMovement {
        $locationId = $locationId ?? InventoryLocation::getDefault()?->id;

        if (! $locationId) {
            throw new \Exception('No inventory location specified and no default location exists.');
        }

        return DB::transaction(function () use ($product, $quantity, $locationId, $referenceType, $referenceId, $notes) {
            // Update stock level
            $stockLevel = StockLevel::getOrCreate($product->id, $locationId);
            $stockLevel->increment('quantity_on_hand', $quantity);

            // Record movement
            return StockMovement::create([
                'reference_number' => $this->generateReferenceNumber('RTN'),
                'product_id' => $product->id,
                'from_location_id' => null,
                'to_location_id' => $locationId,
                'quantity' => $quantity,
                'movement_type' => MovementType::Return,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'notes' => $notes,
                'created_by' => auth()->id(),
            ]);
        });
    }

    /**
     * Reserve stock (e.g., for a sales order)
     */
    public function reserveStock(Product $product, float $quantity, int $locationId): void
    {
        $stockLevel = StockLevel::getOrCreate($product->id, $locationId);

        if ($stockLevel->quantity_available < $quantity) {
            throw new \Exception(
                "Insufficient stock to reserve. Available: {$stockLevel->quantity_available}, Requested: {$quantity}"
            );
        }

        $stockLevel->increment('quantity_reserved', $quantity);
    }

    /**
     * Release reserved stock
     */
    public function releaseReservedStock(Product $product, float $quantity, int $locationId): void
    {
        $stockLevel = StockLevel::getOrCreate($product->id, $locationId);

        $releaseAmount = min($quantity, (float) $stockLevel->quantity_reserved);
        $stockLevel->decrement('quantity_reserved', $releaseAmount);
    }

    /**
     * Get low stock products
     */
    public function getLowStockProducts(): \Illuminate\Database\Eloquent\Collection
    {
        return Product::tracksInventory()
            ->active()
            ->whereHas('stockLevels', function ($query) {
                $query->lowStock();
            })
            ->with(['stockLevels.location'])
            ->get();
    }

    /**
     * Get stock movement history for a product
     */
    public function getMovementHistory(
        Product $product,
        ?int $locationId = null,
        ?int $days = 30
    ): \Illuminate\Database\Eloquent\Collection {
        $query = StockMovement::forProduct($product->id)
            ->with(['fromLocation', 'toLocation', 'createdBy'])
            ->orderBy('created_at', 'desc');

        if ($locationId) {
            $query->forLocation($locationId);
        }

        if ($days) {
            $query->recent($days);
        }

        return $query->get();
    }

    protected function generateReferenceNumber(string $prefix): string
    {
        $datePrefix = now()->format('Ymd');
        $fullPrefix = "{$prefix}-{$datePrefix}-";

        $lastMovement = StockMovement::where('reference_number', 'like', $fullPrefix.'%')
            ->orderBy('reference_number', 'desc')
            ->first();

        $nextNumber = $lastMovement
            ? (int) substr($lastMovement->reference_number, -4) + 1
            : 1;

        return $fullPrefix.str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
