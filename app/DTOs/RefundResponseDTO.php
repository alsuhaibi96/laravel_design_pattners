<?php

namespace App\DTOs;

use App\Enums\PaymentGateway;
use App\Enums\RefundStatus;

/**
 * Refund Response Data Transfer Object
 */
readonly class RefundResponseDTO
{
    public function __construct(
        public bool $success,
        public RefundStatus $status,
        public PaymentGateway $gateway,
        public ?string $refundId = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        public ?int $responseTimeMs = null,
        public array $rawResponse = [],
    ) {}

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'status' => $this->status->value,
            'gateway' => $this->gateway->value,
            'refund_id' => $this->refundId,
            'error_code' => $this->errorCode,
            'error_message' => $this->errorMessage,
            'response_time_ms' => $this->responseTimeMs,
        ];
    }
}

