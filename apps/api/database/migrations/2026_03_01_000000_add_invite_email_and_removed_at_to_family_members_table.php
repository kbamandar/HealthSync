<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 2 gap fixes:
 * - invite_email: the schema had nowhere to hold an invitee's contact info
 *   before they accept (member_user_id is null until then). Email is our
 *   invite delivery channel for the same reason it's our OTP channel
 *   (Sprint 1 — SMS/MSG91 blocked on DLT registration).
 * - removed_at: §5 Sprint 2 calls DELETE /family/members/:id a "soft-remove",
 *   but the table had no soft-delete column to remove with.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('family_members', function (Blueprint $table) {
            $table->string('invite_email')->nullable()->after('invite_token');
            $table->timestampTz('removed_at')->nullable()->after('updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('family_members', function (Blueprint $table) {
            $table->dropColumn(['invite_email', 'removed_at']);
        });
    }
};
