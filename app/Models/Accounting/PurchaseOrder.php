<?php

namespace App\Models\Accounting;

use App\Enums\PurchaseOrderStatus;
use App\Models\CRM\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PurchaseOrder extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'po_number',
        'vendor_id',
        'order_date',
        'expected_delivery_date',
        'subtotal',
        'tax_amount',
        'total_amount',
        'status',
        'notes',
        'sent_at',
        'confirmed_at',
        'received_at',
        'cancelled_at',
        'cancel_reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'expected_delivery_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'status' => PurchaseOrderStatus::class,
            'sent_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'received_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty();
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'vendor_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class)->orderBy('sort_order');
    }

    public function vendorBills(): HasMany
    {
        return $this->hasMany(VendorBill::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getIsFullyReceivedAttribute(): bool
    {
        return $this->items->every(fn ($item) => $item->received_quantity >= $item->quantity);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->po_number;
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', [
            PurchaseOrderStatus::Draft,
            PurchaseOrderStatus::Sent,
            PurchaseOrderStatus::Confirmed,
            PurchaseOrderStatus::PartiallyReceived,
        ]);
    }

    public function scopeForVendor($query, Customer $vendor)
    {
        return $query->where('vendor_id', $vendor->id);
    }
}
