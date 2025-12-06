<?php

namespace App\DTOs;

/**
 * Refund Request Data Transfer Object
 */
readonly class RefundRequestDTO
{
    public function __construct(
        public string $refundUuid,
        public string $paymentUuid,
        public string $originalTransactionId,
        public float $amount,
        public string $currency,
        public ?string $reason = null,
        public ?int $initiatedBy = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            refundUuid: $data['refund_uuid'] ?? '',
            paymentUuid: $data['payment_uuid'] ?? '',
            originalTransactionId: $data['original_transaction_id'] ?? '',
            amount: (float) ($data['amount'] ?? 0),
            currency: strtoupper($data['currency'] ?? 'USD'),
            reason: $data['reason'] ?? null,
            initiatedBy: isset($data['initiated_by']) ? (int) $data['initiated_by'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'refund_uuid' => $this->refundUuid,
            'payment_uuid' => $this->paymentUuid,
            'original_transaction_id' => $this->originalTransactionId,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'reason' => $this->reason,
            'initiated_by' => $this->initiatedBy,
        ];
    }
}

