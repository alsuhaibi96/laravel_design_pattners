<?php

namespace App\DTOs;

use App\Enums\PaymentMethod;

/**
 * Payment Request Data Transfer Object
 * Contains all data needed to process a payment
 */
readonly class PaymentRequestDTO
{
    public function __construct(
        public string $paymentUuid,
        public float $amount,
        public string $currency,
        public ?string $orderId = null,
        public ?int $userId = null,
        public ?string $countryCode = null,
        public ?PaymentMethod $paymentMethod = null,
        public ?string $preferredGateway = null,
        public ?string $idempotencyKey = null,
        public ?string $ipAddress = null,
        public array $metadata = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            paymentUuid: $data['payment_uuid'] ?? '',
            amount: (float) ($data['amount'] ?? 0),
            currency: strtoupper($data['currency'] ?? 'USD'),
            orderId: $data['order_id'] ?? null,
            userId: isset($data['user_id']) ? (int) $data['user_id'] : null,
            countryCode: isset($data['country_code']) ? strtoupper($data['country_code']) : null,
            paymentMethod: isset($data['payment_method']) 
                ? PaymentMethod::tryFrom($data['payment_method']) 
                : null,
            preferredGateway: $data['preferred_gateway'] ?? null,
            idempotencyKey: $data['idempotency_key'] ?? null,
            ipAddress: $data['ip_address'] ?? null,
            metadata: $data['metadata'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'payment_uuid' => $this->paymentUuid,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'order_id' => $this->orderId,
            'user_id' => $this->userId,
            'country_code' => $this->countryCode,
            'payment_method' => $this->paymentMethod?->value,
            'preferred_gateway' => $this->preferredGateway,
            'idempotency_key' => $this->idempotencyKey,
            'ip_address' => $this->ipAddress,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Get hash for idempotency check
     */
    public function getRequestHash(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'order_id' => $this->orderId,
            'user_id' => $this->userId,
        ];
    }
}

