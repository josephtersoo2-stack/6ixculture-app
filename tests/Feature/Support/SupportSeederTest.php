<?php

namespace Tests\Feature\Support;

use App\Support\Enums\PolicyEffect;
use App\Support\Enums\ToolRiskLevel;
use App\Support\Models\SupportAITool;
use App\Support\Models\SupportDepartment;
use App\Support\Models\SupportPolicy;
use Database\Seeders\SupportDomainSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_domain_seeder_populates_departments_policies_and_tools(): void
    {
        $seeder = new SupportDomainSeeder();
        $seeder->run();

        // 1. Verify 7 seeded departments
        $expectedDepts = ['general-support', 'sales', 'orders', 'returns-refunds', 'payments', 'technical-support', 'wholesale'];
        foreach ($expectedDepts as $slug) {
            $dept = SupportDepartment::where('slug', $slug)->first();
            $this->assertNotNull($dept, "Department with slug {$slug} was not found");
            $this->assertTrue($dept->is_active);
        }

        // 2. Verify conservative baseline policies
        $customerScope = SupportPolicy::where('key', 'customer_data_scope')->first();
        $this->assertNotNull($customerScope);
        $this->assertEquals(PolicyEffect::ALLOW, $customerScope->effect);
        $this->assertTrue($customerScope->configuration['enforce_ownership']);

        $internalNotePolicy = SupportPolicy::where('key', 'internal_note_visibility')->first();
        $this->assertNotNull($internalNotePolicy);
        $this->assertEquals(PolicyEffect::DENY, $internalNotePolicy->effect);

        $refundPolicy = SupportPolicy::where('key', 'refund_requires_approval')->first();
        $this->assertNotNull($refundPolicy);
        $this->assertEquals(PolicyEffect::REQUIRE_HUMAN, $refundPolicy->effect);

        // 3. Verify AI tools registry
        $searchTool = SupportAITool::where('key', 'search_products')->first();
        $this->assertNotNull($searchTool);
        $this->assertEquals(ToolRiskLevel::LOW, $searchTool->risk_level);
        $this->assertFalse($searchTool->requires_confirmation);

        $cancelTool = SupportAITool::where('key', 'cancel_order')->first();
        $this->assertNotNull($cancelTool);
        $this->assertEquals(ToolRiskLevel::SENSITIVE, $cancelTool->risk_level);
        $this->assertTrue($cancelTool->requires_authentication);
        $this->assertTrue($cancelTool->requires_confirmation);

        $refundTool = SupportAITool::where('key', 'request_refund')->first();
        $this->assertNotNull($refundTool);
        $this->assertEquals(ToolRiskLevel::CRITICAL, $refundTool->risk_level);
        $this->assertTrue($refundTool->requires_human);
    }
}
