<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AddressType: string implements HasColor, HasLabel
{
    case Billing = 'billing';
    case Shipping = 'shipping';

    public function getLabel(): string
    {
        return match ($this) {
            self::Billing => 'Billing',
            self::Shipping => 'Shipping',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Billing => 'warning',
            self::Shipping => 'success',
        };
    }
}
