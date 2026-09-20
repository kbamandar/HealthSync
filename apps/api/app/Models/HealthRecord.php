<?php

namespace App\Models;

use App\Casts\PostgresTextArrayCast;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HealthRecord extends Model
{
    use HasUuids;

    protected $fillable = [
        'family_group_id',
        'member_id',
        'uploaded_by_id',
        'category',
        'title',
        'record_date',
        'doctor_name',
        'hospital_clinic',
        'notes',
        'is_favourite',
        'custom_tags',
        'is_deleted',
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'record_date' => 'date',
            'is_favourite' => 'boolean',
            'is_deleted' => 'boolean',
            'deleted_at' => 'datetime',
            'custom_tags' => PostgresTextArrayCast::class,
        ];
    }

    public function familyGroup(): BelongsTo
    {
        return $this->belongsTo(FamilyGroup::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(FamilyMember::class, 'member_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(RecordFile::class, 'record_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_deleted', false);
    }

    public function scopeTrashed($query)
    {
        return $query->where('is_deleted', true);
    }
}
