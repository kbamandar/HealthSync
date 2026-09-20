<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SharedLink extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'created_by_id',
        'link_type',
        'record_id',
        'member_id',
        'token',
        'expires_at',
        'access_count',
        'max_access',
        'is_revoked',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'access_count' => 'integer',
            'max_access' => 'integer',
            'is_revoked' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(HealthRecord::class, 'record_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(FamilyMember::class, 'member_id');
    }

    public function isUsable(): bool
    {
        if ($this->is_revoked || $this->expires_at->isPast()) {
            return false;
        }

        return $this->max_access === null || $this->access_count < $this->max_access;
    }
}
