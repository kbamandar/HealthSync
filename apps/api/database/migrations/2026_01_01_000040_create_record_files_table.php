<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_files', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(new Expression('gen_random_uuid()'));
            $table->foreignUuid('record_id')->constrained('health_records');
            $table->string('file_type', 10);
            $table->text('s3_key');
            $table->integer('file_size_bytes')->nullable();
            $table->string('mime_type')->nullable();
            $table->boolean('ocr_extracted')->default(false);
            $table->jsonb('ocr_data')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_files');
    }
};
