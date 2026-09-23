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

    /**
     * Checked live (not just at share time) so a link automatically stops
     * working the moment its underlying record is deleted or its member is
     * removed from the family group — without needing every delete/removal
     * code path to remember to also revoke associated links.
     */
    public function isUsable(): bool
    {
        if ($this->is_revoked || $this->expires_at->isPast()) {
            return false;
        }

        if ($this->max_access !== null && $this->access_count >= $this->max_access) {
            return false;
        }

        if ($this->link_type === 'record') {
            return $this->record !== null && ! $this->record->is_deleted;
        }

        if ($this->link_type === 'health_summary') {
            return $this->member !== null && $this->member->removed_at === null;
        }

        return true;
    }
}
