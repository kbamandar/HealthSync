<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 1 uses email OTP (MSG91/SMS OTP is blocked on DLT registration — see
 * plan risk R01). Email becomes the auth identifier, so it must be required;
 * `name` is now collected in a separate profile-setup step after OTP verify,
 * so it can no longer be required at user-creation time. `mobile` stays
 * required per the schema — it's just not OTP-verified until SMS is live.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
            $table->string('name')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('name')->nullable(false)->change();
        });
    }
};
