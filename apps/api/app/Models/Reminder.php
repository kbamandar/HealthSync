<?php

namespace App\Models;

use App\Casts\PostgresTextArrayCast;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reminder extends Model
{
    use HasUuids;

    protected $fillable = [
        'family_group_id',
        'member_id',
        'created_by_id',
        'reminder_type',
        'title',
        'description',
        'due_at',
        'recurrence',
        'recurrence_rule',
        'notify_via',
        'is_active',
        'last_sent_at',
        'linked_record_id',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'recurrence_rule' => 'array',
            'notify_via' => PostgresTextArrayCast::class,
            'is_active' => 'boolean',
            'last_sent_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(FamilyMember::class, 'member_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function linkedRecord(): BelongsTo
    {
        return $this->belongsTo(HealthRecord::class, 'linked_record_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDue($query)
    {
        return $query->active()->where('due_at', '<=', now());
    }
}
