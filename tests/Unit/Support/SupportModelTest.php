<?php

namespace Tests\Unit\Support;

use App\Models\User;
use App\Support\Enums\ConversationMode;
use App\Support\Enums\ConversationStatus;
use App\Support\Enums\MessageType;
use App\Support\Enums\PolicyEffect;
use App\Support\Enums\SenderType;
use App\Support\Enums\SupportChannel;
use App\Support\Enums\SupportPriority;
use App\Support\Enums\TicketStatus;
use App\Support\Enums\ToolRiskLevel;
use App\Support\Models\SupportAgentProfile;
use App\Support\Models\SupportAITool;
use App\Support\Models\SupportConversation;
use App\Support\Models\SupportConversationTag;
use App\Support\Models\SupportDepartment;
use App\Support\Models\SupportFeedback;
use App\Support\Models\SupportKnowledgeArticle;
use App\Support\Models\SupportMessage;
use App\Support\Models\SupportPolicy;
use App\Support\Models\SupportTicket;
use App\Support\Models\SupportVoiceSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversation_generates_public_id_and_casts_enums(): void
    {
        $dept = SupportDepartment::create([
            'name' => 'Sales & Sizing',
            'slug' => 'test-sales',
            'description' => 'Test Department',
        ]);

        $conversation = SupportConversation::create([
            'department_id' => $dept->id,
            'status' => ConversationStatus::AI_ACTIVE,
            'mode' => ConversationMode::AI,
            'priority' => SupportPriority::HIGH,
            'channel' => SupportChannel::WEB,
            'language' => 'en',
        ]);

        $this->assertNotEmpty($conversation->public_id);
        $this->assertEquals(ConversationStatus::AI_ACTIVE, $conversation->status);
        $this->assertEquals(ConversationMode::AI, $conversation->mode);
        $this->assertEquals(SupportPriority::HIGH, $conversation->priority);
        $this->assertEquals(SupportChannel::WEB, $conversation->channel);
        $this->assertEquals($dept->id, $conversation->department->id);
    }

    public function test_message_casts_and_internal_note_isolation_scope(): void
    {
        $conversation = SupportConversation::create([
            'status' => ConversationStatus::NEW,
            'mode' => ConversationMode::AI,
        ]);

        // Customer message
        $msg1 = SupportMessage::create([
            'conversation_id' => $conversation->id,
            'sender_type' => SenderType::CUSTOMER,
            'message_type' => MessageType::TEXT,
            'content' => 'Hello, where is my hoodie?',
            'is_internal' => false,
        ]);

        // AI message with structured payload
        $msg2 = SupportMessage::create([
            'conversation_id' => $conversation->id,
            'sender_type' => SenderType::AI,
            'message_type' => MessageType::PRODUCT,
            'content' => 'Here is the matching hoodie',
            'structured_payload' => ['product_id' => 10, 'name' => '6ix Culture Black Hoodie', 'price' => 35000],
            'is_internal' => false,
        ]);

        // Internal agent note
        $msg3 = SupportMessage::create([
            'conversation_id' => $conversation->id,
            'sender_type' => SenderType::AGENT,
            'message_type' => MessageType::INTERNAL_NOTE,
            'content' => 'CONFIDENTIAL: Customer requested manual exchange.',
            'is_internal' => true,
        ]);

        // Total messages in conversation
        $this->assertCount(3, $conversation->messages);

        // Customer-facing scope MUST only return public messages
        $customerVisible = $conversation->messages()->customerVisible()->get();
        $this->assertCount(2, $customerVisible);
        $this->assertFalse($customerVisible->contains('id', $msg3->id));

        // Internal-only scope returns only internal notes
        $internalNotes = $conversation->messages()->internalOnly()->get();
        $this->assertCount(1, $internalNotes);
        $this->assertTrue($internalNotes->contains('id', $msg3->id));
        $this->assertEquals(MessageType::INTERNAL_NOTE, $internalNotes->first()->message_type);
    }

    public function test_support_ticket_generates_ticket_number_and_relationships(): void
    {
        $dept = SupportDepartment::create([
            'name' => 'Orders & Delivery',
            'slug' => 'test-orders',
        ]);

        $ticket = SupportTicket::create([
            'department_id' => $dept->id,
            'subject' => 'Delayed Shipment to Abuja',
            'description' => 'Customer package has not arrived after 4 days.',
            'priority' => SupportPriority::URGENT,
            'status' => TicketStatus::OPEN,
        ]);

        $this->assertNotEmpty($ticket->public_id);
        $this->assertStringStartsWith('6IX-', $ticket->ticket_number);
        $this->assertEquals(SupportPriority::URGENT, $ticket->priority);
        $this->assertEquals(TicketStatus::OPEN, $ticket->status);
        $this->assertEquals($dept->id, $ticket->department->id);
    }

    public function test_support_policy_effect_and_active_scope(): void
    {
        $policy = SupportPolicy::create([
            'key' => 'test_refund_policy',
            'name' => 'Test Refund Policy',
            'category' => 'financial',
            'effect' => PolicyEffect::REQUIRE_HUMAN,
            'is_active' => true,
            'priority' => 90,
            'configuration' => ['threshold' => 10000],
        ]);

        $this->assertEquals(PolicyEffect::REQUIRE_HUMAN, $policy->effect);
        $this->assertTrue($policy->is_active);
        $this->assertEquals(10000, $policy->configuration['threshold']);
        $this->assertCount(1, SupportPolicy::where('key', 'test_refund_policy')->active()->get());
    }

    public function test_support_ai_tool_registry_and_risk_levels(): void
    {
        $tool = SupportAITool::create([
            'key' => 'test_search_products',
            'name' => 'Search Products Test',
            'description' => 'Test search tool',
            'category' => 'product',
            'risk_level' => ToolRiskLevel::LOW,
            'input_schema' => ['type' => 'object'],
            'requires_authentication' => false,
            'requires_confirmation' => false,
            'requires_human' => false,
        ]);

        $this->assertEquals(ToolRiskLevel::LOW, $tool->risk_level);
        $this->assertFalse($tool->risk_level->requiresConfirmation());
    }

    public function test_conversation_tags_pivot_relationship(): void
    {
        $conv = SupportConversation::create([
            'status' => ConversationStatus::NEW,
            'mode' => ConversationMode::AI,
        ]);

        $tag = SupportConversationTag::create([
            'name' => 'VIP Customer',
            'slug' => 'test-vip',
            'color' => '#E74C3C',
        ]);

        $conv->tags()->attach($tag->id);

        $this->assertCount(1, $conv->tags);
        $this->assertEquals('VIP Customer', $conv->tags->first()->name);
    }
}
