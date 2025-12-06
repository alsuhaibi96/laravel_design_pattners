<?php

namespace App\Enums;

enum RefundStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case SUCCESS = 'success';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::PROCESSING => 'Processing',
            self::SUCCESS => 'Success',
            self::FAILED => 'Failed',
        };
    }
}

