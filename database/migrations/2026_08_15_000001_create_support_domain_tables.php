<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for the 6ixCulture Enterprise AI Support domain foundation.
     */
    public function up(): void
    {
        // 1. Departments
        if (!Schema::hasTable('support_departments')) {
            Schema::create('support_departments', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->string('slug', 100)->unique();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }

        // 2. Agent Profiles (references existing users table)
        if (!Schema::hasTable('support_agent_profiles')) {
            Schema::create('support_agent_profiles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->onDelete('cascade');
                $table->string('display_name', 100)->nullable();
                $table->string('status', 30)->default('offline')->index(); // 'online', 'busy', 'away', 'offline'
                $table->string('availability', 30)->default('available');
                $table->unsignedInteger('max_concurrent_conversations')->default(5);
                $table->json('skills')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        // 3. Agent Department Pivot
        if (!Schema::hasTable('support_agent_department')) {
            Schema::create('support_agent_department', function (Blueprint $table) {
                $table->id();
                $table->foreignId('agent_profile_id')->constrained('support_agent_profiles')->onDelete('cascade');
                $table->foreignId('department_id')->constrained('support_departments')->onDelete('cascade');
                $table->boolean('is_primary')->default(false);
                $table->timestamps();

                $table->unique(['agent_profile_id', 'department_id'], 'agent_dept_unique');
            });
        }

        // 4. Support Conversations
        if (!Schema::hasTable('support_conversations')) {
            Schema::create('support_conversations', function (Blueprint $table) {
                $table->id();
                $table->string('public_id', 36)->unique();
                $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('guest_session_id', 64)->nullable()->index();
                $table->string('status', 30)->default('new')->index();
                $table->string('mode', 20)->default('ai'); // 'ai', 'human', 'hybrid'
                $table->string('priority', 20)->default('normal')->index();
                $table->string('language', 10)->default('en')->index();
                $table->string('channel', 20)->default('web')->index();
                $table->foreignId('department_id')->nullable()->constrained('support_departments')->nullOnDelete();
                $table->foreignId('assigned_agent_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('assigned_at')->nullable();
                $table->timestamp('first_response_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamp('last_message_at')->nullable()->index();
                $table->timestamp('last_customer_message_at')->nullable();
                $table->timestamp('last_agent_message_at')->nullable();
                $table->boolean('ai_active')->default(true);
                $table->boolean('human_requested')->default(false);
                $table->string('escalation_reason')->nullable();
                $table->text('ai_summary')->nullable();
                $table->string('sentiment', 30)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        // 5. Support Messages
        if (!Schema::hasTable('support_messages')) {
            Schema::create('support_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_id')->constrained('support_conversations')->onDelete('cascade');
                $table->string('sender_type', 20)->index(); // 'customer', 'ai', 'agent', 'system'
                $table->unsignedBigInteger('sender_id')->nullable()->index();
                $table->string('message_type', 30)->default('text')->index();
                $table->longText('content')->nullable();
                $table->json('structured_payload')->nullable();
                $table->string('language', 10)->nullable();
                $table->boolean('is_internal')->default(false)->index();
                $table->boolean('is_read')->default(false);
                $table->string('tool_call_id', 64)->nullable();
                $table->unsignedBigInteger('reply_to_id')->nullable();
                $table->unsignedInteger('tokens_used')->default(0);
                $table->unsignedInteger('latency_ms')->default(0);
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        // 6. Support Tickets
        if (!Schema::hasTable('support_tickets')) {
            Schema::create('support_tickets', function (Blueprint $table) {
                $table->id();
                $table->string('public_id', 36)->unique();
                $table->string('ticket_number', 32)->unique();
                $table->foreignId('conversation_id')->nullable()->constrained('support_conversations')->nullOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('department_id')->constrained('support_departments');
                $table->foreignId('assigned_agent_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('category', 50)->default('General')->index();
                $table->string('priority', 20)->default('normal')->index();
                $table->string('status', 30)->default('open')->index();
                $table->string('subject', 255);
                $table->longText('description');
                $table->longText('resolution')->nullable();
                $table->timestamp('sla_due_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        // 7. Support Assignments
        if (!Schema::hasTable('support_assignments')) {
            Schema::create('support_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_id')->constrained('support_conversations')->onDelete('cascade');
                $table->foreignId('agent_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('department_id')->nullable()->constrained('support_departments')->nullOnDelete();
                $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('assigned_at')->useCurrent();
                $table->timestamp('unassigned_at')->nullable();
                $table->string('reason')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        // 8. Support Knowledge Articles & Versions
        if (!Schema::hasTable('support_knowledge_articles')) {
            Schema::create('support_knowledge_articles', function (Blueprint $table) {
                $table->id();
                $table->string('title', 255);
                $table->string('slug', 255)->index();
                $table->string('category', 50)->index();
                $table->string('language', 10)->default('en')->index();
                $table->longText('content');
                $table->string('status', 20)->default('draft')->index(); // 'draft', 'published', 'archived'
                $table->unsignedInteger('version')->default(1);
                $table->timestamp('published_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedInteger('view_count')->default(0);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['slug', 'language']);
            });
        }

        if (!Schema::hasTable('support_knowledge_article_versions')) {
            Schema::create('support_knowledge_article_versions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('article_id')->constrained('support_knowledge_articles')->onDelete('cascade');
                $table->unsignedInteger('version');
                $table->string('title', 255);
                $table->longText('content');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        // 9. Support Policies
        if (!Schema::hasTable('support_policies')) {
            Schema::create('support_policies', function (Blueprint $table) {
                $table->id();
                $table->string('key', 64)->unique();
                $table->string('name', 255);
                $table->text('description')->nullable();
                $table->string('category', 50)->default('general')->index();
                $table->string('effect', 30)->default('allow')->index(); // 'allow', 'deny', 'confirm', 'require_verification', 'require_human'
                $table->json('configuration')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->integer('priority')->default(0)->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        // 10. Support AI Tools Registry
        if (!Schema::hasTable('support_ai_tools')) {
            Schema::create('support_ai_tools', function (Blueprint $table) {
                $table->id();
                $table->string('key', 64)->unique();
                $table->string('name', 255);
                $table->text('description');
                $table->string('category', 50)->default('general')->index();
                $table->string('risk_level', 20)->default('normal')->index(); // 'low', 'normal', 'sensitive', 'critical'
                $table->json('input_schema');
                $table->json('output_schema')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->boolean('requires_authentication')->default(false);
                $table->boolean('requires_confirmation')->default(false);
                $table->boolean('requires_human')->default(false);
                $table->timestamps();
            });
        }

        // 11. Support AI Tool Permissions
        if (!Schema::hasTable('support_ai_tool_permissions')) {
            Schema::create('support_ai_tool_permissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tool_id')->constrained('support_ai_tools')->onDelete('cascade');
                $table->string('permission_name', 100)->index();
                $table->string('customer_scope', 50)->default('all');
                $table->boolean('is_enabled')->default(true)->index();
                $table->json('configuration')->nullable();
                $table->timestamps();
            });
        }

        // 12. Support Voice Sessions
        if (!Schema::hasTable('support_voice_sessions')) {
            Schema::create('support_voice_sessions', function (Blueprint $table) {
                $table->id();
                $table->string('public_id', 36)->unique();
                $table->foreignId('conversation_id')->constrained('support_conversations')->onDelete('cascade');
                $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('language', 10)->default('en');
                $table->string('status', 30)->default('starting')->index();
                $table->timestamp('started_at')->useCurrent();
                $table->timestamp('ended_at')->nullable();
                $table->unsignedInteger('duration_seconds')->nullable();
                $table->unsignedInteger('transcript_message_count')->default(0);
                $table->string('provider', 50)->nullable();
                $table->string('provider_session_id', 255)->nullable();
                $table->string('audio_url', 500)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        // 13. Support Audit Logs (Append-only)
        if (!Schema::hasTable('support_audit_logs')) {
            Schema::create('support_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->string('actor_type', 30)->index(); // 'customer', 'ai', 'agent', 'system'
                $table->unsignedBigInteger('actor_id')->nullable()->index();
                $table->unsignedBigInteger('customer_id')->nullable()->index();
                $table->unsignedBigInteger('conversation_id')->nullable()->index();
                $table->unsignedBigInteger('ticket_id')->nullable()->index();
                $table->string('action', 100)->index();
                $table->string('resource_type', 100)->nullable();
                $table->unsignedBigInteger('resource_id')->nullable();
                $table->string('tool_name', 100)->nullable();
                $table->string('authorization_result', 50)->nullable();
                $table->json('before_data')->nullable();
                $table->json('after_data')->nullable();
                $table->json('metadata')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('created_at')->useCurrent()->index();
            });
        }

        // 14. Support Feedback
        if (!Schema::hasTable('support_feedback')) {
            Schema::create('support_feedback', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_id')->constrained('support_conversations')->onDelete('cascade');
                $table->foreignId('ticket_id')->nullable()->constrained('support_tickets')->nullOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedTinyInteger('rating'); // 1-5
                $table->text('comment')->nullable();
                $table->string('language', 10)->default('en');
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        // 15. Support Conversation Tags & Pivot
        if (!Schema::hasTable('support_conversation_tags')) {
            Schema::create('support_conversation_tags', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->string('slug', 100)->unique();
                $table->string('color', 20)->default('#1ABC9C');
                $table->string('type', 50)->default('general');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('support_conversation_tag_pivot')) {
            Schema::create('support_conversation_tag_pivot', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_id')->constrained('support_conversations')->onDelete('cascade');
                $table->foreignId('tag_id')->constrained('support_conversation_tags')->onDelete('cascade');
                $table->timestamps();

                $table->unique(['conversation_id', 'tag_id'], 'conv_tag_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_conversation_tag_pivot');
        Schema::dropIfExists('support_conversation_tags');
        Schema::dropIfExists('support_feedback');
        Schema::dropIfExists('support_audit_logs');
        Schema::dropIfExists('support_voice_sessions');
        Schema::dropIfExists('support_ai_tool_permissions');
        Schema::dropIfExists('support_ai_tools');
        Schema::dropIfExists('support_policies');
        Schema::dropIfExists('support_knowledge_article_versions');
        Schema::dropIfExists('support_knowledge_articles');
        Schema::dropIfExists('support_assignments');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_conversations');
        Schema::dropIfExists('support_agent_department');
        Schema::dropIfExists('support_agent_profiles');
        Schema::dropIfExists('support_departments');
    }
};
