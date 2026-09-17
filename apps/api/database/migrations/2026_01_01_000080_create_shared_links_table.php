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
        Schema::create('shared_links', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(new Expression('gen_random_uuid()'));
            $table->foreignUuid('created_by_id')->constrained('users');
            $table->string('link_type', 30);
            $table->foreignUuid('record_id')->nullable()->constrained('health_records');
            $table->foreignUuid('member_id')->nullable()->constrained('family_members');
            $table->text('token')->unique();
            $table->timestampTz('expires_at');
            $table->integer('access_count')->default(0);
            $table->integer('max_access')->nullable();
            $table->boolean('is_revoked')->default(false);
            $table->timestampTz('created_at')->useCurrent();
        });

        DB::statement(
            'CREATE INDEX idx_shared_links_token ON shared_links(token) WHERE is_revoked = FALSE'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('shared_links');
    }
};
