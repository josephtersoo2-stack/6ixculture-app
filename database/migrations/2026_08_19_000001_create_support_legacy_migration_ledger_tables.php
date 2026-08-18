<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('support_legacy_migration_runs')) {
            Schema::create('support_legacy_migration_runs', function (Blueprint $table) {
                $table->id();
                $table->string('public_id', 36)->unique()->index();
                $table->string('source', 50)->default('legacy_chat');
                $table->string('mode', 20)->default('apply'); // 'audit', 'dry_run', 'apply', 'rollback', 'verify'
                $table->string('status', 20)->default('pending'); // 'pending', 'running', 'completed', 'partial', 'failed', 'rolled_back'
                $table->timestamp('started_at');
                $table->timestamp('completed_at')->nullable();
                $table->json('source_counts')->nullable();
                $table->json('result_counts')->nullable();
                $table->json('error_counts')->nullable();
                $table->string('checksum', 64)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('support_legacy_migration_items')) {
            Schema::create('support_legacy_migration_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('migration_run_id')->constrained('support_legacy_migration_runs')->onDelete('cascade');
                $table->string('source_table', 50);
                $table->unsignedBigInteger('source_id');
                $table->string('target_table', 50)->nullable();
                $table->unsignedBigInteger('target_id')->nullable();
                $table->string('source_checksum', 64);
                $table->string('state', 20)->default('migrated'); // 'migrated', 'skipped', 'failed', 'conflict', 'rolled_back'
                $table->timestamp('migrated_at')->nullable();
                $table->timestamp('last_verified_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                // Unique index ensures one active mapping item per source record
                $table->unique(['source_table', 'source_id'], 'uniq_source_table_id');
                $table->index(['migration_run_id', 'state']);
                $table->index(['target_table', 'target_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_legacy_migration_items');
        Schema::dropIfExists('support_legacy_migration_runs');
    }
};
