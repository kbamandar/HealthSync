<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Doctor extends Model
{
    use HasUuids;

    protected $fillable = [
        'family_group_id',
        'name',
        'speciality',
        'hospital_clinic',
        'phone',
        'location',
        'notes',
    ];

    public function familyGroup(): BelongsTo
    {
        return $this->belongsTo(FamilyGroup::class);
    }
}
