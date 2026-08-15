<?php

namespace App\Support\Services\Adapters;

use App\Http\AiAgents\Agents\Openrouter as BaseOpenrouter;
use App\Support\Contracts\AiProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenrouterSupportAdapter implements AiProviderInterface
{
    protected BaseOpenrouter $baseAgent;
    protected string $apiKey;
    protected string $model;

    public function __construct()
    {
        $this->baseAgent = new BaseOpenrouter();
        $this->apiKey = $this->baseAgent->getApiKey();
        $this->model = $this->baseAgent->getModel();
    }

    public function chat(array $messages, array $tools = []): array
    {
        if (empty($this->apiKey)) {
            return [
                'text' => null,
                'tool_calls' => [],
                'usage' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
                'metadata' => ['model' => $this->model, 'provider' => 'openrouter'],
                'finish_reason' => 'error',
                'error' => 'OpenRouter API key is not configured.',
            ];
        }

        try {
            $payload = [
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => 0.2, // Low temperature for high reliability in tool calling/routing
            ];

            if (!empty($tools)) {
                $payload['tools'] = array_map(function ($tool) {
                    return [
                        'type' => 'function',
                        'function' => [
                            'name' => $tool['key'],
                            'description' => $tool['description'],
                            'parameters' => $tool['input_schema'],
                        ],
                    ];
                }, $tools);
                $payload['tool_choice'] = 'auto';
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'HTTP-Referer' => 'https://6ixculture.com.ng',
                'X-Title' => '6ix Culture E-Commerce Support',
                'Content-Type' => 'application/json',
            ])
            ->withoutVerifying()
            ->timeout(45)
            ->post('https://openrouter.ai/api/v1/chat/completions', $payload);

            if ($response->successful()) {
                $json = $response->json();
                $message = $json['choices'][0]['message'] ?? [];
                $text = $message['content'] ?? null;
                $finishReason = $json['choices'][0]['finish_reason'] ?? 'stop';
                
                $toolCalls = [];
                if (!empty($message['tool_calls'])) {
                    foreach ($message['tool_calls'] as $tc) {
                        $args = $tc['function']['arguments'] ?? '{}';
                        if (is_string($args)) {
                            $args = json_decode($args, true) ?: [];
                        }
                        $toolCalls[] = [
                            'id' => $tc['id'] ?? uniqid('call_'),
                            'name' => $tc['function']['name'],
                            'arguments' => $args,
                        ];
                    }
                }

                $usage = $json['usage'] ?? [
                    'prompt_tokens' => 0,
                    'completion_tokens' => 0,
                    'total_tokens' => 0
                ];

                return [
                    'text' => $text,
                    'tool_calls' => $toolCalls,
                    'usage' => [
                        'prompt_tokens' => $usage['prompt_tokens'] ?? 0,
                        'completion_tokens' => $usage['completion_tokens'] ?? 0,
                        'total_tokens' => $usage['total_tokens'] ?? 0,
                    ],
                    'metadata' => [
                        'model' => $this->model,
                        'provider' => 'openrouter',
                    ],
                    'finish_reason' => $finishReason,
                    'error' => null,
                ];
            }

            $errorBody = $response->body();
            Log::warning("OpenRouterSupportAdapter request failed: status {$response->status()}: {$errorBody}");

            return [
                'text' => null,
                'tool_calls' => [],
                'usage' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
                'metadata' => ['model' => $this->model, 'provider' => 'openrouter'],
                'finish_reason' => 'error',
                'error' => "OpenRouter Error ({$response->status()}): " . ($json['error']['message'] ?? $errorBody),
            ];
        } catch (\Throwable $e) {
            Log::error("OpenRouterSupportAdapter exception: " . $e->getMessage());
            return [
                'text' => null,
                'tool_calls' => [],
                'usage' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
                'metadata' => ['model' => $this->model, 'provider' => 'openrouter'],
                'finish_reason' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    public function supportsToolCalling(): bool
    {
        return true;
    }

    public function supportsStructuredOutput(): bool
    {
        return true;
    }

    public function supportsStreaming(): bool
    {
        return false;
    }
}
