<?php

namespace Tests\Feature\Support;

use App\Models\User;
use App\Support\Enums\ConversationMode;
use App\Support\Enums\ConversationStatus;
use App\Support\Enums\MessageType;
use App\Support\Enums\SenderType;
use App\Support\Enums\TicketStatus;
use App\Support\Models\SupportConversation;
use App\Support\Models\SupportDepartment;
use App\Support\Models\SupportMessage;
use App\Support\Models\SupportTicket;
use App\Support\Policies\SupportConversationPolicy;
use App\Support\Policies\SupportTicketPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_only_access_own_conversation(): void
    {
        $policy = new SupportConversationPolicy();

        // Customer A
        $customerA = new User(['name' => 'Chioma Ade']);
        $customerA->id = 1001;

        // Customer B
        $customerB = new User(['name' => 'Tunde Bello']);
        $customerB->id = 1002;

        // Conversation belonging to Customer A
        $convA = new SupportConversation([
            'customer_id' => $customerA->id,
            'status' => ConversationStatus::AI_ACTIVE,
            'mode' => ConversationMode::AI,
        ]);

        // Customer A can view
        $this->assertTrue($policy->view($customerA, $convA));

        // Customer B CANNOT view Customer A's conversation
        $this->assertFalse($policy->view($customerB, $convA));
    }

    public function test_assigned_agent_and_admin_can_access_conversation(): void
    {
        $policy = new SupportConversationPolicy();

        $customer = new User(['name' => 'Customer']);
        $customer->id = 1001;

        $agent = new User(['name' => 'Support Agent']);
        $agent->id = 2001;

        $admin = new User(['name' => 'Admin User']);
        $admin->id = 3001;
        $admin->role_id = 1; // Admin role ID

        $conversation = new SupportConversation([
            'customer_id' => $customer->id,
            'assigned_agent_id' => $agent->id,
            'status' => ConversationStatus::HUMAN_ACTIVE,
            'mode' => ConversationMode::HUMAN,
        ]);

        // Customer can view
        $this->assertTrue($policy->view($customer, $conversation));

        // Assigned agent can view
        $this->assertTrue($policy->view($agent, $conversation));

        // Admin can view and assign
        $this->assertTrue($policy->view($admin, $conversation));
        $this->assertTrue($policy->assign($admin, $conversation));
    }

    public function test_internal_messages_are_strictly_isolated_from_customer_queries(): void
    {
        $conversation = SupportConversation::create([
            'status' => ConversationStatus::AI_ACTIVE,
            'mode' => ConversationMode::AI,
        ]);

        SupportMessage::create([
            'conversation_id' => $conversation->id,
            'sender_type' => SenderType::CUSTOMER,
            'message_type' => MessageType::TEXT,
            'content' => 'Public customer inquiry.',
            'is_internal' => false,
        ]);

        SupportMessage::create([
            'conversation_id' => $conversation->id,
            'sender_type' => SenderType::AGENT,
            'message_type' => MessageType::INTERNAL_NOTE,
            'content' => 'CONFIDENTIAL: Suspected duplicate order, hold shipping.',
            'is_internal' => true,
        ]);

        // Verify customer scope query
        $customerPayload = SupportMessage::where('conversation_id', $conversation->id)
            ->customerVisible()
            ->pluck('content')
            ->toArray();

        $this->assertCount(1, $customerPayload);
        $this->assertEquals(['Public customer inquiry.'], $customerPayload);
        $this->assertNotContains('CONFIDENTIAL: Suspected duplicate order, hold shipping.', $customerPayload);
    }

    public function test_customer_ticket_ownership_authorization(): void
    {
        $policy = new SupportTicketPolicy();

        $customerA = new User(['name' => 'Customer A']);
        $customerA->id = 1001;

        $customerB = new User(['name' => 'Customer B']);
        $customerB->id = 1002;

        $ticket = new SupportTicket([
            'customer_id' => $customerA->id,
            'subject' => 'Wrong size delivered',
            'description' => 'Need XL instead of L',
            'status' => TicketStatus::OPEN,
        ]);

        // Customer A can view own ticket
        $this->assertTrue($policy->view($customerA, $ticket));

        // Customer B cannot view Customer A's ticket
        $this->assertFalse($policy->view($customerB, $ticket));
    }
}
