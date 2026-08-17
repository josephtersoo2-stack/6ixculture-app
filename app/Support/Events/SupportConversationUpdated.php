<?php

namespace App\Support\Events;

use App\Support\Models\SupportConversation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupportConversationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public SupportConversation $conversation;
    public string $updateType;

    public function __construct(SupportConversation $conversation, string $updateType = 'status_changed')
    {
        $this->conversation = $conversation;
        $this->updateType = $updateType;
    }

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('support.conversation.' . $this->conversation->public_id),
            new PrivateChannel('support.guest.conversation.' . $this->conversation->public_id),
            new PrivateChannel('support.agent.conversation.' . $this->conversation->public_id),
            new PrivateChannel('support.agent.queue'), // For elevated users
        ];

        if ($this->conversation->department_id) {
            $channels[] = new PrivateChannel('support.agent.department.' . $this->conversation->department_id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'support.conversation.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation->public_id,
            'update_type' => $this->updateType,
            'status' => $this->conversation->status?->value,
            'mode' => $this->conversation->mode?->value,
            'priority' => $this->conversation->priority?->value,
            'department' => $this->conversation->department ? [
                'id' => $this->conversation->department->id,
                'name' => $this->conversation->department->name,
                'slug' => $this->conversation->department->slug,
            ] : null,
            'assigned_agent' => $this->conversation->assignedAgent ? [
                'id' => $this->conversation->assignedAgent->id,
                'name' => $this->conversation->assignedAgent->name,
            ] : null,
            'updated_at' => $this->conversation->updated_at?->toIso8601String() ?: now()->toIso8601String(),
        ];
    }
}
