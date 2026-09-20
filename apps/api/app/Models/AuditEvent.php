<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'family_group_id',
        'event_type',
        'resource_type',
        'resource_id',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public static function record(
        string $eventType,
        ?string $userId,
        ?Request $request = null,
        array $metadata = [],
    ): void {
        static::create([
            'user_id' => $userId,
            'event_type' => $eventType,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata' => $metadata,
        ]);
    }
}
