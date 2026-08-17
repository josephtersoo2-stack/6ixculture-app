<?php

namespace App\Http\Controllers\Api\V1\Support\Voice;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Contracts\AiOrchestratorInterface;
use App\Support\Contracts\SpeechToTextInterface;
use App\Support\Contracts\TextToSpeechInterface;
use App\Support\DTOs\ChatMessageDTO;
use App\Support\Enums\MessageType;
use App\Support\Enums\SenderType;
use App\Support\Enums\VoiceSessionStatus;
use App\Support\Events\SupportMessageCreated;
use App\Support\Models\SupportAgentProfile;
use App\Support\Models\SupportAuditLog;
use App\Support\Models\SupportConversation;
use App\Support\Models\SupportMessage;
use App\Support\Models\SupportVoiceSession;
use App\Support\Services\Multilingual\LanguageDetectionService;
use App\Support\Services\Voice\TranscriptNormalizationService;
use App\Support\Services\Voice\VoiceCapabilityService;
use App\Support\Services\Voice\VoiceProviderFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SupportVoiceController extends Controller
{
    protected AiOrchestratorInterface $orchestrator;
    protected SpeechToTextInterface $stt;
    protected TextToSpeechInterface $tts;
    protected LanguageDetectionService $langDetector;
    protected TranscriptNormalizationService $transcriptNormalizer;
    protected VoiceCapabilityService $capabilityService;

    public function __construct(
        AiOrchestratorInterface $orchestrator,
        ?SpeechToTextInterface $stt = null,
        ?TextToSpeechInterface $tts = null,
        ?LanguageDetectionService $langDetector = null,
        ?TranscriptNormalizationService $transcriptNormalizer = null,
        ?VoiceCapabilityService $capabilityService = null
    ) {
        $this->orchestrator = $orchestrator;
        $this->stt = $stt ?? (app()->bound(SpeechToTextInterface::class) ? app(SpeechToTextInterface::class) : VoiceProviderFactory::makeStt());
        $this->tts = $tts ?? (app()->bound(TextToSpeechInterface::class) ? app(TextToSpeechInterface::class) : VoiceProviderFactory::makeTts());
        $this->langDetector = $langDetector ?? new LanguageDetectionService();
        $this->transcriptNormalizer = $transcriptNormalizer ?? new TranscriptNormalizationService();
        $this->capabilityService = $capabilityService ?? new VoiceCapabilityService();
    }

    /**
     * Get safe reporting object of current voice & multilingual capabilities.
     */
    public function capabilities(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->capabilityService->getCapabilities(),
            'message' => 'Voice capabilities retrieved successfully.',
        ], 200);
    }

    /**
     * Start a new voice session for the conversation.
     */
    public function startSession(Request $request, string $conversation): JsonResponse
    {
        $conv = SupportConversation::where('public_id', $conversation)->first();
        if (!$conv) {
            return $this->errorNotFound();
        }

        $user = $this->resolveUser($request);
        if (!$this->authorizeConversationAccess($request, $user, $conv)) {
            return $this->errorForbidden();
        }

        $language = $request->input('language', $conv->language ?: 'en');

        $session = SupportVoiceSession::create([
            'conversation_id' => $conv->id,
            'customer_id' => $conv->customer_id,
            'language' => $language,
            'status' => VoiceSessionStatus::ACTIVE,
            'started_at' => now(),
            'provider' => 'whisper_tts',
            'metadata' => [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'voice' => $request->input('voice', 'nova'),
                'speaking_rate' => (float)$request->input('speaking_rate', 1.0),
            ],
        ]);

        SupportAuditLog::log([
            'actor_type' => $user ? 'user' : 'guest',
            'actor_id' => $user?->id,
            'customer_id' => $conv->customer_id,
            'conversation_id' => $conv->id,
            'action' => 'START_VOICE_SESSION',
            'resource_type' => 'support_voice_session',
            'resource_id' => (string)$session->id,
            'authorization_result' => 'ALLOW',
            'metadata' => ['language' => $language, 'session_public_id' => $session->public_id],
        ]);

        return response()->json([
            'data' => [
                'session_id' => $session->public_id,
                'status' => $session->status->value,
                'language' => $session->language,
                'started_at' => $session->started_at->toIso8601String(),
            ],
            'message' => 'Voice session started.',
        ], 201);
    }

    /**
     * Get voice session status.
     */
    public function getSession(Request $request, string $conversation, string $session): JsonResponse
    {
        $conv = SupportConversation::where('public_id', $conversation)->first();
        if (!$conv) {
            return $this->errorNotFound();
        }

        $user = $this->resolveUser($request);
        if (!$this->authorizeConversationAccess($request, $user, $conv)) {
            return $this->errorForbidden();
        }

        $voiceSession = SupportVoiceSession::where('conversation_id', $conv->id)
            ->where('public_id', $session)
            ->first();

        if (!$voiceSession) {
            return response()->json([
                'error' => [
                    'code' => 'VOICE_SESSION_NOT_FOUND',
                    'message' => 'The voice session was not found.',
                ]
            ], 404);
        }

        return response()->json([
            'data' => [
                'session_id' => $voiceSession->public_id,
                'status' => $voiceSession->status->value,
                'language' => $voiceSession->language,
                'started_at' => $voiceSession->started_at?->toIso8601String(),
                'ended_at' => $voiceSession->ended_at?->toIso8601String(),
                'duration_seconds' => $voiceSession->duration_seconds,
                'transcript_message_count' => $voiceSession->transcript_message_count,
            ]
        ], 200);
    }

    /**
     * Reconcile / recover active voice session across browser reloads or temporary drops.
     */
    public function recoverSession(Request $request, string $conversation): JsonResponse
    {
        $conv = SupportConversation::where('public_id', $conversation)->first();
        if (!$conv) {
            return $this->errorNotFound();
        }

        $user = $this->resolveUser($request);
        if (!$this->authorizeConversationAccess($request, $user, $conv)) {
            return $this->errorForbidden();
        }

        $activeSession = SupportVoiceSession::where('conversation_id', $conv->id)
            ->where('status', VoiceSessionStatus::ACTIVE)
            ->latest('id')
            ->first();

        if (!$activeSession) {
            // Auto-recreate active session seamlessly
            $activeSession = SupportVoiceSession::create([
                'conversation_id' => $conv->id,
                'customer_id' => $conv->customer_id,
                'language' => $conv->language ?: 'en',
                'status' => VoiceSessionStatus::ACTIVE,
                'started_at' => now(),
                'provider' => 'whisper_tts',
                'metadata' => [
                    'recovered' => true,
                    'ip' => $request->ip(),
                ],
            ]);
        }

        return response()->json([
            'data' => [
                'session_id' => $activeSession->public_id,
                'status' => $activeSession->status->value,
                'language' => $activeSession->language,
                'started_at' => $activeSession->started_at?->toIso8601String(),
                'conversation_id' => $conv->public_id,
            ],
            'message' => 'Voice session recovered.',
        ], 200);
    }

    /**
     * Update customer voice preferences (voice selection, speaking rate).
     */
    public function updatePreferences(Request $request, string $conversation): JsonResponse
    {
        $conv = SupportConversation::where('public_id', $conversation)->first();
        if (!$conv) {
            return $this->errorNotFound();
        }

        $user = $this->resolveUser($request);
        if (!$this->authorizeConversationAccess($request, $user, $conv)) {
            return $this->errorForbidden();
        }

        $validated = $request->validate([
            'voice' => ['nullable', 'string', 'in:alloy,echo,fable,onyx,nova,shimmer'],
            'speaking_rate' => ['nullable', 'numeric', 'min:0.75', 'max:1.5'],
            'language' => ['nullable', 'string', 'in:en,yo,ig,ha'],
        ]);

        $meta = is_array($conv->metadata) ? $conv->metadata : [];
        if (!empty($validated['voice'])) $meta['preferred_voice'] = $validated['voice'];
        if (!empty($validated['speaking_rate'])) $meta['preferred_speaking_rate'] = (float)$validated['speaking_rate'];
        
        $conv->metadata = $meta;
        if (!empty($validated['language'])) {
            $conv->language = $validated['language'];
        }
        $conv->save();

        return response()->json([
            'data' => [
                'voice' => $meta['preferred_voice'] ?? 'nova',
                'speaking_rate' => $meta['preferred_speaking_rate'] ?? 1.0,
                'language' => $conv->language,
            ],
            'message' => 'Voice preferences updated successfully.',
        ], 200);
    }

    /**
     * End an active voice session.
     */
    public function endSession(Request $request, string $conversation, string $session): JsonResponse
    {
        $conv = SupportConversation::where('public_id', $conversation)->first();
        if (!$conv) {
            return $this->errorNotFound();
        }

        $user = $this->resolveUser($request);
        if (!$this->authorizeConversationAccess($request, $user, $conv)) {
            return $this->errorForbidden();
        }

        $voiceSession = SupportVoiceSession::where('conversation_id', $conv->id)
            ->where('public_id', $session)
            ->first();

        if (!$voiceSession) {
            return response()->json([
                'error' => [
                    'code' => 'VOICE_SESSION_NOT_FOUND',
                    'message' => 'The voice session was not found.',
                ]
            ], 404);
        }

        $startedAt = $voiceSession->started_at ?? now();
        $durationSeconds = (int)now()->diffInSeconds($startedAt);

        $voiceSession->update([
            'status' => VoiceSessionStatus::COMPLETED,
            'ended_at' => now(),
            'duration_seconds' => $durationSeconds,
        ]);

        return response()->json([
            'data' => [
                'session_id' => $voiceSession->public_id,
                'status' => $voiceSession->status->value,
                'duration_seconds' => $durationSeconds,
                'ended_at' => $voiceSession->ended_at->toIso8601String(),
            ],
            'message' => 'Voice session concluded.',
        ], 200);
    }

    /**
     * Process spoken audio or transcript turn through the canonical SupportOrchestrator.
     */
    public function process(Request $request, string $conversation): JsonResponse
    {
        $conv = SupportConversation::where('public_id', $conversation)->first();
        if (!$conv) {
            return $this->errorNotFound();
        }

        $user = $this->resolveUser($request);
        if (!$this->authorizeConversationAccess($request, $user, $conv)) {
            return $this->errorForbidden();
        }

        $requestedLang = $request->input('language', $conv->language ?: 'en');
        $rawTranscript = $request->input('transcript');
        $sttConfidence = 1.0;

        // 1. Audio Speech-To-Text Transcribe (if audio uploaded)
        if ($request->hasFile('audio') || $request->filled('audio_base64')) {
            $audioInput = $request->file('audio') ?: $request->input('audio_base64');
            $sttResult = $this->stt->transcribe($audioInput, $requestedLang);

            if (!empty($sttResult['error'])) {
                return response()->json([
                    'error' => [
                        'code' => 'SPEECH_TO_TEXT_FAILED',
                        'message' => $sttResult['error'],
                    ]
                ], 422);
            }

            $rawTranscript = $sttResult['transcript'] ?? '';
            $sttConfidence = (float)($sttResult['confidence'] ?? 1.0);
        }

        $rawTranscript = trim((string)$rawTranscript);
        if (empty($rawTranscript)) {
            return response()->json([
                'error' => [
                    'code' => 'EMPTY_VOICE_TRANSCRIPT',
                    'message' => 'No audible speech or transcript was detected.',
                ]
            ], 422);
        }

        // 2. Transcript Normalization & Disfluency Removal
        $normalization = $this->transcriptNormalizer->normalize($rawTranscript);
        $normalizedText = $normalization['normalized_transcript'];

        // 3. Language & Code-Switching Detection
        $detection = $this->langDetector->detect($normalizedText, $requestedLang);
        $effectiveLanguage = $this->langDetector->resolveEffectiveLanguage(
            $conv->language,
            null,
            $requestedLang,
            $detection
        );

        // 4. Low-Confidence handling: If transcript is too uncertain, clarify without executing risky action
        if ($sttConfidence < 0.5 || $detection['is_low_confidence']) {
            return response()->json([
                'data' => [
                    'user_transcript' => $normalizedText,
                    'raw_transcript' => $rawTranscript,
                    'detected_language' => $effectiveLanguage,
                    'confidence' => $detection['confidence'],
                    'needs_clarification' => true,
                    'assistant_message' => [
                        'content' => 'I did not catch that clearly. Could you please repeat your request?',
                        'message_type' => 'text',
                    ],
                ],
                'message' => 'Voice transcript low confidence; clarification requested.',
            ], 200);
        }

        // 5. Canonical Turn Execution via SupportOrchestrator
        $inboundDTO = new ChatMessageDTO(
            senderType: SenderType::CUSTOMER,
            messageType: MessageType::VOICE_TRANSCRIPT,
            content: $normalizedText,
            isInternal: false,
            language: $effectiveLanguage,
            metadata: [
                'channel' => 'voice',
                'session_id' => $request->input('session_id'),
                'raw_transcript' => $rawTranscript,
                'confidence' => $detection['confidence'],
                'is_code_switching' => $detection['is_code_switching'],
            ]
        );

        // Update voice session stats if session_id provided
        if ($request->filled('session_id')) {
            SupportVoiceSession::where('public_id', $request->input('session_id'))
                ->where('conversation_id', $conv->id)
                ->increment('transcript_message_count');
        }

        // Execute orchestrator
        $aiResponseDTO = $this->orchestrator->handle($conv, $inboundDTO);
        $assistantText = $aiResponseDTO->content ?? '';

        // 6. Synthesize Assistant Voice Audio via Text-To-Speech
        $ttsResult = $this->tts->synthesize($assistantText, $effectiveLanguage);

        // 7. Dispatch Realtime Event
        $latestMessage = $conv->messages()->latest('id')->first();
        if ($latestMessage) {
            try {
                broadcast(new SupportMessageCreated($latestMessage));
            } catch (\Throwable $e) {
                // Realtime broadcast is an acceleration layer
            }
        }

        return response()->json([
            'data' => [
                'user_transcript' => $normalizedText,
                'raw_transcript' => $rawTranscript,
                'detected_language' => $detection['detected_language'],
                'effective_language' => $effectiveLanguage,
                'confidence' => $detection['confidence'],
                'is_code_switching' => $detection['is_code_switching'],
                'assistant_message' => [
                    'id' => $latestMessage?->id,
                    'content' => $assistantText,
                    'message_type' => $aiResponseDTO->messageType->value,
                    'structured_payload' => $aiResponseDTO->structuredPayload,
                ],
                'audio_content' => $ttsResult['audio_content'] ?? null,
                'audio_url' => $ttsResult['audio_url'] ?? null,
                'audio_format' => $ttsResult['format'] ?? 'mp3',
                'duration_seconds' => $ttsResult['duration_seconds'] ?? 0.0,
                'session_id' => $request->input('session_id'),
            ],
            'message' => 'Voice turn processed successfully.',
        ], 200);
    }

    /**
     * Interrupt voice playback without corrupting conversation history.
     */
    public function interrupt(Request $request, string $conversation): JsonResponse
    {
        $conv = SupportConversation::where('public_id', $conversation)->first();
        if (!$conv) {
            return $this->errorNotFound();
        }

        $user = $this->resolveUser($request);
        if (!$this->authorizeConversationAccess($request, $user, $conv)) {
            return $this->errorForbidden();
        }

        SupportAuditLog::log([
            'actor_type' => $user ? 'user' : 'guest',
            'actor_id' => $user?->id,
            'customer_id' => $conv->customer_id,
            'conversation_id' => $conv->id,
            'action' => 'VOICE_PLAYBACK_INTERRUPTED',
            'resource_type' => 'support_conversation',
            'resource_id' => (string)$conv->id,
            'authorization_result' => 'ALLOW',
        ]);

        return response()->json([
            'data' => [
                'interrupted' => true,
                'status' => 'ready',
            ],
            'message' => 'Voice playback interrupted cleanly.',
        ], 200);
    }

    /**
     * Resolve authenticated user.
     */
    protected function resolveUser(Request $request): ?User
    {
        return $request->user('sanctum') ?? Auth::guard('sanctum')->user() ?? Auth::user();
    }

    /**
     * Authorize conversation access for customer, guest token, or agent.
     */
    protected function authorizeConversationAccess(Request $request, ?User $user, SupportConversation $conversation): bool
    {
        // 1. Authenticated customer ownership
        if ($user && $conversation->customer_id && (int)$conversation->customer_id === (int)$user->id) {
            return true;
        }

        // 2. Guest token validation
        $guestToken = $request->header('X-Guest-Token') ?: $request->input('guest_token');
        if (!empty($guestToken) && !empty($conversation->guest_session_id)) {
            if (hash_equals((string)$conversation->guest_session_id, (string)$guestToken)) {
                return true;
            }
        }

        // 3. Support Agent / Admin verification
        if ($user) {
            try {
                if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Admin', 'Manager'])) {
                    return true;
                }
            } catch (\Throwable $e) {}

            if ($conversation->assigned_agent_id && (int)$conversation->assigned_agent_id === (int)$user->id) {
                return true;
            }

            $profile = SupportAgentProfile::where('user_id', $user->id)->first();
            if ($profile && $conversation->department_id) {
                return $profile->departments()->where('support_departments.id', $conversation->department_id)->exists();
            }
        }

        return false;
    }

    protected function errorForbidden(string $message = 'Access denied for this support conversation.'): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'SUPPORT_ACCESS_DENIED',
                'message' => $message,
            ]
        ], 403);
    }

    protected function errorNotFound(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'SUPPORT_CONVERSATION_NOT_FOUND',
                'message' => 'The requested support conversation was not found.',
            ]
        ], 404);
    }
}
