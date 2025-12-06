<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CREDIT_CARD = 'credit_card';
    case DEBIT_CARD = 'debit_card';
    case PAYPAL = 'paypal';
    case BANK_TRANSFER = 'bank_transfer';

    public function label(): string
    {
        return match ($this) {
            self::CREDIT_CARD => 'Credit Card',
            self::DEBIT_CARD => 'Debit Card',
            self::PAYPAL => 'PayPal',
            self::BANK_TRANSFER => 'Bank Transfer',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::CREDIT_CARD => 'credit-card',
            self::DEBIT_CARD => 'credit-card',
            self::PAYPAL => 'paypal',
            self::BANK_TRANSFER => 'bank',
        };
    }

    public function supportedGateways(): array
    {
        return match ($this) {
            self::CREDIT_CARD, self::DEBIT_CARD => [PaymentGateway::STRIPE],
            self::PAYPAL => [PaymentGateway::PAYPAL],
            self::BANK_TRANSFER => [PaymentGateway::BANK_TRANSFER],
        };
    }
}

