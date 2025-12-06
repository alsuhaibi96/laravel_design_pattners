<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'idempotency_key',
        'user_id',
        'order_id',
        'amount',
        'currency',
        'payment_method',
        'preferred_gateway',
        'final_gateway',
        'status',
        'failure_reason',
        'country_code',
        'ip_address',
        'gateway_transaction_id',
        'gateway_metadata',
        'attempt_count',
        'max_retries',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gateway_metadata' => 'array',
        'attempt_count' => 'integer',
        'max_retries' => 'integer',
        'processed_at' => 'datetime',
        'status' => PaymentStatus::class,
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Payment $payment) {
            if (empty($payment->uuid)) {
                $payment->uuid = (string) Str::uuid();
            }
        });
    }

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class)->orderBy('attempt_number');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function transactionLogs(): MorphMany
    {
        return $this->morphMany(TransactionLog::class, 'loggable');
    }

    // Helper methods

    public function isPending(): bool
    {
        return $this->status === PaymentStatus::PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->status === PaymentStatus::PROCESSING;
    }

    public function isSuccessful(): bool
    {
        return $this->status === PaymentStatus::SUCCESS;
    }

    public function isFailed(): bool
    {
        return $this->status === PaymentStatus::FAILED;
    }

    public function canRetry(): bool
    {
        return $this->attempt_count < ($this->max_retries + 1) 
            && !$this->isSuccessful();
    }

    public function getTotalRefundedAmount(): float
    {
        return $this->refunds()
            ->where('status', 'success')
            ->sum('amount');
    }

    public function getRefundableAmount(): float
    {
        return max(0, $this->amount - $this->getTotalRefundedAmount());
    }

    public function markAsProcessing(): void
    {
        $this->update(['status' => PaymentStatus::PROCESSING]);
    }

    public function markAsSuccess(string $gateway, ?string $transactionId = null, ?array $metadata = null): void
    {
        $this->update([
            'status' => PaymentStatus::SUCCESS,
            'final_gateway' => $gateway,
            'gateway_transaction_id' => $transactionId,
            'gateway_metadata' => $metadata,
            'processed_at' => now(),
        ]);
    }

    public function markAsFailed(string $reason): void
    {
        $this->update([
            'status' => PaymentStatus::FAILED,
            'failure_reason' => $reason,
            'processed_at' => now(),
        ]);
    }

    public function incrementAttemptCount(): void
    {
        $this->increment('attempt_count');
    }
}

