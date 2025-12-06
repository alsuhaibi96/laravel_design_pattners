<?php

namespace App\Enums;

enum PaymentGateway: string
{
    case STRIPE = 'stripe';
    case PAYPAL = 'paypal';
    case BANK_TRANSFER = 'bank_transfer';

    public function label(): string
    {
        return match ($this) {
            self::STRIPE => 'Stripe',
            self::PAYPAL => 'PayPal',
            self::BANK_TRANSFER => 'Bank Transfer',
        };
    }

    public function rateLimit(): int
    {
        return match ($this) {
            self::STRIPE => 100, // 100 req/sec
            self::PAYPAL => 50,  // 50 req/sec
            self::BANK_TRANSFER => 30,
        };
    }

    public function supportedCountries(): array
    {
        return match ($this) {
            self::STRIPE => ['US', 'CA', 'GB', 'AU', 'DE', 'FR', 'JP', 'SG'],
            self::PAYPAL => ['US', 'CA', 'GB', 'AU', 'DE', 'FR', 'ES', 'IT', 'NL'],
            self::BANK_TRANSFER => ['US', 'CA', 'GB', 'DE', 'FR', 'AU'],
        };
    }

    public function supportsCountry(string $countryCode): bool
    {
        return in_array(strtoupper($countryCode), $this->supportedCountries());
    }

    public function minAmount(): float
    {
        return match ($this) {
            self::STRIPE => 0.50,
            self::PAYPAL => 1.00,
            self::BANK_TRANSFER => 10.00,
        };
    }

    public function maxAmount(): float
    {
        return match ($this) {
            self::STRIPE => 999999.99,
            self::PAYPAL => 60000.00,
            self::BANK_TRANSFER => 1000000.00,
        };
    }

    public function feePercentage(): float
    {
        return match ($this) {
            self::STRIPE => 2.9,
            self::PAYPAL => 3.49,
            self::BANK_TRANSFER => 0.5,
        };
    }

    public function fixedFee(): float
    {
        return match ($this) {
            self::STRIPE => 0.30,
            self::PAYPAL => 0.49,
            self::BANK_TRANSFER => 1.00,
        };
    }

    public function calculateFee(float $amount): float
    {
        return round(($amount * $this->feePercentage() / 100) + $this->fixedFee(), 2);
    }
}

