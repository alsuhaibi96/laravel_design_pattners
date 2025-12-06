<?php

namespace App\Models;

use App\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

class Refund extends Model
{
    protected $fillable = [
        'uuid',
        'payment_id',
        'gateway',
        'amount',
        'currency',
        'status',
        'reason',
        'notes',
        'gateway_refund_id',
        'gateway_response',
        'initiated_by',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gateway_response' => 'array',
        'processed_at' => 'datetime',
        'status' => RefundStatus::class,
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Refund $refund) {
            if (empty($refund->uuid)) {
                $refund->uuid = (string) Str::uuid();
            }
        });
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function transactionLogs(): MorphMany
    {
        return $this->morphMany(TransactionLog::class, 'loggable');
    }

    public function isPending(): bool
    {
        return $this->status === RefundStatus::PENDING;
    }

    public function isSuccessful(): bool
    {
        return $this->status === RefundStatus::SUCCESS;
    }

    public function markAsProcessing(): void
    {
        $this->update(['status' => RefundStatus::PROCESSING]);
    }

    public function markAsSuccess(string $refundId, ?array $response = null): void
    {
        $this->update([
            'status' => RefundStatus::SUCCESS,
            'gateway_refund_id' => $refundId,
            'gateway_response' => $response,
            'processed_at' => now(),
        ]);
    }

    public function markAsFailed(?array $response = null): void
    {
        $this->update([
            'status' => RefundStatus::FAILED,
            'gateway_response' => $response,
            'processed_at' => now(),
        ]);
    }
}

