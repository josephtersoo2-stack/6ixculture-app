<?php

namespace App\Http\Resources\Support;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportConversationDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        // Strictly filter to customer-visible messages only
        $messages = $this->messages()
            ->customerVisible()
            ->orderBy('id', 'asc')
            ->get();

        return [
            'conversation' => [
                'id' => $this->public_id,
                'status' => $this->status ? $this->status->value : 'ai_active',
                'mode' => $this->mode ? $this->mode->value : 'ai',
                'language' => $this->language ?: 'en',
                'subject' => $this->subject,
                'is_authenticated' => !empty($this->customer_id),
                'guest_token' => $this->customer_id ? null : $this->guest_session_id,
                'created_at' => $this->created_at?->toIso8601String(),
                'updated_at' => $this->updated_at?->toIso8601String(),
            ],
            'messages' => SupportMessageResource::collection($messages),
        ];
    }
}
