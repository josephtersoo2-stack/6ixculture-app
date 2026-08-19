<?php

namespace Tests\Feature\Support;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use App\Support\Cutover\SupportCutoverManager;
use App\Support\Cutover\SupportReadinessService;
use App\Support\Enums\ConversationStatus;
use App\Support\Enums\SupportPriority;
use App\Support\Enums\TicketStatus;
use App\Support\Models\SupportAuditLog;
use App\Support\Models\SupportConversation;
use App\Support\Models\SupportDepartment;
use App\Support\Models\SupportMessage;
use App\Support\Models\SupportTicket;
use Carbon\Carbon;
use Database\Seeders\SupportDomainSeeder;
use Dipokhalder\Settings\Facades\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupportPhase10CutoverTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected User $agent;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        putenv('OPENROUTER_API_KEY=test_openrouter_sk_key');
        putenv('GEMINI_API_KEY=test_gemini_api_key');

        $this->seed(SupportDomainSeeder::class);

        $openRouter = \App\Models\AiAgent::firstOrCreate(['slug' => 'openrouter'], ['name' => 'OpenRouter', 'status' => 5]);
        \App\Models\AiAgent::firstOrCreate(['slug' => 'gemini'], ['name' => 'Gemini', 'status' => 5]);
        Settings::group('site')->set('site_default_ai_agent', $openRouter->id);

        $this->customer = User::factory()->create([
            'username' => 'testcustomer_' . Str::random(6),
            'email' => 'customer@example.com',
        ]);

        $this->agent = User::factory()->create([
            'username' => 'supportagent_' . Str::random(6),
            'email' => 'agent@example.com',
        ]);

        $this->admin = User::factory()->create([
            'username' => 'admin_' . Str::random(6),
            'email' => 'admin@example.com',
        ]);

        // Default to legacy cutover state
        Settings::group('support')->set('cutover_state', SupportCutoverManager::STATE_LEGACY);
    }

    public function test_cutover_manager_defaults_to_legacy_state(): void
    {
        $this->assertEquals(SupportCutoverManager::STATE_LEGACY, SupportCutoverManager::getState());
        $this->assertTrue(SupportCutoverManager::isLegacy());
        $this->assertFalse(SupportCutoverManager::isDraining());
        $this->assertFalse(SupportCutoverManager::isSupport());
        $this->assertTrue(SupportCutoverManager::canMutateLegacy());
    }

    public function test_cutover_manager_transitions_legacy_to_draining_and_persists(): void
    {
        $result = SupportCutoverManager::enterDraining($this->admin->id);

        $this->assertTrue($result['success']);
        $this->assertEquals(SupportCutoverManager::STATE_DRAINING, SupportCutoverManager::getState());
        $this->assertTrue(SupportCutoverManager::isDraining());
        $this->assertFalse(SupportCutoverManager::canMutateLegacy());

        // Audit log created
        $audit = SupportAuditLog::where('action', 'support_cutover_entered_draining')->first();
        $this->assertNotNull($audit);
        $this->assertEquals($this->admin->id, $audit->actor_id);
    }

    public function test_cutover_manager_activates_support_with_delta_and_verification(): void
    {
        // Seed legacy data
        $conv = ChatConversation::create(['session_token' => 'sess_cutover_1', 'status' => 'ai']);
        ChatMessage::create(['conversation_id' => $conv->id, 'sender_type' => 'user', 'message' => 'Hello']);

        $result = SupportCutoverManager::activateSupport($this->admin->id);

        $this->assertTrue($result['success']);
        $this->assertEquals(SupportCutoverManager::STATE_SUPPORT, SupportCutoverManager::getState());
        $this->assertTrue(SupportCutoverManager::isSupport());
        $this->assertFalse(SupportCutoverManager::canMutateLegacy());

        // Target record was migrated
        $this->assertEquals(1, SupportConversation::count());
        $this->assertEquals(1, SupportMessage::count());

        // Audit log created
        $audit = SupportAuditLog::where('action', 'support_cutover_activated')->first();
        $this->assertNotNull($audit);
    }

    public function test_legacy_customer_send_message_blocked_in_draining_mode(): void
    {
        SupportCutoverManager::enterDraining();

        $response = $this->postJson('/api/chat/send', [
            'message' => 'Test message',
            'session_token' => 'sess_test',
        ]);

        $response->assertStatus(423);
        $response->assertJson([
            'status' => false,
            'code' => 'LEGACY_CHAT_LOCKED',
            'cutover_state' => 'draining',
        ]);
    }

    public function test_legacy_customer_request_human_blocked_in_draining_mode(): void
    {
        SupportCutoverManager::enterDraining();

        $response = $this->postJson('/api/chat/request-human', [
            'session_token' => 'sess_test',
        ]);

        $response->assertStatus(423);
        $response->assertJson([
            'status' => false,
            'code' => 'LEGACY_CHAT_LOCKED',
            'cutover_state' => 'draining',
        ]);
    }

    public function test_legacy_admin_reply_blocked_in_draining_mode(): void
    {
        $conv = ChatConversation::create(['session_token' => 'sess_admin_block', 'status' => 'human']);

        SupportCutoverManager::enterDraining();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/chat/reply/{$conv->id}", [
                'message' => 'Admin reply',
            ]);

        $response->assertStatus(423);
        $response->assertJson([
            'status' => false,
            'code' => 'LEGACY_CHAT_LOCKED',
        ]);
    }

    public function test_legacy_admin_status_update_blocked_in_draining_mode(): void
    {
        $conv = ChatConversation::create(['session_token' => 'sess_admin_status', 'status' => 'human']);

        SupportCutoverManager::enterDraining();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/chat/update-status/{$conv->id}", [
                'status' => 'closed',
            ]);

        $response->assertStatus(423);
        $response->assertJson([
            'status' => false,
            'code' => 'LEGACY_CHAT_LOCKED',
        ]);
    }

    public function test_legacy_admin_destroy_blocked_in_draining_mode(): void
    {
        $conv = ChatConversation::create(['session_token' => 'sess_admin_del', 'status' => 'human']);

        SupportCutoverManager::enterDraining();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/chat/{$conv->id}");

        $response->assertStatus(423);
        $response->assertJson([
            'status' => false,
            'code' => 'LEGACY_CHAT_LOCKED',
        ]);
    }

    public function test_legacy_customer_and_admin_mutations_blocked_in_support_mode(): void
    {
        SupportCutoverManager::activateSupport();

        $res1 = $this->postJson('/api/chat/send', ['message' => 'Hello']);
        $res1->assertStatus(423);
        $res1->assertJson(['cutover_state' => 'support']);

        $res2 = $this->postJson('/api/frontend/chat/send', ['message' => 'Hello']);
        $res2->assertStatus(423);
    }

    public function test_legacy_history_read_remains_available_in_draining_and_support(): void
    {
        $conv = ChatConversation::create(['session_token' => 'sess_read_ok', 'status' => 'ai']);
        ChatMessage::create(['conversation_id' => $conv->id, 'sender_type' => 'user', 'message' => 'Existing legacy msg']);

        // In draining mode
        SupportCutoverManager::enterDraining();
        $resDrain = $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/chat');
        $resDrain->assertStatus(200);
        $resDrain->assertJson(['status' => true]);

        // In support mode
        SupportCutoverManager::activateSupport();
        $resSupport = $this->actingAs($this->admin, 'sanctum')->getJson("/api/admin/chat/show/{$conv->id}");
        $resSupport->assertStatus(200);
        $resSupport->assertJson(['status' => true]);
    }

    public function test_support_api_canonical_and_functional_in_support_mode(): void
    {
        SupportCutoverManager::activateSupport();

        // Customer conversation endpoint works
        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v1/support/conversations', [
                'language' => 'en',
            ]);

        $this->assertContains($response->status(), [200, 201]);
        $convPublicId = $response->json('data.conversation.id');
        $this->assertNotEmpty($convPublicId);

        // Send message to new Support domain
        $msgRes = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/support/conversations/{$convPublicId}/messages", [
                'message' => 'Canonical support query',
            ]);

        $msgRes->assertStatus(200);
        $this->assertDatabaseHas('support_messages', [
            'content' => 'Canonical support query',
        ]);
    }

    public function test_rollback_allowed_when_zero_post_cutover_activity(): void
    {
        SupportCutoverManager::activateSupport();
        $this->assertTrue(SupportCutoverManager::isSupport());

        $rollback = SupportCutoverManager::rollback();
        $this->assertTrue($rollback['success']);
        $this->assertEquals(SupportCutoverManager::STATE_LEGACY, SupportCutoverManager::getState());
        $this->assertTrue(SupportCutoverManager::canMutateLegacy());
    }

    public function test_rollback_blocked_fail_closed_when_post_cutover_support_conversations_exist(): void
    {
        SupportCutoverManager::activateSupport();

        // Create a new post-cutover conversation
        SupportConversation::create([
            'customer_id' => $this->customer->id,
            'status' => ConversationStatus::AI_ACTIVE,
        ]);

        $rollback = SupportCutoverManager::rollback();

        $this->assertFalse($rollback['success']);
        $this->assertStringContainsString('Rollback blocked', $rollback['message']);
        $this->assertNotEmpty($rollback['blockers']);
        $this->assertEquals(SupportCutoverManager::STATE_SUPPORT, SupportCutoverManager::getState());
    }

    public function test_rollback_blocked_fail_closed_when_post_cutover_support_tickets_exist(): void
    {
        SupportCutoverManager::activateSupport();

        $conv = SupportConversation::create([
            'customer_id' => $this->customer->id,
            'status' => ConversationStatus::HUMAN_ACTIVE,
        ]);

        $dept = SupportDepartment::first();

        SupportTicket::create([
            'conversation_id' => $conv->id,
            'department_id' => $dept?->id,
            'ticket_number' => 'TCK-CUTOVER-001',
            'subject' => 'Post cutover ticket',
            'description' => 'Test',
            'status' => TicketStatus::OPEN,
            'priority' => SupportPriority::NORMAL,
        ]);

        $rollback = SupportCutoverManager::rollback();

        $this->assertFalse($rollback['success']);
        $this->assertStringContainsString('Support tickets created post-cutover', $rollback['blockers'][1] ?? $rollback['blockers'][0]);
        $this->assertEquals(SupportCutoverManager::STATE_SUPPORT, SupportCutoverManager::getState());
    }

    public function test_support_readiness_service_returns_sanitized_metrics_without_secrets(): void
    {
        $service = new SupportReadinessService();
        $readiness = $service->getReadiness();

        $this->assertEquals('ready', $readiness['status']);
        $this->assertTrue($readiness['infrastructure']['support_tables_ready']);
        $this->assertTrue($readiness['governance']['ready']);
        $this->assertGreaterThanOrEqual(1, $readiness['governance']['active_departments']);

        $jsonContent = json_encode($readiness);
        $this->assertStringNotContainsString('test_openrouter_sk_key', $jsonContent);
        $this->assertStringNotContainsString('test_gemini_api_key', $jsonContent);
    }

    public function test_legacy_classes_and_tables_remain_preserved(): void
    {
        $this->assertTrue(class_exists(\App\Http\Controllers\Frontend\ChatController::class));
        $this->assertTrue(class_exists(\App\Http\Controllers\Admin\AdminChatController::class));
        $this->assertTrue(class_exists(\App\Services\ChatService::class));
        $this->assertTrue(class_exists(\App\Models\ChatConversation::class));
        $this->assertTrue(class_exists(\App\Models\ChatMessage::class));
    }

    public function test_artisan_support_cutover_command_workflow(): void
    {
        // 1. Status
        $this->artisan('support:cutover', ['--status' => true])
            ->assertExitCode(0);

        // 2. Preflight
        $this->artisan('support:cutover', ['--preflight' => true])
            ->assertExitCode(0);

        // 3. Enter Draining
        $this->artisan('support:cutover', ['--enter-draining' => true])
            ->assertExitCode(0);
        $this->assertEquals(SupportCutoverManager::STATE_DRAINING, SupportCutoverManager::getState());

        // 4. Activate Support
        $this->artisan('support:cutover', ['--activate-support' => true])
            ->assertExitCode(0);
        $this->assertEquals(SupportCutoverManager::STATE_SUPPORT, SupportCutoverManager::getState());

        // 5. Rollback (allowed because no post-cutover activity)
        $this->artisan('support:cutover', ['--rollback' => true])
            ->assertExitCode(0);
        $this->assertEquals(SupportCutoverManager::STATE_LEGACY, SupportCutoverManager::getState());
    }
}
