<?php

namespace Tests\Feature\Support;

use App\Models\User;
use App\Support\Enums\ConversationMode;
use App\Support\Enums\ConversationStatus;
use App\Support\Enums\MessageType;
use App\Support\Enums\SenderType;
use App\Support\Enums\SupportChannel;
use App\Support\Enums\SupportPriority;
use App\Support\Events\SupportAgentPresenceChanged;
use App\Support\Events\SupportConversationUpdated;
use App\Support\Events\SupportMessageCreated;
use App\Support\Events\SupportQueueUpdated;
use App\Support\Models\SupportAgentProfile;
use App\Support\Models\SupportConversation;
use App\Support\Models\SupportDepartment;
use App\Support\Models\SupportMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RealtimeAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer1;
    protected User $customer2;
    protected User $agent1;
    protected User $agent2;
    protected User $adminUser;
    protected User $managerUser;
    protected SupportDepartment $deptReturns;
    protected SupportDepartment $deptSales;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Customer', 'guard_name' => 'sanctum']);
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'sanctum']);
        Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'sanctum']);
        Role::firstOrCreate(['name' => 'Support Agent', 'guard_name' => 'sanctum']);

        $this->customer1 = User::factory()->create([
            'name' => 'Customer 1',
            'email' => 'cust1@example.com',
            'username' => 'cust1',
        ]);
        $this->customer1->assignRole('Customer');

        $this->customer2 = User::factory()->create([
            'name' => 'Customer 2',
            'email' => 'cust2@example.com',
            'username' => 'cust2',
        ]);
        $this->customer2->assignRole('Customer');

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

        $this->deptReturns = SupportDepartment::create([
            'name' => 'Returns & Refunds',
            'slug' => 'returns',
            'is_active' => true,
        ]);

        $this->deptSales = SupportDepartment::create([
            'name' => 'Sales & Sizing',
            'slug' => 'sales',
            'is_active' => true,
        ]);

        $this->agent1 = User::factory()->create([
            'name' => 'Agent Returns',
            'email' => 'agent1@example.com',
            'username' => 'agent_returns',
        ]);
        $this->agent1->assignRole('Support Agent');
        $p1 = SupportAgentProfile::create(['user_id' => $this->agent1->id, 'is_active' => true, 'status' => 'online']);
        $p1->departments()->attach($this->deptReturns->id, ['is_primary' => true]);

        $this->agent2 = User::factory()->create([
            'name' => 'Agent Sales',
            'email' => 'agent2@example.com',
            'username' => 'agent_sales',
        ]);
        $this->agent2->assignRole('Support Agent');
        $p2 = SupportAgentProfile::create(['user_id' => $this->agent2->id, 'is_active' => true, 'status' => 'online']);
        $p2->departments()->attach($this->deptSales->id, ['is_primary' => true]);
    }

    protected function getChannelCallback(string $channelPattern): ?\Closure
    {
        $broadcaster = Broadcast::driver();
        $ref = new \ReflectionClass($broadcaster);
        if ($ref->hasProperty('channels')) {
            $prop = $ref->getProperty('channels');
            $prop->setAccessible(true);
            $channels = $prop->getValue($broadcaster);
            return $channels[$channelPattern] ?? null;
        }
        return null;
    }

    public function test_customer_can_authorize_own_conversation_channel(): void
    {
        $conv = SupportConversation::create([
            'customer_id' => $this->customer1->id,
            'status' => ConversationStatus::AI_ACTIVE,
            'mode' => ConversationMode::AI,
            'channel' => SupportChannel::WEB,
            'priority' => SupportPriority::NORMAL,
        ]);

        $callback = $this->getChannelCallback('support.conversation.{publicId}');
        $this->assertNotNull($callback);

        // Customer 1 owns conversation
        $this->assertTrue((bool)$callback($this->customer1, $conv->public_id));
    }

    public function test_customer_is_denied_another_customers_conversation_channel(): void
    {
        $conv = SupportConversation::create([
            'customer_id' => $this->customer1->id,
            'status' => ConversationStatus::AI_ACTIVE,
            'mode' => ConversationMode::AI,
            'channel' => SupportChannel::WEB,
            'priority' => SupportPriority::NORMAL,
        ]);

        $callback = $this->getChannelCallback('support.conversation.{publicId}');

        // Customer 2 attempts to subscribe
        $this->assertFalse((bool)$callback($this->customer2, $conv->public_id));
    }

    public function test_guest_realtime_authorization_with_valid_token(): void
    {
        $guestConv = SupportConversation::create([
            'guest_session_id' => 'valid-secret-guest-token-12345',
            'status' => ConversationStatus::AI_ACTIVE,
            'mode' => ConversationMode::AI,
            'channel' => SupportChannel::WEB,
            'priority' => SupportPriority::NORMAL,
        ]);

        $callback = $this->getChannelCallback('support.guest.conversation.{publicId}');
        $this->assertNotNull($callback);

        // Simulate request with header X-Guest-Token
        $request = Request::create('/broadcasting/auth', 'POST', [], [], [], [
            'HTTP_X_GUEST_TOKEN' => 'valid-secret-guest-token-12345',
        ]);
        $this->app->instance('request', $request);

        $this->assertTrue((bool)$callback(null, $guestConv->public_id));
    }

    public function test_guest_realtime_authorization_with_wrong_or_missing_token_is_denied(): void
    {
        $guestConv = SupportConversation::create([
            'guest_session_id' => 'valid-secret-guest-token-12345',
            'status' => ConversationStatus::AI_ACTIVE,
            'mode' => ConversationMode::AI,
            'channel' => SupportChannel::WEB,
            'priority' => SupportPriority::NORMAL,
        ]);

        $callback = $this->getChannelCallback('support.guest.conversation.{publicId}');

        // 1. Missing token
        $requestMissing = Request::create('/broadcasting/auth', 'POST');
        $this->app->instance('request', $requestMissing);
        $this->assertFalse((bool)$callback(null, $guestConv->public_id));

        // 2. Wrong token
        $requestWrong = Request::create('/broadcasting/auth', 'POST', [], [], [], [
            'HTTP_X_GUEST_TOKEN' => 'wrong-token-999',
        ]);
        $this->app->instance('request', $requestWrong);
        $this->assertFalse((bool)$callback(null, $guestConv->public_id));
    }

    public function test_guest_token_cannot_authorize_another_conversation(): void
    {
        $guestConv1 = SupportConversation::create([
            'guest_session_id' => 'token-conversation-one',
            'status' => ConversationStatus::AI_ACTIVE,
            'mode' => ConversationMode::AI,
            'channel' => SupportChannel::WEB,
        ]);

        $guestConv2 = SupportConversation::create([
            'guest_session_id' => 'token-conversation-two',
            'status' => ConversationStatus::AI_ACTIVE,
            'mode' => ConversationMode::AI,
            'channel' => SupportChannel::WEB,
        ]);

        $callback = $this->getChannelCallback('support.guest.conversation.{publicId}');

        // Token 1 passed to Conversation 2
        $request = Request::create('/broadcasting/auth', 'POST', [], [], [], [
            'HTTP_X_GUEST_TOKEN' => 'token-conversation-one',
        ]);
        $this->app->instance('request', $request);

        $this->assertFalse((bool)$callback(null, $guestConv2->public_id));
    }

    public function test_department_scoped_agent_can_authorize_conversation_in_own_department(): void
    {
        $conv = SupportConversation::create([
            'customer_id' => $this->customer1->id,
            'department_id' => $this->deptReturns->id,
            'status' => ConversationStatus::QUEUED,
            'mode' => ConversationMode::HUMAN,
            'channel' => SupportChannel::WEB,
            'priority' => SupportPriority::NORMAL,
        ]);

        $callback = $this->getChannelCallback('support.conversation.{publicId}');

        // Agent 1 is in Returns department
        $this->assertTrue((bool)$callback($this->agent1, $conv->public_id));

        // Agent 2 is in Sales department (cannot authorize Returns conversation unless assigned)
        $this->assertFalse((bool)$callback($this->agent2, $conv->public_id));
    }

    public function test_assigned_agent_can_authorize_even_if_outside_department(): void
    {
        $conv = SupportConversation::create([
            'customer_id' => $this->customer1->id,
            'department_id' => $this->deptReturns->id,
            'assigned_agent_id' => $this->agent2->id,
            'status' => ConversationStatus::HUMAN_ACTIVE,
            'mode' => ConversationMode::HUMAN,
            'channel' => SupportChannel::WEB,
            'priority' => SupportPriority::NORMAL,
        ]);

        $callback = $this->getChannelCallback('support.conversation.{publicId}');

        // Agent 2 is assigned
        $this->assertTrue((bool)$callback($this->agent2, $conv->public_id));
    }

    public function test_elevated_admin_and_manager_can_authorize_any_conversation(): void
    {
        $conv = SupportConversation::create([
            'customer_id' => $this->customer1->id,
            'department_id' => $this->deptReturns->id,
            'status' => ConversationStatus::HUMAN_ACTIVE,
            'mode' => ConversationMode::HUMAN,
            'channel' => SupportChannel::WEB,
            'priority' => SupportPriority::NORMAL,
        ]);

        $callback = $this->getChannelCallback('support.conversation.{publicId}');

        $this->assertTrue((bool)$callback($this->adminUser, $conv->public_id));
        $this->assertTrue((bool)$callback($this->managerUser, $conv->public_id));
    }

    public function test_department_scoped_agent_is_denied_global_queue(): void
    {
        $callback = $this->getChannelCallback('support.agent.queue');
        $this->assertNotNull($callback);

        // Non-elevated department-scoped agents MUST be denied global queue
        $this->assertFalse((bool)$callback($this->agent1));
        $this->assertFalse((bool)$callback($this->agent2));

        // Normal customer denied
        $this->assertFalse((bool)$callback($this->customer1));
    }

    public function test_elevated_admin_and_manager_are_allowed_global_queue(): void
    {
        $callback = $this->getChannelCallback('support.agent.queue');

        // Elevated Admin and Manager allowed
        $this->assertTrue((bool)$callback($this->adminUser));
        $this->assertTrue((bool)$callback($this->managerUser));
    }

    public function test_department_queue_channel_authorization(): void
    {
        $callback = $this->getChannelCallback('support.agent.department.{departmentId}');

        // Agent 1 belongs to deptReturns
        $this->assertTrue((bool)$callback($this->agent1, $this->deptReturns->id));
        $this->assertFalse((bool)$callback($this->agent1, $this->deptSales->id));

        // Agent 2 belongs to deptSales
        $this->assertTrue((bool)$callback($this->agent2, $this->deptSales->id));
        $this->assertFalse((bool)$callback($this->agent2, $this->deptReturns->id));

        // Elevated admin and manager allowed on any department
        $this->assertTrue((bool)$callback($this->adminUser, $this->deptReturns->id));
        $this->assertTrue((bool)$callback($this->adminUser, $this->deptSales->id));
        $this->assertTrue((bool)$callback($this->managerUser, $this->deptReturns->id));

        // Customer denied
        $this->assertFalse((bool)$callback($this->customer1, $this->deptReturns->id));
    }

    public function test_internal_notes_are_strictly_isolated_from_customer_and_guest_channels(): void
    {
        $conv = SupportConversation::create([
            'customer_id' => $this->customer1->id,
            'status' => ConversationStatus::HUMAN_ACTIVE,
            'mode' => ConversationMode::HUMAN,
            'channel' => SupportChannel::WEB,
            'priority' => SupportPriority::NORMAL,
        ]);

        $internalNote = SupportMessage::create([
            'conversation_id' => $conv->id,
            'sender_type' => SenderType::AGENT,
            'sender_id' => $this->agent1->id,
            'message_type' => MessageType::INTERNAL_NOTE,
            'content' => 'Private staff verification note.',
            'is_internal' => true,
        ]);

        $internalEvent = new SupportMessageCreated($internalNote);
        $internalChannels = array_map(fn($c) => $c->name, $internalEvent->broadcastOn());

        // Internal note event MUST NOT broadcast on customer or guest channels
        $this->assertNotContains('private-support.conversation.' . $conv->public_id, $internalChannels);
        $this->assertNotContains('private-support.guest.conversation.' . $conv->public_id, $internalChannels);
        $this->assertContains('private-support.agent.conversation.' . $conv->public_id, $internalChannels);
    }

    public function test_customer_visible_messages_broadcast_to_customer_guest_and_agent_channels(): void
    {
        $conv = SupportConversation::create([
            'customer_id' => $this->customer1->id,
            'status' => ConversationStatus::HUMAN_ACTIVE,
            'mode' => ConversationMode::HUMAN,
            'channel' => SupportChannel::WEB,
            'priority' => SupportPriority::NORMAL,
        ]);

        $customerMsg = SupportMessage::create([
            'conversation_id' => $conv->id,
            'sender_type' => SenderType::CUSTOMER,
            'sender_id' => $this->customer1->id,
            'message_type' => MessageType::TEXT,
            'content' => 'Hello support!',
            'is_internal' => false,
        ]);

        $customerEvent = new SupportMessageCreated($customerMsg);
        $customerChannels = array_map(fn($c) => $c->name, $customerEvent->broadcastOn());

        // Customer message reaches authenticated, guest, and agent conversation channels
        $this->assertContains('private-support.conversation.' . $conv->public_id, $customerChannels);
        $this->assertContains('private-support.guest.conversation.' . $conv->public_id, $customerChannels);
        $this->assertContains('private-support.agent.conversation.' . $conv->public_id, $customerChannels);

        // Payload safety checks: must not contain secrets or internal flags as true
        $payload = $customerEvent->broadcastWith();
        $this->assertFalse($payload['is_internal']);
        $this->assertArrayNotHasKey('guest_token', $payload);
        $this->assertArrayNotHasKey('secret', $payload);
    }

    public function test_presence_event_exposes_minimal_safe_agent_data(): void
    {
        $event = new SupportAgentPresenceChanged($this->agent1, 'online', 'available');
        $payload = $event->broadcastWith();

        $this->assertEquals($this->agent1->id, $payload['agent_id']);
        $this->assertEquals($this->agent1->name, $payload['agent_name']);
        $this->assertEquals('online', $payload['status']);
        $this->assertEquals('available', $payload['availability']);
        $this->assertArrayNotHasKey('customer_id', $payload);
        $this->assertArrayNotHasKey('token', $payload);
    }
}
