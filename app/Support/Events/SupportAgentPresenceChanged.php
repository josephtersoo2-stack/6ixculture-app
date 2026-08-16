<?php

namespace App\Support\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupportAgentPresenceChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public User $agent;
    public string $status;
    public string $availability;

    public function __construct(User $agent, string $status, string $availability = 'available')
    {
        $this->agent = $agent;
        $this->status = $status;
        $this->availability = $availability;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('support.agent.presence'),
            new PrivateChannel('support.agent.queue'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'support.agent.presence.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'agent_id' => $this->agent->id,
            'agent_name' => $this->agent->name,
            'status' => $this->status,
            'availability' => $this->availability,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
