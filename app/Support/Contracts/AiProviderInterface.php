<?php

namespace App\Support\Contracts;

interface AiProviderInterface
{
    /**
     * Send messages (conversation history) and optional tool schemas to the AI provider.
     * Returns a normalized array containing:
     * - 'text' => string|null
     * - 'tool_calls' => array (each element: ['id' => string, 'name' => string, 'arguments' => array])
     * - 'usage' => ['prompt_tokens' => int, 'completion_tokens' => int, 'total_tokens' => int]
     * - 'metadata' => ['model' => string, 'provider' => string]
     * - 'finish_reason' => string
     * - 'error' => string|null
     */
    public function chat(array $messages, array $tools = []): array;

    public function supportsToolCalling(): bool;

    public function supportsStructuredOutput(): bool;

    public function supportsStreaming(): bool;
}
