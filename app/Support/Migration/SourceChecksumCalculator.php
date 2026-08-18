<?php

namespace App\Support\Migration;

use App\Models\ChatConversation;
use App\Models\ChatMessage;

class SourceChecksumCalculator
{
    /**
     * Compute stable checksum for a legacy ChatConversation record.
     */
    public static function forConversation(ChatConversation $conversation): string
    {
        $normalized = [
            'id' => (int) $conversation->id,
            'session_token' => (string) $conversation->session_token,
            'user_id' => $conversation->user_id ? (int) $conversation->user_id : null,
            'user_name' => (string) $conversation->user_name,
            'user_email' => (string) $conversation->user_email,
            'user_phone' => (string) $conversation->user_phone,
            'status' => (string) $conversation->status,
            'ip_address' => (string) $conversation->ip_address,
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'created_at' => $conversation->created_at?->toIso8601String(),
            'updated_at' => $conversation->updated_at?->toIso8601String(),
        ];

        return hash('sha256', json_encode($normalized));
    }

    /**
     * Compute stable checksum for a legacy ChatMessage record.
     */
    public static function forMessage(ChatMessage $message): string
    {
        $normalized = [
            'id' => (int) $message->id,
            'conversation_id' => (int) $message->conversation_id,
            'sender_type' => (string) $message->sender_type,
            'sender_id' => $message->sender_id ? (int) $message->sender_id : null,
            'message' => (string) $message->message,
            'is_read' => (bool) $message->is_read,
            'created_at' => $message->created_at?->toIso8601String(),
            'updated_at' => $message->updated_at?->toIso8601String(),
        ];

        return hash('sha256', json_encode($normalized));
    }
}
