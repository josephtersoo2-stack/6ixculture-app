<?php

namespace App\Http\Resources\Support\Agent;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgentConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $lastMessage = $this->messages()->orderBy('id', 'desc')->first();

        return [
            'id' => $this->public_id,
            'status' => $this->status ? $this->status->value : 'queued',
            'mode' => $this->mode ? $this->mode->value : 'ai',
            'priority' => $this->priority ? $this->priority->value : 'normal',
            'channel' => $this->channel ? $this->channel->value : 'web',
            'language' => $this->language ?: 'en',
            'subject' => $this->subject,
            'ai_summary' => $this->ai_summary,
            'customer' => [
                'id' => $this->customer?->id,
                'name' => $this->customer?->name ?: ($this->guest_session_id ? 'Guest User' : 'Customer'),
                'email' => $this->customer?->email,
                'phone' => $this->customer?->phone,
                'is_guest' => empty($this->customer_id),
            ],
            'assigned_agent' => $this->assignedAgent ? [
                'id' => $this->assignedAgent->id,
                'name' => $this->assignedAgent->name,
                'email' => $this->assignedAgent->email,
            ] : null,
            'department' => $this->department ? [
                'id' => $this->department->id,
                'name' => $this->department->name,
            ] : null,
            'last_message' => $lastMessage ? [
                'sender_type' => $lastMessage->sender_type?->value,
                'content' => $lastMessage->content,
                'is_internal' => (bool)$lastMessage->is_internal,
                'created_at' => $lastMessage->created_at?->toIso8601String(),
            ] : null,
            'unread_agent_count' => $this->unread_agent_count ?? 0,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
