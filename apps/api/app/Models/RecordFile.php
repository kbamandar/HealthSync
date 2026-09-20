<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecordFile extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'record_id',
        'file_type',
        's3_key',
        'file_size_bytes',
        'mime_type',
        'ocr_extracted',
        'ocr_data',
    ];

    protected function casts(): array
    {
        return [
            'ocr_extracted' => 'boolean',
            'ocr_data' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(HealthRecord::class, 'record_id');
    }
}
