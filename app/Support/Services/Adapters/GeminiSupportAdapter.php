<?php

namespace App\Support\Services\Adapters;

use App\Http\AiAgents\Agents\Gemini as BaseGemini;
use App\Support\Contracts\AiProviderInterface;
use App\Support\Services\AuditRedactionService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiSupportAdapter implements AiProviderInterface
{
    protected BaseGemini $baseAgent;
    protected string $apiKey;
    protected string $model;

    public function __construct()
    {
        $this->baseAgent = new BaseGemini();
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
                'metadata' => ['model' => $this->model, 'provider' => 'gemini'],
                'finish_reason' => 'error',
                'error' => 'AI_PROVIDER_CONFIGURATION_ERROR',
            ];
        }

        try {
            $contents = [];
            $systemInstruction = null;

            foreach ($messages as $msg) {
                $role = $msg['role'] ?? 'user';
                $content = $msg['content'] ?? '';

                if ($role === 'system') {
                    $systemInstruction = ['parts' => [['text' => $content]]];
                } elseif ($role === 'tool') {
                    // Tool output response content for Gemini
                    $contents[] = [
                        'role' => 'function',
                        'parts' => [
                            [
                                'functionResponse' => [
                                    'name' => $msg['name'] ?? '',
                                    'response' => ['output' => $content],
                                ]
                            ]
                        ]
                    ];
                } elseif (!empty($msg['tool_calls'])) {
                    // Recreate model tool call turn for Gemini
                    $parts = [];
                    foreach ($msg['tool_calls'] as $tc) {
                        $parts[] = [
                            'functionCall' => [
                                'name' => $tc['name'],
                                'args' => $tc['arguments'],
                            ]
                        ];
                    }
                    $contents[] = [
                        'role' => 'model',
                        'parts' => $parts,
                    ];
                } else {
                    $geminiRole = ($role === 'assistant' || $role === 'ai') ? 'model' : 'user';
                    $contents[] = [
                        'role' => $geminiRole,
                        'parts' => [['text' => $content]],
                    ];
                }
            }

            if (empty($contents)) {
                $contents[] = [
                    'role' => 'user',
                    'parts' => [['text' => 'Hello']],
                ];
            }

            $payload = [
                'contents' => $contents,
            ];

            if ($systemInstruction) {
                $payload['systemInstruction'] = $systemInstruction;
            }

            if (!empty($tools)) {
                $declarations = array_map(function ($tool) {
                    return [
                        'name' => $tool['key'],
                        'description' => $tool['description'],
                        'parameters' => $tool['input_schema'],
                    ];
                }, $tools);
                $payload['tools'] = [['functionDeclarations' => $declarations]];
            }

            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])
            ->timeout(30)
            ->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                $candidate = $data['candidates'][0] ?? [];
                $parts = $candidate['content']['parts'] ?? [];
                
                $text = null;
                $toolCalls = [];
                $finishReason = $candidate['finishReason'] ?? 'STOP';

                foreach ($parts as $part) {
                    if (isset($part['text'])) {
                        $text = $part['text'];
                    }
                    if (isset($part['functionCall'])) {
                        $fc = $part['functionCall'];
                        $toolCalls[] = [
                            'id' => uniqid('call_'),
                            'name' => $fc['name'],
                            'arguments' => $fc['args'] ?? [],
                        ];
                    }
                }

                // Metadata tokens
                $usage = [
                    'prompt_tokens' => $data['usageMetadata']['promptTokenCount'] ?? 0,
                    'completion_tokens' => $data['usageMetadata']['candidatesTokenCount'] ?? 0,
                    'total_tokens' => $data['usageMetadata']['totalTokenCount'] ?? 0,
                ];

                return [
                    'text' => $text,
                    'tool_calls' => $toolCalls,
                    'usage' => $usage,
                    'metadata' => [
                        'model' => $this->model,
                        'provider' => 'gemini',
                    ],
                    'finish_reason' => strtolower($finishReason),
                    'error' => null,
                ];
            }

            $status = $response->status();
            $sanitizedMsg = AuditRedactionService::sanitizeString((string)($response->json('error.message') ?? "HTTP {$status}"));

            Log::warning('GeminiSupportAdapter request failed', [
                'event' => 'gemini_request_failed',
                'provider' => 'gemini',
                'status' => $status,
                'message' => $sanitizedMsg,
            ]);

            $errorCode = $status === 429 ? 'AI_PROVIDER_RATE_LIMITED' : 'AI_PROVIDER_UNAVAILABLE';

            return [
                'text' => null,
                'tool_calls' => [],
                'usage' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
                'metadata' => ['model' => $this->model, 'provider' => 'gemini'],
                'finish_reason' => 'error',
                'error' => $errorCode,
            ];
        } catch (\Throwable $e) {
            Log::error('GeminiSupportAdapter exception', [
                'event' => 'gemini_exception',
                'provider' => 'gemini',
                'exception_class' => get_class($e),
                'message' => AuditRedactionService::sanitizeString($e->getMessage()),
            ]);

            return [
                'text' => null,
                'tool_calls' => [],
                'usage' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
                'metadata' => ['model' => $this->model, 'provider' => 'gemini'],
                'finish_reason' => 'error',
                'error' => 'AI_PROVIDER_UNAVAILABLE',
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

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    public function providerName(): string
    {
        return 'gemini';
    }
}
