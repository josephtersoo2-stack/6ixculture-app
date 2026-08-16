<?php

namespace App\Support\Events;

use App\Support\Models\SupportConversation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupportQueueUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public SupportConversation $conversation;
    public string $action;

    public function __construct(SupportConversation $conversation, string $action = 'queued')
    {
        $this->conversation = $conversation;
        $this->action = $action;
    }

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('support.agent.queue'),
        ];

        if ($this->conversation->department_id) {
            $channels[] = new PrivateChannel('support.agent.department.' . $this->conversation->department_id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'support.queue.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'conversation' => [
                'id' => $this->conversation->public_id,
                'status' => $this->conversation->status?->value,
                'priority' => $this->conversation->priority?->value,
                'channel' => $this->conversation->channel?->value,
                'subject' => $this->conversation->subject,
                'customer_name' => $this->conversation->customer?->name ?: ($this->conversation->guest_session_id ? 'Guest User' : 'Customer'),
                'department_id' => $this->conversation->department_id,
                'assigned_agent_id' => $this->conversation->assigned_agent_id,
                'created_at' => $this->conversation->created_at?->toIso8601String(),
                'updated_at' => $this->conversation->updated_at?->toIso8601String(),
            ],
        ];
    }
}
