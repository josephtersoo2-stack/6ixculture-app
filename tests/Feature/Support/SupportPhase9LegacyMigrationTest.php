<?php

namespace Tests\Feature\Support;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use App\Models\AiAgent;
use App\Support\Enums\ConversationMode;
use App\Support\Enums\ConversationStatus;
use App\Support\Enums\MessageType;
use App\Support\Enums\SenderType;
use App\Support\Migration\LegacyChatAuditService;
use App\Support\Migration\LegacyChatMigrationService;
use App\Support\Migration\LegacyConfigurationMapper;
use App\Support\Migration\LegacyMigrationRollbackService;
use App\Support\Migration\LegacyMigrationVerificationService;
use App\Support\Models\SupportAuditLog;
use App\Support\Models\SupportConversation;
use App\Support\Models\SupportLegacyMigrationItem;
use App\Support\Models\SupportLegacyMigrationRun;
use App\Support\Models\SupportMessage;
use Carbon\Carbon;
use Database\Seeders\SupportDomainSeeder;
use Dipokhalder\Settings\Facades\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupportPhase9LegacyMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected User $otherCustomer;
    protected User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        putenv('OPENROUTER_API_KEY=test_openrouter_sk_key');
        putenv('GEMINI_API_KEY=test_gemini_api_key');

        $this->seed(SupportDomainSeeder::class);

        \App\Models\AiAgent::firstOrCreate(['slug' => 'openrouter'], ['name' => 'OpenRouter', 'status' => 5]);
        \App\Models\AiAgent::firstOrCreate(['slug' => 'gemini'], ['name' => 'Gemini', 'status' => 5]);

        $this->customer = User::factory()->create([
            'username' => 'testcustomer_' . Str::random(6),
            'email' => 'customer@example.com',
        ]);

        $this->otherCustomer = User::factory()->create([
            'username' => 'othercustomer_' . Str::random(6),
            'email' => 'other@example.com',
        ]);

        $this->agent = User::factory()->create([
            'username' => 'agentuser_' . Str::random(6),
            'email' => 'agent@example.com',
        ]);
    }

    /* =========================================================================
     * 1. PREFLIGHT AUDIT TESTS
     * ========================================================================= */

    public function test_audit_reports_accurate_counts_and_breakdowns(): void
    {
        // Create sample legacy conversations
        $conv1 = ChatConversation::create([
            'session_token' => 'sess_' . Str::random(16),
            'user_id' => $this->customer->id,
            'user_name' => 'Customer Name',
            'user_email' => 'customer@example.com',
            'status' => 'ai',
            'created_at' => Carbon::now()->subDays(2),
        ]);

        $conv2 = ChatConversation::create([
            'session_token' => 'sess_' . Str::random(16),
            'user_id' => null,
            'user_name' => 'Guest Visitor',
            'status' => 'closed',
            'created_at' => Carbon::now()->subDays(1),
        ]);

        ChatMessage::create([
            'conversation_id' => $conv1->id,
            'sender_type' => 'user',
            'message' => 'Hello, I have an order issue.',
            'created_at' => Carbon::now()->subDays(2),
        ]);

        ChatMessage::create([
            'conversation_id' => $conv1->id,
            'sender_type' => 'ai',
            'message' => 'Hi! How can I assist you with your order?',
            'created_at' => Carbon::now()->subDays(2)->addMinutes(1),
        ]);

        $auditService = new LegacyChatAuditService();
        $report = $auditService->audit();

        $this->assertEquals('ready', $report['status']);
        $this->assertEquals(2, $report['counts']['total_conversations']);
        $this->assertEquals(2, $report['counts']['total_messages']);
        $this->assertEquals(1, $report['counts']['authenticated_conversations']);
        $this->assertEquals(1, $report['counts']['guest_conversations']);
        $this->assertEquals(0, $report['counts']['missing_user_conversations']);
        $this->assertEquals(0, $report['counts']['orphan_messages']);

        $this->assertEquals(1, $report['breakdowns']['conversation_statuses']['ai']);
        $this->assertEquals(1, $report['breakdowns']['conversation_statuses']['closed']);
        $this->assertEquals(1, $report['breakdowns']['message_senders']['user']);
        $this->assertEquals(1, $report['breakdowns']['message_senders']['ai']);
    }

    public function test_audit_detects_orphans_and_missing_user_references(): void
    {
        // Legacy conversation with non-existent user ID 99999
        $conv = ChatConversation::create([
            'session_token' => 'sess_' . Str::random(16),
            'user_id' => 99999,
            'user_name' => 'Deleted User',
            'status' => 'human',
        ]);

        ChatMessage::create([
            'conversation_id' => $conv->id,
            'sender_type' => 'user',
            'message' => 'Valid message under missing-user conversation',
        ]);

        $auditService = new LegacyChatAuditService();
        $report = $auditService->audit();

        $this->assertEquals(1, $report['counts']['missing_user_conversations']);
        $this->assertEquals(0, $report['counts']['orphan_messages']);
    }

    public function test_audit_output_contains_no_secrets(): void
    {
        ChatConversation::create([
            'session_token' => 'secret_session_token_12345',
            'user_id' => $this->customer->id,
            'status' => 'ai',
        ]);

        $auditService = new LegacyChatAuditService();
        $report = $auditService->audit();
        $json = json_encode($report);

        $this->assertStringNotContainsString('secret_session_token_12345', $json);
        $this->assertStringNotContainsString('api_key', $json);
        $this->assertStringNotContainsString('password', $json);
    }

    /* =========================================================================
     * 2. CONVERSATION & MESSAGE MAPPING TESTS
     * ========================================================================= */

    public function test_authenticated_legacy_conversation_maps_to_valid_customer(): void
    {
        $legacyConv = ChatConversation::create([
            'session_token' => 'sess_' . Str::random(16),
            'user_id' => $this->customer->id,
            'status' => 'ai',
            'created_at' => Carbon::now()->subDays(5),
            'updated_at' => Carbon::now()->subDays(5)->addMinutes(10),
        ]);

        $msg1 = ChatMessage::create([
            'conversation_id' => $legacyConv->id,
            'sender_type' => 'user',
            'message' => 'Need help tracking order',
            'created_at' => Carbon::now()->subDays(5),
        ]);

        $migrationService = new LegacyChatMigrationService();
        $result = $migrationService->migrate(['apply' => true]);

        $this->assertEquals('completed', $result['status']);
        $this->assertEquals(1, $result['stats']['conversations_created']);
        $this->assertEquals(1, $result['stats']['messages_created']);

        $targetConv = SupportConversation::where('customer_id', $this->customer->id)->first();
        $this->assertNotNull($targetConv);
        $this->assertEquals(ConversationStatus::AI_ACTIVE, $targetConv->status);
        $this->assertEquals(ConversationMode::AI, $targetConv->mode);
        $this->assertTrue($targetConv->ai_active);
        $this->assertFalse($targetConv->human_requested);
        $this->assertEquals('chat_conversations', $targetConv->metadata['legacy_migration']['source_table']);
        $this->assertEquals($legacyConv->id, $targetConv->metadata['legacy_migration']['source_id']);
    }

    public function test_missing_user_reference_becomes_unlinked_safely(): void
    {
        $legacyConv = ChatConversation::create([
            'session_token' => 'sess_' . Str::random(16),
            'user_id' => 77777, // Missing user ID
            'user_name' => 'Ghost User',
            'user_email' => 'ghost@example.com',
            'status' => 'closed',
        ]);

        $migrationService = new LegacyChatMigrationService();
        $result = $migrationService->migrate(['apply' => true]);

        $this->assertEquals('completed', $result['status']);

        $targetConv = SupportConversation::first();
        $this->assertNotNull($targetConv);
        $this->assertNull($targetConv->customer_id);
        $this->assertNotNull($targetConv->guest_session_id);
        $this->assertEquals(77777, $targetConv->metadata['legacy_migration']['broken_user_id']);
        $this->assertEquals('Ghost User', $targetConv->metadata['legacy_migration']['contact_snapshot']['name']);
    }

    public function test_status_and_mode_mapping_for_ai_human_and_closed(): void
    {
        // 1. AI conversation
        $aiConv = ChatConversation::create(['session_token' => 's1', 'status' => 'ai']);
        // 2. Human without agent reply -> queued
        $humanQueuedConv = ChatConversation::create(['session_token' => 's2', 'status' => 'human']);
        ChatMessage::create(['conversation_id' => $humanQueuedConv->id, 'sender_type' => 'user', 'message' => 'Help please']);
        // 3. Human with agent reply -> human_active
        $humanActiveConv = ChatConversation::create(['session_token' => 's3', 'status' => 'human']);
        ChatMessage::create(['conversation_id' => $humanActiveConv->id, 'sender_type' => 'user', 'message' => 'Need human']);
        ChatMessage::create(['conversation_id' => $humanActiveConv->id, 'sender_type' => 'agent', 'sender_id' => $this->agent->id, 'message' => 'Hello, I am taking this.']);
        // 4. Closed conversation
        $closedConv = ChatConversation::create(['session_token' => 's4', 'status' => 'closed']);

        $migrationService = new LegacyChatMigrationService();
        $result = $migrationService->migrate(['apply' => true]);

        $this->assertEquals(4, $result['stats']['conversations_created']);

        $item1 = SupportLegacyMigrationItem::where('source_table', 'chat_conversations')->where('source_id', $aiConv->id)->first();
        $target1 = SupportConversation::find($item1->target_id);
        $this->assertEquals(ConversationStatus::AI_ACTIVE, $target1->status);
        $this->assertEquals(ConversationMode::AI, $target1->mode);

        $item2 = SupportLegacyMigrationItem::where('source_table', 'chat_conversations')->where('source_id', $humanQueuedConv->id)->first();
        $target2 = SupportConversation::find($item2->target_id);
        $this->assertEquals(ConversationStatus::QUEUED, $target2->status);
        $this->assertEquals(ConversationMode::HUMAN, $target2->mode);
        $this->assertTrue($target2->human_requested);

        $item3 = SupportLegacyMigrationItem::where('source_table', 'chat_conversations')->where('source_id', $humanActiveConv->id)->first();
        $target3 = SupportConversation::find($item3->target_id);
        $this->assertEquals(ConversationStatus::HUMAN_ACTIVE, $target3->status);
        $this->assertEquals(ConversationMode::HUMAN, $target3->mode);

        $item4 = SupportLegacyMigrationItem::where('source_table', 'chat_conversations')->where('source_id', $closedConv->id)->first();
        $target4 = SupportConversation::find($item4->target_id);
        $this->assertEquals(ConversationStatus::CLOSED, $target4->status);
    }

    public function test_message_sender_mapping_user_ai_agent_admin(): void
    {
        $conv = ChatConversation::create([
            'session_token' => 'sess_multi',
            'user_id' => $this->customer->id,
            'status' => 'closed',
        ]);

        $m1 = ChatMessage::create(['conversation_id' => $conv->id, 'sender_type' => 'user', 'message' => 'User text']);
        $m2 = ChatMessage::create(['conversation_id' => $conv->id, 'sender_type' => 'ai', 'message' => 'AI text']);
        $m3 = ChatMessage::create(['conversation_id' => $conv->id, 'sender_type' => 'agent', 'sender_id' => $this->agent->id, 'message' => 'Agent text']);
        $m4 = ChatMessage::create(['conversation_id' => $conv->id, 'sender_type' => 'admin', 'sender_id' => $this->agent->id, 'message' => 'Admin text']);

        $migrationService = new LegacyChatMigrationService();
        $migrationService->migrate(['apply' => true]);

        $targetMessages = SupportMessage::orderBy('id', 'asc')->get();
        $this->assertCount(4, $targetMessages);

        $this->assertEquals(SenderType::CUSTOMER, $targetMessages[0]->sender_type);
        $this->assertEquals($this->customer->id, $targetMessages[0]->sender_id);
        $this->assertEquals('User text', $targetMessages[0]->content);
        $this->assertFalse($targetMessages[0]->is_internal);

        $this->assertEquals(SenderType::AI, $targetMessages[1]->sender_type);
        $this->assertNull($targetMessages[1]->sender_id);
        $this->assertEquals('AI text', $targetMessages[1]->content);

        $this->assertEquals(SenderType::AGENT, $targetMessages[2]->sender_type);
        $this->assertEquals($this->agent->id, $targetMessages[2]->sender_id);

        $this->assertEquals(SenderType::AGENT, $targetMessages[3]->sender_type);
        $this->assertEquals($this->agent->id, $targetMessages[3]->sender_id);
    }

    /* =========================================================================
     * 3. IDEMPOTENCY & DELTA MIGRATION TESTS
     * ========================================================================= */

    public function test_dry_run_does_not_write_records(): void
    {
        $conv = ChatConversation::create(['session_token' => 'dry_sess', 'status' => 'ai']);
        ChatMessage::create(['conversation_id' => $conv->id, 'sender_type' => 'user', 'message' => 'Dry run msg']);

        $migrationService = new LegacyChatMigrationService();
        $result = $migrationService->migrate(['apply' => false]);

        $this->assertEquals('dry_run', $result['mode']);
        $this->assertEquals(1, $result['stats']['conversations_created']);
        $this->assertEquals(1, $result['stats']['messages_created']);

        // Verify zero target support records written
        $this->assertEquals(0, SupportConversation::count());
        $this->assertEquals(0, SupportMessage::count());
        $this->assertEquals(0, SupportLegacyMigrationItem::count());
    }

    public function test_second_identical_run_creates_zero_duplicates(): void
    {
        $conv = ChatConversation::create(['session_token' => 'sess_idemp', 'status' => 'ai']);
        ChatMessage::create(['conversation_id' => $conv->id, 'sender_type' => 'user', 'message' => 'Idempotent msg']);

        $migrationService = new LegacyChatMigrationService();

        // Pass 1: Apply
        $result1 = $migrationService->migrate(['apply' => true]);
        $this->assertEquals(1, $result1['stats']['conversations_created']);
        $this->assertEquals(1, $result1['stats']['messages_created']);
        $this->assertEquals(1, SupportConversation::count());
        $this->assertEquals(1, SupportMessage::count());

        // Pass 2: Re-apply identical dataset
        $result2 = $migrationService->migrate(['apply' => true]);
        $this->assertEquals(0, $result2['stats']['conversations_created']);
        $this->assertEquals(0, $result2['stats']['messages_created']);
        $this->assertEquals(1, $result2['stats']['conversations_skipped']);
        $this->assertEquals(1, $result2['stats']['messages_skipped']);

        // Strict count assertions
        $this->assertEquals(1, SupportConversation::count());
        $this->assertEquals(1, SupportMessage::count());
    }

    public function test_delta_run_appends_new_legacy_messages_safely(): void
    {
        $conv = ChatConversation::create(['session_token' => 'sess_delta', 'status' => 'ai']);
        $m1 = ChatMessage::create(['conversation_id' => $conv->id, 'sender_type' => 'user', 'message' => 'Initial msg']);

        $migrationService = new LegacyChatMigrationService();
        $migrationService->migrate(['apply' => true]);
        $this->assertEquals(1, SupportMessage::count());

        // Simulate new message arriving in legacy chat after initial migration
        $m2 = ChatMessage::create(['conversation_id' => $conv->id, 'sender_type' => 'ai', 'message' => 'New delta msg']);

        // Run delta migration
        $deltaResult = $migrationService->migrate(['apply' => true]);
        $this->assertEquals(1, $deltaResult['stats']['conversations_updated']);
        $this->assertEquals(1, $deltaResult['stats']['messages_created']);
        $this->assertEquals(1, $deltaResult['stats']['messages_skipped']);

        $this->assertEquals(1, SupportConversation::count());
        $this->assertEquals(2, SupportMessage::count());
    }

    /* =========================================================================
     * 4. CONFIGURATION MIGRATION TESTS
     * ========================================================================= */

    public function test_configuration_migration_maps_provider_only_when_target_unset(): void
    {
        Settings::group('site')->set('site_chat_ai_agent', 'gemini');
        Settings::group('site')->set('site_chat_ai_model', 'gemini-1.5-pro');
        Settings::group('site')->set('site_default_ai_agent', null);

        $result = LegacyConfigurationMapper::migrate(true);

        $this->assertTrue($result['migrated']);
        $this->assertEquals('site_default_ai_agent', $result['migrated_key']);

        $geminiAgent = AiAgent::where('slug', 'gemini')->first();
        $this->assertEquals($geminiAgent->id, Settings::group('site')->get('site_default_ai_agent'));
    }

    public function test_configuration_does_not_overwrite_existing_target_setting(): void
    {
        $openRouterAgent = AiAgent::where('slug', 'openrouter')->first();
        Settings::group('site')->set('site_default_ai_agent', $openRouterAgent->id);
        Settings::group('site')->set('site_chat_ai_agent', 'gemini');

        $result = LegacyConfigurationMapper::migrate(true);

        $this->assertFalse($result['migrated']);
        // Verify setting was preserved
        $this->assertEquals($openRouterAgent->id, Settings::group('site')->get('site_default_ai_agent'));
    }

    /* =========================================================================
     * 5. VERIFICATION SERVICE TESTS
     * ========================================================================= */

    public function test_verification_passes_on_complete_parity(): void
    {
        $conv = ChatConversation::create([
            'session_token' => 'sess_verify',
            'user_id' => $this->customer->id,
            'status' => 'closed',
        ]);
        ChatMessage::create(['conversation_id' => $conv->id, 'sender_type' => 'user', 'message' => 'Verify me']);

        $migrationService = new LegacyChatMigrationService();
        $runResult = $migrationService->migrate(['apply' => true]);

        $verifier = new LegacyMigrationVerificationService();
        $vResult = $verifier->verify($runResult['run_id']);

        $this->assertTrue($vResult['passed']);
        $this->assertEquals(1, $vResult['checked']['conversations']);
        $this->assertEquals(1, $vResult['checked']['messages']);
        $this->assertEquals(0, $vResult['mismatch_count']);
    }

    /* =========================================================================
     * 6. ROLLBACK TESTS
     * ========================================================================= */

    public function test_untouched_migrated_records_roll_back_safely(): void
    {
        $conv = ChatConversation::create(['session_token' => 'sess_rb', 'status' => 'ai']);
        ChatMessage::create(['conversation_id' => $conv->id, 'sender_type' => 'user', 'message' => 'Rollback msg']);

        $migrationService = new LegacyChatMigrationService();
        $runResult = $migrationService->migrate(['apply' => true]);

        $this->assertEquals(1, SupportConversation::count());
        $this->assertEquals(1, SupportMessage::count());

        $rollbackService = new LegacyMigrationRollbackService();
        $rbResult = $rollbackService->rollback($runResult['run_id']);

        $this->assertTrue($rbResult['success']);
        $this->assertEquals(1, $rbResult['rolled_back']['conversations']);
        $this->assertEquals(1, $rbResult['rolled_back']['messages']);

        // Target records deleted
        $this->assertEquals(0, SupportConversation::count());
        $this->assertEquals(0, SupportMessage::count());

        // Legacy source rows remain 100% intact
        $this->assertEquals(1, ChatConversation::count());
        $this->assertEquals(1, ChatMessage::count());
    }

    public function test_subsequent_support_message_blocks_rollback(): void
    {
        $conv = ChatConversation::create(['session_token' => 'sess_rb_block', 'status' => 'ai']);
        ChatMessage::create(['conversation_id' => $conv->id, 'sender_type' => 'user', 'message' => 'Old msg']);

        $migrationService = new LegacyChatMigrationService();
        $runResult = $migrationService->migrate(['apply' => true]);

        $targetConv = SupportConversation::first();

        // Simulate subsequent new Support message added after migration
        SupportMessage::create([
            'conversation_id' => $targetConv->id,
            'sender_type' => SenderType::AGENT,
            'message_type' => MessageType::TEXT,
            'content' => 'New live agent support response',
            'is_internal' => false,
            'is_read' => true,
        ]);

        $rollbackService = new LegacyMigrationRollbackService();
        $rbResult = $rollbackService->rollback($runResult['run_id']);

        $this->assertFalse($rbResult['success']);
        $this->assertStringContainsString('Rollback blocked', $rbResult['message']);
        $this->assertEquals(1, SupportConversation::count());
    }

    /* =========================================================================
     * 7. SECURITY & CUSTOMER ISOLATION TESTS
     * ========================================================================= */

    public function test_migrated_customer_conversation_cannot_be_accessed_by_other_customer(): void
    {
        $conv = ChatConversation::create([
            'session_token' => 'sess_iso',
            'user_id' => $this->customer->id,
            'status' => 'closed',
        ]);
        ChatMessage::create(['conversation_id' => $conv->id, 'sender_type' => 'user', 'message' => 'Private text']);

        $migrationService = new LegacyChatMigrationService();
        $migrationService->migrate(['apply' => true]);

        $targetConv = SupportConversation::first();

        // Customer 1 can access
        $res1 = $this->actingAs($this->customer, 'sanctum')
            ->getJson("/api/v1/support/conversations/{$targetConv->public_id}");
        $res1->assertStatus(200);

        // Customer 2 is forbidden (404/403)
        $res2 = $this->actingAs($this->otherCustomer, 'sanctum')
            ->getJson("/api/v1/support/conversations/{$targetConv->public_id}");
        $res2->assertStatus(404);
    }

    public function test_customer_detail_resource_does_not_leak_migration_pii_or_session_hashes(): void
    {
        $conv = ChatConversation::create([
            'session_token' => 'super_secret_browser_session_hash',
            'user_id' => null,
            'user_name' => 'Guest PII Name',
            'user_email' => 'guest_pii@example.com',
            'status' => 'ai',
        ]);

        $migrationService = new LegacyChatMigrationService();
        $migrationService->migrate(['apply' => true]);

        $targetConv = SupportConversation::first();

        $res = $this->withHeader('X-Guest-Token', $targetConv->guest_session_id)
            ->getJson("/api/v1/support/conversations/{$targetConv->public_id}");

        $res->assertStatus(200);
        $content = $res->getContent();

        $this->assertStringNotContainsString('super_secret_browser_session_hash', $content);
        $this->assertStringNotContainsString('guest_pii@example.com', $content);
    }

    public function test_rollback_blocks_when_support_ticket_or_voice_session_attached(): void
    {
        $conv = ChatConversation::create(['session_token' => 'sess_tkt_rb', 'status' => 'ai']);
        ChatMessage::create(['conversation_id' => $conv->id, 'sender_type' => 'user', 'message' => 'Help ticket']);

        $migrationService = new LegacyChatMigrationService();
        $runResult = $migrationService->migrate(['apply' => true]);

        $targetConv = SupportConversation::first();

        $dept = \App\Support\Models\SupportDepartment::first();

        // Attach a SupportTicket
        \App\Support\Models\SupportTicket::create([
            'conversation_id' => $targetConv->id,
            'customer_id' => null,
            'department_id' => $dept?->id,
            'ticket_number' => 'TCK-TEST-001',
            'subject' => 'Issue',
            'description' => 'Test',
            'status' => \App\Support\Enums\TicketStatus::OPEN,
            'priority' => \App\Support\Enums\SupportPriority::NORMAL,
        ]);

        $rollbackService = new LegacyMigrationRollbackService();
        $rbResult = $rollbackService->rollback($runResult['run_id']);

        $this->assertFalse($rbResult['success']);
        $this->assertStringContainsString('SupportTicket', $rbResult['blockers'][0]);
        $this->assertEquals(1, SupportConversation::count());
    }

    public function test_legacy_classes_and_tables_remain_present_and_coexistent(): void
    {
        $this->assertTrue(class_exists(\App\Http\Controllers\Frontend\ChatController::class));
        $this->assertTrue(class_exists(\App\Http\Controllers\Admin\AdminChatController::class));
        $this->assertTrue(class_exists(\App\Services\ChatService::class));
        $this->assertTrue(class_exists(\App\Models\ChatConversation::class));
        $this->assertTrue(class_exists(\App\Models\ChatMessage::class));

        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('chat_conversations'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('chat_messages'));
    }
}
