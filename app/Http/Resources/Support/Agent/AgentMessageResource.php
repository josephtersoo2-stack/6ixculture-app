<?php

namespace App\Http\Resources\Support\Agent;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgentMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender_type' => $this->sender_type ? $this->sender_type->value : 'customer',
            'message_type' => $this->message_type ? $this->message_type->value : 'text',
            'content' => $this->content,
            'payload' => $this->structured_payload,
            'is_internal' => (bool)$this->is_internal,
            'agent' => $this->when($this->sender_type?->value === 'agent' && $this->sender_id, function () {
                return [
                    'id' => $this->user?->id,
                    'name' => $this->user?->name,
                    'email' => $this->user?->email,
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
