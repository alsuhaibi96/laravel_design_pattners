<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class TransactionLog extends Model
{
    protected $fillable = [
        'uuid',
        'loggable_type',
        'loggable_id',
        'event',
        'gateway',
        'data',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'data' => 'array',
        'metadata' => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (TransactionLog $log) {
            if (empty($log->uuid)) {
                $log->uuid = (string) Str::uuid();
            }
        });
    }

    public function loggable(): MorphTo
    {
        return $this->morphTo();
    }

    public static function logEvent(
        Model $loggable,
        string $event,
        ?string $gateway = null,
        ?array $data = null,
        ?array $metadata = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): self {
        return self::create([
            'loggable_type' => get_class($loggable),
            'loggable_id' => $loggable->id,
            'event' => $event,
            'gateway' => $gateway,
            'data' => $data,
            'metadata' => $metadata,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }
}

