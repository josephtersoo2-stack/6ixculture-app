<?php

namespace App\Support\Contracts;

use App\Support\DTOs\ToolCallDTO;
use App\Support\Enums\PolicyEffect;
use App\Support\Models\SupportConversation;

interface PolicyEngineInterface
{
    /**
     * Evaluate whether a tool execution or customer action is permitted under active policies.
     */
    public function evaluateToolCall(ToolCallDTO $call, SupportConversation $conversation): PolicyEffect;
}
