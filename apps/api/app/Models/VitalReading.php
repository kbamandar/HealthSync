<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VitalReading extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'family_group_id',
        'member_id',
        'logged_by_id',
        'vital_type',
        'value',
        'unit',
        'reading_context',
        'notes',
        'recorded_at',
        'is_abnormal',
        'alert_sent',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'float',
            'recorded_at' => 'datetime',
            'is_abnormal' => 'boolean',
            'alert_sent' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(FamilyMember::class, 'member_id');
    }

    public function loggedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by_id');
    }
}
