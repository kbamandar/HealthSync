<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(new Expression('gen_random_uuid()'));
            $table->foreignUuid('family_group_id')->constrained('family_groups');
            $table->foreignUuid('member_id')->constrained('family_members');
            $table->foreignUuid('created_by_id')->constrained('users');
            $table->string('reminder_type', 50);
            $table->string('title', 500);
            $table->text('description')->nullable();
            $table->timestampTz('due_at');
            $table->string('recurrence', 50)->nullable();
            $table->jsonb('recurrence_rule')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('last_sent_at')->nullable();
            $table->foreignUuid('linked_record_id')->nullable()->constrained('health_records');
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE reminders ADD COLUMN notify_via text[] DEFAULT '{push}'");

        DB::statement(
            'CREATE INDEX idx_reminders_due ON reminders(due_at, is_active) WHERE is_active = TRUE'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
