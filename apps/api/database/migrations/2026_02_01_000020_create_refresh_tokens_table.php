<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(new Expression('gen_random_uuid()'));
            $table->foreignUuid('user_id')->constrained('users');
            $table->string('token_hash')->unique();
            $table->timestampTz('expires_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->uuid('replaced_by_id')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['user_id', 'revoked_at'], 'idx_refresh_tokens_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refresh_tokens');
    }
};
