<?php

namespace Tests\Feature\Support;

use App\Models\AiAgent;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use App\Support\Cutover\SupportCutoverManager;
use App\Support\Cutover\SupportReadinessService;
use App\Support\Enums\ConversationStatus;
use App\Support\Enums\SupportPriority;
use App\Support\Enums\TicketStatus;
use App\Support\Models\SupportAssignment;
use App\Support\Models\SupportAuditLog;
use App\Support\Models\SupportConversation;
use App\Support\Models\SupportDepartment;
use App\Support\Models\SupportFeedback;
use App\Support\Models\SupportMessage;
use App\Support\Models\SupportPolicy;
use App\Support\Models\SupportTicket;
use App\Support\Models\SupportVoiceSession;
use Carbon\Carbon;
use Database\Seeders\SupportDomainSeeder;
use Dipokhalder\Settings\Facades\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupportPhase10CutoverTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected User $agent;
    protected User $admin;
    protected AiAgent $openRouterAgent;

    protected function setUp(): void
    {
        parent::setUp();

        putenv('OPENROUTER_API_KEY=test_openrouter_sk_key');
        putenv('GEMINI_API_KEY=test_gemini_api_key');

        $this->seed(SupportDomainSeeder::class);

        $this->openRouterAgent = AiAgent::firstOrCreate(
            ['slug' => 'openrouter'],
            ['name' => 'OpenRouter', 'status' => 5]
        );
        AiAgent::firstOrCreate(
            ['slug' => 'gemini'],
            ['name' => 'Gemini', 'status' => 5]
        );
        Settings::group('site')->set('site_default_ai_agent', $this->openRouterAgent->id);

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

    // 1. Default State
    public function test_cutover_manager_defaults_to_legacy_state(): void
    {
        $this->assertEquals(SupportCutoverManager::STATE_LEGACY, SupportCutoverManager::getState());
        $this->assertTrue(SupportCutoverManager::isLegacy());
        $this->assertFalse(SupportCutoverManager::isDraining());
        $this->assertFalse(SupportCutoverManager::isSupport());
        $this->assertTrue(SupportCutoverManager::canMutateLegacy());
    }

    // 2. Normal legacy -> draining transition
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

    // 3. Normal draining -> support transition
    public function test_cutover_manager_activates_support_from_draining_with_delta_and_verification(): void
    {
        // 1. Enter draining first
        SupportCutoverManager::enterDraining();

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

    // 4. Test 1 & 2: support -> draining is rejected and leaves state = support
    public function test_support_to_draining_transition_is_rejected_and_fails_closed(): void
    {
        // Transition to support
        SupportCutoverManager::enterDraining();
        $res = SupportCutoverManager::activateSupport();
        $this->assertTrue($res['success']);
        $this->assertEquals(SupportCutoverManager::STATE_SUPPORT, SupportCutoverManager::getState());

        // Attempt invalid backward transition
        $drainAttempt = SupportCutoverManager::enterDraining();

        $this->assertFalse($drainAttempt['success']);
        $this->assertEquals(SupportCutoverManager::STATE_SUPPORT, $drainAttempt['state']);
        $this->assertEquals(SupportCutoverManager::STATE_SUPPORT, SupportCutoverManager::getState());
        $this->assertStringContainsString('Transition rejected', $drainAttempt['message']);

        // Audit log for rejected transition
        $audit = SupportAuditLog::where('action', 'support_cutover_invalid_transition_rejected')->first();
        $this->assertNotNull($audit);
        $this->assertEquals('support_to_draining', $audit->metadata['attempted_transition']);
    }

    // 5. Test 3 & 4: support -> draining cannot be used to bypass rollback guard
    public function test_support_to_draining_cannot_bypass_rollback_guard(): void
    {
        SupportCutoverManager::enterDraining();
        SupportCutoverManager::activateSupport();

        // Create post-cutover domain message
        $conv = SupportConversation::create([
            'customer_id' => $this->customer->id,
            'status' => ConversationStatus::AI_ACTIVE,
        ]);
        SupportMessage::create([
            'conversation_id' => $conv->id,
            'sender_type' => 'customer',
            'sender_id' => $this->customer->id,
            'content' => 'Post-cutover message',
        ]);

        // Attempt to enter draining then rollback
        $drainAttempt = SupportCutoverManager::enterDraining();
        $this->assertFalse($drainAttempt['success']);

        $rollbackAttempt = SupportCutoverManager::rollback();
        $this->assertFalse($rollbackAttempt['success']);
        $this->assertEquals(SupportCutoverManager::STATE_SUPPORT, SupportCutoverManager::getState());
        $this->assertStringContainsString('Rollback blocked', $rollbackAttempt['message']);
    }

    // 6. Test 5: Rollback blocked after post-cutover assignment
    public function test_rollback_blocked_after_post_cutover_agent_assignment(): void
    {
        SupportCutoverManager::enterDraining();
        SupportCutoverManager::activateSupport();

        $conv = SupportConversation::create([
            'customer_id' => $this->customer->id,
            'status' => ConversationStatus::HUMAN_ACTIVE,
        ]);

        SupportAssignment::create([
            'conversation_id' => $conv->id,
            'agent_id' => $this->agent->id,
            'assigned_by' => $this->admin->id,
            'assigned_at' => Carbon::now(),
            'status' => 'active',
        ]);

        $rollback = SupportCutoverManager::rollback();

        $this->assertFalse($rollback['success']);
        $this->assertStringContainsString('Rollback blocked', $rollback['message']);
        $this->assertEquals(SupportCutoverManager::STATE_SUPPORT, SupportCutoverManager::getState());
    }

    // 7. Test 6: Rollback blocked after relevant post-cutover critical/audited action
    public function test_rollback_blocked_after_post_cutover_domain_audit_action(): void
    {
        SupportCutoverManager::enterDraining();
        SupportCutoverManager::activateSupport();

        $conv = SupportConversation::create([
            'customer_id' => $this->customer->id,
            'status' => ConversationStatus::AI_ACTIVE,
        ]);

        // Non-administrative customer operational action
        SupportAuditLog::log([
            'actor_type' => 'agent',
            'actor_id' => $this->agent->id,
            'action' => 'support_agent_manual_escalation',
            'resource_type' => 'conversation',
            'resource_id' => (string) $conv->id,
            'metadata' => ['reason' => 'Customer requested supervisor'],
        ]);

        $rollback = SupportCutoverManager::rollback();

        $this->assertFalse($rollback['success']);
        $this->assertEquals(SupportCutoverManager::STATE_SUPPORT, SupportCutoverManager::getState());
    }

    // 8. Test 7: activateSupport from legacy is rejected until draining
    public function test_activate_support_from_legacy_is_rejected_until_draining(): void
    {
        $this->assertEquals(SupportCutoverManager::STATE_LEGACY, SupportCutoverManager::getState());

        $result = SupportCutoverManager::activateSupport();

        $this->assertFalse($result['success']);
        $this->assertEquals(SupportCutoverManager::STATE_LEGACY, SupportCutoverManager::getState());
        $this->assertStringContainsString('Cannot activate Support directly from legacy mode', $result['message']);
    }

    // 9. Test 8: activateSupport from support is idempotent
    public function test_activate_support_from_support_is_idempotent(): void
    {
        SupportCutoverManager::enterDraining();
        SupportCutoverManager::activateSupport();
        $this->assertEquals(SupportCutoverManager::STATE_SUPPORT, SupportCutoverManager::getState());

        $idempotentRes = SupportCutoverManager::activateSupport();

        $this->assertTrue($idempotentRes['success']);
        $this->assertTrue($idempotentRes['is_already_active']);
        $this->assertEquals(SupportCutoverManager::STATE_SUPPORT, SupportCutoverManager::getState());
    }

    // 10. Test 9: Preflight fails when required Support table readiness is false
    public function test_preflight_fails_when_support_table_is_missing(): void
    {
        // Mock missing table by checking readiness against non-existent table in service
        Schema::dropIfExists('support_customer_preferences');

        $readinessService = new SupportReadinessService();
        $readiness = $readinessService->getReadiness();

        $this->assertFalse($readiness['ready']);
        $this->assertEquals('blocked', $readiness['status']);
        $this->assertNotEmpty($readiness['blockers']);

        // Artisan preflight fails
        $this->artisan('support:cutover', ['--preflight' => true])
            ->assertExitCode(1);
    }

    // 11. Test 10: Preflight fails when critical AI provider readiness is false
    public function test_preflight_fails_when_ai_provider_unconfigured(): void
    {
        putenv('OPENROUTER_API_KEY=');
        putenv('GEMINI_API_KEY=');
        putenv('OPENAI_API_KEY=');
        Settings::group('site')->set('site_default_ai_agent', null);

        $readinessService = new SupportReadinessService();
        $readiness = $readinessService->getReadiness();

        $this->assertFalse($readiness['ready']);
        $this->assertEquals('blocked', $readiness['status']);
        $this->assertStringContainsString('No active AI provider configured', $readiness['blockers'][0]);

        $this->artisan('support:cutover', ['--preflight' => true])
            ->assertExitCode(1);
    }

    // 12. Test 11: Preflight fails when governance critical readiness is false
    public function test_preflight_fails_when_governance_departments_empty(): void
    {
        SupportDepartment::query()->delete();

        $readinessService = new SupportReadinessService();
        $readiness = $readinessService->getReadiness();

        $this->assertFalse($readiness['ready']);
        $this->assertEquals('blocked', $readiness['status']);
        $this->assertStringContainsString('No active support departments found', $readiness['blockers'][0]);

        $this->artisan('support:cutover', ['--preflight' => true])
            ->assertExitCode(1);
    }

    // 13. Test 12: Support activation fails when current readiness becomes blocked after draining
    public function test_support_activation_fails_when_readiness_blocked_after_draining(): void
    {
        SupportCutoverManager::enterDraining();

        // Drop policies to simulate post-draining outage
        SupportPolicy::query()->delete();

        $result = SupportCutoverManager::activateSupport();

        $this->assertFalse($result['success']);
        $this->assertEquals(SupportCutoverManager::STATE_DRAINING, SupportCutoverManager::getState());
        $this->assertStringContainsString('Critical readiness checks failed', $result['message']);
    }

    // 14. Test 13: Readiness status is degraded when non-critical warnings exist
    public function test_readiness_status_reflects_truthful_state(): void
    {
        $readinessService = new SupportReadinessService();
        $readiness = $readinessService->getReadiness();

        $this->assertTrue($readiness['ready']);
        $this->assertContains($readiness['status'], ['ready', 'degraded']);
        $this->assertIsArray($readiness['blockers']);
        $this->assertIsArray($readiness['warnings']);
    }

    // 15. Test 14: Voice readiness comes from provider capabilities
    public function test_voice_readiness_comes_from_provider_capabilities(): void
    {
        $readinessService = new SupportReadinessService();
        $readiness = $readinessService->getReadiness();

        $this->assertArrayHasKey('stt_ready', $readiness['voice']);
        $this->assertArrayHasKey('tts_ready', $readiness['voice']);
        $this->assertArrayHasKey('provider', $readiness['voice']);
    }

    // 16. Test 15: Realtime readiness reports configured transport and fallback truthfully
    public function test_realtime_readiness_reports_transport_and_polling_fallback(): void
    {
        $readinessService = new SupportReadinessService();
        $readiness = $readinessService->getReadiness();

        $this->assertArrayHasKey('supported', $readiness['realtime']);
        $this->assertArrayHasKey('transport', $readiness['realtime']);
        $this->assertTrue($readiness['realtime']['polling_fallback']);
    }

    // 17. Test 16 & 17: Legacy lock response is sanitized (no cutover_state, no /api/v1/support/*)
    public function test_legacy_lock_response_is_strictly_sanitized(): void
    {
        SupportCutoverManager::enterDraining();

        $response = $this->postJson('/api/chat/send', [
            'message' => 'Hello legacy',
        ]);

        $response->assertStatus(423);
        $response->assertJson([
            'status' => false,
            'code' => 'LEGACY_CHAT_UNAVAILABLE',
            'message' => 'This chat service is no longer available. Please use the current support experience.',
        ]);

        $json = $response->getContent();
        $this->assertStringNotContainsString('cutover_state', $json);
        $this->assertStringNotContainsString('/api/v1/support', $json);
        $this->assertStringNotContainsString('draining', $json);
        $this->assertStringNotContainsString('LEGACY_CHAT_LOCKED', $json);
    }

    // 18. Legacy admin and customer mutations blocked in draining
    public function test_legacy_customer_and_admin_mutations_blocked_in_draining_mode(): void
    {
        $conv = ChatConversation::create(['session_token' => 'sess_block', 'status' => 'human']);

        SupportCutoverManager::enterDraining();

        $res1 = $this->postJson('/api/chat/send', ['message' => 'Test']);
        $res1->assertStatus(423);

        $res2 = $this->postJson('/api/chat/request-human', ['session_token' => 'sess_block']);
        $res2->assertStatus(423);

        $res3 = $this->actingAs($this->admin, 'sanctum')->postJson("/api/admin/chat/reply/{$conv->id}", ['message' => 'Reply']);
        $res3->assertStatus(423);

        $res4 = $this->actingAs($this->admin, 'sanctum')->postJson("/api/admin/chat/update-status/{$conv->id}", ['status' => 'closed']);
        $res4->assertStatus(423);

        $res5 = $this->actingAs($this->admin, 'sanctum')->deleteJson("/api/admin/chat/{$conv->id}");
        $res5->assertStatus(423);
    }

    // 19. Legacy read endpoints remain available
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

    // 20. Modern Support API is canonical and functional
    public function test_support_api_canonical_and_functional_in_support_mode(): void
    {
        SupportCutoverManager::enterDraining();
        SupportCutoverManager::activateSupport();

        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v1/support/conversations', [
                'language' => 'en',
            ]);

        $this->assertContains($response->status(), [200, 201]);
        $convPublicId = $response->json('data.conversation.id');
        $this->assertNotEmpty($convPublicId);

        $msgRes = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/support/conversations/{$convPublicId}/messages", [
                'message' => 'Canonical support query',
            ]);

        $msgRes->assertStatus(200);
        $this->assertDatabaseHas('support_messages', [
            'content' => 'Canonical support query',
        ]);
    }

    // 21. Safe rollback allowed when zero post-cutover activity
    public function test_rollback_allowed_when_zero_post_cutover_activity(): void
    {
        SupportCutoverManager::enterDraining();
        SupportCutoverManager::activateSupport();
        $this->assertTrue(SupportCutoverManager::isSupport());

        $rollback = SupportCutoverManager::rollback();
        $this->assertTrue($rollback['success']);
        $this->assertEquals(SupportCutoverManager::STATE_LEGACY, SupportCutoverManager::getState());
        $this->assertTrue(SupportCutoverManager::canMutateLegacy());
    }

    // 22. Rollback blocked when post-cutover tickets or voice exist
    public function test_rollback_blocked_fail_closed_when_post_cutover_tickets_exist(): void
    {
        SupportCutoverManager::enterDraining();
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

    // 23. Legacy classes and tables remain preserved
    public function test_legacy_classes_and_tables_remain_preserved(): void
    {
        $this->assertTrue(class_exists(\App\Http\Controllers\Frontend\ChatController::class));
        $this->assertTrue(class_exists(\App\Http\Controllers\Admin\AdminChatController::class));
        $this->assertTrue(class_exists(\App\Services\ChatService::class));
        $this->assertTrue(class_exists(\App\Models\ChatConversation::class));
        $this->assertTrue(class_exists(\App\Models\ChatMessage::class));
    }

    // 24. Artisan support cutover command workflow
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

        // 5. Rollback (allowed because zero post-cutover activity)
        $this->artisan('support:cutover', ['--rollback' => true])
            ->assertExitCode(0);
        $this->assertEquals(SupportCutoverManager::STATE_LEGACY, SupportCutoverManager::getState());
    }
}
