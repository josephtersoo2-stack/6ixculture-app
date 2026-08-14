<?php

namespace App\Support\Events;

use App\Models\User;
use App\Support\Models\SupportConversation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupportConversationAssigned
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public SupportConversation $conversation,
        public User $agent,
        public ?User $assignedBy = null
    ) {}
}
