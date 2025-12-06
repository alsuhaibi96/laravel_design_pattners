<?php

namespace App\Enums;

enum PaymentAttemptStatus: string
{
    case INITIATED = 'initiated';
    case SUCCESS = 'success';
    case FAILED = 'failed';
    case TIMEOUT = 'timeout';
    case RATE_LIMITED = 'rate_limited';
    case DECLINED = 'declined';

    public function isRetryable(): bool
    {
        return match ($this) {
            self::TIMEOUT, self::RATE_LIMITED => true,
            self::SUCCESS, self::FAILED, self::INITIATED, self::DECLINED => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::INITIATED => 'Initiated',
            self::SUCCESS => 'Success',
            self::FAILED => 'Failed',
            self::TIMEOUT => 'Timeout',
            self::RATE_LIMITED => 'Rate Limited',
            self::DECLINED => 'Declined',
        };
    }
}

