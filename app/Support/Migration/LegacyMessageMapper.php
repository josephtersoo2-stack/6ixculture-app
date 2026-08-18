<?php

namespace App\Support\Migration;

use App\Models\ChatMessage;
use App\Models\User;
use App\Support\Enums\MessageType;
use App\Support\Enums\SenderType;

class LegacyMessageMapper
{
    /**
     * Map a legacy ChatMessage to SupportMessage attributes.
     *
     * @param ChatMessage $legacy
     * @param int $supportConversationId
     * @param int|null $customerId
     * @param string|null $runPublicId
     * @return array
     */
    public static function map(ChatMessage $legacy, int $supportConversationId, ?int $customerId = null, ?string $runPublicId = null): array
    {
        $legacySenderType = strtolower(trim((string) $legacy->sender_type));
        $senderId = null;

        switch ($legacySenderType) {
            case 'user':
                $senderType = SenderType::CUSTOMER;
                $senderId = $customerId;
                break;

            case 'ai':
                $senderType = SenderType::AI;
                $senderId = null;
                break;

            case 'agent':
            case 'admin':
                $senderType = SenderType::AGENT;
                if (!empty($legacy->sender_id) && User::where('id', $legacy->sender_id)->exists()) {
                    $senderId = (int) $legacy->sender_id;
                }
                break;

            default:
                $senderType = SenderType::SYSTEM;
                break;
        }

        $migrationMeta = [
            'source_table' => 'chat_messages',
            'source_id' => (int) $legacy->id,
            'source_sender_type' => (string) $legacy->sender_type,
            'source_sender_id' => $legacy->sender_id ? (int) $legacy->sender_id : null,
            'migration_run' => $runPublicId,
        ];

        return [
            'conversation_id' => $supportConversationId,
            'sender_type' => $senderType,
            'sender_id' => $senderId,
            'message_type' => MessageType::TEXT,
            'content' => (string) $legacy->message,
            'structured_payload' => null,
            'language' => 'en',
            'is_internal' => false,
            'is_read' => (bool) $legacy->is_read,
            'tool_call_id' => null,
            'reply_to_id' => null,
            'tokens_used' => 0,
            'latency_ms' => 0,
            'metadata' => [
                'legacy_migration' => $migrationMeta,
            ],
            'created_at' => $legacy->created_at,
            'updated_at' => $legacy->updated_at,
        ];
    }
}
