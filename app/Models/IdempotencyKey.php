<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    protected $fillable = [
        'key',
        'request_hash',
        'response',
        'status_code',
        'is_processing',
        'locked_at',
        'expires_at',
    ];

    protected $casts = [
        'request_hash' => 'array',
        'response' => 'array',
        'status_code' => 'integer',
        'is_processing' => 'boolean',
        'locked_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isProcessing(): bool
    {
        return $this->is_processing;
    }

    public function hasResponse(): bool
    {
        return !empty($this->response);
    }

    public function lock(): void
    {
        $this->update([
            'is_processing' => true,
            'locked_at' => now(),
        ]);
    }

    public function unlock(): void
    {
        $this->update([
            'is_processing' => false,
            'locked_at' => null,
        ]);
    }

    public function storeResponse(array $response, int $statusCode): void
    {
        $this->update([
            'response' => $response,
            'status_code' => $statusCode,
            'is_processing' => false,
            'locked_at' => null,
        ]);
    }

    public static function findByKey(string $key): ?self
    {
        return self::where('key', $key)
            ->where('expires_at', '>', now())
            ->first();
    }

    public static function createForRequest(string $key, array $requestHash): self
    {
        return self::create([
            'key' => $key,
            'request_hash' => $requestHash,
            'expires_at' => now()->addHours(24),
        ]);
    }
}

