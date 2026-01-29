<?php

namespace App\Models\CRM;

use App\Enums\AddressType;
use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Models\Accounting\Expense;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Payment;
use App\Models\Accounting\Quote;
use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Customer extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'customer_number',
        'company_name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'tax_id',
        'type',
        'billing_address',
        'shipping_address',
        'payment_terms',
        'credit_limit',
        'currency',
        'status',
        'assigned_to',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => CustomerType::class,
            'billing_address' => 'array',
            'shipping_address' => 'array',
            'credit_limit' => 'decimal:2',
            'status' => CustomerStatus::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty();
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function primaryContact(): HasMany
    {
        return $this->hasMany(Contact::class)->where('is_primary', true);
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function shippingAddresses(): HasMany
    {
        return $this->hasMany(Address::class)->where('type', AddressType::Shipping);
    }

    public function defaultShippingAddress(): HasMany
    {
        return $this->hasMany(Address::class)
            ->where('type', AddressType::Shipping)
            ->where('is_default', true);
    }

    public function getDisplayNameAttribute(): string
    {
        if ($this->type === CustomerType::Company) {
            return $this->company_name ?? "{$this->first_name} {$this->last_name}";
        }

        return trim("{$this->first_name} {$this->last_name}") ?: $this->company_name ?? '';
    }

    public function getFullAddressAttribute(): string
    {
        $address = $this->billing_address;
        if (! $address) {
            return '';
        }

        return collect([
            $address['street'] ?? null,
            $address['city'] ?? null,
            $address['state'] ?? null,
            $address['postcode'] ?? null,
            $address['country'] ?? null,
        ])->filter()->implode(', ');
    }

    public function getOutstandingBalanceAttribute(): float
    {
        return $this->invoices()->unpaid()->sum('balance_due');
    }

    public function scopeActive($query)
    {
        return $query->where('status', CustomerStatus::Active);
    }

    public function scopeCompanies($query)
    {
        return $query->where('type', CustomerType::Company);
    }

    public function scopeIndividuals($query)
    {
        return $query->where('type', CustomerType::Individual);
    }
}
