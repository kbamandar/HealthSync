<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Not in the original product schema doc — added to back the plan's
 * `POST /devices` (Sprint 4). Storage only: this sandbox has no Firebase
 * project, so there is no FCM integration to actually deliver push
 * notifications yet, just a registry to send to once one exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(new Expression('gen_random_uuid()'));
            $table->foreignUuid('user_id')->constrained('users');
            $table->text('push_token');
            $table->string('platform', 10);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->unique('push_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
