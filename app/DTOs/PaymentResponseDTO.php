<?php

namespace App\DTOs;

use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentGateway;

/**
 * Payment Response Data Transfer Object
 * Standardized response from any payment gateway
 */
readonly class PaymentResponseDTO
{
    public function __construct(
        public bool $success,
        public PaymentAttemptStatus $status,
        public PaymentGateway $gateway,
        public ?string $transactionId = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        public bool $isRetryable = false,
        public ?int $responseTimeMs = null,
        public array $rawResponse = [],
    ) {}

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'status' => $this->status->value,
            'gateway' => $this->gateway->value,
            'transaction_id' => $this->transactionId,
            'error_code' => $this->errorCode,
            'error_message' => $this->errorMessage,
            'is_retryable' => $this->isRetryable,
            'response_time_ms' => $this->responseTimeMs,
        ];
    }

    public function shouldRetry(): bool
    {
        return !$this->success && $this->isRetryable;
    }
}

