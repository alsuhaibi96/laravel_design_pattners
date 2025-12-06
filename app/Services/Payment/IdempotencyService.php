<?php

namespace App\Services\Payment;

use App\Models\IdempotencyKey;
use Illuminate\Support\Facades\DB;

/**
 * Idempotency Service
 * 
 * Ensures payment processing is idempotent:
 * - Same request with same key = same result
 * - Prevents duplicate charges
 * - Handles concurrent requests safely
 */
class IdempotencyService
{
    private const LOCK_TIMEOUT_SECONDS = 30;

    /**
     * Check if request with this key has already been processed
     * Returns stored response if exists, null if new request
     */
    public function check(string $key): ?array
    {
        $record = IdempotencyKey::findByKey($key);

        if (!$record) {
            return null;
        }

        // If expired, treat as new request
        if ($record->isExpired()) {
            $record->delete();
            return null;
        }

        // If still processing, wait or return conflict
        if ($record->isProcessing()) {
            // Check if lock is stale (processing for too long)
            if ($record->locked_at && $record->locked_at->diffInSeconds(now()) > self::LOCK_TIMEOUT_SECONDS) {
                $record->unlock();
                return null;
            }

            return [
                'status' => 'processing',
                'message' => 'Request is currently being processed',
                'retry_after' => 5,
            ];
        }

        // Return stored response
        if ($record->hasResponse()) {
            return [
                'status' => 'completed',
                'response' => $record->response,
                'status_code' => $record->status_code,
            ];
        }

        return null;
    }

    /**
     * Lock the idempotency key for processing
     */
    public function lock(string $key, array $requestHash): IdempotencyKey
    {
        return DB::transaction(function () use ($key, $requestHash) {
            $record = IdempotencyKey::findByKey($key);

            if (!$record) {
                $record = IdempotencyKey::createForRequest($key, $requestHash);
            }

            $record->lock();
            return $record;
        });
    }

    /**
     * Store the response for future replay
     */
    public function storeResponse(string $key, array $response, int $statusCode): void
    {
        $record = IdempotencyKey::where('key', $key)->first();
        
        if ($record) {
            $record->storeResponse($response, $statusCode);
        }
    }

    /**
     * Unlock without storing response (on error)
     */
    public function unlock(string $key): void
    {
        $record = IdempotencyKey::where('key', $key)->first();
        
        if ($record) {
            $record->unlock();
        }
    }

    /**
     * Clean up expired keys
     */
    public function cleanupExpired(): int
    {
        return IdempotencyKey::where('expires_at', '<', now())->delete();
    }
}

