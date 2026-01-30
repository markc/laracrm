<?php

namespace App\Models\Accounting;

use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Product extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'sku',
        'name',
        'description',
        'unit_price',
        'cost_price',
        'tax_rate',
        'income_account_id',
        'expense_account_id',
        'type',
        'is_active',
        'track_inventory',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'type' => ProductType::class,
            'is_active' => 'boolean',
            'track_inventory' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty();
    }

    public function incomeAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'income_account_id');
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function quoteItems(): HasMany
    {
        return $this->hasMany(QuoteItem::class);
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function getTotalStockAttribute(): float
    {
        return (float) $this->stockLevels()->sum('quantity_on_hand');
    }

    public function getTotalAvailableAttribute(): float
    {
        return (float) $this->stockLevels()->sum(\DB::raw('quantity_on_hand - quantity_reserved'));
    }

    public function getStockAtLocation(?int $locationId = null): float
    {
        if (! $locationId) {
            $location = InventoryLocation::getDefault();
            $locationId = $location?->id;
        }

        if (! $locationId) {
            return 0;
        }

        return (float) ($this->stockLevels()
            ->where('location_id', $locationId)
            ->value('quantity_on_hand') ?? 0);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeTracksInventory($query)
    {
        return $query->where('track_inventory', true);
    }
}
