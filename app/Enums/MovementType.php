<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum MovementType: string implements HasColor, HasIcon, HasLabel
{
    case Receipt = 'receipt';
    case Shipment = 'shipment';
    case Transfer = 'transfer';
    case Adjustment = 'adjustment';
    case Return = 'return';

    public function getLabel(): string
    {
        return match ($this) {
            self::Receipt => 'Receipt',
            self::Shipment => 'Shipment',
            self::Transfer => 'Transfer',
            self::Adjustment => 'Adjustment',
            self::Return => 'Return',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Receipt => 'success',
            self::Shipment => 'info',
            self::Transfer => 'warning',
            self::Adjustment => 'gray',
            self::Return => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Receipt => 'heroicon-m-arrow-down-tray',
            self::Shipment => 'heroicon-m-truck',
            self::Transfer => 'heroicon-m-arrows-right-left',
            self::Adjustment => 'heroicon-m-adjustments-horizontal',
            self::Return => 'heroicon-m-arrow-uturn-left',
        };
    }

    public function affectsQuantity(): int
    {
        return match ($this) {
            self::Receipt, self::Return => 1,  // Increases stock
            self::Shipment => -1,               // Decreases stock
            self::Transfer, self::Adjustment => 0, // Handled specially
        };
    }
}
