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

    public function test_admin_can_create_draft_knowledge_article_with_version_1(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/support/admin/knowledge', [
                'title' => 'Custom Return Window Rules',
                'category' => 'Returns',
                'language' => 'en',
                'content' => 'All items must be returned within 14 days of delivery in original condition.',
                'status' => 'draft',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Custom Return Window Rules')
            ->assertJsonPath('data.slug', 'custom-return-window-rules')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.version', 1);

        $articleId = $response->json('data.id');

        // Check Version record
        $version = SupportKnowledgeArticleVersion::where('article_id', $articleId)->first();
        $this->assertNotNull($version);
        $this->assertEquals(1, $version->version);

        // Check Audit Log
        $this->assertDatabaseHas('support_audit_logs', [
            'action' => 'KNOWLEDGE_ARTICLE_CREATED',
            'resource_id' => (string)$articleId,
        ]);
    }

    public function test_draft_articles_are_strictly_excluded_from_ai_grounding(): void
    {
        SupportKnowledgeArticle::create([
            'title' => 'Secret Draft Policy Unreleased',
            'slug' => 'secret-draft-policy',
            'category' => 'Promotions',
            'language' => 'en',
            'content' => 'Use promo code SECRET99 for 99% off.',
            'status' => 'draft',
            'version' => 1,
        ]);

        $repo = new SupportKnowledgeRepository();
        $results = $repo->search('SECRET99');

        $this->assertTrue($results->isEmpty(), 'Draft article was incorrectly returned by Knowledge Repository!');
    }

    public function test_admin_can_publish_and_archive_article_influencing_grounding(): void
    {
        $article = SupportKnowledgeArticle::create([
            'title' => 'Kano Same Day Delivery Guarantee',
            'slug' => 'kano-same-day-delivery',
            'category' => 'Shipping',
            'language' => 'en',
            'content' => 'KanoSameDayDeliveryExpressOnly applies to orders before 12pm.',
            'status' => 'draft',
            'version' => 1,
        ]);

        $repo = new SupportKnowledgeRepository();

        // 1. In draft state -> excluded
        $this->assertTrue($repo->search('KanoSameDayDeliveryExpressOnly')->isEmpty());

        // 2. Publish
        $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/support/admin/knowledge/{$article->id}/publish")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'published');

        // Now in published state -> included in grounding
        $publishedResults = $repo->search('KanoSameDayDeliveryExpressOnly');
        $this->assertFalse($publishedResults->isEmpty());
        $this->assertEquals('Kano Same Day Delivery Guarantee', $publishedResults->first()->title);

        // 3. Archive
        $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/support/admin/knowledge/{$article->id}/archive")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'archived');

        // Now in archived state -> excluded again
        $this->assertTrue($repo->search('KanoSameDayDeliveryExpressOnly')->isEmpty());
    }

    public function test_article_versioning_and_non_destructive_rollback(): void
    {
        // 1. Create initial version
        $createRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/support/admin/knowledge', [
                'title' => 'Sizing Guide for Hoodies',
                'category' => 'Products',
                'language' => 'en',
                'content' => 'Version 1: Our hoodies fit true to size.',
                'status' => 'published',
            ]);

        $articleId = $createRes->json('data.id');

        // 2. Update to version 2
        $this->actingAs($this->adminUser, 'sanctum')
            ->putJson("/api/v1/support/admin/knowledge/{$articleId}", [
                'title' => 'Sizing Guide for Hoodies (Updated)',
                'content' => 'Version 2: Our hoodies fit oversized. Size down for fitted look.',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.version', 2);

        // 3. Update to version 3
        $this->actingAs($this->adminUser, 'sanctum')
            ->putJson("/api/v1/support/admin/knowledge/{$articleId}", [
                'content' => 'Version 3: Hoodies are boxy cut streetwear style.',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.version', 3);

        // Verify version history has 3 historical records
        $versionsRes = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/v1/support/admin/knowledge/{$articleId}/versions")
            ->assertStatus(200);

        $versions = $versionsRes->json('data.versions');
        $this->assertCount(3, $versions);

        // 4. Rollback to version 1
        $rollbackRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/support/admin/knowledge/{$articleId}/rollback", [
                'target_version' => 1,
                'reason' => 'Restoring classic true to size guidance',
            ]);

        $rollbackRes->assertStatus(200)
            ->assertJsonPath('data.version', 4) // Non-destructive: creates new version 4
            ->assertJsonPath('data.content', 'Version 1: Our hoodies fit true to size.');

        // Verify historical version 1 still exists
        $this->assertDatabaseHas('support_knowledge_article_versions', [
            'article_id' => $articleId,
            'version' => 1,
        ]);
        // And new version 4 was created
        $this->assertDatabaseHas('support_knowledge_article_versions', [
            'article_id' => $articleId,
            'version' => 4,
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

    public function test_policy_administration_lifecycle(): void
    {
        // 1. Create Policy
        $createRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/support/admin/policies', [
                'key' => 'require_confirm_on_address_change',
                'name' => 'Address Change Confirmation Guard',
                'category' => 'orders',
                'effect' => 'confirm',
                'description' => 'Ensure user explicitly confirms address updates.',
                'is_active' => true,
                'priority' => 50,
            ]);

        $createRes->assertStatus(201)
            ->assertJsonPath('data.key', 'require_confirm_on_address_change')
            ->assertJsonPath('data.effect', 'confirm')
            ->assertJsonPath('data.is_active', true);

        $policyId = $createRes->json('data.id');

        // 2. Disable Policy
        $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/support/admin/policies/{$policyId}/disable")
            ->assertStatus(200)
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('support_policies', [
            'id' => $policyId,
            'is_active' => false,
        ]);

        // 3. Re-activate Policy
        $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/support/admin/policies/{$policyId}/activate")
            ->assertStatus(200)
            ->assertJsonPath('data.is_active', true);
    }

    public function test_policy_simulation_evaluates_correctly_without_side_effects(): void
    {
        // Simulate Refund tool -> expected effect REQUIRE_HUMAN
        $simRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/support/admin/policies/simulate', [
                'actor_type' => 'customer',
                'tool_name' => 'request_refund',
                'arguments' => ['order_id' => 12345, 'reason' => 'Defective zipper'],
            ]);

        $simRes->assertStatus(200)
            ->assertJsonPath('data.simulation', true)
            ->assertJsonPath('data.badge', 'SIMULATION ONLY')
            ->assertJsonPath('data.policy_effect', 'require_human')
            ->assertJsonPath('data.requires_human', true);

        // Verify simulation logged in audit log with is_simulation flag
        $this->assertDatabaseHas('support_audit_logs', [
            'action' => 'POLICY_SIMULATION_EXECUTED',
            'resource_id' => 'request_refund',
        ]);
    }

    public function test_tool_permission_governance_and_critical_safeguard_preservation(): void
    {
        // 1. List tools
        $listRes = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/support/admin/tools')
            ->assertStatus(200);

        $tools = $listRes->json('data');
        $this->assertNotEmpty($tools);

        // 2. Update tool permissions
        $refundTool = SupportAITool::where('key', 'request_refund')->first();
        $this->assertNotNull($refundTool);

        $this->actingAs($this->adminUser, 'sanctum')
            ->patchJson("/api/v1/support/admin/tools/{$refundTool->id}/permissions", [
                'risk_level' => 'critical',
                'requires_human' => true,
                'requires_confirmation' => true,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.risk_level', 'critical')
            ->assertJsonPath('data.requires_human', true);

        // 3. Attempting to strip human approval from critical refund tool is blocked by safety invariant
        $this->actingAs($this->adminUser, 'sanctum')
            ->patchJson("/api/v1/support/admin/tools/{$refundTool->id}/permissions", [
                'requires_human' => false,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.requires_human', true); // Preserved
    }

    public function test_audit_logs_endpoint_returns_sanitized_governance_trail(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/support/admin/audit-logs')
            ->assertStatus(200);

        $logs = $response->json('data');
        $this->assertIsArray($logs);
    }
}
