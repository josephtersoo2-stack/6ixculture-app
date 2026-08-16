<?php

namespace Tests\Feature\Support;

use App\Models\AiAgent;
use App\Models\Order;
use App\Models\User;
use App\Support\Enums\ConversationMode;
use App\Support\Enums\ConversationStatus;
use App\Support\Enums\MessageType;
use App\Support\Enums\SenderType;
use App\Support\Enums\SupportChannel;
use App\Support\Enums\SupportPriority;
use App\Support\Models\SupportAgentProfile;
use App\Support\Models\SupportAssignment;
use App\Support\Models\SupportConversation;
use App\Support\Models\SupportDepartment;
use App\Support\Models\SupportMessage;
use App\Support\Models\SupportTicket;
use Database\Seeders\SupportDomainSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgentSupportApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $ordersAgent;
    protected User $salesAgent;
    protected User $dualDeptAgent;
    protected User $customerUser;
    protected SupportDepartment $salesDept;
    protected SupportDepartment $ordersDept;
    protected SupportDepartment $billingDept;

    protected function setUp(): void
    {
        parent::setUp();

        putenv('OPENROUTER_API_KEY=test_openrouter_sk_key');
        putenv('GEMINI_API_KEY=test_gemini_api_key');

        AiAgent::create(['name' => 'OpenRouter', 'slug' => 'openrouter', 'status' => 5]);
        AiAgent::create(['name' => 'Gemini', 'slug' => 'gemini', 'status' => 5]);

        $this->seed([
            \Database\Seeders\RoleTableSeeder::class,
            SupportDomainSeeder::class,
        ]);

        // 1. Admin User
        $this->adminUser = User::factory()->create([
            'name' => 'Support Admin',
            'email' => 'admin@6ixculture.com',
            'username' => 'support_admin',
        ]);
        $this->adminUser->assignRole('Admin');

        $this->ordersDept = SupportDepartment::where('slug', 'orders')->first()
            ?? SupportDepartment::create(['name' => 'Orders', 'slug' => 'orders', 'is_active' => true]);

        $this->salesDept = SupportDepartment::where('slug', 'sales')->first()
            ?? SupportDepartment::create(['name' => 'Sales', 'slug' => 'sales', 'is_active' => true]);

        $this->billingDept = SupportDepartment::where('slug', 'billing')->first()
            ?? SupportDepartment::create(['name' => 'Billing', 'slug' => 'billing', 'is_active' => true]);

        // 2. Orders Department Agent Alex
        $this->ordersAgent = User::factory()->create([
            'name' => 'Agent Alex',
            'email' => 'alex@6ixculture.com',
            'username' => 'agent_alex',
        ]);
        $this->ordersAgent->assignRole('Stuff');

        $ordersProfile = SupportAgentProfile::create([
            'user_id' => $this->ordersAgent->id,
            'display_name' => 'Agent Alex',
            'status' => 'online',
            'availability' => 'available',
            'max_concurrent_conversations' => 5,
        ]);
        $ordersProfile->departments()->attach($this->ordersDept->id, ['is_primary' => true]);

        // 3. Sales Department Agent Sarah
        $this->salesAgent = User::factory()->create([
            'name' => 'Agent Sarah',
            'email' => 'sarah@6ixculture.com',
            'username' => 'agent_sarah',
        ]);
        $this->salesAgent->assignRole('Stuff');

        $salesProfile = SupportAgentProfile::create([
            'user_id' => $this->salesAgent->id,
            'display_name' => 'Agent Sarah',
            'status' => 'online',
            'availability' => 'available',
            'max_concurrent_conversations' => 5,
        ]);
        $salesProfile->departments()->attach($this->salesDept->id, ['is_primary' => true]);

        // 4. Dual Department Agent Sam (Orders + Billing)
        $this->dualDeptAgent = User::factory()->create([
            'name' => 'Agent Sam',
            'email' => 'sam@6ixculture.com',
            'username' => 'agent_sam',
        ]);
        $this->dualDeptAgent->assignRole('Stuff');

        $dualProfile = SupportAgentProfile::create([
            'user_id' => $this->dualDeptAgent->id,
            'display_name' => 'Agent Sam',
            'status' => 'online',
            'availability' => 'available',
            'max_concurrent_conversations' => 5,
        ]);
        $dualProfile->departments()->attach([
            $this->ordersDept->id => ['is_primary' => true],
            $this->billingDept->id => ['is_primary' => false],
        ]);

        // 5. Normal Customer User
        $this->customerUser = User::factory()->create([
            'name' => 'Customer Jane',
            'email' => 'jane@gmail.com',
            'username' => 'customer_jane',
        ]);
        $this->customerUser->assignRole('Customer');
    }

    /** @test */
    public function unauthenticated_requests_to_agent_endpoints_are_rejected(): void
    {
        $response = $this->getJson('/api/v1/support/agent/conversations');
        $response->assertStatus(401);
    }

    /** @test */
    public function regular_customer_is_forbidden_from_agent_console(): void
    {
        Sanctum::actingAs($this->customerUser);

        $response = $this->getJson('/api/v1/support/agent/conversations');
        $response->assertStatus(403)
            ->assertJson([
                'error' => [
                    'code' => 'SUPPORT_AGENT_FORBIDDEN',
                ]
            ]);
    }

    /** @test */
    public function agent_cannot_access_another_department_conversation(): void
    {
        Sanctum::actingAs($this->ordersAgent);

        // Create a conversation in the Sales department
        $salesConv = SupportConversation::create([
            'customer_id' => $this->customerUser->id,
            'department_id' => $this->salesDept->id,
            'status' => ConversationStatus::QUEUED,
            'priority' => SupportPriority::NORMAL,
            'channel' => SupportChannel::WEB,
            'mode' => ConversationMode::HUMAN,
            'subject' => 'Restock question for oversized hoodies',
        ]);

        $response = $this->getJson("/api/v1/support/agent/conversations/{$salesConv->public_id}");
        $response->assertStatus(403)
            ->assertJson([
                'error' => [
                    'code' => 'SUPPORT_AGENT_FORBIDDEN',
                ]
            ]);
    }

    /** @test */
    public function authorized_department_agent_can_access_its_conversation(): void
    {
        Sanctum::actingAs($this->ordersAgent);

        $ordersConv = SupportConversation::create([
            'customer_id' => $this->customerUser->id,
            'department_id' => $this->ordersDept->id,
            'status' => ConversationStatus::QUEUED,
            'priority' => SupportPriority::NORMAL,
            'channel' => SupportChannel::WEB,
            'mode' => ConversationMode::HUMAN,
            'subject' => 'Order #9921 delay',
        ]);

        $response = $this->getJson("/api/v1/support/agent/conversations/{$ordersConv->public_id}");
        $response->assertStatus(200)
            ->assertJsonPath('data.id', $ordersConv->public_id);
    }

    /** @test */
    public function assigned_agent_can_access_its_assigned_conversation_even_if_outside_department(): void
    {
        Sanctum::actingAs($this->ordersAgent);

        // Conversation in Sales dept, but specifically assigned to Alex
        $assignedConv = SupportConversation::create([
            'customer_id' => $this->customerUser->id,
            'department_id' => $this->salesDept->id,
            'assigned_agent_id' => $this->ordersAgent->id,
            'status' => ConversationStatus::HUMAN_ACTIVE,
            'priority' => SupportPriority::NORMAL,
            'channel' => SupportChannel::WEB,
            'mode' => ConversationMode::HUMAN,
            'subject' => 'Special sales consultation assigned to Alex',
        ]);

        $response = $this->getJson("/api/v1/support/agent/conversations/{$assignedConv->public_id}");
        $response->assertStatus(200)
            ->assertJsonPath('data.id', $assignedConv->public_id)
            ->assertJsonPath('data.assigned_agent.id', $this->ordersAgent->id);
    }

    /** @test */
    public function queue_department_id_filter_cannot_escape_authorization_scope(): void
    {
        Sanctum::actingAs($this->ordersAgent);

        // Create 1 conversation in Orders and 1 in Sales
        SupportConversation::create([
            'customer_id' => $this->customerUser->id,
            'department_id' => $this->ordersDept->id,
            'status' => ConversationStatus::QUEUED,
            'priority' => SupportPriority::NORMAL,
            'channel' => SupportChannel::WEB,
            'mode' => ConversationMode::HUMAN,
            'subject' => 'Orders convo',
        ]);

        SupportConversation::create([
            'customer_id' => $this->customerUser->id,
            'department_id' => $this->salesDept->id,
            'status' => ConversationStatus::QUEUED,
            'priority' => SupportPriority::NORMAL,
            'channel' => SupportChannel::WEB,
            'mode' => ConversationMode::HUMAN,
            'subject' => 'Sales convo',
        ]);

        // Orders agent lists queue: should ONLY see 1 conversation (Orders), never Sales
        $response = $this->getJson('/api/v1/support/agent/conversations');
        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('meta.total'));

        // Orders agent tries to pass ?department_id=salesDept: must return 0 results, not leak Sales
        $leakAttempt = $this->getJson("/api/v1/support/agent/conversations?department_id={$this->salesDept->id}");
        $leakAttempt->assertStatus(200);
        $this->assertEquals(0, $leakAttempt->json('meta.total'));
    }

    /** @test */
    public function agent_cannot_assign_to_a_normal_customer(): void
    {
        Sanctum::actingAs($this->ordersAgent);

        $conv = SupportConversation::create([
            'customer_id' => $this->customerUser->id,
            'department_id' => $this->ordersDept->id,
            'status' => ConversationStatus::QUEUED,
            'priority' => SupportPriority::NORMAL,
            'channel' => SupportChannel::WEB,
            'mode' => ConversationMode::HUMAN,
            'subject' => 'Assign test',
        ]);

        // Attempting to assign to a normal customer user
        $response = $this->postJson("/api/v1/support/agent/conversations/{$conv->public_id}/assign", [
            'agent_id' => $this->customerUser->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_ASSIGNMENT_TARGET');
    }

    /** @test */
    public function agent_cannot_assign_to_an_unauthorized_agent_from_another_department(): void
    {
        Sanctum::actingAs($this->ordersAgent);

        $conv = SupportConversation::create([
            'customer_id' => $this->customerUser->id,
            'department_id' => $this->ordersDept->id,
            'status' => ConversationStatus::QUEUED,
            'priority' => SupportPriority::NORMAL,
            'channel' => SupportChannel::WEB,
            'mode' => ConversationMode::HUMAN,
            'subject' => 'Cross dept assignment test',
        ]);

        // Attempting to assign Orders conversation to Sarah (who only belongs to Sales)
        $response = $this->postJson("/api/v1/support/agent/conversations/{$conv->public_id}/assign", [
            'agent_id' => $this->salesAgent->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'UNAUTHORIZED_ASSIGNMENT_TARGET');
    }

    /** @test */
    public function agent_can_claim_self_and_records_assignment_history(): void
    {
        Sanctum::actingAs($this->ordersAgent);

        $conv = SupportConversation::create([
            'customer_id' => $this->customerUser->id,
            'department_id' => $this->ordersDept->id,
            'status' => ConversationStatus::QUEUED,
            'priority' => SupportPriority::NORMAL,
            'channel' => SupportChannel::WEB,
            'mode' => ConversationMode::HUMAN,
            'subject' => 'Claim test',
        ]);

        $response = $this->postJson("/api/v1/support/agent/conversations/{$conv->public_id}/assign", [
            'agent_id' => 'self',
            'reason' => 'Claimed by Alex',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.assigned_agent.id', $this->ordersAgent->id)
            ->assertJsonPath('data.status', 'human_active');

        $this->assertDatabaseHas('support_assignments', [
            'conversation_id' => $conv->id,
            'agent_id' => $this->ordersAgent->id,
            'assigned_by' => $this->ordersAgent->id,
        ]);
    }

    /** @test */
    public function non_elevated_agent_cannot_transfer_to_unauthorized_target_department(): void
    {
        Sanctum::actingAs($this->ordersAgent);

        // Orders conversation that Alex IS authorized for, but target department is Sales (unauthorized for Alex)
        $ordersConv = SupportConversation::create([
            'customer_id' => $this->customerUser->id,
            'department_id' => $this->ordersDept->id,
            'status' => ConversationStatus::HUMAN_ACTIVE,
            'priority' => SupportPriority::NORMAL,
            'channel' => SupportChannel::WEB,
            'mode' => ConversationMode::HUMAN,
            'subject' => 'Unauthorized target department transfer attempt',
        ]);

        $response = $this->patchJson("/api/v1/support/agent/conversations/{$ordersConv->public_id}/department", [
            'department_id' => $this->salesDept->id,
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'SUPPORT_AGENT_FORBIDDEN');
    }

    /** @test */
    public function non_elevated_agent_can_transfer_to_authorized_target_department(): void
    {
        Sanctum::actingAs($this->dualDeptAgent);

        // Sam is authorized for both Orders and Billing
        $ordersConv = SupportConversation::create([
            'customer_id' => $this->customerUser->id,
            'department_id' => $this->ordersDept->id,
            'assigned_agent_id' => $this->dualDeptAgent->id,
            'status' => ConversationStatus::HUMAN_ACTIVE,
            'priority' => SupportPriority::NORMAL,
            'channel' => SupportChannel::WEB,
            'mode' => ConversationMode::HUMAN,
            'subject' => 'Transfer from orders to billing',
        ]);

        $response = $this->patchJson("/api/v1/support/agent/conversations/{$ordersConv->public_id}/department", [
            'department_id' => $this->billingDept->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.department.id', $this->billingDept->id);

        $ordersConv->refresh();
        $this->assertEquals($this->billingDept->id, $ordersConv->department_id);
    }

    /** @test */
    public function elevated_admin_can_transfer_across_any_departments(): void
    {
        Sanctum::actingAs($this->adminUser);

        $ordersConv = SupportConversation::create([
            'customer_id' => $this->customerUser->id,
            'department_id' => $this->ordersDept->id,
            'status' => ConversationStatus::HUMAN_ACTIVE,
            'priority' => SupportPriority::NORMAL,
            'channel' => SupportChannel::WEB,
            'mode' => ConversationMode::HUMAN,
            'subject' => 'Admin transfer across departments',
        ]);

        $response = $this->patchJson("/api/v1/support/agent/conversations/{$ordersConv->public_id}/department", [
            'department_id' => $this->salesDept->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.department.id', $this->salesDept->id);

        $ordersConv->refresh();
        $this->assertEquals($this->salesDept->id, $ordersConv->department_id);
    }

    /** @test */
    public function department_scoped_agent_directory_excludes_another_departments_agent(): void
    {
        Sanctum::actingAs($this->ordersAgent);

        $response = $this->getJson('/api/v1/support/agent/agents');
        $response->assertStatus(200);

        $agentEmails = collect($response->json('data'))->pluck('email')->toArray();

        // Alex (Orders) and Sam (Orders+Billing) and Admin appear
        $this->assertContains('alex@6ixculture.com', $agentEmails);
        $this->assertContains('sam@6ixculture.com', $agentEmails);
        $this->assertContains('admin@6ixculture.com', $agentEmails);

        // Sarah (Sales only) must NOT appear in Alex's directory
        $this->assertNotContains('sarah@6ixculture.com', $agentEmails);
    }

    /** @test */
    public function elevated_directory_visibility_includes_all_agents(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/v1/support/agent/agents');
        $response->assertStatus(200);

        $agentEmails = collect($response->json('data'))->pluck('email')->toArray();

        // Admin sees all agents across all departments
        $this->assertContains('alex@6ixculture.com', $agentEmails);
        $this->assertContains('sarah@6ixculture.com', $agentEmails);
        $this->assertContains('sam@6ixculture.com', $agentEmails);
        $this->assertContains('admin@6ixculture.com', $agentEmails);
    }

    /** @test */
    public function directory_results_and_assignment_eligibility_agree(): void
    {
        Sanctum::actingAs($this->ordersAgent);

        // 1. In directory, Alex can see Sam (Orders) but not Sarah (Sales)
        $directoryResponse = $this->getJson('/api/v1/support/agent/agents');
        $directoryEmails = collect($directoryResponse->json('data'))->pluck('email')->toArray();
        $this->assertContains('sam@6ixculture.com', $directoryEmails);
        $this->assertNotContains('sarah@6ixculture.com', $directoryEmails);

        // 2. Orders conversation
        $conv = SupportConversation::create([
            'customer_id' => $this->customerUser->id,
            'department_id' => $this->ordersDept->id,
            'status' => ConversationStatus::QUEUED,
            'priority' => SupportPriority::NORMAL,
            'channel' => SupportChannel::WEB,
            'mode' => ConversationMode::HUMAN,
            'subject' => 'Directory consistency test',
        ]);

        // 3. Assigning to Sam (in directory) succeeds
        $assignSam = $this->postJson("/api/v1/support/agent/conversations/{$conv->public_id}/assign", [
            'agent_id' => $this->dualDeptAgent->id,
        ]);
        $assignSam->assertStatus(200)
            ->assertJsonPath('data.assigned_agent.id', $this->dualDeptAgent->id);

        // 4. Assigning to Sarah (excluded from directory) fails with 422
        $assignSarah = $this->postJson("/api/v1/support/agent/conversations/{$conv->public_id}/assign", [
            'agent_id' => $this->salesAgent->id,
        ]);
        $assignSarah->assertStatus(422)
            ->assertJsonPath('error.code', 'UNAUTHORIZED_ASSIGNMENT_TARGET');
    }

    /** @test */
    public function unauthorized_agent_cannot_access_customer_360_orders_or_ticket(): void
    {
        Sanctum::actingAs($this->ordersAgent);

        // Sales conversation
        $salesConv = SupportConversation::create([
            'customer_id' => $this->customerUser->id,
            'department_id' => $this->salesDept->id,
            'status' => ConversationStatus::QUEUED,
            'priority' => SupportPriority::NORMAL,
            'channel' => SupportChannel::WEB,
            'mode' => ConversationMode::HUMAN,
            'subject' => 'Restricted context test',
        ]);

        $this->getJson("/api/v1/support/agent/conversations/{$salesConv->public_id}/customer")->assertStatus(403);
        $this->getJson("/api/v1/support/agent/conversations/{$salesConv->public_id}/orders")->assertStatus(403);
        $this->getJson("/api/v1/support/agent/conversations/{$salesConv->public_id}/ticket")->assertStatus(403);
    }

    /** @test */
    public function agent_reply_creates_customer_visible_message_and_updates_status(): void
    {
        Sanctum::actingAs($this->ordersAgent);

        $conv = SupportConversation::create([
            'customer_id' => $this->customerUser->id,
            'department_id' => $this->ordersDept->id,
            'status' => ConversationStatus::HUMAN_ACTIVE,
            'priority' => SupportPriority::NORMAL,
            'channel' => SupportChannel::WEB,
            'mode' => ConversationMode::HUMAN,
            'subject' => 'Tracking info',
        ]);

        $replyText = 'Hi Jane, your order has been dispatched via DHL Express with tracking #DHL-8899.';
        $response = $this->postJson("/api/v1/support/agent/conversations/{$conv->public_id}/reply", [
            'message' => $replyText,
            'resolve_after_reply' => false,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'awaiting_customer');

        $this->assertDatabaseHas('support_messages', [
            'conversation_id' => $conv->id,
            'sender_type' => SenderType::AGENT->value,
            'content' => $replyText,
            'is_internal' => false,
        ]);

        // Customer can see this message
        Sanctum::actingAs($this->customerUser);
        $customerResponse = $this->getJson("/api/v1/support/conversations/{$conv->public_id}/messages");
        $customerResponse->assertStatus(200)
            ->assertJsonFragment(['content' => $replyText]);
    }

    /** @test */
    public function internal_staff_notes_are_strictly_isolated_from_customer_api(): void
    {
        Sanctum::actingAs($this->ordersAgent);

        $conv = SupportConversation::create([
            'customer_id' => $this->customerUser->id,
            'department_id' => $this->ordersDept->id,
            'status' => ConversationStatus::HUMAN_ACTIVE,
            'priority' => SupportPriority::HIGH,
            'channel' => SupportChannel::WEB,
            'mode' => ConversationMode::HUMAN,
            'subject' => 'Suspected courier theft',
        ]);

        $privateNote = 'CONFIDENTIAL: Logistics partner confirmed driver route investigation. Do NOT disclose driver name.';
        $response = $this->postJson("/api/v1/support/agent/conversations/{$conv->public_id}/notes", [
            'content' => $privateNote,
        ]);

        $response->assertStatus(200);

        // 1. Authorized agent can see internal note
        $agentView = $this->getJson("/api/v1/support/agent/conversations/{$conv->public_id}");
        $agentView->assertStatus(200)
            ->assertJsonFragment([
                'content' => $privateNote,
                'is_internal' => true,
            ]);

        // 2. Customer API NEVER sees internal note
        Sanctum::actingAs($this->customerUser);
        $this->getJson("/api/v1/support/conversations/{$conv->public_id}/messages")
            ->assertStatus(200)
            ->assertJsonMissing(['content' => $privateNote]);

        $this->getJson("/api/v1/support/conversations/{$conv->public_id}")
            ->assertStatus(200)
            ->assertJsonMissing(['content' => $privateNote]);
    }

    /** @test */
    public function actual_ai_support_orchestrator_path_is_invoked_for_summary_and_persisted(): void
    {
        Sanctum::actingAs($this->ordersAgent);

        $conv = SupportConversation::create([
            'customer_id' => $this->customerUser->id,
            'department_id' => $this->ordersDept->id,
            'status' => ConversationStatus::HUMAN_ACTIVE,
            'priority' => SupportPriority::NORMAL,
            'channel' => SupportChannel::WEB,
            'mode' => ConversationMode::HUMAN,
            'subject' => 'Delayed order inquiry',
        ]);

        SupportMessage::create([
            'conversation_id' => $conv->id,
            'sender_type' => SenderType::CUSTOMER,
            'sender_id' => $this->customerUser->id,
            'message_type' => MessageType::TEXT,
            'content' => 'My order #6IX-4029 has not arrived yet. It was supposed to be delivered 2 days ago.',
            'is_internal' => false,
        ]);

        $structuredSummary = "Customer Issue: Order #6IX-4029 delivery delay.\nDetected Intent: Order Tracking.\nLanguage: EN.\nRelevant Order: #6IX-4029.\nKey Facts: Delivery 2 days overdue.\nActions Already Taken: Customer reported delay.\nCurrent Status: human_active in Orders, Priority: normal.\nReason for Escalation: Customer requested shipment verification.\nRecommended Next Step: Check courier tracking and reassure customer.";

        Http::fake([
            'openrouter.ai/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => $structuredSummary,
                        ],
                        'finish_reason' => 'stop',
                    ]
                ]
            ], 200)
        ]);

        $response = $this->postJson("/api/v1/support/agent/conversations/{$conv->public_id}/summarize");

        $response->assertStatus(200)
            ->assertJsonPath('data.ai_summary', $structuredSummary);

        $conv->refresh();
        $this->assertEquals($structuredSummary, $conv->ai_summary);

        // Verify audit log recorded
        $this->assertDatabaseHas('support_audit_logs', [
            'conversation_id' => $conv->id,
            'action' => 'GENERATE_AI_SUMMARY',
            'authorization_result' => 'ALLOW',
        ]);
    }

    /** @test */
    public function provider_failure_during_summary_returns_safe_error_without_damaging_conversation(): void
    {
        Sanctum::actingAs($this->ordersAgent);

        $conv = SupportConversation::create([
            'customer_id' => $this->customerUser->id,
            'department_id' => $this->ordersDept->id,
            'status' => ConversationStatus::HUMAN_ACTIVE,
            'priority' => SupportPriority::NORMAL,
            'channel' => SupportChannel::WEB,
            'mode' => ConversationMode::HUMAN,
            'subject' => 'Provider error test',
            'ai_summary' => 'Existing safe summary',
        ]);

        // Provider returns 500 error
        Http::fake([
            'openrouter.ai/*' => Http::response(['error' => 'Rate limit exceeded'], 429)
        ]);

        $response = $this->postJson("/api/v1/support/agent/conversations/{$conv->public_id}/summarize");

        $response->assertStatus(502)
            ->assertJsonPath('error.code', 'AI_SUMMARY_PROVIDER_ERROR');

        // Conversation data must remain intact and unharmed
        $conv->refresh();
        $this->assertEquals('Existing safe summary', $conv->ai_summary);
        $this->assertEquals(ConversationStatus::HUMAN_ACTIVE, $conv->status);
    }
}
