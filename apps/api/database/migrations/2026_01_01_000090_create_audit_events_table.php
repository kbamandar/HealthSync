<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->nullable()->constrained('users');
            $table->uuid('family_group_id')->nullable();
            $table->string('event_type', 100);
            $table->string('resource_type', 50)->nullable();
            $table->uuid('resource_id')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['user_id', 'created_at'], 'idx_audit_events_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
