<?php

namespace App\Models;

use App\Enums\AddressType;
use App\Models\CRM\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    protected $fillable = [
        'customer_id',
        'label',
        'type',
        'street',
        'street2',
        'street3',
        'street4',
        'street5',
        'city',
        'state',
        'postcode',
        'country',
        'contact_name',
        'contact_phone',
        'is_default',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => AddressType::class,
            'is_default' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function getFullAddressAttribute(): string
    {
        return collect([
            $this->street,
            $this->street2,
            $this->street3,
            $this->street4,
            $this->street5,
            $this->city,
            $this->state,
            $this->postcode,
            $this->country,
        ])->filter()->implode(', ');
    }
}
