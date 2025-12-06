<?php

namespace App\DTOs;

use App\Enums\PaymentGateway;
use Carbon\Carbon;

/**
 * Webhook Payload Data Transfer Object
 * Normalized webhook data from any gateway
 */
readonly class WebhookPayloadDTO
{
    public function __construct(
        public PaymentGateway $gateway,
        public string $eventType,
        public string $originalEventType,
        public ?string $transactionId = null,
        public ?string $paymentIntentId = null,
        public ?float $amount = null,
        public ?string $currency = null,
        public ?string $status = null,
        public array $rawPayload = [],
        public ?Carbon $timestamp = null,
    ) {}

    public function isPaymentSuccess(): bool
    {
        return $this->eventType === 'payment.success';
    }

    public function isPaymentFailed(): bool
    {
        return $this->eventType === 'payment.failed';
    }

    public function isRefundEvent(): bool
    {
        return str_starts_with($this->eventType, 'refund.');
    }

    public function toArray(): array
    {
        return [
            'gateway' => $this->gateway->value,
            'event_type' => $this->eventType,
            'original_event_type' => $this->originalEventType,
            'transaction_id' => $this->transactionId,
            'payment_intent_id' => $this->paymentIntentId,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'timestamp' => $this->timestamp?->toIso8601String(),
        ];
    }
}

