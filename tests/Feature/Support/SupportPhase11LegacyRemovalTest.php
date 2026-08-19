<?php

namespace Tests\Feature\Support;

use App\Http\Controllers\Admin\AiAgentController;
use App\Http\Controllers\Admin\AiController;
use App\Models\AiAgent;
use App\Models\GatewayOption;
use App\Models\User;
use App\Support\Cutover\SupportCutoverManager;
use App\Support\Cutover\SupportReadinessService;
use App\Support\Enums\ConversationMode;
use App\Support\Enums\ConversationStatus;
use App\Support\Enums\SenderType;
use App\Support\Migration\Legacy\Models\LegacyChatConversation;
use App\Support\Migration\Legacy\Models\LegacyChatMessage;
use App\Support\Migration\LegacyChatAuditService;
use App\Support\Migration\LegacyChatMigrationService;
use App\Support\Migration\LegacyMigrationVerificationService;
use App\Support\Models\SupportAuditLog;
use App\Support\Models\SupportConversation;
use App\Support\Models\SupportDepartment;
use App\Support\Models\SupportLegacyMigrationItem;
use App\Support\Models\SupportLegacyMigrationRun;
use App\Support\Models\SupportMessage;
use App\Support\Models\SupportPolicy;
use App\Support\Models\SupportTicket;
use App\Support\Models\SupportVoiceSession;
use App\Support\Services\AiProviderFactory;
use Carbon\Carbon;
use Database\Seeders\SupportDomainSeeder;
use Dipokhalder\Settings\Facades\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SupportPhase11LegacyRemovalTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $customer;
    protected User $otherCustomer;
    protected AiAgent $openrouterAgent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            \Database\Seeders\SiteTableSeeder::class,
            \Database\Seeders\RoleTableSeeder::class,
            \Database\Seeders\PermissionTableSeeder::class,
            SupportDomainSeeder::class,
        ]);

        $this->admin = User::factory()->create([
            'username' => 'admin_' . \Illuminate\Support\Str::random(6),
            'email' => 'admin@example.com',
        ]);
        $adminRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'sanctum']);
        $adminRole->givePermissionTo(\Spatie\Permission\Models\Permission::all());
        $this->admin->assignRole('Admin');

        $this->customer = User::factory()->create([
            'username' => 'customer_' . \Illuminate\Support\Str::random(6),
            'email' => 'customer@example.com',
        ]);
        $this->customer->assignRole('Customer');

        $this->otherCustomer = User::factory()->create([
            'username' => 'other_' . \Illuminate\Support\Str::random(6),
            'email' => 'other@example.com',
        ]);
        $this->otherCustomer->assignRole('Customer');

        putenv('OPENROUTER_API_KEY=sk-or-test-mock-key-1234567890');
        putenv('GEMINI_API_KEY=sk-gemini-test-mock-key-1234567890');

        $this->openrouterAgent = AiAgent::firstOrCreate(
            ['slug' => 'openrouter'],
            ['name' => 'OpenRouter', 'status' => 5]
        );
        $this->openrouterAgent->status = 5;
        $this->openrouterAgent->save();

        GatewayOption::firstOrCreate(
            ['model_type' => AiAgent::class, 'model_id' => $this->openrouterAgent->id, 'option' => 'openrouter_api_key'],
            ['value' => 'sk-or-test-mock-key-1234567890', 'type' => 1]
        );

        Settings::group('site')->set('site_default_ai_agent', $this->openrouterAgent->id);
        Settings::group('support')->set('cutover_state', SupportCutoverManager::STATE_SUPPORT);
    }

    // 1. Legacy customer chat routes absent
    public function test_legacy_customer_chat_routes_absent(): void
    {
        $res1 = $this->postJson('/api/chat/send', ['message' => 'Hello']);
        $this->assertTrue(in_array($res1->status(), [404, 405]));

        $res2 = $this->postJson('/api/chat/request-human');
        $this->assertTrue(in_array($res2->status(), [404, 405]));

        $legacyRoutes = array_filter(
            \Illuminate\Support\Facades\Route::getRoutes()->getRoutes(),
            fn ($r) => str_starts_with($r->uri(), 'api/chat')
        );
        $this->assertEmpty($legacyRoutes);
    }

    // 2. Legacy frontend chat routes absent
    public function test_legacy_frontend_chat_routes_absent(): void
    {
        $res1 = $this->postJson('/api/frontend/chat/send', ['message' => 'Hello']);
        $this->assertTrue(in_array($res1->status(), [404, 405]));

        $res2 = $this->postJson('/api/frontend/chat/request-human');
        $this->assertTrue(in_array($res2->status(), [404, 405]));

        $legacyRoutes = array_filter(
            \Illuminate\Support\Facades\Route::getRoutes()->getRoutes(),
            fn ($r) => str_starts_with($r->uri(), 'api/frontend/chat')
        );
        $this->assertEmpty($legacyRoutes);
    }

    // 3. Legacy admin chat routes absent
    public function test_legacy_admin_chat_routes_absent(): void
    {
        $res1 = $this->actingAs($this->admin, 'sanctum')->postJson('/api/admin/chat/reply/1', ['message' => 'Hi']);
        $this->assertTrue(in_array($res1->status(), [404, 405]));

        $res2 = $this->actingAs($this->admin, 'sanctum')->postJson('/api/admin/chat/update-status/1', ['status' => 'closed']);
        $this->assertTrue(in_array($res2->status(), [404, 405]));

        $res3 = $this->actingAs($this->admin, 'sanctum')->deleteJson('/api/admin/chat/1');
        $this->assertTrue(in_array($res3->status(), [404, 405]));

        $legacyRoutes = array_filter(
            \Illuminate\Support\Facades\Route::getRoutes()->getRoutes(),
            fn ($r) => str_starts_with($r->uri(), 'api/admin/chat')
        );
        $this->assertEmpty($legacyRoutes);
    }

    // 4. Modern /api/v1/support/* routes remain active and reachable
    public function test_modern_support_api_routes_remain(): void
    {
        $res = $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/support/conversations', [
            'subject' => 'Order Help',
        ]);
        $res->assertStatus(201);
        $this->assertNotNull($res->json('data.conversation.id'));
    }

    // 5. AiSupportWidget remains canonical frontend component
    public function test_ai_support_widget_remains_canonical(): void
    {
        $this->assertFileExists(resource_path('js/components/frontend/support/AiSupportWidget.vue'));
        $this->assertFileDoesNotExist(resource_path('js/components/frontend/chat/LiveChatWidgetComponent.vue'));
    }

    // 6. /admin/support remains canonical agent console
    public function test_admin_support_remains_canonical(): void
    {
        $this->assertFileExists(resource_path('js/components/admin/support/SupportCenterComponent.vue'));
        $this->assertFileExists(resource_path('js/router/modules/supportRoutes.js'));
        $this->assertFileDoesNotExist(resource_path('js/components/admin/chat/LiveChatComponent.vue'));
        $this->assertFileDoesNotExist(resource_path('js/router/modules/liveChatRoutes.js'));
    }

    // 7. Legacy ChatController runtime absent
    public function test_legacy_chat_controller_runtime_absent(): void
    {
        $this->assertFalse(class_exists('App\Http\Controllers\Frontend\ChatController'));
    }

    // 8. Legacy AdminChatController runtime absent
    public function test_legacy_admin_chat_controller_runtime_absent(): void
    {
        $this->assertFalse(class_exists('App\Http\Controllers\Admin\AdminChatController'));
    }

    // 9. Legacy ChatService runtime absent
    public function test_legacy_chat_service_runtime_absent(): void
    {
        $this->assertFalse(class_exists('App\Services\ChatService'));
    }

    // 10. No active runtime import uses deleted files
    public function test_no_active_runtime_import_uses_deleted_files(): void
    {
        $this->assertFalse(class_exists('App\Models\ChatConversation'));
        $this->assertFalse(class_exists('App\Models\ChatMessage'));
        $this->assertFalse(class_exists('App\Http\Middleware\Support\GateLegacyChatMutationMiddleware'));
    }

    // 11. Migration tooling remains functional
    public function test_migration_tooling_remains_functional(): void
    {
        $auditService = new LegacyChatAuditService();
        $audit = $auditService->audit();
        $this->assertEquals('ready', $audit['status']);
        $this->assertArrayHasKey('counts', $audit);
    }

    // 12. Migration audit can inspect retained legacy tables
    public function test_migration_audit_inspects_retained_legacy_tables(): void
    {
        LegacyChatConversation::create([
            'session_token' => 'sess_audit_test',
            'user_id' => $this->customer->id,
            'status' => 'ai',
        ]);

        $auditService = new LegacyChatAuditService();
        $audit = $auditService->audit();

        $this->assertEquals(1, $audit['counts']['total_conversations']);
        $this->assertEquals(1, $audit['counts']['authenticated_conversations']);
    }

    // 13. Production final delta migration mechanism remains usable
    public function test_production_final_delta_migration_remains_usable(): void
    {
        $legacyConv = LegacyChatConversation::create([
            'session_token' => 'sess_delta_1',
            'user_id' => $this->customer->id,
            'status' => 'closed',
        ]);
        LegacyChatMessage::create([
            'conversation_id' => $legacyConv->id,
            'sender_type' => 'user',
            'message' => 'Legacy query',
        ]);

        $migrationService = new LegacyChatMigrationService();
        $result = $migrationService->migrate(['apply' => true]);

        $this->assertEquals('completed', $result['status']);
        $this->assertEquals(1, $result['stats']['conversations_created']);
        $this->assertEquals(1, $result['stats']['messages_created']);
    }

    // 14. Migration verification remains usable
    public function test_migration_verification_remains_usable(): void
    {
        $legacyConv = LegacyChatConversation::create([
            'session_token' => 'sess_verify_1',
            'user_id' => $this->customer->id,
            'status' => 'closed',
        ]);

        $migrationService = new LegacyChatMigrationService();
        $runResult = $migrationService->migrate(['apply' => true]);

        $verifier = new LegacyMigrationVerificationService();
        $verResult = $verifier->verify($runResult['run_id']);

        $this->assertTrue($verResult['passed']);
        $this->assertEquals(0, $verResult['mismatch_count']);
    }

    // 15. Legacy source tables remain in database
    public function test_legacy_source_tables_remain(): void
    {
        $this->assertTrue(Schema::hasTable('chat_conversations'));
        $this->assertTrue(Schema::hasTable('chat_messages'));
    }

    // 16. Migration ledger tables remain in database
    public function test_migration_ledger_tables_remain(): void
    {
        $this->assertTrue(Schema::hasTable('support_legacy_migration_runs'));
        $this->assertTrue(Schema::hasTable('support_legacy_migration_items'));
    }

    // 17. Selected AI provider still works
    public function test_selected_ai_provider_still_works(): void
    {
        $provider = AiProviderFactory::make();
        $this->assertNotNull($provider);
        $this->assertTrue($provider->isConfigured());
        $this->assertEquals('openrouter', $provider->providerName());
    }

    // 18. Customer Support conversations work
    public function test_customer_support_conversations_work(): void
    {
        $res = $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/support/conversations', [
            'subject' => 'Need order help',
            'initial_message' => 'Where is my order #1234?',
        ]);
        $res->assertStatus(201);
        $this->assertDatabaseHas('support_conversations', [
            'customer_id' => $this->customer->id,
        ]);
    }

    // 19. Support messaging works
    public function test_support_messaging_works(): void
    {
        $conv = SupportConversation::create([
            'public_id' => 'conv_msg_test_01',
            'customer_id' => $this->customer->id,
            'subject' => 'Messaging test',
            'mode' => ConversationMode::AI,
            'status' => ConversationStatus::AI_ACTIVE,
        ]);

        $res = $this->actingAs($this->customer, 'sanctum')->postJson("/api/v1/support/conversations/{$conv->public_id}/messages", [
            'message' => 'Customer followup message',
        ]);

        $res->assertStatus(200);
        $this->assertDatabaseHas('support_messages', [
            'conversation_id' => $conv->id,
            'content' => 'Customer followup message',
        ]);
    }

    // 20. Guest identity and security works
    public function test_guest_identity_and_security_works(): void
    {
        $res = $this->postJson('/api/v1/support/conversations', [
            'guest_token' => 'guest_session_secret_token_123',
            'subject' => 'Hello as guest',
        ]);
        $res->assertStatus(201);
        $publicId = $res->json('data.conversation.id');
        $this->assertNotEmpty($publicId);

        // Fetch with valid guest token
        $getRes = $this->getJson("/api/v1/support/conversations/{$publicId}?guest_token=guest_session_secret_token_123");
        $getRes->assertStatus(200);

        // Fetch with invalid guest token denied (404 not found)
        $invalidRes = $this->getJson("/api/v1/support/conversations/{$publicId}?guest_token=wrong_token");
        $invalidRes->assertStatus(404);
    }

    // 21. Customer isolation works
    public function test_customer_isolation_works(): void
    {
        $conv = SupportConversation::create([
            'public_id' => 'conv_iso_test_01',
            'customer_id' => $this->customer->id,
            'subject' => 'Private inquiry',
            'mode' => ConversationMode::AI,
            'status' => ConversationStatus::AI_ACTIVE,
        ]);

        $res = $this->actingAs($this->otherCustomer, 'sanctum')->getJson("/api/v1/support/conversations/{$conv->public_id}");
        $res->assertStatus(404);
    }

    // 22. Human handoff works
    public function test_human_handoff_works(): void
    {
        $conv = SupportConversation::create([
            'public_id' => 'conv_handoff_01',
            'customer_id' => $this->customer->id,
            'subject' => 'Handoff test',
            'mode' => ConversationMode::AI,
            'status' => ConversationStatus::AI_ACTIVE,
        ]);

        $res = $this->actingAs($this->customer, 'sanctum')->postJson("/api/v1/support/conversations/{$conv->public_id}/request-human", [
            'reason' => 'Need human agent',
        ]);

        $res->assertStatus(200);
        $conv->refresh();
        $this->assertEquals(ConversationMode::HYBRID, $conv->mode);
    }

    // 23. Agent queue works
    public function test_agent_queue_works(): void
    {
        SupportConversation::create([
            'public_id' => 'conv_queue_01',
            'customer_id' => $this->customer->id,
            'subject' => 'Waiting for agent',
            'mode' => ConversationMode::HUMAN,
            'status' => ConversationStatus::QUEUED,
        ]);

        $res = $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/support/agent/conversations');
        $res->assertStatus(200);
        $this->assertNotEmpty($res->json('data'));
    }

    // 24. Internal notes remain private from customer API
    public function test_internal_notes_remain_private(): void
    {
        $conv = SupportConversation::create([
            'public_id' => 'conv_note_01',
            'customer_id' => $this->customer->id,
            'subject' => 'Notes test',
            'mode' => ConversationMode::HUMAN,
            'status' => ConversationStatus::HUMAN_ACTIVE,
        ]);

        // Agent adds note
        $noteRes = $this->actingAs($this->admin, 'sanctum')->postJson("/api/v1/support/agent/conversations/{$conv->public_id}/notes", [
            'content' => 'Staff internal note - customer must not see',
        ]);
        $noteRes->assertStatus(200);

        // Customer reads conversation
        $custRes = $this->actingAs($this->customer, 'sanctum')->getJson("/api/v1/support/conversations/{$conv->public_id}");
        $custRes->assertStatus(200);
        $custJson = json_encode($custRes->json());
        $this->assertStringNotContainsString('Staff internal note - customer must not see', $custJson);
    }

    // 25. Support tools and policies work
    public function test_support_tools_and_policies_work(): void
    {
        $policyCount = SupportPolicy::where('is_active', true)->count();
        $this->assertGreaterThan(0, $policyCount);
    }

    // 26. Voice capability endpoint remains active
    public function test_voice_capability_remains(): void
    {
        $res = $this->getJson('/api/v1/support/voice/capabilities');
        $res->assertStatus(200);
        $this->assertArrayHasKey('stt', $res->json('data'));
        $this->assertArrayHasKey('tts', $res->json('data'));
    }

    // 27. Realtime and polling fallback remains functional
    public function test_realtime_and_polling_fallback_remains(): void
    {
        $conv = SupportConversation::create([
            'public_id' => 'conv_poll_01',
            'customer_id' => $this->customer->id,
            'subject' => 'Polling test',
            'mode' => ConversationMode::AI,
            'status' => ConversationStatus::AI_ACTIVE,
        ]);

        $res = $this->actingAs($this->customer, 'sanctum')->getJson("/api/v1/support/conversations/{$conv->public_id}/updates?since=" . urlencode(now()->subMinute()->toIso8601String()));
        $res->assertStatus(200);
        $this->assertArrayHasKey('data', $res->json());
    }

    // 28. Admin AI system remains functional
    public function test_admin_ai_system_remains_functional(): void
    {
        $this->assertTrue(class_exists(AiController::class));
        $this->assertTrue(class_exists(AiAgentController::class));
    }

    // 29. AI Agent configuration remains functional
    public function test_ai_agent_configuration_remains_functional(): void
    {
        $res = $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/setting/ai-agent');
        $res->assertStatus(200);
        $this->assertArrayHasKey('data', $res->json());
    }

    // 30. Unrelated ecommerce functionality unaffected
    public function test_unrelated_ecommerce_functionality_unaffected(): void
    {
        $res = $this->getJson('/api/frontend/setting');
        $res->assertStatus(200);
    }
}
