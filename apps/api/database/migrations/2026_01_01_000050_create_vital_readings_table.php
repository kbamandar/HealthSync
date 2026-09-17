<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vital_readings', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(new Expression('gen_random_uuid()'));
            $table->foreignUuid('family_group_id')->constrained('family_groups');
            $table->foreignUuid('member_id')->constrained('family_members');
            $table->foreignUuid('logged_by_id')->constrained('users');
            $table->string('vital_type', 50);
            $table->decimal('value', 8, 2);
            $table->string('unit', 20);
            $table->string('reading_context', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestampTz('recorded_at');
            $table->boolean('is_abnormal')->default(false);
            $table->boolean('alert_sent')->default(false);
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['member_id', 'vital_type', 'recorded_at'], 'idx_vital_readings_member');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vital_readings');
    }
};
