<?php

namespace Tests\Feature\Support;

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
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgentSupportApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $agentUser;
    protected User $customerUser;
    protected SupportDepartment $salesDept;
    protected SupportDepartment $ordersDept;

    protected function setUp(): void
    {
        parent::setUp();

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

        // 2. Dedicated Agent User with Profile
        $this->agentUser = User::factory()->create([
            'name' => 'Agent Alex',
            'email' => 'alex@6ixculture.com',
            'username' => 'agent_alex',
        ]);
        $this->agentUser->assignRole('Stuff');

        $this->ordersDept = SupportDepartment::where('slug', 'orders')->first()
            ?? SupportDepartment::create(['name' => 'Orders', 'slug' => 'orders', 'is_active' => true]);

        $this->salesDept = SupportDepartment::where('slug', 'sales')->first()
            ?? SupportDepartment::create(['name' => 'Sales', 'slug' => 'sales', 'is_active' => true]);

        $profile = SupportAgentProfile::create([
            'user_id' => $this->agentUser->id,
            'display_name' => 'Agent Alex',
            'status' => 'online',
            'availability' => 'available',
            'max_concurrent_conversations' => 5,
        ]);
        $profile->departments()->attach($this->ordersDept->id, ['is_primary' => true]);

        // 3. Normal Customer User
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
    public function agent_can_list_conversations_queue_with_filters(): void
    {
        Sanctum::actingAs($this->agentUser);

        // Create conversations with different statuses & departments
        $conv1 = SupportConversation::create([
            'customer_id' => $this->customerUser->id,
            'department_id' => $this->ordersDept->id,
            'status' => ConversationStatus::QUEUED,
            'priority' => SupportPriority::HIGH,
            'channel' => SupportChannel::WEB,
            'mode' => ConversationMode::HUMAN,
            'subject' => 'Where is my order #1001?',
        ]);

        $conv2 = SupportConversation::create([
            'customer_id' => $this->customerUser->id,
            'department_id' => $this->salesDept->id,
            'status' => ConversationStatus::RESOLVED,
            'priority' => SupportPriority::LOW,
            'channel' => SupportChannel::WEB,
            'mode' => ConversationMode::AI,
            'subject' => 'Sizing question',
        ]);

        // 1. All active filter
        $response = $this->getJson('/api/v1/support/agent/conversations');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'status', 'mode', 'priority', 'customer', 'department', 'last_message']
                ],
                'meta' => ['current_page', 'total']
            ]);

        $this->assertEquals(2, $response->json('meta.total'));

        // 2. Department filter
        $filteredResponse = $this->getJson("/api/v1/support/agent/conversations?department_id={$this->ordersDept->id}");
        $filteredResponse->assertStatus(200);
        $this->assertEquals(1, $filteredResponse->json('meta.total'));
        $this->assertEquals($conv1->public_id, $filteredResponse->json('data.0.id'));

        // 3. Status filter
        $statusResponse = $this->getJson('/api/v1/support/agent/conversations?status=resolved');
        $statusResponse->assertStatus(200);
        $this->assertEquals(1, $statusResponse->json('meta.total'));
        $this->assertEquals($conv2->public_id, $statusResponse->json('data.0.id'));
    }

    /** @test */
    public function agent_can_view_full_conversation_details(): void
    {
        Sanctum::actingAs($this->agentUser);

        $conv = SupportConversation::create([
            'customer_id' => $this->customerUser->id,
            'department_id' => $this->ordersDept->id,
            'status' => ConversationStatus::HUMAN_ACTIVE,
            'priority' => SupportPriority::NORMAL,
            'channel' => SupportChannel::WEB,
            'mode' => ConversationMode::HUMAN,
            'subject' => 'Delivery delay',
        ]);

        SupportMessage::create([
            'conversation_id' => $conv->id,
            'sender_type' => SenderType::CUSTOMER,
            'sender_id' => $this->customerUser->id,
            'message_type' => MessageType::TEXT,
            'content' => 'Hello, is my package coming today?',
            'is_internal' => false,
        ]);

        $response = $this->getJson("/api/v1/support/agent/conversations/{$conv->public_id}");
        $response->assertStatus(200)
            ->assertJsonPath('data.id', $conv->public_id)
            ->assertJsonPath('data.customer.name', $this->customerUser->name)
            ->assertJsonCount(1, 'data.messages');
    }

    /** @test */
    public function agent_can_claim_and_assign_conversation_and_records_assignment_history(): void
    {
        Sanctum::actingAs($this->agentUser);

        $conv = SupportConversation::create([
            'customer_id' => $this->customerUser->id,
            'department_id' => $this->ordersDept->id,
            'status' => ConversationStatus::QUEUED,
            'priority' => SupportPriority::NORMAL,
            'channel' => SupportChannel::WEB,
            'mode' => ConversationMode::HUMAN,
            'subject' => 'Unassigned inquiry',
        ]);

        $this->assertNull($conv->assigned_agent_id);

        // Self-assign
        $response = $this->postJson("/api/v1/support/agent/conversations/{$conv->public_id}/assign", [
            'agent_id' => 'self',
            'reason' => 'Claimed by agent Alex for immediate processing',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.assigned_agent.id', $this->agentUser->id)
            ->assertJsonPath('data.status', 'human_active');

        $conv->refresh();
        $this->assertEquals($this->agentUser->id, $conv->assigned_agent_id);
        $this->assertEquals(ConversationStatus::HUMAN_ACTIVE, $conv->status);

        // Verify assignment history in SupportAssignment
        $this->assertDatabaseHas('support_assignments', [
            'conversation_id' => $conv->id,
            'assigned_by' => $this->agentUser->id,
            'agent_id' => $this->agentUser->id,
        ]);

        // Verify system message generated in timeline
        $this->assertDatabaseHas('support_messages', [
            'conversation_id' => $conv->id,
            'sender_type' => SenderType::SYSTEM->value,
        ]);
    }

    /** @test */
    public function agent_reply_creates_customer_visible_message_and_updates_status(): void
    {
        Sanctum::actingAs($this->agentUser);

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

        // Check message in database
        $this->assertDatabaseHas('support_messages', [
            'conversation_id' => $conv->id,
            'sender_type' => SenderType::AGENT->value,
            'content' => $replyText,
            'is_internal' => false,
        ]);

        // Customer API verification: Customer can see this agent message
        Sanctum::actingAs($this->customerUser);
        $customerResponse = $this->getJson("/api/v1/support/conversations/{$conv->public_id}/messages");
        $customerResponse->assertStatus(200)
            ->assertJsonFragment(['content' => $replyText]);
    }

    /** @test */
    public function internal_staff_notes_are_strictly_isolated_from_customer_api(): void
    {
        Sanctum::actingAs($this->agentUser);

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

        // 1. Agent console CAN see the internal note
        $agentView = $this->getJson("/api/v1/support/agent/conversations/{$conv->public_id}");
        $agentView->assertStatus(200)
            ->assertJsonFragment([
                'content' => $privateNote,
                'is_internal' => true,
            ]);

        // 2. CRITICAL REGRESSION TEST: Customer API MUST NEVER return this internal note
        Sanctum::actingAs($this->customerUser);

        $customerMessages = $this->getJson("/api/v1/support/conversations/{$conv->public_id}/messages");
        $customerMessages->assertStatus(200)
            ->assertJsonMissing(['content' => $privateNote]);

        $customerDetail = $this->getJson("/api/v1/support/conversations/{$conv->public_id}");
        $customerDetail->assertStatus(200)
            ->assertJsonMissing(['content' => $privateNote]);
    }

    /** @test */
    public function agent_can_update_status_and_priority_and_department(): void
    {
        Sanctum::actingAs($this->agentUser);

        $conv = SupportConversation::create([
            'customer_id' => $this->customerUser->id,
            'department_id' => $this->ordersDept->id,
            'status' => ConversationStatus::HUMAN_ACTIVE,
            'priority' => SupportPriority::NORMAL,
            'channel' => SupportChannel::WEB,
            'mode' => ConversationMode::HUMAN,
            'subject' => 'Status transition test',
        ]);

        // 1. Update status to resolved
        $statusRes = $this->patchJson("/api/v1/support/agent/conversations/{$conv->public_id}/status", [
            'status' => 'resolved',
        ]);
        $statusRes->assertStatus(200)->assertJsonPath('data.status', 'resolved');
        $conv->refresh();
        $this->assertNotNull($conv->resolved_at);

        // 2. Update priority to urgent
        $priorityRes = $this->patchJson("/api/v1/support/agent/conversations/{$conv->public_id}/priority", [
            'priority' => 'urgent',
        ]);
        $priorityRes->assertStatus(200)->assertJsonPath('data.priority', 'urgent');

        // 3. Transfer department to sales
        $deptRes = $this->patchJson("/api/v1/support/agent/conversations/{$conv->public_id}/department", [
            'department_id' => $this->salesDept->id,
        ]);
        $deptRes->assertStatus(200)->assertJsonPath('data.department.id', $this->salesDept->id);
    }

    /** @test */
    public function customer_360_endpoint_returns_metrics_and_recent_orders(): void
    {
        Sanctum::actingAs($this->agentUser);

        $conv = SupportConversation::create([
            'customer_id' => $this->customerUser->id,
            'department_id' => $this->ordersDept->id,
            'status' => ConversationStatus::HUMAN_ACTIVE,
            'priority' => SupportPriority::NORMAL,
            'channel' => SupportChannel::WEB,
            'mode' => ConversationMode::HUMAN,
            'subject' => '360 context test',
        ]);

        $response = $this->getJson("/api/v1/support/agent/conversations/{$conv->public_id}/customer");
        $response->assertStatus(200)
            ->assertJsonPath('data.id', $this->customerUser->id)
            ->assertJsonPath('data.name', $this->customerUser->name)
            ->assertJsonPath('data.email', $this->customerUser->email)
            ->assertJsonStructure(['data' => ['total_orders', 'total_spend', 'open_tickets_count']]);
    }

    /** @test */
    public function agent_can_generate_ai_summary_and_fetch_departments_and_agents(): void
    {
        Sanctum::actingAs($this->agentUser);

        $conv = SupportConversation::create([
            'customer_id' => $this->customerUser->id,
            'department_id' => $this->ordersDept->id,
            'status' => ConversationStatus::HUMAN_ACTIVE,
            'priority' => SupportPriority::NORMAL,
            'channel' => SupportChannel::WEB,
            'mode' => ConversationMode::HUMAN,
            'subject' => 'AI summary test',
        ]);

        // Summarize
        $sumRes = $this->postJson("/api/v1/support/agent/conversations/{$conv->public_id}/summarize");
        $sumRes->assertStatus(200)
            ->assertJsonStructure(['data' => ['ai_summary']]);

        // Departments
        $deptRes = $this->getJson('/api/v1/support/agent/departments');
        $deptRes->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'name', 'slug']]]);

        // Agents
        $agentRes = $this->getJson('/api/v1/support/agent/agents');
        $agentRes->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'name', 'email']]]);
    }
}
