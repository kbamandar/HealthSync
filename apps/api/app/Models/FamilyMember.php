<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyMember extends Model
{
    use HasUuids;

    protected $fillable = [
        'family_group_id',
        'member_user_id',
        'added_by_user_id',
        'relationship',
        'custom_label',
        'display_name',
        'date_of_birth',
        'gender',
        'blood_group',
        'is_guardian_managed',
        'access_level',
        'invite_status',
        'invite_token',
        'invite_email',
        'invite_sent_at',
        'removed_at',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'is_guardian_managed' => 'boolean',
            'invite_sent_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }

    public function familyGroup(): BelongsTo
    {
        return $this->belongsTo(FamilyGroup::class);
    }

    public function memberUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_user_id');
    }

    public function addedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user_id');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('removed_at');
    }
}
