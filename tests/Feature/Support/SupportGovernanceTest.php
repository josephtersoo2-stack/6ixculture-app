<?php

namespace Tests\Feature\Support;

use App\Models\AiAgent;
use App\Models\User;
use App\Support\Enums\PolicyEffect;
use App\Support\Enums\ToolRiskLevel;
use App\Support\Models\SupportAITool;
use App\Support\Models\SupportAuditLog;
use App\Support\Models\SupportConversation;
use App\Support\Models\SupportKnowledgeArticle;
use App\Support\Models\SupportKnowledgeArticleVersion;
use App\Support\Models\SupportPolicy;
use App\Support\Policies\CriticalActionSafetyPolicy;
use App\Support\Services\SupportKnowledgeRepository;
use Database\Seeders\SupportDomainSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupportGovernanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $managerUser;
    protected User $agentUser;
    protected User $customerUser;

    protected function setUp(): void
    {
        parent::setUp();

        putenv('OPENROUTER_API_KEY=test_openrouter_sk_key');
        putenv('GEMINI_API_KEY=test_gemini_api_key');

        AiAgent::create(['name' => 'OpenRouter', 'slug' => 'openrouter', 'status' => 5]);
        AiAgent::create(['name' => 'Gemini', 'slug' => 'gemini', 'status' => 5]);

        $seeder = new SupportDomainSeeder();
        $seeder->run();

        Role::firstOrCreate(['name' => 'Customer', 'guard_name' => 'sanctum']);
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'sanctum']);
        Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'sanctum']);
        Role::firstOrCreate(['name' => 'Support Agent', 'guard_name' => 'sanctum']);

        $this->adminUser = User::factory()->create([
            'name' => 'Admin Boss',
            'email' => 'admin@example.com',
            'username' => 'adminboss',
        ]);
        $this->adminUser->assignRole('Admin');

        $this->managerUser = User::factory()->create([
            'name' => 'Manager Lead',
            'email' => 'manager@example.com',
            'username' => 'managerlead',
        ]);
        $this->managerUser->assignRole('Manager');

        $this->agentUser = User::factory()->create([
            'name' => 'Agent Support',
            'email' => 'agent@example.com',
            'username' => 'agentsupport',
        ]);
        $this->agentUser->assignRole('Support Agent');

        $this->customerUser = User::factory()->create([
            'name' => 'Regular Customer',
            'email' => 'customer@example.com',
            'username' => 'regcustomer',
        ]);
        $this->customerUser->assignRole('Customer');
    }

    public function test_unauthenticated_and_regular_customer_denied_governance_endpoints(): void
    {
        // Unauthenticated
        $this->getJson('/api/v1/support/admin/knowledge')->assertStatus(401);
        $this->getJson('/api/v1/support/admin/policies')->assertStatus(401);
        $this->getJson('/api/v1/support/admin/tools')->assertStatus(401);

        // Regular Customer
        $this->actingAs($this->customerUser, 'sanctum')
            ->getJson('/api/v1/support/admin/knowledge')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'SUPPORT_GOVERNANCE_FORBIDDEN');
    }

    public function test_normal_support_agent_without_governance_powers_is_denied(): void
    {
        $this->actingAs($this->agentUser, 'sanctum')
            ->getJson('/api/v1/support/admin/knowledge')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'SUPPORT_GOVERNANCE_FORBIDDEN');

        $this->actingAs($this->agentUser, 'sanctum')
            ->postJson('/api/v1/support/admin/knowledge', [
                'title' => 'Unauthorized Draft',
                'category' => 'Returns',
                'language' => 'en',
                'content' => 'Test content',
            ])
            ->assertStatus(403);
    }

    // --- ISSUE A: Critical Action Safety Tests ---

    public function test_critical_refund_cannot_disable_human_approval(): void
    {
        $refundTool = SupportAITool::where('key', 'request_refund')->first();
        $this->assertNotNull($refundTool);

        // Attempting to downgrade requires_human to false
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->patchJson("/api/v1/support/admin/tools/{$refundTool->id}/permissions", [
                'requires_human' => false,
            ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('data.requires_human'), 'request_refund must remain requires_human = true');
        
        $refundTool->refresh();
        $this->assertTrue((bool)$refundTool->requires_human);
        $this->assertEquals(ToolRiskLevel::CRITICAL, $refundTool->risk_level);
    }

    public function test_critical_and_sensitive_actions_remain_protected(): void
    {
        $this->assertTrue(CriticalActionSafetyPolicy::isCriticalAction('request_refund'));
        $this->assertTrue(CriticalActionSafetyPolicy::isCriticalAction('change_password'));
        $this->assertTrue(CriticalActionSafetyPolicy::isSensitiveAction('cancel_order'));
        $this->assertTrue(CriticalActionSafetyPolicy::isSensitiveAction('change_address'));
    }

    // --- ISSUE B & C: Policy Lifecycle & Activation Validation Tests ---

    public function test_new_policy_always_starts_inactive(): void
    {
        // Attempting to pass is_active = true on creation
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/support/admin/policies', [
                'key' => 'new_promo_discount_policy',
                'name' => 'Promo Discount Policy',
                'category' => 'orders',
                'effect' => 'confirm',
                'description' => 'Test policy description',
                'is_active' => true,
                'priority' => 10,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.key', 'new_promo_discount_policy')
            ->assertJsonPath('data.is_active', false); // Invariant: starts inactive

        $policyId = $response->json('data.id');
        $this->assertDatabaseHas('support_policies', [
            'id' => $policyId,
            'is_active' => false,
        ]);
    }

    public function test_explicit_activation_works(): void
    {
        $policy = SupportPolicy::create([
            'key' => 'explicit_activation_test_policy',
            'name' => 'Explicit Activation Test',
            'category' => 'orders',
            'effect' => 'confirm',
            'is_active' => false,
            'priority' => 20,
        ]);

        $this->assertFalse((bool)$policy->is_active);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/support/admin/policies/{$policy->id}/activate");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_active', true);

        $policy->refresh();
        $this->assertTrue((bool)$policy->is_active);
    }

    public function test_invalid_policy_with_nonexistent_tool_cannot_activate(): void
    {
        $policy = SupportPolicy::create([
            'key' => 'invalid_tool_reference_policy',
            'name' => 'Invalid Tool Policy',
            'category' => 'orders',
            'effect' => 'allow',
            'configuration' => [
                'tool_name' => 'nonexistent_malicious_tool_xyz',
            ],
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/support/admin/policies/{$policy->id}/activate");

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'POLICY_ACTIVATION_INVALID');

        $policy->refresh();
        $this->assertFalse((bool)$policy->is_active, 'Invalid policy must remain inactive');
    }

    public function test_unsafe_critical_policy_cannot_activate(): void
    {
        $policy = SupportPolicy::create([
            'key' => 'unsafe_refund_allow_policy',
            'name' => 'Unsafe Direct Refund Allow',
            'category' => 'financial',
            'effect' => 'allow',
            'configuration' => [
                'tool_name' => 'request_refund',
            ],
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/support/admin/policies/{$policy->id}/activate");

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'POLICY_ACTIVATION_INVALID');

        $policy->refresh();
        $this->assertFalse((bool)$policy->is_active, 'Unsafe policy must remain inactive');
    }

    // --- ISSUE D: Simulation Audit Redaction Tests ---

    public function test_sensitive_simulation_arguments_are_redacted_in_audit_log(): void
    {
        $simPayload = [
            'actor_type' => 'customer',
            'tool_name' => 'lookup_order',
            'arguments' => [
                'order_id' => '12345',
                'token' => 'secret-super-token-12345',
                'api_key' => 'sk_live_abcdef1234567890',
                'password' => 'superSecretPassword!',
                'authorization' => 'Bearer sensitiveBearerTokenValue',
            ],
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/support/admin/policies/simulate', $simPayload);

        $response->assertStatus(200)
            ->assertJsonPath('data.simulation', true)
            ->assertJsonPath('data.badge', 'SIMULATION ONLY');

        // Check the created SupportAuditLog
        $auditLog = SupportAuditLog::where('action', 'POLICY_SIMULATION_EXECUTED')
            ->orderBy('id', 'desc')
            ->first();

        $this->assertNotNull($auditLog);
        $metadata = $auditLog->metadata;

        $this->assertNotNull($metadata);
        $this->assertEquals('12345', $metadata['arguments']['order_id']);
        $this->assertEquals('[REDACTED]', $metadata['arguments']['token']);
        $this->assertEquals('[REDACTED]', $metadata['arguments']['api_key']);
        $this->assertEquals('[REDACTED]', $metadata['arguments']['password']);
        $this->assertEquals('[REDACTED]', $metadata['arguments']['authorization']);
    }

    // --- ISSUE E: Knowledge Draft -> Publish Lifecycle Tests ---

    public function test_new_article_cannot_be_published_through_create(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/support/admin/knowledge', [
                'title' => 'Attempt Direct Publish',
                'category' => 'General',
                'language' => 'en',
                'content' => 'Direct published content attempt',
                'status' => 'published',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_LIFECYCLE_STATE');

        $this->assertDatabaseMissing('support_knowledge_articles', [
            'title' => 'Attempt Direct Publish',
        ]);
    }

    public function test_explicit_publish_transitions_draft_to_published(): void
    {
        $createRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/support/admin/knowledge', [
                'title' => 'Warranty Coverage Guide',
                'slug' => 'warranty-coverage-guide',
                'category' => 'Warranty',
                'language' => 'en',
                'content' => 'Authentic 6ixCulture garments feature ZIPPSTITCHWARRANTY99 coverage.',
            ]);

        $createRes->assertStatus(201)
            ->assertJsonPath('data.status', 'draft');

        $articleId = $createRes->json('data.id');
        $repo = new SupportKnowledgeRepository();

        // 1. In draft state -> excluded from AI grounding
        $this->assertTrue($repo->search('ZIPPSTITCHWARRANTY99')->isEmpty());

        // 2. Explicit publish
        $publishRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/support/admin/knowledge/{$articleId}/publish");

        $publishRes->assertStatus(200)
            ->assertJsonPath('data.status', 'published');

        // 3. Now published -> accessible to AI grounding
        $results = $repo->search('ZIPPSTITCHWARRANTY99');
        $this->assertFalse($results->isEmpty());
        $this->assertEquals('Warranty Coverage Guide', $results->first()->title);
    }

    public function test_direct_update_cannot_publish_or_archive_article(): void
    {
        $article = SupportKnowledgeArticle::create([
            'title' => 'Draft Lifecycle Test',
            'slug' => 'draft-lifecycle-test',
            'category' => 'General',
            'language' => 'en',
            'content' => 'Content for lifecycle test.',
            'status' => 'draft',
            'version' => 1,
        ]);

        // Attempting to directly publish via update endpoint
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->patchJson("/api/v1/support/admin/knowledge/{$article->id}", [
                'status' => 'published',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'STATUS_IMMUTABLE_ON_UPDATE');

        // Attempting to directly archive via update endpoint
        $archiveResponse = $this->actingAs($this->adminUser, 'sanctum')
            ->patchJson("/api/v1/support/admin/knowledge/{$article->id}", [
                'status' => 'archived',
            ]);

        $archiveResponse->assertStatus(422)
            ->assertJsonPath('error.code', 'STATUS_IMMUTABLE_ON_UPDATE');

        $article->refresh();
        $this->assertEquals('draft', $article->status);
    }

    public function test_editing_published_content_creates_draft_version_preserving_live_content(): void
    {
        // 1. Create and publish initial article version 1
        $article = SupportKnowledgeArticle::create([
            'title' => 'VIP Club Discount Guide',
            'slug' => 'vip-club-discount',
            'category' => 'Promotions',
            'language' => 'en',
            'content' => 'Version 1: VIP_GOLD_TIER_TEN_PERCENT discount on all items.',
            'status' => 'published',
            'published_at' => now(),
            'version' => 1,
        ]);

        SupportKnowledgeArticleVersion::create([
            'article_id' => $article->id,
            'version' => 1,
            'title' => $article->title,
            'content' => $article->content,
            'created_by' => $this->adminUser->id,
        ]);

        $repo = new SupportKnowledgeRepository();

        // Runtime grounding returns Version 1 content
        $initialSearch = $repo->search('VIP_GOLD_TIER_TEN_PERCENT');
        $this->assertFalse($initialSearch->isEmpty());

        // 2. Admin edits published article to Version 2 content
        $updateRes = $this->actingAs($this->adminUser, 'sanctum')
            ->patchJson("/api/v1/support/admin/knowledge/{$article->id}", [
                'content' => 'Version 2 (Draft): VIP_PLATINUM_TIER_TWENTYFIVE_PERCENT discount on all items.',
            ]);

        $updateRes->assertStatus(200);

        // Verify Version 2 record was created in versions table
        $this->assertDatabaseHas('support_knowledge_article_versions', [
            'article_id' => $article->id,
            'version' => 2,
            'content' => 'Version 2 (Draft): VIP_PLATINUM_TIER_TWENTYFIVE_PERCENT discount on all items.',
        ]);

        // CRITICAL INVARIANT: Live runtime grounding STILL serves Version 1
        $liveSearch = $repo->search('VIP_GOLD_TIER_TEN_PERCENT');
        $this->assertFalse($liveSearch->isEmpty(), 'Live published content must remain unchanged until explicitly published.');
        
        $draftSearch = $repo->search('VIP_PLATINUM_TIER_TWENTYFIVE_PERCENT');
        $this->assertTrue($draftSearch->isEmpty(), 'Unpublished draft content must NOT enter runtime AI grounding.');

        // 3. Now explicitly publish the updated article
        $publishRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/support/admin/knowledge/{$article->id}/publish");

        $publishRes->assertStatus(200)
            ->assertJsonPath('data.version', 2);

        // Now runtime grounding serves Version 2
        $promotedSearch = $repo->search('VIP_PLATINUM_TIER_TWENTYFIVE_PERCENT');
        $this->assertFalse($promotedSearch->isEmpty());
    }

    public function test_rollback_is_non_destructive(): void
    {
        // 1. Create article and versions 1, 2, 3
        $article = SupportKnowledgeArticle::create([
            'title' => 'Sneaker Washing Guide',
            'slug' => 'sneaker-washing-guide',
            'category' => 'Products',
            'language' => 'en',
            'content' => 'Version 3: Use cold water only.',
            'status' => 'published',
            'published_at' => now(),
            'version' => 3,
        ]);

        SupportKnowledgeArticleVersion::create([
            'article_id' => $article->id,
            'version' => 1,
            'title' => $article->title,
            'content' => 'Version 1: Hand wash with mild soap and soft brush.',
            'created_by' => $this->adminUser->id,
        ]);

        SupportKnowledgeArticleVersion::create([
            'article_id' => $article->id,
            'version' => 2,
            'title' => $article->title,
            'content' => 'Version 2: Machine wash delicate cycle.',
            'created_by' => $this->adminUser->id,
        ]);

        SupportKnowledgeArticleVersion::create([
            'article_id' => $article->id,
            'version' => 3,
            'title' => $article->title,
            'content' => 'Version 3: Use cold water only.',
            'created_by' => $this->adminUser->id,
        ]);

        // Rollback from version 3 to version 1
        $rollbackRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/support/admin/knowledge/{$article->id}/rollback", [
                'target_version' => 1,
                'reason' => 'Restore classic hand wash guidance',
            ]);

        $rollbackRes->assertStatus(200)
            ->assertJsonPath('data.version', 4)
            ->assertJsonPath('data.content', 'Version 1: Hand wash with mild soap and soft brush.');

        // Verify version 1 is preserved and version 4 was created
        $this->assertDatabaseHas('support_knowledge_article_versions', [
            'article_id' => $article->id,
            'version' => 1,
        ]);
        $this->assertDatabaseHas('support_knowledge_article_versions', [
            'article_id' => $article->id,
            'version' => 4,
            'content' => 'Version 1: Hand wash with mild soap and soft brush.',
        ]);
    }

    public function test_multilingual_knowledge_fallback_safe_behavior(): void
    {
        // 1. Published English article
        SupportKnowledgeArticle::create([
            'title' => 'Store Hours',
            'slug' => 'store-hours',
            'category' => 'General',
            'language' => 'en',
            'content' => 'We are open Monday through Saturday from 9am to 8pm WAT.',
            'status' => 'published',
            'published_at' => now(),
            'version' => 1,
        ]);

        // 2. Draft Yoruba translation (MUST NOT BE GROUNDED)
        SupportKnowledgeArticle::create([
            'title' => 'Akoko Ṣiṣii Ile-itaja (Draft)',
            'slug' => 'store-hours',
            'category' => 'General',
            'language' => 'yo',
            'content' => 'A n ṣiṣẹ lati aago mẹsan owurọ titi di aago mẹjọ alẹ.',
            'status' => 'draft',
            'version' => 1,
        ]);

        $repo = new SupportKnowledgeRepository();

        // Query in Yoruba -> Draft is skipped, safely falls back to English published article
        $results = $repo->search('store hours', null, 'yo');
        $this->assertFalse($results->isEmpty());
        $this->assertEquals('en', $results->first()->language);

        // 3. Now publish Yoruba translation
        $yoArticle = SupportKnowledgeArticle::where('slug', 'store-hours')->where('language', 'yo')->first();
        $yoArticle->update(['status' => 'published', 'published_at' => now()]);

        // Now query in Yoruba -> returns matching published Yoruba article
        $yoResults = $repo->search('Akoko', null, 'yo');
        $this->assertFalse($yoResults->isEmpty());
        $this->assertEquals('yo', $yoResults->first()->language);
    }
}
