<?php

namespace App\Support\Events;

use App\Support\Models\SupportMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupportMessageCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public SupportMessage $message;
    public string $conversationPublicId;

    public function __construct(SupportMessage $message)
    {
        $this->message = $message;
        $this->conversationPublicId = $message->conversation->public_id;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        // If it is an internal staff note, only broadcast to the agent-specific channel
        if ($this->message->is_internal) {
            return [
                new PrivateChannel('support.agent.conversation.' . $this->conversationPublicId),
            ];
        }

        // Customer-visible messages broadcast to the shared conversation channel
        return [
            new PrivateChannel('support.conversation.' . $this->conversationPublicId),
            new PrivateChannel('support.agent.conversation.' . $this->conversationPublicId),
        ];
    }

    /**
     * Broadcast event name.
     */
    public function broadcastAs(): string
    {
        return 'support.message.created';
    }

    /**
     * Sanitized DTO-style broadcast payload.
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'conversation_id' => $this->conversationPublicId,
            'sender_type' => $this->message->sender_type?->value,
            'sender_name' => $this->message->sender?->name ?: ($this->message->sender_type?->value === 'ai' ? 'CultureAI' : 'Support'),
            'message_type' => $this->message->message_type?->value,
            'content' => $this->message->content,
            'structured_payload' => $this->message->structured_payload,
            'is_internal' => (bool)$this->message->is_internal,
            'created_at' => $this->message->created_at?->toIso8601String() ?: now()->toIso8601String(),
        ];
    }
}
