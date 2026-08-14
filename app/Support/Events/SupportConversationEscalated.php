<?php

namespace App\Support\Events;

use App\Support\Models\SupportConversation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupportConversationEscalated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public SupportConversation $conversation,
        public string $reason
    ) {}
}
