<?php

namespace App\Support\Migration;

use App\Models\ChatConversation;
use App\Models\User;
use App\Support\Enums\ConversationMode;
use App\Support\Enums\ConversationStatus;
use App\Support\Enums\SupportChannel;
use App\Support\Enums\SupportPriority;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class LegacyConversationMapper
{
    /**
     * Map a legacy ChatConversation model to SupportConversation attributes.
     *
     * @param ChatConversation $legacy
     * @param Collection|null $messages Collection of legacy ChatMessage models
     * @param string|null $runPublicId
     * @return array
     */
    public static function map(ChatConversation $legacy, ?Collection $messages = null, ?string $runPublicId = null): array
    {
        $messages = $messages ?? $legacy->messages;

        // 1. Resolve Customer vs Guest Identity
        $customerId = null;
        $brokenUserId = null;
        $guestSessionId = null;

        if (!empty($legacy->user_id)) {
            $userExists = User::where('id', $legacy->user_id)->exists();
            if ($userExists) {
                $customerId = (int) $legacy->user_id;
            } else {
                $brokenUserId = (int) $legacy->user_id;
                $guestSessionId = (string) Str::uuid();
            }
        } else {
            $guestSessionId = (string) Str::uuid();
        }

        // 2. Resolve Status and Mode from legacy status and message history
        $hasHumanAgentReply = $messages->contains(function ($m) {
            return in_array($m->sender_type, ['agent', 'admin']);
        });

        $legacyStatus = strtolower(trim((string) $legacy->status));
        $closedAt = null;

        switch ($legacyStatus) {
            case 'ai':
                $status = ConversationStatus::AI_ACTIVE;
                $mode = ConversationMode::AI;
                $aiActive = true;
                $humanRequested = false;
                break;

            case 'human':
                if ($hasHumanAgentReply) {
                    $status = ConversationStatus::HUMAN_ACTIVE;
                } else {
                    $status = ConversationStatus::QUEUED;
                }
                $mode = ConversationMode::HUMAN;
                $aiActive = false;
                $humanRequested = true;
                break;

            case 'closed':
            default:
                $status = ConversationStatus::CLOSED;
                $mode = $hasHumanAgentReply ? ConversationMode::HUMAN : ConversationMode::AI;
                $aiActive = false;
                $humanRequested = $hasHumanAgentReply;
                $closedAt = $legacy->updated_at ?? $legacy->last_message_at ?? $legacy->created_at;
                break;
        }

        // 3. Compute Aggregate Timestamps from Messages
        $lastMessageAt = $legacy->last_message_at ?? $legacy->created_at;
        $lastCustomerMessageAt = null;
        $lastAgentMessageAt = null;
        $firstResponseAt = null;

        if ($messages->isNotEmpty()) {
            $sorted = $messages->sortBy(function ($m) {
                return $m->created_at ? $m->created_at->getTimestamp() : 0;
            });

            $lastMsg = $sorted->last();
            if ($lastMsg && $lastMsg->created_at) {
                $lastMessageAt = $lastMsg->created_at;
            }

            $lastCustomerMsg = $sorted->where('sender_type', 'user')->last();
            if ($lastCustomerMsg && $lastCustomerMsg->created_at) {
                $lastCustomerMessageAt = $lastCustomerMsg->created_at;
            }

            $lastAgentMsg = $sorted->filter(fn($m) => in_array($m->sender_type, ['agent', 'admin']))->last();
            if ($lastAgentMsg && $lastAgentMsg->created_at) {
                $lastAgentMessageAt = $lastAgentMsg->created_at;
            }

            // First response: first AI/Agent/Admin message after first customer message
            $firstCustomer = $sorted->where('sender_type', 'user')->first();
            if ($firstCustomer) {
                $firstResp = $sorted->filter(function ($m) use ($firstCustomer) {
                    return in_array($m->sender_type, ['ai', 'agent', 'admin'])
                        && $m->created_at >= $firstCustomer->created_at;
                })->first();

                if ($firstResp && $firstResp->created_at) {
                    $firstResponseAt = $firstResp->created_at;
                }
            }
        }

        // 4. Build namespaced migration metadata
        $migrationMeta = [
            'source_table' => 'chat_conversations',
            'source_id' => (int) $legacy->id,
            'source_status' => (string) $legacy->status,
            'source_session_hash' => hash('sha256', (string) $legacy->session_token),
            'source_created_at' => $legacy->created_at?->toIso8601String(),
            'source_updated_at' => $legacy->updated_at?->toIso8601String(),
            'migration_run' => $runPublicId,
            'historical_only' => true,
        ];

        if ($brokenUserId) {
            $migrationMeta['broken_user_id'] = $brokenUserId;
        }

        // Preserve contact snapshot for guests/missing users
        if (!$customerId && (!empty($legacy->user_name) || !empty($legacy->user_email) || !empty($legacy->user_phone))) {
            $migrationMeta['contact_snapshot'] = array_filter([
                'name' => $legacy->user_name,
                'email' => $legacy->user_email,
                'phone' => $legacy->user_phone,
            ]);
        }

        return [
            'customer_id' => $customerId,
            'guest_session_id' => $guestSessionId,
            'status' => $status,
            'mode' => $mode,
            'priority' => SupportPriority::NORMAL,
            'language' => 'en',
            'channel' => SupportChannel::WEB,
            'department_id' => null,
            'assigned_agent_id' => null,
            'assigned_at' => null,
            'first_response_at' => $firstResponseAt,
            'resolved_at' => $status === ConversationStatus::CLOSED ? $closedAt : null,
            'closed_at' => $closedAt,
            'last_message_at' => $lastMessageAt,
            'last_customer_message_at' => $lastCustomerMessageAt,
            'last_agent_message_at' => $lastAgentMessageAt,
            'ai_active' => $aiActive,
            'human_requested' => $humanRequested,
            'escalation_reason' => null,
            'ai_summary' => null,
            'sentiment' => null,
            'metadata' => [
                'legacy_migration' => $migrationMeta,
            ],
            'created_at' => $legacy->created_at,
            'updated_at' => $legacy->updated_at,
        ];
    }
}
