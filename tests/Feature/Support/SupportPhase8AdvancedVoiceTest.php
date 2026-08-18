<?php

namespace Tests\Feature\Support;

use App\Models\User;
use App\Support\Contracts\SpeechToTextInterface;
use App\Support\Contracts\TextToSpeechInterface;
use App\Support\DTOs\AiResponseDTO;
use App\Support\DTOs\ChatMessageDTO;
use App\Support\Enums\ConversationMode;
use App\Support\Enums\ConversationStatus;
use App\Support\Enums\MessageType;
use App\Support\Enums\PolicyEffect;
use App\Support\Enums\SenderType;
use App\Support\Enums\VoiceSessionStatus;
use App\Support\Models\SupportAgentProfile;
use App\Support\Models\SupportConversation;
use App\Support\Models\SupportDepartment;
use App\Support\Models\SupportMessage;
use App\Support\Models\SupportVoiceSession;
use App\Support\Services\Multilingual\LanguageDetectionService;
use App\Support\Services\Multilingual\TranslationService;
use App\Support\Services\Voice\TranscriptNormalizationService;
use App\Support\Services\Voice\VoiceCapabilityService;
use Database\Seeders\SupportDomainSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupportPhase8AdvancedVoiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected User $agent;
    protected User $otherCustomer;
    protected SupportDepartment $generalDept;

    protected function setUp(): void
    {
        parent::setUp();

        putenv('OPENROUTER_API_KEY=test_openrouter_sk_key');
        putenv('GEMINI_API_KEY=test_gemini_api_key');

        $this->seed(SupportDomainSeeder::class);

        \App\Models\AiAgent::firstOrCreate(['slug' => 'openrouter'], ['name' => 'OpenRouter', 'status' => 5]);
        \App\Models\AiAgent::firstOrCreate(['slug' => 'gemini'], ['name' => 'Gemini', 'status' => 5]);

        Role::firstOrCreate(['name' => 'Customer', 'guard_name' => 'sanctum']);
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'sanctum']);
        Role::firstOrCreate(['name' => 'Customer', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);

        $this->customer = User::factory()->create([
            'name' => 'Customer Test',
            'email' => 'customer@example.com',
            'username' => 'customer_test',
        ]);
        $this->customer->assignRole('Customer');

        $this->otherCustomer = User::factory()->create([
            'name' => 'Other Customer',
            'email' => 'other@example.com',
            'username' => 'other_customer',
        ]);
        $this->otherCustomer->assignRole('Customer');

        $this->agent = User::factory()->create([
            'name' => 'Agent Test',
            'email' => 'agent@example.com',
            'username' => 'agent_test',
        ]);
        $this->agent->assignRole('Admin');

        $this->generalDept = SupportDepartment::where('slug', 'general-support')->first()
            ?? SupportDepartment::create(['name' => 'General Support', 'slug' => 'general-support', 'is_active' => true]);

        SupportAgentProfile::firstOrCreate(
            ['user_id' => $this->agent->id],
            ['display_name' => 'Agent Support', 'is_online' => true]
        );
    }

    public function test_voice_capabilities_endpoint_returns_safe_schema(): void
    {
        $res = $this->getJson('/api/v1/support/voice/capabilities');

        $res->assertStatus(200);
        $res->assertJsonStructure([
            'data' => [
                'stt' => ['enabled', 'provider', 'languages', 'audio_formats', 'max_duration_seconds'],
                'tts' => ['enabled', 'provider', 'languages', 'voices', 'default_voice', 'speaking_rate'],
                'features' => ['interruption_barge_in', 'session_continuity', 'code_switching_support'],
            ],
            'message'
        ]);

        $data = $res->json('data');
        $this->assertTrue($data['stt']['languages']['en']['supported']);
        $this->assertTrue($data['stt']['languages']['yo']['supported']);
        $this->assertTrue($data['stt']['languages']['ig']['supported']);
        $this->assertTrue($data['stt']['languages']['ha']['supported']);
    }

    public function test_language_inspection_and_explicit_switch_mid_conversation(): void
    {
        $conv = SupportConversation::create([
            'customer_id' => $this->customer->id,
            'status' => ConversationStatus::AI_ACTIVE,
            'language' => 'en',
        ]);

        // 1. Get initial language
        $res = $this->actingAs($this->customer, 'sanctum')
            ->getJson("/api/v1/support/conversations/{$conv->public_id}/language");

        $res->assertStatus(200);
        $this->assertEquals('en', $res->json('data.language'));

        // 2. Switch to Yoruba (yo)
        $switchRes = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/support/conversations/{$conv->public_id}/language", [
                'language' => 'yo',
            ]);

        $switchRes->assertStatus(200);
        $this->assertEquals('yo', $switchRes->json('data.language'));

        // Verify conversation was updated in DB
        $this->assertEquals('yo', $conv->fresh()->language);
    }

    public function test_transcript_normalization_removes_disfluencies_and_preserves_raw(): void
    {
        $normalizer = new TranscriptNormalizationService();
        $raw = "Um, uh, hello... where is my order, um, #ORD1234???";

        $result = $normalizer->normalize($raw);

        $this->assertStringNotContainsString('Um,', $result['normalized_transcript']);
        $this->assertStringNotContainsString('uh,', $result['normalized_transcript']);
        $this->assertStringContainsString('where is my order', $result['normalized_transcript']);
        $this->assertStringContainsString('#ORD1234?', $result['normalized_transcript']);
        $this->assertEquals($raw, $result['raw_transcript']);
        $this->assertGreaterThan(0, $result['disfluencies_removed_count']);
    }

    public function test_language_detection_and_code_switching_support(): void
    {
        $detector = new LanguageDetectionService();

        // Yoruba turn
        $yoTurn = $detector->detect("Bawo ni, mo fe mo ibi ti order mi wa lori 6ixCulture");
        $this->assertEquals('yo', $yoTurn['detected_language']);
        $this->assertTrue($yoTurn['is_code_switching']);

        // Igbo turn
        $igTurn = $detector->detect("Kedu ebe ngwaahịa m nọ biko, achorom tracking");
        $this->assertEquals('ig', $igTurn['detected_language']);

        // Hausa turn
        $haTurn = $detector->detect("Sannu, don Allah ina kayan oda ta take?");
        $this->assertEquals('ha', $haTurn['detected_language']);

        // English turn
        $enTurn = $detector->detect("Can you please help me track my package?");
        $this->assertEquals('en', $enTurn['detected_language']);
        $this->assertFalse($enTurn['is_code_switching']);
    }

    public function test_low_confidence_voice_transcript_requests_clarification(): void
    {
        // Mock STT returning low confidence with garbled speech
        $mockStt = $this->createMock(SpeechToTextInterface::class);
        $mockStt->method('transcribe')->willReturn([
            'transcript' => 'mumble xyz ???',
            'confidence' => 0.2,
            'detected_language' => 'en',
        ]);
        $this->app->instance(SpeechToTextInterface::class, $mockStt);

        $conv = SupportConversation::create([
            'customer_id' => $this->customer->id,
            'status' => ConversationStatus::AI_ACTIVE,
            'language' => 'en',
        ]);

        $fakeAudio = UploadedFile::fake()->create('speech.webm', 20, 'audio/webm');

        $res = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/support/conversations/{$conv->public_id}/voice/process", [
                'audio' => $fakeAudio,
            ]);

        $res->assertStatus(200);
        $this->assertTrue($res->json('data.needs_clarification'));
        $this->assertStringContainsString('repeat', $res->json('data.assistant_message.content'));
    }

    public function test_voice_preferences_update_and_persistence(): void
    {
        $conv = SupportConversation::create([
            'customer_id' => $this->customer->id,
            'status' => ConversationStatus::AI_ACTIVE,
            'language' => 'en',
        ]);

        $res = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/support/conversations/{$conv->public_id}/voice/preferences", [
                'voice' => 'onyx',
                'speaking_rate' => 1.25,
                'language' => 'yo',
            ]);

        $res->assertStatus(200);
        $this->assertEquals('onyx', $res->json('data.voice'));
        $this->assertEquals(1.25, $res->json('data.speaking_rate'));
        $this->assertEquals('yo', $res->json('data.language'));

        $this->assertEquals('yo', $conv->fresh()->language);
        $this->assertEquals('onyx', $conv->fresh()->metadata['preferred_voice']);
    }

    public function test_voice_session_recovery_across_reconnect(): void
    {
        $conv = SupportConversation::create([
            'customer_id' => $this->customer->id,
            'status' => ConversationStatus::AI_ACTIVE,
            'language' => 'en',
        ]);

        // Start initial session
        $startRes = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/support/conversations/{$conv->public_id}/voice/sessions");

        $sessionId = $startRes->json('data.session_id');

        // Recover session
        $recoverRes = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/support/conversations/{$conv->public_id}/voice/recover");

        $recoverRes->assertStatus(200);
        $this->assertEquals($sessionId, $recoverRes->json('data.session_id'));
        $this->assertEquals('active', $recoverRes->json('data.status'));
    }

    public function test_voice_interruption_safety(): void
    {
        $conv = SupportConversation::create([
            'customer_id' => $this->customer->id,
            'status' => ConversationStatus::AI_ACTIVE,
            'language' => 'en',
        ]);

        $res = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/support/conversations/{$conv->public_id}/voice/interrupt");

        $res->assertStatus(200);
        $this->assertTrue($res->json('data.interrupted'));
        $this->assertEquals('ready', $res->json('data.status'));
    }

    public function test_agent_translation_endpoint_authorization(): void
    {
        $conv = SupportConversation::create([
            'customer_id' => $this->customer->id,
            'status' => ConversationStatus::AI_ACTIVE,
            'language' => 'yo',
            'department_id' => $this->generalDept->id,
        ]);

        // 1. Authorized agent translation
        $res = $this->actingAs($this->agent, 'sanctum')
            ->postJson("/api/v1/support/agent/conversations/{$conv->public_id}/translate", [
                'text' => 'where is my order',
                'target_language' => 'yo',
                'source_language' => 'en',
            ]);

        $res->assertStatus(200);
        $this->assertNotEmpty($res->json('data.translated_text'));
        $this->assertEquals('yo', $res->json('data.target_language'));
        $this->assertTrue($res->json('data.is_machine_translated'));

        // 2. Regular customer is forbidden from accessing agent translation
        $customerRes = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/support/agent/conversations/{$conv->public_id}/translate", [
                'text' => 'hello',
                'target_language' => 'en',
            ]);

        $customerRes->assertStatus(403);
    }

    public function test_guest_conversation_requires_valid_guest_token_for_language_and_voice(): void
    {
        $guestToken = (string)Str::uuid();
        $conv = SupportConversation::create([
            'customer_id' => null,
            'guest_session_id' => $guestToken,
            'status' => ConversationStatus::AI_ACTIVE,
            'language' => 'en',
        ]);

        // Without token -> 404/403
        $deniedRes = $this->getJson("/api/v1/support/conversations/{$conv->public_id}/language");
        $deniedRes->assertStatus(404);

        // With valid guest token -> 200
        $allowedRes = $this->withHeader('X-Guest-Token', $guestToken)
            ->getJson("/api/v1/support/conversations/{$conv->public_id}/language");
        $allowedRes->assertStatus(200);
        $this->assertEquals('en', $allowedRes->json('data.language'));
    }

    public function test_sensitive_action_through_voice_remains_protected_by_policy(): void
    {
        $conv = SupportConversation::create([
            'customer_id' => $this->customer->id,
            'status' => ConversationStatus::AI_ACTIVE,
            'language' => 'en',
        ]);

        // Voice process with intent to cancel order requires explicit confirmation
        $res = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/support/conversations/{$conv->public_id}/voice/process", [
                'transcript' => 'I want to cancel order #ORD1234 right now please',
            ]);

        $res->assertStatus(200);
        $this->assertNotEmpty($res->json('data.assistant_message.content'));
        // Conversation must not execute unauthorized destructive mutation directly through voice without policy confirmation
        $this->assertDatabaseMissing('support_messages', [
            'conversation_id' => $conv->id,
            'content' => 'Order successfully cancelled.',
        ]);
    }

    public function test_authenticated_language_preference_persists_and_new_conversation_inherits_it(): void
    {
        $conv1 = SupportConversation::create([
            'customer_id' => $this->customer->id,
            'status' => ConversationStatus::AI_ACTIVE,
            'language' => 'en',
        ]);

        // Explicitly switch language to Yoruba in conversation 1
        $res = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/support/conversations/{$conv1->public_id}/language", [
                'language' => 'yo',
            ]);
        $res->assertStatus(200);

        // Verify customer support preference record was persisted
        $this->assertDatabaseHas('support_customer_preferences', [
            'user_id' => $this->customer->id,
            'preferred_language' => 'yo',
        ]);

        // Close/resolve previous conversation so a new conversation can be initiated
        $conv1->update(['status' => ConversationStatus::RESOLVED]);

        // Create new conversation without specifying language -> must inherit persisted customer preference 'yo'
        $newConvRes = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v1/support/conversations', []);

        $newConvRes->assertStatus(201);
        $this->assertEquals('yo', $newConvRes->json('data.conversation.language'));
    }

    public function test_voice_preference_persists_and_new_conversation_inherits_it(): void
    {
        $conv1 = SupportConversation::create([
            'customer_id' => $this->customer->id,
            'status' => ConversationStatus::AI_ACTIVE,
            'language' => 'en',
        ]);

        // Update voice preference in conversation 1
        $res = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/support/conversations/{$conv1->public_id}/voice/preferences", [
                'voice' => 'onyx',
                'speaking_rate' => 1.25,
                'language' => 'ig',
            ]);
        $res->assertStatus(200);

        // Verify customer support preference record was persisted in database
        $this->assertDatabaseHas('support_customer_preferences', [
            'user_id' => $this->customer->id,
            'preferred_voice' => 'onyx',
            'preferred_speaking_rate' => 1.25,
            'preferred_language' => 'ig',
        ]);

        // Close/resolve previous conversation so a new conversation can be initiated
        $conv1->update(['status' => ConversationStatus::RESOLVED]);

        // Create new conversation without specifying voice/language -> inherits customer preferences
        $newConvRes = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v1/support/conversations', []);

        $newConvRes->assertStatus(201);
        $this->assertEquals('ig', $newConvRes->json('data.conversation.language'));
        $this->assertEquals('onyx', $newConvRes->json('data.conversation.metadata.preferred_voice'));
        $this->assertEquals(1.25, $newConvRes->json('data.conversation.metadata.preferred_speaking_rate'));
    }

    public function test_unauthorized_customer_cannot_modify_another_customers_preference(): void
    {
        $conv = SupportConversation::create([
            'customer_id' => $this->customer->id,
            'status' => ConversationStatus::AI_ACTIVE,
            'language' => 'en',
        ]);

        // Other customer attempts to switch language on Customer 1's conversation
        $res = $this->actingAs($this->otherCustomer, 'sanctum')
            ->postJson("/api/v1/support/conversations/{$conv->public_id}/language", [
                'language' => 'ha',
            ]);

        $res->assertStatus(404);

        // Verify Customer 1's preference remains unchanged
        $this->assertDatabaseMissing('support_customer_preferences', [
            'user_id' => $this->customer->id,
            'preferred_language' => 'ha',
        ]);
    }

    public function test_guest_preference_remains_conversation_scoped_and_does_not_leak(): void
    {
        $guestToken1 = (string)Str::uuid();
        $guestConv1 = SupportConversation::create([
            'customer_id' => null,
            'guest_session_id' => $guestToken1,
            'status' => ConversationStatus::AI_ACTIVE,
            'language' => 'en',
        ]);

        // Guest switches language on conversation 1
        $res = $this->withHeader('X-Guest-Token', $guestToken1)
            ->postJson("/api/v1/support/conversations/{$guestConv1->public_id}/language", [
                'language' => 'yo',
            ]);
        $res->assertStatus(200);

        // Verify guest action does NOT write to customer preferences table
        $this->assertEquals(0, \App\Support\Models\SupportCustomerPreference::count());

        // New unrelated guest conversation remains default 'en'
        $guestToken2 = (string)Str::uuid();
        $newGuestRes = $this->withHeader('X-Guest-Token', $guestToken2)
            ->postJson('/api/v1/support/conversations', []);

        $newGuestRes->assertStatus(201);
        $this->assertEquals('en', $newGuestRes->json('data.conversation.language'));
    }

    public function test_preference_survives_new_request_session_context(): void
    {
        // Set preference
        $prefService = new \App\Support\Services\Customer\CustomerPreferenceService();
        $prefService->updatePreferences($this->customer->id, [
            'preferred_support_language' => 'ha',
            'preferred_support_voice' => 'shimmer',
            'preferred_support_speaking_rate' => 0.85,
        ]);

        // In a fresh request context, create a conversation
        $res = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v1/support/conversations', []);

        $res->assertStatus(201);
        $this->assertEquals('ha', $res->json('data.conversation.language'));
        $this->assertEquals('shimmer', $res->json('data.conversation.metadata.preferred_voice'));
        $this->assertEquals(0.85, $res->json('data.conversation.metadata.preferred_speaking_rate'));
    }

    public function test_capability_report_reflects_configured_provider(): void
    {
        $mockStt = $this->createMock(SpeechToTextInterface::class);
        $mockStt->method('isConfigured')->willReturn(true);
        $mockStt->method('capabilities')->willReturn([
            'provider' => 'test_custom_stt',
            'enabled' => true,
            'languages' => [
                'en' => ['name' => 'English', 'native' => 'English', 'supported' => true],
                'yo' => ['name' => 'Yoruba', 'native' => 'Yorùbá', 'supported' => true],
            ],
            'audio_formats' => ['audio/webm'],
            'max_duration_seconds' => 45,
            'code_switching' => true,
        ]);

        $this->app->instance(SpeechToTextInterface::class, $mockStt);

        $res = $this->getJson('/api/v1/support/voice/capabilities');
        $res->assertStatus(200);

        $this->assertEquals('test_custom_stt', $res->json('data.stt.provider'));
        $this->assertTrue($res->json('data.stt.enabled'));
        $this->assertEquals(45, $res->json('data.stt.max_duration_seconds'));
    }

    public function test_unconfigured_provider_is_not_reported_as_active(): void
    {
        $mockStt = $this->createMock(SpeechToTextInterface::class);
        $mockStt->method('isConfigured')->willReturn(false);
        $mockStt->method('capabilities')->willReturn([
            'provider' => 'unconfigured_stt',
            'enabled' => false,
            'languages' => [
                'en' => ['name' => 'English', 'native' => 'English', 'supported' => false],
            ],
            'audio_formats' => [],
            'max_duration_seconds' => 0,
            'code_switching' => false,
        ]);

        $this->app->instance(SpeechToTextInterface::class, $mockStt);

        $res = $this->getJson('/api/v1/support/voice/capabilities');
        $res->assertStatus(200);

        $this->assertFalse($res->json('data.stt.enabled'));
        $this->assertFalse($res->json('data.stt.languages.en.supported'));
    }

    public function test_tts_fallback_is_accurately_reported(): void
    {
        $res = $this->getJson('/api/v1/support/voice/capabilities');
        $res->assertStatus(200);

        $ttsData = $res->json('data.tts');
        $this->assertFalse($ttsData['languages']['yo']['supported']);
        $this->assertEquals('en-NG', $ttsData['languages']['yo']['fallback']);
        $this->assertFalse($ttsData['languages']['ig']['supported']);
        $this->assertEquals('en-NG', $ttsData['languages']['ig']['fallback']);
    }

    public function test_capability_response_contains_no_secrets_or_internal_endpoints(): void
    {
        $res = $this->getJson('/api/v1/support/voice/capabilities');
        $res->assertStatus(200);

        $content = $res->getContent();
        $this->assertStringNotContainsString('api_key', $content);
        $this->assertStringNotContainsString('apiKey', $content);
        $this->assertStringNotContainsString('secret', $content);
        $this->assertStringNotContainsString('sk-', $content);
        $this->assertStringNotContainsString('https://api.openai.com', $content);
        $this->assertStringNotContainsString('https://generativelanguage.googleapis.com', $content);
    }
}
