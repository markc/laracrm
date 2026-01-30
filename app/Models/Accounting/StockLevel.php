<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLevel extends Model
{
    protected $fillable = [
        'product_id',
        'location_id',
        'quantity_on_hand',
        'quantity_reserved',
        'reorder_point',
        'last_counted_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'decimal:4',
            'quantity_reserved' => 'decimal:4',
            'quantity_available' => 'decimal:4',
            'reorder_point' => 'decimal:4',
            'last_counted_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    public function getNeedsReorderAttribute(): bool
    {
        if ($this->reorder_point === null) {
            return false;
        }

        return $this->quantity_available <= $this->reorder_point;
    }

    public function scopeLowStock($query)
    {
        return $query->whereNotNull('reorder_point')
            ->whereRaw('CAST(quantity_on_hand AS REAL) - CAST(quantity_reserved AS REAL) <= CAST(reorder_point AS REAL)');
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('quantity_on_hand', '<=', 0);
    }

    public function scopeInStock($query)
    {
        return $query->where('quantity_on_hand', '>', 0);
    }

    public static function getOrCreate(int $productId, int $locationId): self
    {
        return static::firstOrCreate(
            ['product_id' => $productId, 'location_id' => $locationId],
            ['quantity_on_hand' => 0, 'quantity_reserved' => 0]
        );
    }
}
