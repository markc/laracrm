<?php

namespace App\Models\Accounting;

use App\Enums\VendorBillStatus;
use App\Models\CRM\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class VendorBill extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'bill_number',
        'vendor_id',
        'purchase_order_id',
        'vendor_reference',
        'bill_date',
        'due_date',
        'subtotal',
        'tax_amount',
        'total_amount',
        'paid_amount',
        'balance_due',
        'status',
        'currency',
        'exchange_rate',
        'notes',
        'received_at',
        'paid_at',
        'voided_at',
        'void_reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'bill_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'balance_due' => 'decimal:2',
            'exchange_rate' => 'decimal:6',
            'status' => VendorBillStatus::class,
            'received_at' => 'datetime',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
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

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(VendorBillItem::class)->orderBy('sort_order');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function journalEntry(): MorphOne
    {
        return $this->morphOne(JournalEntry::class, 'reference', 'reference_type', 'reference_id');
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->status !== VendorBillStatus::Paid
            && $this->status !== VendorBillStatus::Void
            && $this->due_date < now();
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->bill_number.($this->vendor_reference ? " ({$this->vendor_reference})" : '');
    }

    public function scopeUnpaid($query)
    {
        return $query->whereIn('status', [VendorBillStatus::Received, VendorBillStatus::Partial]);
    }

    public function scopeOverdue($query)
    {
        return $query->unpaid()->where('due_date', '<', now());
    }

    public function scopeForVendor($query, Customer $vendor)
    {
        return $query->where('vendor_id', $vendor->id);
    }
}
