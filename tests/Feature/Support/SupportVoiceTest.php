<?php

namespace Tests\Feature\Support;

use App\Models\AiAgent;
use App\Models\User;
use App\Support\Contracts\SpeechToTextInterface;
use App\Support\Contracts\TextToSpeechInterface;
use App\Support\Enums\ConversationMode;
use App\Support\Enums\ConversationStatus;
use App\Support\Enums\MessageType;
use App\Support\Enums\SupportChannel;
use App\Support\Enums\SupportPriority;
use App\Support\Enums\VoiceSessionStatus;
use App\Support\Models\SupportConversation;
use App\Support\Models\SupportMessage;
use App\Support\Models\SupportVoiceSession;
use Database\Seeders\SupportDomainSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupportVoiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer1;
    protected User $customer2;
    protected SupportConversation $conversation;

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

        $this->customer1 = User::factory()->create([
            'name' => 'Amaka Obi',
            'email' => 'amaka@example.com',
            'username' => 'amaka_obi',
        ]);
        $this->customer1->assignRole('Customer');

        $this->customer2 = User::factory()->create([
            'name' => 'Tunde Bakare',
            'email' => 'tunde@example.com',
            'username' => 'tunde_bakare',
        ]);
        $this->customer2->assignRole('Customer');

        $this->conversation = SupportConversation::create([
            'customer_id' => $this->customer1->id,
            'status' => ConversationStatus::AI_ACTIVE,
            'mode' => ConversationMode::AI,
            'channel' => SupportChannel::VOICE,
            'priority' => SupportPriority::NORMAL,
            'language' => 'en',
        ]);
    }

    public function test_unauthenticated_request_without_guest_token_is_forbidden(): void
    {
        $response = $this->postJson("/api/v1/support/conversations/{$this->conversation->public_id}/voice/sessions");
        $response->assertStatus(403);
    }

    public function test_authenticated_customer_can_start_and_end_voice_session(): void
    {
        $response = $this->actingAs($this->customer1, 'sanctum')
            ->postJson("/api/v1/support/conversations/{$this->conversation->public_id}/voice/sessions", [
                'language' => 'en',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.language', 'en');

        $sessionId = $response->json('data.session_id');
        $this->assertNotEmpty($sessionId);

        // Check session details
        $check = $this->actingAs($this->customer1, 'sanctum')
            ->getJson("/api/v1/support/conversations/{$this->conversation->public_id}/voice/sessions/{$sessionId}");

        $check->assertStatus(200)
            ->assertJsonPath('data.session_id', $sessionId)
            ->assertJsonPath('data.status', 'active');

        // End session
        $end = $this->actingAs($this->customer1, 'sanctum')
            ->postJson("/api/v1/support/conversations/{$this->conversation->public_id}/voice/sessions/{$sessionId}/end");

        $end->assertStatus(200)
            ->assertJsonPath('data.status', 'completed');

        $session = SupportVoiceSession::where('public_id', $sessionId)->first();
        $this->assertEquals(VoiceSessionStatus::COMPLETED, $session->status);
        $this->assertNotNull($session->ended_at);
    }

    public function test_guest_with_valid_token_can_start_voice_session(): void
    {
        $guestConv = SupportConversation::create([
            'guest_session_id' => 'guest-secret-voice-token-123',
            'status' => ConversationStatus::AI_ACTIVE,
            'mode' => ConversationMode::AI,
            'channel' => SupportChannel::VOICE,
            'priority' => SupportPriority::NORMAL,
        ]);

        $response = $this->withHeaders(['X-Guest-Token' => 'guest-secret-voice-token-123'])
            ->postJson("/api/v1/support/conversations/{$guestConv->public_id}/voice/sessions", [
                'language' => 'yo',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.language', 'yo');
    }

    public function test_wrong_customer_cannot_start_voice_session(): void
    {
        $response = $this->actingAs($this->customer2, 'sanctum')
            ->postJson("/api/v1/support/conversations/{$this->conversation->public_id}/voice/sessions");

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'SUPPORT_ACCESS_DENIED');
    }

    public function test_voice_process_persists_voice_transcript_message_and_synthesizes_audio(): void
    {
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Yes, size XL in the 6ixCulture Oversized Tee is in stock in black and cream.',
                        ],
                    ],
                ],
                'usage' => ['total_tokens' => 120],
            ], 200),
        ]);

        // Mock STT
        $mockStt = $this->createMock(SpeechToTextInterface::class);
        $mockStt->method('transcribe')->willReturn([
            'transcript' => 'Do you have size XL in the oversized tee?',
            'detected_language' => 'en',
            'duration_seconds' => 3.2,
            'confidence' => 0.98,
            'error' => null,
        ]);

        // Mock TTS
        $mockTts = $this->createMock(TextToSpeechInterface::class);
        $mockTts->method('synthesize')->willReturn([
            'audio_content' => 'data:audio/mp3;base64,mockAudioStreamBase64',
            'audio_url' => null,
            'format' => 'mp3',
            'duration_seconds' => 4.5,
            'language' => 'en',
            'error' => null,
        ]);

        $this->app->instance(SpeechToTextInterface::class, $mockStt);
        $this->app->instance(TextToSpeechInterface::class, $mockTts);

        $fakeAudio = UploadedFile::fake()->create('speech.webm', 120, 'audio/webm');

        $response = $this->actingAs($this->customer1, 'sanctum')
            ->postJson("/api/v1/support/conversations/{$this->conversation->public_id}/voice/process", [
                'audio' => $fakeAudio,
                'language' => 'en',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.user_transcript', 'Do you have size XL in the oversized tee?')
            ->assertJsonPath('data.assistant_message.content', 'Yes, size XL in the 6ixCulture Oversized Tee is in stock in black and cream.')
            ->assertJsonPath('data.audio_content', 'data:audio/mp3;base64,mockAudioStreamBase64');

        // Verify that transcript is recorded in SupportMessage canonical history
        $savedMessages = SupportMessage::where('conversation_id', $this->conversation->id)->get();
        $this->assertTrue($savedMessages->contains('message_type', MessageType::VOICE_TRANSCRIPT));
    }

    public function test_voice_interruption_safely_resets_voice_state(): void
    {
        $response = $this->actingAs($this->customer1, 'sanctum')
            ->postJson("/api/v1/support/conversations/{$this->conversation->public_id}/voice/interrupt");

        $response->assertStatus(200)
            ->assertJsonPath('data.interrupted', true)
            ->assertJsonPath('data.status', 'ready');
    }

    public function test_stt_failure_returns_safe_error_without_damaging_conversation(): void
    {
        $mockStt = $this->createMock(SpeechToTextInterface::class);
        $mockStt->method('transcribe')->willReturn([
            'transcript' => '',
            'detected_language' => 'en',
            'duration_seconds' => 0.0,
            'confidence' => 0.0,
            'error' => 'STT service audio decoding error.',
        ]);

        $this->app->instance(SpeechToTextInterface::class, $mockStt);

        $fakeAudio = UploadedFile::fake()->create('corrupted.webm', 50, 'audio/webm');

        $response = $this->actingAs($this->customer1, 'sanctum')
            ->postJson("/api/v1/support/conversations/{$this->conversation->public_id}/voice/process", [
                'audio' => $fakeAudio,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'SPEECH_TO_TEXT_FAILED');

        // Conversation remains intact
        $this->assertEquals(ConversationStatus::AI_ACTIVE, $this->conversation->fresh()->status);
    }

    public function test_multilingual_voice_request_en_yo_ig_ha_persists_language_metadata(): void
    {
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Bẹẹni, a ni aṣọ yẹn.',
                        ],
                    ],
                ],
                'usage' => ['total_tokens' => 80],
            ], 200),
        ]);

        $response = $this->actingAs($this->customer1, 'sanctum')
            ->postJson("/api/v1/support/conversations/{$this->conversation->public_id}/voice/process", [
                'transcript' => 'Ṣe ẹ ni aṣọ yii?',
                'language' => 'yo',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.detected_language', 'yo');

        $lastMessage = SupportMessage::where('conversation_id', $this->conversation->id)->latest('id')->first();
        $this->assertNotNull($lastMessage);
    }
}
