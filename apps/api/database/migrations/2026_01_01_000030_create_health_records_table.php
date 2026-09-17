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
        Schema::create('health_records', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(new Expression('gen_random_uuid()'));
            $table->foreignUuid('family_group_id')->constrained('family_groups');
            $table->foreignUuid('member_id')->constrained('family_members');
            $table->foreignUuid('uploaded_by_id')->constrained('users');
            $table->string('category', 50);
            $table->string('title', 500)->nullable();
            $table->date('record_date')->nullable();
            $table->string('doctor_name')->nullable();
            $table->string('hospital_clinic')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_favourite')->default(false);
            $table->string('fhir_resource_type', 100)->nullable();
            $table->string('fhir_resource_id')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestampTz('deleted_at')->nullable();
            $table->integer('version')->default(1);
            $table->uuid('parent_record_id')->nullable();
            $table->timestampsTz();

            $table->index(['member_id', 'record_date'], 'idx_health_records_member');
            $table->index(['family_group_id', 'category'], 'idx_health_records_category');
        });

        // Self-referential FK added after table creation: Postgres can't validate a
        // reference to the table's own primary key within the same CREATE TABLE.
        Schema::table('health_records', function (Blueprint $table) {
            $table->foreign('parent_record_id')->references('id')->on('health_records');
        });

        // Blueprint has no native Postgres array type.
        DB::statement("ALTER TABLE health_records ADD COLUMN custom_tags text[] DEFAULT '{}'");

        DB::statement(
            "CREATE INDEX idx_health_records_search ON health_records USING GIN(to_tsvector('english', coalesce(title,'') || ' ' || coalesce(notes,'')))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('health_records');
    }
};
