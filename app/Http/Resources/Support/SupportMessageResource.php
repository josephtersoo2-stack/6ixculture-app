<?php

namespace App\Http\Resources\Support;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportMessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation ? $this->conversation->public_id : $this->conversation_id,
            'sender' => $this->sender_type ? $this->sender_type->value : 'ai',
            'type' => $this->message_type ? $this->message_type->value : 'text',
            'content' => $this->content,
            'payload' => $this->structured_payload,
            'language' => $this->language,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
