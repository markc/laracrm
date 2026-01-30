<?php

namespace App\Models\Accounting;

use App\Enums\MovementType;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    protected $fillable = [
        'reference_number',
        'product_id',
        'from_location_id',
        'to_location_id',
        'quantity',
        'movement_type',
        'reference_type',
        'reference_id',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'movement_type' => MovementType::class,
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'to_location_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo('reference', 'reference_type', 'reference_id');
    }

    public function getDirectionAttribute(): string
    {
        return match ($this->movement_type) {
            MovementType::Receipt, MovementType::Return => 'in',
            MovementType::Shipment => 'out',
            MovementType::Transfer => 'transfer',
            MovementType::Adjustment => 'adjustment',
        };
    }

    public function scopeReceipts($query)
    {
        return $query->where('movement_type', MovementType::Receipt);
    }

    public function scopeShipments($query)
    {
        return $query->where('movement_type', MovementType::Shipment);
    }

    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeForLocation($query, int $locationId)
    {
        return $query->where(function ($q) use ($locationId) {
            $q->where('from_location_id', $locationId)
                ->orWhere('to_location_id', $locationId);
        });
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
