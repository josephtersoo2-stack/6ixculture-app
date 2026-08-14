<?php

namespace App\Support\Contracts;

use App\Support\DTOs\ChatMessageDTO;
use App\Support\Models\SupportConversation;

interface AiOrchestratorInterface
{
    /**
     * Process an incoming customer or guest message and return a structured AI response.
     */
    public function handle(SupportConversation $conversation, ChatMessageDTO $incomingMessage): ChatMessageDTO;
}
