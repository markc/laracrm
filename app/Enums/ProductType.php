<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ProductType: string implements HasColor, HasLabel
{
    case Service = 'service';
    case Product = 'product';

    public function getLabel(): string
    {
        return match ($this) {
            self::Service => 'Service',
            self::Product => 'Product',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Service => 'info',
            self::Product => 'success',
        };
    }
}
