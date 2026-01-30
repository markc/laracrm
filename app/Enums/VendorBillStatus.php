<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum VendorBillStatus: string implements HasColor, HasIcon, HasLabel
{
    case Draft = 'draft';
    case Received = 'received';
    case Partial = 'partial';
    case Paid = 'paid';
    case Void = 'void';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Received => 'Received',
            self::Partial => 'Partially Paid',
            self::Paid => 'Paid',
            self::Void => 'Void',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Received => 'info',
            self::Partial => 'warning',
            self::Paid => 'success',
            self::Void => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Draft => 'heroicon-m-pencil-square',
            self::Received => 'heroicon-m-inbox-arrow-down',
            self::Partial => 'heroicon-m-clock',
            self::Paid => 'heroicon-m-check-circle',
            self::Void => 'heroicon-m-x-circle',
        };
    }
}
