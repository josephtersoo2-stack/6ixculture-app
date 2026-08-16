<?php

namespace Tests\Feature\Support;

use App\Models\AiAgent;
use App\Models\User;
use App\Support\DTOs\ChatMessageDTO;
use App\Support\Enums\ConversationMode;
use App\Support\Enums\ConversationStatus;
use App\Support\Enums\MessageType;
use App\Support\Enums\SenderType;
use App\Support\Models\SupportConversation;
use App\Support\Models\SupportMessage;
use Database\Seeders\SupportDomainSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupportApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        putenv('OPENROUTER_API_KEY=test_openrouter_sk_key');
        putenv('GEMINI_API_KEY=test_gemini_api_key');

        AiAgent::create(['name' => 'OpenRouter', 'slug' => 'openrouter', 'status' => 5]);
        AiAgent::create(['name' => 'Gemini', 'slug' => 'gemini', 'status' => 5]);

        $this->seed([
            \Database\Seeders\ThemeTableSeeder::class,
            \Database\Seeders\SiteTableSeeder::class,
            \Database\Seeders\CompanyTableSeeder::class,
            \Database\Seeders\SupportDomainSeeder::class,
        ]);
    }

    public function test_guest_can_create_conversation_and_send_messages(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Hello! Welcome to 6ixCulture. How can I help you with our streetwear collection today?',
                        ],
                        'finish_reason' => 'stop',
                    ]
                ]
            ], 200)
        ]);

        // 1. Create conversation as guest
        $res = $this->postJson('/api/v1/support/conversations', [
            'language' => 'en',
            'subject' => 'Guest Sizing Question',
        ]);

        $res->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'conversation' => [
                        'id',
                        'status',
                        'mode',
                        'language',
                    ],
                    'messages'
                ],
                'resumed'
            ]);

        $convId = $res->json('data.conversation.id');
        $guestToken = $res->json('data.conversation.guest_token');

        // 2. Send message as guest
        $msgRes = $this->postJson("/api/v1/support/conversations/{$convId}/messages", [
            'message' => 'Do you have oversized streetwear hoodies?',
            'language' => 'en',
        ], ['X-Guest-Token' => $guestToken]);

        $msgRes->assertStatus(200)
            ->assertJsonPath('response.sender', 'ai')
            ->assertJsonPath('response.type', 'text');
    }

    public function test_authenticated_customer_conversation_lifecycle(): void
    {
        $customer = User::factory()->create(['username' => 'support_cust_1']);
        Sanctum::actingAs($customer);

        // 1. Create conversation
        $res = $this->postJson('/api/v1/support/conversations', [
            'language' => 'en',
            'subject' => 'Order inquiry',
        ]);

        $res->assertStatus(201);
        $convId = $res->json('data.conversation.id');

        // 2. Resume active conversation
        $resumeRes = $this->postJson('/api/v1/support/conversations', [
            'language' => 'en',
        ]);

        $resumeRes->assertStatus(200)
            ->assertJsonPath('resumed', true)
            ->assertJsonPath('data.conversation.id', $convId);

        // 3. List conversations
        $listRes = $this->getJson('/api/v1/support/conversations');
        $listRes->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_customer_isolation_blocks_cross_access(): void
    {
        $customerA = User::factory()->create(['username' => 'cust_a']);
        $customerB = User::factory()->create(['username' => 'cust_b']);

        // Customer A creates conversation
        Sanctum::actingAs($customerA);
        $res = $this->postJson('/api/v1/support/conversations', ['subject' => 'Customer A chat']);
        $convId = $res->json('data.conversation.id');

        // Customer B attempts to access Customer A's conversation
        Sanctum::actingAs($customerB);
        $unauthRes = $this->getJson("/api/v1/support/conversations/{$convId}");
        $unauthRes->assertStatus(404);

        $unauthSend = $this->postJson("/api/v1/support/conversations/{$convId}/messages", [
            'message' => 'Trying to access unauthorized conversation'
        ]);
        $unauthSend->assertStatus(404);
    }

    public function test_internal_staff_messages_are_never_exposed_via_api(): void
    {
        $customer = User::factory()->create(['username' => 'cust_internal_test']);
        Sanctum::actingAs($customer);

        $conv = SupportConversation::create([
            'customer_id' => $customer->id,
            'status' => ConversationStatus::AI_ACTIVE,
            'mode' => ConversationMode::AI,
            'language' => 'en',
        ]);

        // Customer visible message
        SupportMessage::create([
            'conversation_id' => $conv->id,
            'sender_type' => SenderType::CUSTOMER,
            'message_type' => MessageType::TEXT,
            'content' => 'Public customer query',
            'is_internal' => false,
        ]);

        // Internal staff note
        SupportMessage::create([
            'conversation_id' => $conv->id,
            'sender_type' => SenderType::AGENT,
            'message_type' => MessageType::TEXT,
            'content' => 'INTERNAL NOTE: Flagged for fraud review, do not share with customer.',
            'is_internal' => true,
        ]);

        $res = $this->getJson("/api/v1/support/conversations/{$conv->public_id}");
        $res->assertStatus(200);

        $messages = $res->json('data.messages');
        $this->assertCount(1, $messages);
        $this->assertEquals('Public customer query', $messages[0]['content']);
        $this->assertStringNotContainsString('INTERNAL NOTE', json_encode($messages));
    }

    public function test_idempotency_prevents_duplicate_submissions(): void
    {
        $customer = User::factory()->create(['username' => 'cust_idemp']);
        Sanctum::actingAs($customer);

        $conv = SupportConversation::create([
            'customer_id' => $customer->id,
            'status' => ConversationStatus::AI_ACTIVE,
            'mode' => ConversationMode::AI,
            'language' => 'en',
        ]);

        Http::fake([
            'openrouter.ai/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'First processed response.',
                        ],
                        'finish_reason' => 'stop',
                    ]
                ]
            ], 200)
        ]);

        $clientMsgId = 'unique_client_turn_999';

        // 1st request
        $res1 = $this->postJson("/api/v1/support/conversations/{$conv->public_id}/messages", [
            'message' => 'What is the standard delivery timeline?',
            'client_message_id' => $clientMsgId,
        ]);
        $res1->assertStatus(200);

        // 2nd duplicate request with same client_message_id
        $res2 = $this->postJson("/api/v1/support/conversations/{$conv->public_id}/messages", [
            'message' => 'What is the standard delivery timeline?',
            'client_message_id' => $clientMsgId,
        ]);

        $res2->assertStatus(200)
            ->assertJsonPath('idempotent', true);
    }

    public function test_human_escalation_request_flow(): void
    {
        $customer = User::factory()->create(['username' => 'cust_escalate']);
        Sanctum::actingAs($customer);

        $conv = SupportConversation::create([
            'customer_id' => $customer->id,
            'status' => ConversationStatus::AI_ACTIVE,
            'mode' => ConversationMode::AI,
            'language' => 'en',
        ]);

        $res = $this->postJson("/api/v1/support/conversations/{$conv->public_id}/request-human");

        $res->assertStatus(200)
            ->assertJsonPath('data.conversation.status', 'queued')
            ->assertJsonPath('data.conversation.mode', 'hybrid');

        $this->assertEquals(ConversationStatus::QUEUED, $conv->fresh()->status);
        $this->assertEquals(ConversationMode::HYBRID, $conv->fresh()->mode);
    }

    public function test_oversized_message_is_rejected_gracefully(): void
    {
        $customer = User::factory()->create(['username' => 'cust_oversized']);
        Sanctum::actingAs($customer);

        $conv = SupportConversation::create([
            'customer_id' => $customer->id,
            'status' => ConversationStatus::AI_ACTIVE,
            'mode' => ConversationMode::AI,
        ]);

        $longMessage = str_repeat('A', 1001); // Exceeds 1000 char max limit

        $res = $this->postJson("/api/v1/support/conversations/{$conv->public_id}/messages", [
            'message' => $longMessage,
        ]);

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }

    public function test_conversation_updates_polling(): void
    {
        $customer = User::factory()->create(['username' => 'cust_polling']);
        Sanctum::actingAs($customer);

        $conv = SupportConversation::create([
            'customer_id' => $customer->id,
            'status' => ConversationStatus::AI_ACTIVE,
            'mode' => ConversationMode::AI,
            'language' => 'en',
        ]);

        $msg1 = SupportMessage::create([
            'conversation_id' => $conv->id,
            'sender_type' => SenderType::CUSTOMER,
            'message_type' => MessageType::TEXT,
            'content' => 'Initial inquiry',
            'is_internal' => false,
        ]);

        $res1 = $this->getJson("/api/v1/support/conversations/{$conv->public_id}/updates?after_id=0");
        $res1->assertStatus(200)
            ->assertJsonCount(1, 'data.new_messages');

        // Add a second message
        SupportMessage::create([
            'conversation_id' => $conv->id,
            'sender_type' => SenderType::AI,
            'message_type' => MessageType::TEXT,
            'content' => 'Follow up response',
            'is_internal' => false,
        ]);

        $res2 = $this->getJson("/api/v1/support/conversations/{$conv->public_id}/updates?after_id={$msg1->id}");
        $res2->assertStatus(200)
            ->assertJsonCount(1, 'data.new_messages')
            ->assertJsonPath('data.new_messages.0.content', 'Follow up response');
    }

    public function test_action_execution_revalidates_server_policy(): void
    {
        $customer = User::factory()->create(['username' => 'cust_action']);
        Sanctum::actingAs($customer);

        $conv = SupportConversation::create([
            'customer_id' => $customer->id,
            'status' => ConversationStatus::AI_ACTIVE,
            'mode' => ConversationMode::AI,
            'language' => 'en',
        ]);

        // Critical action (request_refund) revalidates to REQUIRE_HUMAN on the server
        $res = $this->postJson("/api/v1/support/conversations/{$conv->public_id}/actions/request_refund", [
            'tool_name' => 'request_refund',
            'arguments' => ['order_id' => 123, 'reason' => 'Defective item'],
            'confirmed' => true,
        ]);

        $res->assertStatus(200)
            ->assertJsonPath('status', 'queued_for_human');

        $this->assertEquals(ConversationStatus::QUEUED, $conv->fresh()->status);
    }
}
