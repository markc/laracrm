<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasColor, HasLabel
{
    case Cash = 'cash';
    case Cheque = 'cheque';
    case Card = 'card';
    case BankTransfer = 'bank_transfer';
    case DirectDebit = 'direct_debit';
    case PayPal = 'paypal';
    case Stripe = 'stripe';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Cheque => 'Cheque',
            self::Card => 'Credit/Debit Card',
            self::BankTransfer => 'Bank Transfer',
            self::DirectDebit => 'Direct Debit',
            self::PayPal => 'PayPal',
            self::Stripe => 'Stripe',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Cash => 'success',
            self::Cheque => 'warning',
            self::Card => 'info',
            self::BankTransfer => 'primary',
            self::DirectDebit => 'primary',
            self::PayPal => 'info',
            self::Stripe => 'info',
        };
    }
}
