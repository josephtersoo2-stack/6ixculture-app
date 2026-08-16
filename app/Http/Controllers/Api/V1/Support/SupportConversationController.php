<?php

namespace App\Http\Controllers\Api\V1\Support;

use App\Http\Controllers\Controller;
use App\Http\Requests\Support\ExecuteActionRequest;
use App\Http\Requests\Support\SendMessageRequest;
use App\Http\Requests\Support\StoreConversationRequest;
use App\Http\Resources\Support\SupportConversationDetailResource;
use App\Http\Resources\Support\SupportConversationResource;
use App\Http\Resources\Support\SupportMessageResource;
use App\Support\Contracts\AiOrchestratorInterface;
use App\Support\Contracts\PolicyEngineInterface;
use App\Support\DTOs\ChatMessageDTO;
use App\Support\DTOs\ToolCallDTO;
use App\Support\Enums\ConversationMode;
use App\Support\Enums\ConversationStatus;
use App\Support\Enums\MessageType;
use App\Support\Enums\PolicyEffect;
use App\Support\Enums\SenderType;
use App\Support\Models\SupportAuditLog;
use App\Support\Models\SupportConversation;
use App\Support\Models\SupportMessage;
use App\Support\Tools\ToolRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SupportConversationController extends Controller
{
    protected AiOrchestratorInterface $orchestrator;
    protected PolicyEngineInterface $policyEngine;
    protected ToolRegistry $toolRegistry;

    public function __construct(
        AiOrchestratorInterface $orchestrator,
        PolicyEngineInterface $policyEngine
    ) {
        $this->orchestrator = $orchestrator;
        $this->policyEngine = $policyEngine;
        $this->toolRegistry = new ToolRegistry();
    }

    /**
     * Start a new conversation or resume an existing active conversation.
     */
    public function store(StoreConversationRequest $request): JsonResponse
    {
        [$customerId, $guestToken] = $this->resolveIdentity($request);

        // Check if there is an active ongoing conversation to resume
        if ($customerId) {
            if ($guestToken) {
                $guestConv = SupportConversation::where('guest_session_id', $guestToken)
                    ->whereNull('customer_id')
                    ->whereIn('status', [ConversationStatus::AI_ACTIVE, ConversationStatus::QUEUED, ConversationStatus::HUMAN_ACTIVE])
                    ->latest()
                    ->first();

                if ($guestConv) {
                    $guestConv->update(['customer_id' => $customerId]);
                    return response()->json([
                        'data' => new SupportConversationDetailResource($guestConv->fresh()),
                        'resumed' => true,
                    ], 200);
                }
            }

            $existing = SupportConversation::where('customer_id', $customerId)
                ->whereIn('status', [ConversationStatus::AI_ACTIVE, ConversationStatus::QUEUED, ConversationStatus::HUMAN_ACTIVE])
                ->latest()
                ->first();

            if ($existing) {
                return response()->json([
                    'data' => new SupportConversationDetailResource($existing),
                    'resumed' => true,
                ], 200);
            }
        } elseif ($guestToken) {
            $existing = SupportConversation::where('guest_session_id', $guestToken)
                ->whereNull('customer_id')
                ->whereIn('status', [ConversationStatus::AI_ACTIVE, ConversationStatus::QUEUED, ConversationStatus::HUMAN_ACTIVE])
                ->latest()
                ->first();

            if ($existing) {
                return response()->json([
                    'data' => new SupportConversationDetailResource($existing),
                    'resumed' => true,
                ], 200);
            }
        }

        $sessionGuestToken = $customerId ? null : ($guestToken ?: (string)Str::uuid());

        $conversation = SupportConversation::create([
            'customer_id' => $customerId,
            'guest_session_id' => $sessionGuestToken,
            'status' => ConversationStatus::AI_ACTIVE,
            'mode' => ConversationMode::AI,
            'language' => $request->input('language', 'en'),
            'subject' => $request->input('subject', 'Customer Support Inquiry'),
        ]);

        return response()->json([
            'data' => new SupportConversationDetailResource($conversation),
            'resumed' => false,
        ], 201);
    }

    /**
     * List conversations for the authenticated customer.
     */
    public function index(Request $request): JsonResponse
    {
        [$customerId, $guestToken] = $this->resolveIdentity($request);

        if (!$customerId) {
            // For guests, return conversations matching their guest session token only
            if (!$guestToken) {
                return response()->json(['data' => []], 200);
            }
            $conversations = SupportConversation::where('guest_session_id', $guestToken)
                ->orderBy('id', 'desc')
                ->limit(10)
                ->get();

            return response()->json([
                'data' => SupportConversationResource::collection($conversations),
            ], 200);
        }

        $conversations = SupportConversation::where('customer_id', $customerId)
            ->orderBy('id', 'desc')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => SupportConversationResource::collection($conversations),
        ], 200);
    }

    /**
     * Get a specific conversation with customer-visible messages.
     */
    public function show(Request $request, string $conversation): JsonResponse
    {
        $conv = $this->findAndAuthorize($request, $conversation);
        if (!$conv) {
            return $this->errorNotFound();
        }

        return response()->json([
            'data' => new SupportConversationDetailResource($conv),
        ], 200);
    }

    /**
     * Send a customer message into the conversation and get AI response.
     */
    public function sendMessage(SendMessageRequest $request, string $conversation): JsonResponse
    {
        $conv = $this->findAndAuthorize($request, $conversation);
        if (!$conv) {
            return $this->errorNotFound();
        }

        if ($conv->status === ConversationStatus::RESOLVED || $conv->status === ConversationStatus::CLOSED) {
            return response()->json([
                'error' => [
                    'code' => 'CONVERSATION_CLOSED',
                    'message' => 'This conversation has been closed. Please start a new conversation for assistance.',
                ]
            ], 400);
        }

        $clientMessageId = $request->input('client_message_id');
        $idempotencyKey = $clientMessageId ? "support_idemp_{$conv->id}_{$clientMessageId}" : null;

        if ($idempotencyKey && Cache::has($idempotencyKey)) {
            // Duplicate request detected: return latest messages safely without duplicate orchestrator turn
            return response()->json([
                'data' => new SupportConversationDetailResource($conv->fresh()),
                'idempotent' => true,
            ], 200);
        }

        if ($idempotencyKey) {
            Cache::put($idempotencyKey, true, 60); // 1 minute protection window
        }

        $language = $request->input('language', $conv->language ?: 'en');
        if ($language !== $conv->language) {
            $conv->update(['language' => $language]);
        }

        $inboundDTO = new ChatMessageDTO(
            senderType: SenderType::CUSTOMER,
            messageType: MessageType::TEXT,
            content: $request->input('message'),
            language: $language,
            isInternal: false
        );

        // Execute orchestrator loop
        $responseDTO = $this->orchestrator->handle($conv, $inboundDTO);

        // Dispatch realtime message event
        $latestMessage = $conv->messages()->latest('id')->first();
        if ($latestMessage) {
            try {
                broadcast(new \App\Support\Events\SupportMessageCreated($latestMessage));
            } catch (\Throwable $e) {}
        }

        return response()->json([
            'data' => new SupportConversationDetailResource($conv->fresh()),
            'response' => [
                'sender' => $responseDTO->senderType->value,
                'type' => $responseDTO->messageType->value,
                'content' => $responseDTO->content,
                'payload' => $responseDTO->structuredPayload,
            ]
        ], 200);
    }

    /**
     * Get messages for the conversation.
     */
    public function getMessages(Request $request, string $conversation): JsonResponse
    {
        $conv = $this->findAndAuthorize($request, $conversation);
        if (!$conv) {
            return $this->errorNotFound();
        }

        $messages = $conv->messages()
            ->customerVisible()
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'data' => SupportMessageResource::collection($messages),
        ], 200);
    }

    /**
     * Polling endpoint for incremental updates.
     */
    public function getUpdates(Request $request, string $conversation): JsonResponse
    {
        $conv = $this->findAndAuthorize($request, $conversation);
        if (!$conv) {
            return $this->errorNotFound();
        }

        $afterId = (int)$request->input('after_id', 0);

        $newMessages = $conv->messages()
            ->customerVisible()
            ->where('id', '>', $afterId)
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'data' => [
                'conversation_id' => $conv->public_id,
                'status' => $conv->status->value,
                'mode' => $conv->mode->value,
                'new_messages' => SupportMessageResource::collection($newMessages),
            ]
        ], 200);
    }

    /**
     * Request human agent support escalation.
     */
    public function requestHuman(Request $request, string $conversation): JsonResponse
    {
        $conv = $this->findAndAuthorize($request, $conversation);
        if (!$conv) {
            return $this->errorNotFound();
        }

        $conv->update([
            'status' => ConversationStatus::QUEUED,
            'mode' => ConversationMode::HYBRID,
        ]);

        $escalationMsg = SupportMessage::create([
            'conversation_id' => $conv->id,
            'sender_type' => SenderType::SYSTEM,
            'message_type' => MessageType::ESCALATION,
            'content' => 'Your conversation has been connected to our support queue. A representative will assist you shortly.',
            'structured_payload' => ['status' => 'queued'],
            'is_internal' => false,
        ]);

        try {
            broadcast(new \App\Support\Events\SupportMessageCreated($escalationMsg));
            broadcast(new \App\Support\Events\SupportConversationUpdated($conv, 'escalated'));
            broadcast(new \App\Support\Events\SupportQueueUpdated($conv, 'escalated'));
        } catch (\Throwable $e) {}

        return response()->json([
            'data' => new SupportConversationDetailResource($conv->fresh()),
            'message' => 'Escalation request submitted successfully.',
        ], 200);
    }

    /**
     * Mark conversation as resolved by customer.
     */
    public function resolve(Request $request, string $conversation): JsonResponse
    {
        $conv = $this->findAndAuthorize($request, $conversation);
        if (!$conv) {
            return $this->errorNotFound();
        }

        $conv->update([
            'status' => ConversationStatus::RESOLVED,
        ]);

        $resolvedMsg = SupportMessage::create([
            'conversation_id' => $conv->id,
            'sender_type' => SenderType::SYSTEM,
            'message_type' => MessageType::SYSTEM,
            'content' => 'Conversation has been marked as resolved.',
            'is_internal' => false,
        ]);

        try {
            broadcast(new \App\Support\Events\SupportMessageCreated($resolvedMsg));
            broadcast(new \App\Support\Events\SupportConversationUpdated($conv, 'resolved'));
        } catch (\Throwable $e) {}

        return response()->json([
            'data' => new SupportConversationDetailResource($conv->fresh()),
            'message' => 'Conversation marked as resolved.',
        ], 200);
    }

    /**
     * Execute a confirmed sensitive action with server-side policy revalidation.
     */
    public function executeAction(ExecuteActionRequest $request, string $conversation, string $action): JsonResponse
    {
        $conv = $this->findAndAuthorize($request, $conversation);
        if (!$conv) {
            return $this->errorNotFound();
        }

        // Route action is the authoritative capability selector
        $toolName = $action;

        // If request body contains a tool_name assertion, enforce exact match with route action
        if ($request->filled('tool_name') && $request->input('tool_name') !== $action) {
            return response()->json([
                'error' => [
                    'code' => 'ACTION_MISMATCH',
                    'message' => 'The tool_name in the request body does not match the URL action parameter.',
                ]
            ], 422);
        }

        // Verify action exists in either ToolRegistry or database tool catalog
        $tool = $this->toolRegistry->get($toolName);
        $hasDbTool = \App\Support\Models\SupportAITool::active()->where('key', $toolName)->exists();

        if (!$tool && !$hasDbTool) {
            return response()->json([
                'error' => [
                    'code' => 'TOOL_NOT_FOUND',
                    'message' => 'Requested action capability is not registered.',
                ]
            ], 404);
        }

        $args = $request->input('arguments', []);
        $confirmed = $request->boolean('confirmed');

        if (!$confirmed) {
            return response()->json([
                'error' => [
                    'code' => 'ACTION_NOT_CONFIRMED',
                    'message' => 'Action was cancelled by user.',
                ]
            ], 400);
        }

        $toolCall = new ToolCallDTO((string)Str::uuid(), $toolName, $args);

        // Validate argument schema if executable definition is present
        if ($tool) {
            $valErr = $this->toolRegistry->validate($toolCall, $tool);
            if ($valErr) {
                return response()->json([
                    'error' => [
                        'code' => 'INVALID_ARGUMENTS',
                        'message' => $valErr,
                    ]
                ], 422);
            }
        }

        // Revalidate policy on the server
        $policyResult = $this->policyEngine->evaluateToolCall($toolCall, $conv);

        if ($policyResult === PolicyEffect::DENY) {
            return response()->json([
                'error' => [
                    'code' => 'ACTION_DENIED',
                    'message' => 'Action was denied by security policy.',
                ]
            ], 403);
        }

        if ($policyResult === PolicyEffect::REQUIRE_HUMAN) {
            $conv->update([
                'status' => ConversationStatus::QUEUED,
                'mode' => ConversationMode::HYBRID,
            ]);

            SupportMessage::create([
                'conversation_id' => $conv->id,
                'sender_type' => SenderType::SYSTEM,
                'message_type' => MessageType::ESCALATION,
                'content' => 'This action requires supervisor verification and has been queued for our team.',
                'structured_payload' => ['tool_name' => $toolName, 'status' => 'queued'],
                'is_internal' => false,
            ]);

            return response()->json([
                'data' => new SupportConversationDetailResource($conv->fresh()),
                'status' => 'queued_for_human',
            ], 200);
        }

        if (!$tool) {
            return response()->json([
                'error' => [
                    'code' => 'TOOL_NOT_FOUND',
                    'message' => 'Requested tool is not registered.',
                ]
            ], 404);
        }

        $result = $tool->execute($toolCall, $conv);

        // Store result message in chat
        SupportMessage::create([
            'conversation_id' => $conv->id,
            'sender_type' => SenderType::AI,
            'message_type' => MessageType::TEXT,
            'content' => $result->success ? 'Action executed successfully.' : ($result->errorMessage ?: 'Action failed.'),
            'structured_payload' => $result->data,
            'is_internal' => false,
        ]);

        return response()->json([
            'data' => new SupportConversationDetailResource($conv->fresh()),
            'result' => $result->toArray(),
        ], 200);
    }

    /**
     * Resolve authenticated customer ID or guest token.
     */
    protected function resolveIdentity(Request $request): array
    {
        $user = $request->user('sanctum') ?? Auth::guard('sanctum')->user() ?? Auth::user();
        $customerId = $user ? (int)$user->id : null;
        $guestToken = $request->header('X-Guest-Token') ?: $request->input('guest_token');

        return [$customerId, $guestToken];
    }

    /**
     * Find conversation by public_id and authorize access.
     */
    protected function findAndAuthorize(Request $request, string $publicId): ?SupportConversation
    {
        $conversation = SupportConversation::where('public_id', $publicId)->first();
        if (!$conversation) {
            return null;
        }

        [$customerId, $guestToken] = $this->resolveIdentity($request);

        // If conversation belongs to an authenticated customer
        if ($conversation->customer_id) {
            if (!$customerId || (int)$conversation->customer_id !== (int)$customerId) {
                return null; // Deny access across customers or if unauthenticated
            }
            return $conversation;
        }

        // If conversation is a guest session (unlinked)
        if ($conversation->guest_session_id) {
            // Require valid guest-token proof
            if (!$guestToken || $conversation->guest_session_id !== $guestToken) {
                return null;
            }

            // If user is authenticated AND supplied valid guest token proof, link conversation
            if ($customerId) {
                $conversation->update(['customer_id' => $customerId]);
            }

            return $conversation;
        }

        return null;
    }

    protected function errorNotFound(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'SUPPORT_CONVERSATION_NOT_FOUND',
                'message' => 'The requested support conversation was not found or access is denied.',
            ]
        ], 404);
    }
}
