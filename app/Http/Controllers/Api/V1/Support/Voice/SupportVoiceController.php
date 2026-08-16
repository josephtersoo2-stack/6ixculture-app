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

    public function __construct(
        AiOrchestratorInterface $orchestrator,
        ?SpeechToTextInterface $stt = null,
        ?TextToSpeechInterface $tts = null
    ) {
        $this->orchestrator = $orchestrator;
        $this->stt = $stt ?? VoiceProviderFactory::makeStt();
        $this->tts = $tts ?? VoiceProviderFactory::makeTts();
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

        $language = $request->input('language', $conv->language ?: 'en');
        $transcript = $request->input('transcript');
        $detectedLanguage = $language;

        // 1. Audio Speech-To-Text Transcribe (if audio uploaded)
        if ($request->hasFile('audio') || $request->filled('audio_base64')) {
            $audioInput = $request->file('audio') ?: $request->input('audio_base64');
            $sttResult = $this->stt->transcribe($audioInput, $language);

            if (!empty($sttResult['error'])) {
                return response()->json([
                    'error' => [
                        'code' => 'SPEECH_TO_TEXT_FAILED',
                        'message' => $sttResult['error'],
                    ]
                ], 422);
            }

            $transcript = $sttResult['transcript'];
            $detectedLanguage = $sttResult['detected_language'] ?? $language;
        }

        $transcript = trim((string)$transcript);
        if (empty($transcript)) {
            return response()->json([
                'error' => [
                    'code' => 'EMPTY_VOICE_TRANSCRIPT',
                    'message' => 'No audible speech or transcript was detected.',
                ]
            ], 422);
        }

        // 2. Canonical Turn Execution via SupportOrchestrator
        $inboundDTO = new ChatMessageDTO(
            senderType: SenderType::CUSTOMER,
            messageType: MessageType::VOICE_TRANSCRIPT,
            content: $transcript,
            isInternal: false,
            language: $detectedLanguage,
            metadata: [
                'channel' => 'voice',
                'session_id' => $request->input('session_id'),
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

        // 3. Synthesize Assistant Voice Audio via Text-To-Speech
        $ttsResult = $this->tts->synthesize($assistantText, $detectedLanguage);

        // 4. Dispatch Realtime Event
        $latestMessage = $conv->messages()->latest('id')->first();
        if ($latestMessage) {
            try {
                broadcast(new SupportMessageCreated($latestMessage));
            } catch (\Throwable $e) {
                // Realtime broadcast is an acceleration layer; don't fail turn if broker is down
            }
        }

        return response()->json([
            'data' => [
                'user_transcript' => $transcript,
                'detected_language' => $detectedLanguage,
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
