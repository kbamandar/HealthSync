<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_members', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(new Expression('gen_random_uuid()'));
            $table->foreignUuid('family_group_id')->constrained('family_groups');
            $table->foreignUuid('member_user_id')->nullable()->constrained('users');
            $table->foreignUuid('added_by_user_id')->constrained('users');
            $table->string('relationship', 50);
            $table->string('custom_label', 100)->nullable();
            $table->string('display_name');
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('blood_group', 10)->nullable();
            $table->boolean('is_guardian_managed')->default(false);
            $table->string('access_level', 30)->default('self_only');
            $table->string('invite_status', 20)->default('pending');
            $table->text('invite_token')->nullable();
            $table->timestampTz('invite_sent_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_members');
    }
};
