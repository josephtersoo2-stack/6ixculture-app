<?php

namespace App\Http\Resources\Support\Agent;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgentConversationDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Load all messages chronologically (both customer-visible and internal notes for authorized agents)
        $messages = $this->messages()->with(['user'])->orderBy('id', 'asc')->get();

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
                'created_at' => $this->customer?->created_at?->toIso8601String(),
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
            'ticket' => $this->ticket ? [
                'id' => $this->ticket->id,
                'ticket_number' => $this->ticket->ticket_number,
                'subject' => $this->ticket->subject,
                'status' => $this->ticket->status?->value,
                'priority' => $this->ticket->priority?->value,
            ] : null,
            'tags' => $this->tags->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'color' => $tag->color,
                ];
            }),
            'messages' => AgentMessageResource::collection($messages),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
