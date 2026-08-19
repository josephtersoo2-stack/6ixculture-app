<?php

namespace Tests\Feature\Support;

use App\Models\User;
use App\Models\AiAgent;
use App\Support\Cutover\SupportCutoverManager;
use App\Support\Cutover\SupportReadinessService;
use App\Support\DTOs\ChatMessageDTO;
use App\Support\DTOs\ToolCallDTO;
use App\Support\Enums\ConversationMode;
use App\Support\Enums\ConversationStatus;
use App\Support\Enums\MessageType;
use App\Support\Enums\PolicyEffect;
use App\Support\Enums\SenderType;
use App\Support\Migration\Legacy\Models\LegacyChatConversation;
use App\Support\Migration\Legacy\Models\LegacyChatMessage;
use App\Support\Models\SupportAITool;
use App\Support\Models\SupportAuditLog;
use App\Support\Models\SupportConversation;
use App\Support\Models\SupportDepartment;
use App\Support\Models\SupportMessage;
use App\Support\Models\SupportPolicy;
use App\Support\Policies\SupportActionPolicyEngine;
use App\Support\Services\AuditRedactionService;
use App\Support\Services\SupportOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupportPhase12ProductionHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer1;
    protected User $customer2;
    protected User $agentUser;
    protected User $unauthorizedUser;
    protected SupportDepartment $deptSupport;
    protected SupportDepartment $deptBilling;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('support-conversations');
        RateLimiter::clear('support-messages');
        RateLimiter::clear('support-voice');
        RateLimiter::clear('support-actions');
        RateLimiter::clear('support-polling');
        RateLimiter::clear('support-agent');
        RateLimiter::clear('support-admin');

        $this->seed([
            \Database\Seeders\SiteTableSeeder::class,
            \Database\Seeders\RoleTableSeeder::class,
            \Database\Seeders\PermissionTableSeeder::class,
            \Database\Seeders\SupportDomainSeeder::class,
        ]);

        $this->deptSupport = SupportDepartment::where('slug', 'general-support')->first()
            ?? SupportDepartment::create(['name' => 'Customer Support', 'slug' => 'customer-support', 'is_active' => true]);

        $this->deptBilling = SupportDepartment::where('slug', 'payments')->first()
            ?? SupportDepartment::create(['name' => 'Billing Inquiries', 'slug' => 'billing-inquiries', 'is_active' => true]);

        // Create test users
        $this->customer1 = User::firstOrCreate(
            ['email' => 'customer1_phase12@example.com'],
            ['name' => 'Customer One', 'username' => 'customer1_phase12', 'password' => bcrypt('secret123')]
        );

        $this->customer2 = User::firstOrCreate(
            ['email' => 'customer2_phase12@example.com'],
            ['name' => 'Customer Two', 'username' => 'customer2_phase12', 'password' => bcrypt('secret123')]
        );

        $this->agentUser = User::firstOrCreate(
            ['email' => 'agent_phase12@example.com'],
            ['name' => 'Agent One', 'username' => 'agent_phase12', 'password' => bcrypt('secret123')]
        );

        $this->unauthorizedUser = User::firstOrCreate(
            ['email' => 'unauth_phase12@example.com'],
            ['name' => 'Unauthorized User', 'username' => 'unauth_phase12', 'password' => bcrypt('secret123')]
        );

        // Setup Agent permissions & Profile
        $supportDeskPerm = Permission::firstOrCreate(['name' => 'support_desk', 'guard_name' => 'web']);
        $this->agentUser->givePermissionTo($supportDeskPerm);

        \App\Support\Models\SupportAgentProfile::firstOrCreate(
            ['user_id' => $this->agentUser->id],
            ['display_name' => 'Agent One', 'status' => 'online', 'max_concurrent_conversations' => 5]
        );
    }

    /**
     * 1. Customer Isolation: Customer A cannot view or access Customer B's conversation.
     */
    public function test_customer_cannot_view_other_customers_conversation(): void
    {
        $conv = SupportConversation::create([
            'public_id' => (string) Str::uuid(),
            'customer_id' => $this->customer1->id,
            'status' => ConversationStatus::AI_ACTIVE,
            'mode' => ConversationMode::AI,
        ]);

        // Customer 2 attempts to access Customer 1's conversation
        $response = $this->actingAs($this->customer2, 'sanctum')
            ->getJson("/api/v1/support/conversations/{$conv->public_id}");

        $response->assertStatus(404);
        $response->assertJsonPath('error.code', 'SUPPORT_CONVERSATION_NOT_FOUND');
    }

    /**
     * 2. Guest Token Validation & Isolation: Wrong guest token cannot access conversation.
     */
    public function test_guest_with_wrong_token_is_denied(): void
    {
        $token = 'valid_guest_token_' . Str::random(16);
        $conv = SupportConversation::create([
            'public_id' => (string) Str::uuid(),
            'customer_id' => null,
            'guest_session_id' => $token,
            'status' => ConversationStatus::AI_ACTIVE,
            'mode' => ConversationMode::AI,
        ]);

        // Request with wrong token
        $response = $this->withHeader('X-Guest-Token', 'wrong_token')
            ->getJson("/api/v1/support/conversations/{$conv->public_id}");

        $response->assertStatus(404);
        $response->assertJsonPath('error.code', 'SUPPORT_CONVERSATION_NOT_FOUND');

        // Request with valid token
        $validResponse = $this->withHeader('X-Guest-Token', $token)
            ->getJson("/api/v1/support/conversations/{$conv->public_id}");

        $validResponse->assertStatus(200);
        $validResponse->assertJsonPath('data.conversation.id', $conv->public_id);
    }

    /**
     * 3. Support Agent Permission Enforcement: Non-agent user is forbidden from agent endpoints.
     */
    public function test_unauthorized_user_is_forbidden_from_agent_endpoints(): void
    {
        $response = $this->actingAs($this->unauthorizedUser, 'sanctum')
            ->getJson('/api/v1/support/agent/conversations');

        $response->assertStatus(403);
    }

    /**
     * 4. Authorized Agent can access agent queue.
     */
    public function test_authorized_agent_can_access_queue(): void
    {
        $response = $this->actingAs($this->agentUser, 'sanctum')
            ->getJson('/api/v1/support/agent/conversations');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    /**
     * 5. Internal Note Shielding: Customer cannot see internal notes.
     */
    public function test_internal_notes_are_never_exposed_to_customers(): void
    {
        $conv = SupportConversation::create([
            'public_id' => (string) Str::uuid(),
            'customer_id' => $this->customer1->id,
            'status' => ConversationStatus::HUMAN_ACTIVE,
            'mode' => ConversationMode::HUMAN,
        ]);

        // Public message
        SupportMessage::create([
            'conversation_id' => $conv->id,
            'sender_type' => SenderType::AGENT,
            'message_type' => MessageType::TEXT,
            'content' => 'Public agent reply to customer',
            'is_internal' => false,
        ]);

        // Internal note
        SupportMessage::create([
            'conversation_id' => $conv->id,
            'sender_type' => SenderType::AGENT,
            'message_type' => MessageType::INTERNAL_NOTE,
            'content' => 'CONFIDENTIAL: Customer is requesting a special discount exception.',
            'is_internal' => true,
        ]);

        // Customer fetches messages
        $response = $this->actingAs($this->customer1, 'sanctum')
            ->getJson("/api/v1/support/conversations/{$conv->public_id}/messages");

        $response->assertStatus(200);
        $messages = $response->json('data');

        $this->assertCount(1, $messages);
        $this->assertEquals('Public agent reply to customer', $messages[0]['content']);
        $this->assertStringNotContainsString('CONFIDENTIAL', json_encode($messages));
    }

    /**
     * 6. Internal Note Storing: Agent can save internal note.
     */
    public function test_agent_can_store_internal_note(): void
    {
        $conv = SupportConversation::create([
            'public_id' => (string) Str::uuid(),
            'customer_id' => $this->customer1->id,
            'assigned_agent_id' => $this->agentUser->id,
            'status' => ConversationStatus::HUMAN_ACTIVE,
            'mode' => ConversationMode::HUMAN,
        ]);

        $response = $this->actingAs($this->agentUser, 'sanctum')
            ->postJson("/api/v1/support/agent/conversations/{$conv->public_id}/notes", [
                'content' => 'Staff internal note test content',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('support_messages', [
            'conversation_id' => $conv->id,
            'is_internal' => true,
            'content' => 'Staff internal note test content',
        ]);
    }

    /**
     * 7. Rate Limiter Definition and Configuration.
     */
    public function test_support_rate_limiters_are_registered(): void
    {
        $this->assertNotNull(RateLimiter::limiter('support-conversations'));
        $this->assertNotNull(RateLimiter::limiter('support-messages'));
        $this->assertNotNull(RateLimiter::limiter('support-voice'));
        $this->assertNotNull(RateLimiter::limiter('support-actions'));
        $this->assertNotNull(RateLimiter::limiter('support-polling'));
        $this->assertNotNull(RateLimiter::limiter('support-agent'));
        $this->assertNotNull(RateLimiter::limiter('support-admin'));
    }

    /**
     * 8. Legitimate Customer Traffic is Not Over-Throttled.
     */
    public function test_legitimate_customer_request_succeeds(): void
    {
        $response = $this->actingAs($this->customer1, 'sanctum')
            ->postJson('/api/v1/support/conversations', [
                'subject' => 'Help with order #1001',
                'language' => 'en',
            ]);

        $response->assertStatus(201);
        $this->assertNotNull($response->json('data.conversation.id'));
    }

    /**
     * 9. Behavioral Rate Limiting: Guest conversation creation abuse returns 429.
     */
    public function test_behavioral_rate_limiting_guest_conversations_returns_429(): void
    {
        $ip = '198.51.100.55';

        // Guest limit is 10 per minute
        for ($i = 0; $i < 10; $i++) {
            $resp = $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->postJson('/api/v1/support/conversations', ['subject' => "Guest test {$i}"]);
            $this->assertEquals(201, $resp->getStatusCode(), "Request {$i} should succeed");
        }

        // 11th request must be throttled
        $throttled = $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/v1/support/conversations', ['subject' => 'Over limit request']);

        $throttled->assertStatus(429);
    }

    /**
     * 10. Behavioral Rate Limiting: Action mutation abuse returns 429.
     */
    public function test_behavioral_rate_limiting_action_mutations_returns_429(): void
    {
        $conv = SupportConversation::create([
            'public_id' => (string) Str::uuid(),
            'customer_id' => $this->customer1->id,
            'status' => ConversationStatus::AI_ACTIVE,
            'mode' => ConversationMode::AI,
        ]);

        // Rate limit for support-actions is 30 per minute for authenticated customer
        for ($i = 0; $i < 30; $i++) {
            $resp = $this->actingAs($this->customer1, 'sanctum')
                ->postJson("/api/v1/support/conversations/{$conv->public_id}/language", ['language' => 'en']);
            $this->assertEquals(200, $resp->getStatusCode(), "Language update {$i} should succeed");
        }

        // 31st request must be throttled
        $throttled = $this->actingAs($this->customer1, 'sanctum')
            ->postJson("/api/v1/support/conversations/{$conv->public_id}/language", ['language' => 'en']);

        $throttled->assertStatus(429);
    }

    /**
     * 11. String-Level Secret Redaction: Masks embedded tokens, API keys, passwords, and credentials.
     */
    public function test_audit_redaction_service_string_level_redaction(): void
    {
        $inputs = [
            'Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.e30.t-ID' => 'Authorization: Bearer [REDACTED]',
            'Here is my key: sk-live1234567890abcdef' => 'Here is my key: [REDACTED]',
            'Google Key: AIzaSyD9876543210zyxwvutsrqponmlkjihg' => 'Google Key: [REDACTED]',
            'Config api_key=secret-key-value-123 in url' => 'Config api_key=[REDACTED] in url',
            'User set password: my-super-secret-password-123' => 'User set password:[REDACTED]',
            'Session token=eyJhbGciOi...' => 'Session token=[REDACTED]',
            'credential: internal-credential-token-xyz' => 'credential:[REDACTED]',
        ];

        foreach ($inputs as $input => $expectedMatch) {
            $sanitized = AuditRedactionService::sanitizeString($input);
            $this->assertStringNotContainsString('sk-live', $sanitized);
            $this->assertStringNotContainsString('AIzaSy', $sanitized);
            $this->assertStringNotContainsString('my-super-secret-password', $sanitized);
            $this->assertStringNotContainsString('internal-credential-token', $sanitized);
            $this->assertStringContainsString('[REDACTED]', $sanitized);
        }

        // Test recursive structure string redaction
        $complex = [
            'nested' => [
                'raw_error' => 'Failed calling Google API with key AIzaSyTestKey123456',
                'header' => 'Bearer eyJsecretJWT',
            ],
            'safe_text' => 'Regular customer inquiry about shoes',
        ];

        $sanitizedComplex = AuditRedactionService::sanitize($complex);
        $this->assertEquals('Regular customer inquiry about shoes', $sanitizedComplex['safe_text']);
        $this->assertStringNotContainsString('AIzaSyTestKey123456', $sanitizedComplex['nested']['raw_error']);
        $this->assertStringContainsString('[REDACTED]', $sanitizedComplex['nested']['raw_error']);
        $this->assertStringContainsString('[REDACTED]', $sanitizedComplex['nested']['header']);
    }

    /**
     * 12. AI Provider Error Produces Safe Sanitized Message & Structured Payload.
     */
    public function test_ai_provider_error_produces_safe_sanitized_message_and_payload(): void
    {
        $conv = SupportConversation::create([
            'public_id' => (string) Str::uuid(),
            'customer_id' => $this->customer1->id,
            'status' => ConversationStatus::AI_ACTIVE,
            'mode' => ConversationMode::AI,
        ]);

        $orchestrator = app(SupportOrchestrator::class);
        $incoming = new ChatMessageDTO(
            senderType: SenderType::CUSTOMER,
            messageType: MessageType::TEXT,
            content: 'Help with order containing secret token: sk-secret-12345',
            isInternal: false
        );

        $dto = $orchestrator->handle($conv, $incoming);

        $this->assertNotNull($dto);
        $this->assertEquals(
            'I am currently having trouble processing your request. Please try again shortly or request a human support agent.',
            $dto->content
        );
        $this->assertIsArray($dto->structuredPayload);
        $this->assertEquals('AI_PROVIDER_UNAVAILABLE', $dto->structuredPayload['error']['code'] ?? null);

        // Ensure no internal tokens, headers or stack traces exist
        $serialized = json_encode($dto);
        $this->assertStringNotContainsString('sk-secret', $serialized);
        $this->assertStringNotContainsString('AIzaSy', $serialized);
        $this->assertStringNotContainsString('PDOException', $serialized);
    }

    /**
     * 13. Security Policy Engine: Denied actions are blocked.
     */
    public function test_policy_engine_denies_restricted_actions(): void
    {
        $conv = SupportConversation::create([
            'public_id' => (string) Str::uuid(),
            'customer_id' => $this->customer1->id,
            'status' => ConversationStatus::AI_ACTIVE,
            'mode' => ConversationMode::AI,
        ]);

        $policyEngine = new SupportActionPolicyEngine();
        $call = new ToolCallDTO('call_1', 'admin_delete_database', ['target' => 'all']);

        $effect = $policyEngine->evaluateToolCall($call, $conv);

        $this->assertEquals(PolicyEffect::DENY, $effect);
    }

    /**
     * 14. Security Policy Engine: Confirmation required for sensitive mutations.
     */
    public function test_policy_engine_requires_confirmation_for_cancellations(): void
    {
        $conv = SupportConversation::create([
            'public_id' => (string) Str::uuid(),
            'customer_id' => $this->customer1->id,
            'status' => ConversationStatus::AI_ACTIVE,
            'mode' => ConversationMode::AI,
        ]);

        $policyEngine = new SupportActionPolicyEngine();
        $call = new ToolCallDTO('call_2', 'cancel_order', ['order_id' => 123]);

        $effect = $policyEngine->evaluateToolCall($call, $conv);

        $this->assertEquals(PolicyEffect::CONFIRM, $effect);
    }

    /**
     * 15. Realtime Polling Fallback: Incremental updates return new messages.
     */
    public function test_realtime_polling_endpoint_returns_incremental_updates(): void
    {
        $conv = SupportConversation::create([
            'public_id' => (string) Str::uuid(),
            'customer_id' => $this->customer1->id,
            'status' => ConversationStatus::AI_ACTIVE,
            'mode' => ConversationMode::AI,
        ]);

        $msg1 = SupportMessage::create([
            'conversation_id' => $conv->id,
            'sender_type' => SenderType::CUSTOMER,
            'message_type' => MessageType::TEXT,
            'content' => 'First message',
            'is_internal' => false,
        ]);

        $msg2 = SupportMessage::create([
            'conversation_id' => $conv->id,
            'sender_type' => SenderType::AI,
            'message_type' => MessageType::TEXT,
            'content' => 'Second message',
            'is_internal' => false,
        ]);

        $response = $this->actingAs($this->customer1, 'sanctum')
            ->getJson("/api/v1/support/conversations/{$conv->public_id}/updates?after_id={$msg1->id}");

        $response->assertStatus(200);
        $newMessages = $response->json('data.new_messages');

        $this->assertCount(1, $newMessages);
        $this->assertEquals('Second message', $newMessages[0]['content']);
    }

    /**
     * 16. Voice Capability Graceful Degradation.
     */
    public function test_voice_capabilities_endpoint_reports_safe_structure(): void
    {
        $response = $this->getJson('/api/v1/support/voice/capabilities');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'stt' => ['provider', 'enabled'],
                'tts' => ['provider', 'enabled'],
                'features',
            ]
        ]);
    }

    /**
     * 17. Public Health Endpoint: Shallow safe projection without infrastructure disclosure.
     */
    public function test_public_health_endpoint_projects_safe_shallow_status_and_avoids_disclosure(): void
    {
        $response = $this->getJson('/api/v1/support/health');

        $this->assertContains($response->getStatusCode(), [200, 503]);
        $response->assertJsonStructure([
            'success',
            'status',
            'services' => [
                'support',
                'text',
                'voice',
                'realtime',
                'polling_fallback',
            ]
        ]);

        $content = $response->getContent();

        // Strictly verify NO infrastructure or migration details are disclosed
        $disallowedStrings = [
            'support_conversations',
            'support_messages',
            'support_legacy_migration_runs',
            'cutover',
            'activated_by',
            'final_delta_migration_run_id',
            'app_env',
            'debug',
            'queue',
            'cache',
            'session',
            'infrastructure',
            'provider_configured',
            'DB_PASSWORD',
            'sk-',
            'AIzaSy',
            'Bearer',
        ];

        foreach ($disallowedStrings as $disallowed) {
            $this->assertStringNotContainsString(
                $disallowed,
                $content,
                "Public health endpoint leaked sensitive indicator: {$disallowed}"
            );
        }
    }

    /**
     * 18. Support Readiness Service: Retains full internal fidelity for CLI cutover & operations.
     */
    public function test_support_readiness_service_compiles_indicators_with_full_fidelity(): void
    {
        $service = new SupportReadinessService();
        $readiness = $service->getReadiness();

        $this->assertIsArray($readiness);
        $this->assertArrayHasKey('status', $readiness);
        $this->assertArrayHasKey('ready', $readiness);
        $this->assertArrayHasKey('infrastructure', $readiness);
        $this->assertArrayHasKey('governance', $readiness);
        $this->assertArrayHasKey('realtime', $readiness);
        $this->assertArrayHasKey('voice', $readiness);
        $this->assertArrayHasKey('environment', $readiness);
        $this->assertArrayHasKey('queue', $readiness);
        $this->assertArrayHasKey('cache', $readiness);
        $this->assertArrayHasKey('session', $readiness);
        $this->assertTrue($readiness['infrastructure']['support_tables_ready']);
    }

    /**
     * 19. Phase 9 Legacy Migration Tooling & Models Remain Fully Operational.
     */
    public function test_phase9_migration_models_and_tables_are_retained(): void
    {
        $this->assertTrue(Schema::hasTable('chat_conversations'));
        $this->assertTrue(Schema::hasTable('chat_messages'));
        $this->assertTrue(Schema::hasTable('support_legacy_migration_runs'));
        $this->assertTrue(Schema::hasTable('support_legacy_migration_items'));

        $this->assertTrue(class_exists(LegacyChatConversation::class));
        $this->assertTrue(class_exists(LegacyChatMessage::class));
    }

    /**
     * 20. Phase 11 Legacy Runtime Remains Deleted.
     */
    public function test_phase11_legacy_runtime_classes_and_routes_remain_absent(): void
    {
        $this->assertFalse(class_exists('App\Http\Controllers\Frontend\ChatController'));
        $this->assertFalse(class_exists('App\Http\Controllers\Admin\AdminChatController'));
        $this->assertFalse(class_exists('App\Services\ChatService'));
        $this->assertFalse(class_exists('App\Models\ChatConversation'));
        $this->assertFalse(class_exists('App\Models\ChatMessage'));

        $routes = collect(Route::getRoutes()->getRoutes());
        $legacyRoutes = $routes->filter(function ($route) {
            $uri = $route->uri();
            return str_starts_with($uri, 'api/chat') ||
                   str_starts_with($uri, 'api/frontend/chat') ||
                   str_starts_with($uri, 'api/admin/chat');
        });

        $this->assertCount(0, $legacyRoutes);
    }

    /**
     * 21. Back-office AI Infrastructure is Preserved and Functional.
     */
    public function test_admin_ai_agent_infrastructure_remains_active(): void
    {
        $this->assertTrue(class_exists(\App\Http\Controllers\Admin\AiAgentController::class));
        $this->assertTrue(class_exists(\App\Models\AiAgent::class));

        $adminUser = User::firstOrCreate(
            ['email' => 'admin_phase12@example.com'],
            ['name' => 'Admin User', 'username' => 'admin_phase12', 'password' => bcrypt('secret123')]
        );
        $settingsPerm = Permission::firstOrCreate(['name' => 'settings', 'guard_name' => 'sanctum']);
        $adminUser->givePermissionTo($settingsPerm);

        $response = $this->actingAs($adminUser, 'sanctum')
            ->getJson('/api/admin/setting/ai-agent');

        $response->assertStatus(200);
    }

    /**
     * 22. Canonical Support Routes are Registered and Functional.
     */
    public function test_canonical_support_routes_are_active(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes());

        $supportRoutes = $routes->filter(function ($route) {
            return str_starts_with($route->uri(), 'api/v1/support');
        });

        $this->assertGreaterThan(15, $supportRoutes->count());

        $expectedUris = [
            'api/v1/support/health',
            'api/v1/support/conversations',
            'api/v1/support/voice/capabilities',
            'api/v1/support/agent/conversations',
            'api/v1/support/admin/knowledge',
            'api/v1/support/admin/policies',
            'api/v1/support/admin/tools',
            'api/v1/support/admin/audit-logs',
        ];

        foreach ($expectedUris as $expectedUri) {
            $found = $supportRoutes->first(function ($r) use ($expectedUri) {
                return $r->uri() === $expectedUri;
            });
            $this->assertNotNull($found, "Expected canonical route {$expectedUri} is missing.");
        }
    }

    /**
     * 23. Adapter Security Logging & Secret Redaction Test.
     */
    public function test_adapter_security_logging_does_not_leak_secrets(): void
    {
        $testStrings = [
            'Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIn0.doNotLeak',
            'Failed connecting with OpenAI key sk-secret-1234567890abcdef',
            'Google Gemini key AIzaSyFakeSecretValue12345 invalid',
            'Request to https://generativelanguage.googleapis.com/v1beta/models/gemini:generateContent?key=AIzaSySecretInUrl failed',
            'Context details: api_key=superSecretKey123 and password=superSecretPassword456',
        ];

        foreach ($testStrings as $rawString) {
            $sanitized = AuditRedactionService::sanitizeString($rawString);

            $this->assertStringNotContainsString('eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9', $sanitized);
            $this->assertStringNotContainsString('sk-secret-1234567890abcdef', $sanitized);
            $this->assertStringNotContainsString('AIzaSyFakeSecretValue12345', $sanitized);
            $this->assertStringNotContainsString('AIzaSySecretInUrl', $sanitized);
            $this->assertStringNotContainsString('superSecretKey123', $sanitized);
            $this->assertStringNotContainsString('superSecretPassword456', $sanitized);
        }
    }

    /**
     * 24. Raw Provider Errors are Never Exposed to Customers.
     */
    public function test_raw_provider_error_is_never_exposed_to_customer(): void
    {
        $conv = SupportConversation::create([
            'public_id' => (string) Str::uuid(),
            'customer_id' => $this->customer1->id,
            'status' => ConversationStatus::AI_ACTIVE,
            'mode' => ConversationMode::AI,
        ]);

        $mockAdapter = new class implements \App\Support\Contracts\AiProviderInterface {
            public function chat(array $messages, array $tools = []): array {
                return [
                    'text' => null,
                    'tool_calls' => [],
                    'usage' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
                    'metadata' => ['model' => 'test-model', 'provider' => 'openrouter'],
                    'finish_reason' => 'error',
                    'error' => 'Rate limit exceeded for internal account ORG-SECRET-123. Quota exhausted on cluster-09.',
                ];
            }
            public function supportsToolCalling(): bool { return true; }
            public function supportsStructuredOutput(): bool { return true; }
            public function supportsStreaming(): bool { return false; }
            public function isConfigured(): bool { return true; }
            public function providerName(): string { return 'openrouter'; }
        };

        $orchestrator = new SupportOrchestrator(null, null, null, $mockAdapter);
        $incoming = new ChatMessageDTO(
            senderType: SenderType::CUSTOMER,
            messageType: MessageType::TEXT,
            content: 'How do I track my order?',
            isInternal: false
        );

        $dto = $orchestrator->handle($conv, $incoming);

        $this->assertNotNull($dto);
        $this->assertEquals(
            'I am currently having trouble processing your request. Please try again shortly or request a human support agent.',
            $dto->content
        );
        $this->assertEquals('AI_PROVIDER_UNAVAILABLE', $dto->structuredPayload['error']['code'] ?? null);

        // Prove internal provider diagnostic is NOT in customer message or payload
        $this->assertStringNotContainsString('ORG-SECRET-123', $dto->content);
        $this->assertStringNotContainsString('Quota exhausted', $dto->content);
        $this->assertStringNotContainsString('cluster-09', $dto->content);

        $serializedPayload = json_encode($dto->structuredPayload);
        $this->assertStringNotContainsString('ORG-SECRET-123', $serializedPayload);
        $this->assertStringNotContainsString('cluster-09', $serializedPayload);
    }

    /**
     * 25. TLS Verification is Enabled on All Support Providers.
     */
    public function test_production_support_adapters_do_not_contain_without_verifying(): void
    {
        $openrouterPath = app_path('Support/Services/Adapters/OpenrouterSupportAdapter.php');
        $geminiPath = app_path('Support/Services/Adapters/GeminiSupportAdapter.php');

        $this->assertFileExists($openrouterPath);
        $this->assertFileExists($geminiPath);

        $openrouterContent = file_get_contents($openrouterPath);
        $geminiContent = file_get_contents($geminiPath);

        $this->assertStringNotContainsString('withoutVerifying', $openrouterContent);
        $this->assertStringNotContainsString('withoutVerifying', $geminiContent);
    }
}
