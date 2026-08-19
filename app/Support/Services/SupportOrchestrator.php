<?php

namespace App\Support\Services;

use App\Support\Contracts\AiOrchestratorInterface;
use App\Support\Contracts\AiProviderInterface;
use App\Support\DTOs\ChatMessageDTO;
use App\Support\DTOs\ToolCallDTO;
use App\Support\DTOs\ToolResultDTO;
use App\Support\Enums\ConversationMode;
use App\Support\Enums\ConversationStatus;
use App\Support\Enums\MessageType;
use App\Support\Enums\PolicyEffect;
use App\Support\Enums\SenderType;
use App\Support\Models\SupportAuditLog;
use App\Support\Models\SupportConversation;
use App\Support\Models\SupportMessage;
use App\Support\Policies\SupportActionPolicyEngine;
use App\Support\Tools\ToolRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SupportOrchestrator implements AiOrchestratorInterface
{
    protected ToolRegistry $toolRegistry;
    protected SupportActionPolicyEngine $policyEngine;
    protected SupportContextAssembler $contextAssembler;
    protected ?AiProviderInterface $provider;

    public function __construct(
        ?ToolRegistry $toolRegistry = null,
        ?SupportActionPolicyEngine $policyEngine = null,
        ?SupportContextAssembler $contextAssembler = null,
        ?AiProviderInterface $provider = null
    ) {
        $this->toolRegistry = $toolRegistry ?? new ToolRegistry();
        $this->policyEngine = $policyEngine ?? new SupportActionPolicyEngine();
        $this->contextAssembler = $contextAssembler ?? new SupportContextAssembler();
        $this->provider = $provider;
    }

    public function handle(SupportConversation $conversation, ChatMessageDTO $incomingMessage): ChatMessageDTO
    {
        $startTime = microtime(true);
        $userMessage = $incomingMessage->content ?? '';

        // 1. Enforce input rate limits & safety checks
        $limitError = $this->checkRateLimits($conversation, $userMessage);
        if ($limitError) {
            $errMessage = $this->createErrorMessage($conversation, $limitError);
            return $this->toDTO($errMessage);
        }

        // 2. Persist the inbound customer message
        SupportMessage::create([
            'conversation_id' => $conversation->id,
            'sender_type' => $incomingMessage->senderType,
            'message_type' => $incomingMessage->messageType,
            'content' => $userMessage,
            'is_internal' => $incomingMessage->isInternal,
        ]);

        // 3. Resolve active AI provider adapter
        $provider = $this->provider ?? AiProviderFactory::make();

        // 4. Build secure grounded message context via SupportContextAssembler
        $history = $this->contextAssembler->assemble($conversation, $userMessage);

        // 5. Query active tools catalog
        $tools = $this->toolRegistry->getActiveTools();

        // 6. Execute Provider Chat Loop (supports up to 3 recursive tool execution turns to prevent infinite loops)
        $turn = 0;
        $maxTurns = 3;
        $lastResponse = null;

        while ($turn < $maxTurns) {
            $turn++;
            try {
                $response = $provider->chat($history, $tools);
            } catch (\Throwable $e) {
                Log::warning('AI Provider chat exception', [
                    'event' => 'ai_provider_exception',
                    'provider' => $provider->providerName(),
                    'conversation_public_id' => $conversation->public_id,
                    'exception_class' => get_class($e),
                    'message' => AuditRedactionService::sanitizeString($e->getMessage()),
                ]);
                $errMessage = $this->createErrorMessage($conversation, 'AI_PROVIDER_UNAVAILABLE');
                return $this->toDTO($errMessage);
            }

            if (!empty($response['error'])) {
                $errMessage = $this->createErrorMessage($conversation, 'AI_PROVIDER_UNAVAILABLE', array_merge($response['metadata'] ?? [], ['raw_error' => $response['error']]));
                return $this->toDTO($errMessage);
            }

            // If assistant returned direct text content without tool execution
            if (empty($response['tool_calls'])) {
                $lastResponse = $response;
                break;
            }

            // Process Tool Call turn
            $toolResultsTurn = [];

            foreach ($response['tool_calls'] as $tc) {
                $callDto = new ToolCallDTO($tc['id'], $tc['name'], $tc['arguments']);

                // Evaluate Action Policy Engine first
                $policyResult = $this->policyEngine->evaluateToolCall($callDto, $conversation);

                // Audit log policy action
                $latencyMs = (int)((microtime(true) - $startTime) * 1000);
                SupportAuditLog::log([
                    'conversation_id' => $conversation->id,
                    'actor_type' => $conversation->customer_id ? 'customer' : 'guest',
                    'actor_id' => $conversation->customer_id,
                    'action' => 'tool_policy_evaluate',
                    'tool_name' => $callDto->name,
                    'authorization_result' => $policyResult->value,
                    'before_data' => $callDto->arguments,
                    'after_data' => ['policy_effect' => $policyResult->value, 'latency_ms' => $latencyMs],
                ]);

                if ($policyResult === PolicyEffect::DENY) {
                    $result = ToolResultDTO::error($callDto->id, $callDto->name, 'Access Denied: Action was blocked by security policies.');
                    $toolResultsTurn[] = $result;
                    continue;
                } elseif ($policyResult === PolicyEffect::REQUIRE_VERIFICATION) {
                    $result = ToolResultDTO::error($callDto->id, $callDto->name, 'Authentication required to execute this action.');
                    $toolResultsTurn[] = $result;
                    continue;
                } elseif ($policyResult === PolicyEffect::CONFIRM) {
                    // Stop execution loop; return action confirmation card to customer
                    $confirmMessage = $this->createConfirmationMessage($conversation, $callDto);
                    return $this->toDTO($confirmMessage);
                } elseif ($policyResult === PolicyEffect::REQUIRE_HUMAN) {
                    // Escalation flow: hand off to human agent
                    $conversation->update([
                        'status' => ConversationStatus::QUEUED,
                        'mode' => ConversationMode::HYBRID,
                    ]);
                    $escalateMessage = $this->createEscalationMessage($conversation, "Handing conversation over to a support representative for verification.");
                    return $this->toDTO($escalateMessage);
                }

                // If policy is ALLOW, check if tool is registered
                $tool = $this->toolRegistry->get($callDto->name);
                if (!$tool) {
                    $result = ToolResultDTO::error($callDto->id, $callDto->name, 'Tool not found or is currently disabled.');
                    $toolResultsTurn[] = $result;
                    continue;
                }

                // Input schema validation
                $valErr = $this->toolRegistry->validate($callDto, $tool);
                if ($valErr) {
                    $result = ToolResultDTO::error($callDto->id, $callDto->name, $valErr);
                    $toolResultsTurn[] = $result;
                    continue;
                }

                // Execute the allowed tool
                $result = $tool->execute($callDto, $conversation);
                $toolResultsTurn[] = $result;

                // Audit tool execution
                SupportAuditLog::log([
                    'conversation_id' => $conversation->id,
                    'actor_type' => $conversation->customer_id ? 'customer' : 'guest',
                    'actor_id' => $conversation->customer_id,
                    'action' => 'tool_execute',
                    'tool_name' => $callDto->name,
                    'authorization_result' => 'success',
                    'before_data' => $callDto->arguments,
                    'after_data' => [
                        'success' => $result->success,
                        'message' => $result->errorMessage,
                    ],
                ]);
            }

            // Append assistant tool request & tool responses to provider chat history
            $history[] = [
                'role' => 'assistant',
                'content' => null,
                'tool_calls' => array_map(function ($tc) {
                    return [
                        'id' => $tc['id'],
                        'name' => $tc['name'],
                        'arguments' => $tc['arguments'],
                    ];
                }, $response['tool_calls'])
            ];

            foreach ($toolResultsTurn as $r) {
                $history[] = [
                    'role' => 'tool',
                    'name' => $r->toolName,
                    'tool_call_id' => $r->toolCallId,
                    'content' => json_encode($r->data ?: ['error' => $r->errorMessage]),
                ];
            }

            $lastResponse = $response;
        }

        // 7. Store final assistant text response or last processed structured card
        if ($lastResponse) {
            $msgType = MessageType::TEXT;
            $payload = null;

            // Handle structured response mappings (e.g. product search output list card)
            if (isset($history[count($history) - 1]['role']) && $history[count($history) - 1]['role'] === 'tool') {
                $lastToolResult = $history[count($history) - 1];
                $data = json_decode($lastToolResult['content'], true) ?: [];

                if ($lastToolResult['name'] === 'search_products' && !empty($data['products'])) {
                    $msgType = MessageType::PRODUCT_LIST;
                    $payload = ['products' => $data['products']];
                } elseif ($lastToolResult['name'] === 'get_product_details' && !empty($data['id'])) {
                    $msgType = MessageType::PRODUCT;
                    $payload = ['product' => $data];
                } elseif ($lastToolResult['name'] === 'get_my_orders' && !empty($data['orders'])) {
                    $msgType = MessageType::ORDER;
                    $payload = ['orders' => $data['orders']];
                } elseif ($lastToolResult['name'] === 'track_my_order' && !empty($data['id'])) {
                    $msgType = MessageType::ORDER_STATUS;
                    $payload = ['order_status' => $data];
                }
            }

            $savedMsg = SupportMessage::create([
                'conversation_id' => $conversation->id,
                'sender_type' => SenderType::AI,
                'message_type' => $msgType,
                'content' => $lastResponse['text'] ?? 'Here are the details you requested:',
                'structured_payload' => $payload,
                'is_internal' => false,
            ]);

            return $this->toDTO($savedMsg);
        }

        $failedMsg = $this->createErrorMessage($conversation, 'AI_PROVIDER_UNAVAILABLE');
        return $this->toDTO($failedMsg);
    }

    protected function checkRateLimits(SupportConversation $conversation, string $message): ?string
    {
        if (strlen($message) > 1000) {
            return 'Message size exceeds the maximum limit of 1000 characters.';
        }

        $cacheKey = "support_ai_limit_" . ($conversation->customer_id ?: 'guest_' . request()->ip());
        $count = (int)Cache::get($cacheKey, 0);

        $limit = $conversation->customer_id ? 60 : 20; // 60 queries/hr for auth users, 20/hr for guests
        if ($count >= $limit) {
            return 'Too many requests. Please wait before sending another message.';
        }

        Cache::put($cacheKey, $count + 1, 3600);
        return null;
    }

    protected function createErrorMessage(SupportConversation $conversation, string $err, array $meta = []): SupportMessage
    {
        $sanitizedMeta = AuditRedactionService::sanitize($meta);
        $safeErr = AuditRedactionService::sanitizeString($err);

        $isTechnical = empty($err) || $err === 'AI_PROVIDER_UNAVAILABLE' || (bool)preg_match('/(api[\s\-_]?key|secret|token|bearer|unauthorized|forbidden|sql|exception|stack trace|connection refused|timeout|curl|http status|model turn|configured|openrouter|gemini|openai|provider)/i', $err);

        $displayContent = $isTechnical
            ? 'I am currently having trouble processing your request. Please try again shortly or request a human support agent.'
            : $safeErr;

        $errorCode = $isTechnical ? 'AI_PROVIDER_UNAVAILABLE' : 'SUPPORT_ERROR';

        // Internal audit logging of handled AI error (sanitized diagnostics)
        try {
            SupportAuditLog::log([
                'conversation_id' => $conversation->id,
                'actor_type' => $conversation->customer_id ? 'customer' : 'guest',
                'actor_id' => $conversation->customer_id,
                'action' => 'ai_provider_error',
                'authorization_result' => 'error',
                'after_data' => [
                    'code' => $errorCode,
                    'detail' => $safeErr,
                    'metadata' => $sanitizedMeta,
                ],
            ]);
        } catch (\Throwable $e) {
            // Non-blocking audit safety
        }

        return SupportMessage::create([
            'conversation_id' => $conversation->id,
            'sender_type' => SenderType::AI,
            'message_type' => MessageType::ERROR,
            'content' => $displayContent,
            'structured_payload' => [
                'error' => [
                    'code' => $errorCode,
                ],
            ],
            'is_internal' => false,
        ]);
    }

    protected function createConfirmationMessage(SupportConversation $conversation, ToolCallDTO $call): SupportMessage
    {
        return SupportMessage::create([
            'conversation_id' => $conversation->id,
            'sender_type' => SenderType::AI,
            'message_type' => MessageType::ACTION_CONFIRMATION,
            'content' => 'To complete this action, please confirm your request.',
            'structured_payload' => [
                'tool_name' => $call->name,
                'arguments' => $call->arguments,
                'requires_confirmation' => true,
            ],
            'is_internal' => false,
        ]);
    }

    protected function createEscalationMessage(SupportConversation $conversation, string $escalationText): SupportMessage
    {
        return SupportMessage::create([
            'conversation_id' => $conversation->id,
            'sender_type' => SenderType::SYSTEM,
            'message_type' => MessageType::ESCALATION,
            'content' => $escalationText,
            'is_internal' => false,
        ]);
    }

    protected function toDTO(SupportMessage $msg): ChatMessageDTO
    {
        return new ChatMessageDTO(
            senderType: $msg->sender_type,
            messageType: $msg->message_type,
            content: $msg->content,
            structuredPayload: $msg->structured_payload,
            isInternal: $msg->is_internal
        );
    }
}
