<?php

namespace App\Models;

use App\Enums\PaymentAttemptStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PaymentAttempt extends Model
{
    protected $fillable = [
        'uuid',
        'payment_id',
        'gateway',
        'attempt_number',
        'status',
        'error_code',
        'error_message',
        'is_retryable',
        'gateway_transaction_id',
        'gateway_request',
        'gateway_response',
        'response_time_ms',
    ];

    protected $casts = [
        'attempt_number' => 'integer',
        'is_retryable' => 'boolean',
        'gateway_request' => 'array',
        'gateway_response' => 'array',
        'response_time_ms' => 'integer',
        'status' => PaymentAttemptStatus::class,
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (PaymentAttempt $attempt) {
            if (empty($attempt->uuid)) {
                $attempt->uuid = (string) Str::uuid();
            }
        });
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function isSuccessful(): bool
    {
        return $this->status === PaymentAttemptStatus::SUCCESS;
    }

    public function canRetry(): bool
    {
        return $this->is_retryable;
    }

    public static function createForPayment(
        Payment $payment,
        string $gateway,
        PaymentAttemptStatus $status,
        ?string $errorCode = null,
        ?string $errorMessage = null,
        bool $isRetryable = false,
        ?string $transactionId = null,
        ?array $request = null,
        ?array $response = null,
        ?int $responseTimeMs = null
    ): self {
        return self::create([
            'payment_id' => $payment->id,
            'gateway' => $gateway,
            'attempt_number' => $payment->attempt_count + 1,
            'status' => $status,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'is_retryable' => $isRetryable,
            'gateway_transaction_id' => $transactionId,
            'gateway_request' => $request,
            'gateway_response' => $response,
            'response_time_ms' => $responseTimeMs,
        ]);
    }
}

