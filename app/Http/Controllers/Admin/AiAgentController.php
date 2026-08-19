<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\PaginateRequest;
use App\Http\Resources\AiAgentResource;
use App\Services\AiAgentService;
use Dipokhalder\Settings\Facades\Settings;
use App\Models\AiAgent;
use App\Http\AiAgents\Agents\Openrouter;
use App\Http\AiAgents\Agents\Gemini;
use App\Http\AiAgents\Agents\Openai;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiAgentController extends AdminController implements HasMiddleware
{
    private AiAgentService $aiAgentService;

    public function __construct(AiAgentService $aiAgentService)
    {
        parent::__construct();
        $this->aiAgentService = $aiAgentService;
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:settings', only: ['index', 'update', 'testModel', 'openrouterModels'])
        ];
    }

    public function index(PaginateRequest $request): \Illuminate\Http\Response|\Illuminate\Http\Resources\Json\AnonymousResourceCollection|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        try {
            return AiAgentResource::collection($this->aiAgentService->list($request));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function update(Request $request): AiAgentResource|\Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $className          = 'App\\Http\\AiAgents\\Requests\\' . ucfirst($request->ai_agent_type);
        $gateway            = new $className;
        $validationRequests = $request->validate($gateway->rules());
        try {
            return new AiAgentResource($this->aiAgentService->update($validationRequests));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    /**
     * Fetch dynamic live models directly from openrouter.ai
     */
    public function openrouterModels(Request $request): JsonResponse
    {
        try {
            $search = strtolower(trim($request->input('search', '')));
            $models = Openrouter::fetchModels();

            // Fallback if cache is empty
            if (empty($models)) {
                $response = Http::withoutVerifying()
                    ->timeout(15)
                    ->get('https://openrouter.ai/api/v1/models');

                if ($response->successful()) {
                    $raw = $response->json('data') ?? [];
                    foreach ($raw as $m) {
                        $models[] = [
                            'id'             => $m['id'],
                            'name'           => $m['name'] ?? $m['id'],
                            'description'    => $m['description'] ?? '',
                            'context_length' => $m['context_length'] ?? 0,
                            'pricing'        => $m['pricing'] ?? [],
                        ];
                    }
                    Cache::put('openrouter_available_models', $models, 3600);
                }
            }

            if (!empty($search)) {
                $models = array_values(array_filter($models, function ($m) use ($search) {
                    return str_contains(strtolower($m['id']), $search) ||
                           str_contains(strtolower($m['name']), $search) ||
                           str_contains(strtolower($m['description']), $search);
                }));
            }

            return response()->json([
                'status' => true,
                'count'  => count($models),
                'models' => $models,
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Live Test Connection & Model
     */
    public function testModel(Request $request): JsonResponse
    {
        $agentSlug = strtolower(trim($request->input('agent', 'gemini')));
        $model = trim($request->input('model', ''));
        $apiKey = trim($request->input('api_key', ''));
        $prompt = trim($request->input('prompt', 'Hello! Confirm in one friendly sentence that you are connected to 6ix Culture.'));

        $startTime = microtime(true);

        try {
            if ($agentSlug === 'openrouter') {
                $keyToUse = !empty($apiKey) ? $apiKey : '';
                $modelToUse = !empty($model) ? $model : 'openai/gpt-4o-mini';

                if (empty($keyToUse)) {
                    try {
                        $agent = AiAgent::with('gatewayOptions')->where('slug', 'openrouter')->first();
                        if ($agent) {
                            $opts = $agent->gatewayOptions->pluck('value', 'option');
                            $keyToUse = trim($opts['openrouter_api_key'] ?? '');
                        }
                    } catch (Exception $e) {}
                    if (empty($keyToUse)) {
                        $keyToUse = env('OPENROUTER_API_KEY', '');
                    }
                }

                if (empty($keyToUse)) {
                    throw new Exception('OpenRouter API key is empty. Please enter your OpenRouter key (starts with sk-or-v1-...).', 422);
                }

                $payload = [
                    'model'       => $modelToUse,
                    'messages'    => [
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'max_tokens'  => 120,
                    'temperature' => 0.7,
                ];

                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $keyToUse,
                    'HTTP-Referer'  => 'https://6ixculture.com.ng',
                    'X-Title'       => '6ix Culture Store',
                    'Content-Type'  => 'application/json',
                ])
                ->withoutVerifying()
                ->timeout(30)
                ->post('https://openrouter.ai/api/v1/chat/completions', $payload);

                $durationMs = round((microtime(true) - $startTime) * 1000);

                if ($response->successful()) {
                    $json = $response->json();
                    $reply = $json['choices'][0]['message']['content'] ?? 'Connected successfully (No text returned)';
                    return response()->json([
                        'status'      => true,
                        'provider'    => 'OpenRouter (openrouter.ai)',
                        'model'       => $modelToUse,
                        'latency_ms'  => $durationMs,
                        'reply'       => trim($reply),
                        'http_status' => $response->status(),
                    ]);
                } else {
                    $errText = $response->json('error.message') ?? $response->body();
                    return response()->json([
                        'status'      => false,
                        'provider'    => 'OpenRouter (openrouter.ai)',
                        'model'       => $modelToUse,
                        'latency_ms'  => $durationMs,
                        'error'       => "OpenRouter Error ({$response->status()}): {$errText}",
                        'http_status' => $response->status(),
                    ]);
                }
            } elseif ($agentSlug === 'gemini') {
                $keyToUse = !empty($apiKey) ? $apiKey : '';
                $modelToUse = !empty($model) ? $model : 'gemini-2.5-flash';

                if (empty($keyToUse)) {
                    try {
                        $agent = AiAgent::with('gatewayOptions')->where('slug', 'gemini')->first();
                        if ($agent) {
                            $opts = $agent->gatewayOptions->pluck('value', 'option');
                            $keyToUse = trim($opts['gemini_api_key'] ?? '');
                        }
                    } catch (Exception $e) {}
                    if (empty($keyToUse)) {
                        $keyToUse = env('GEMINI_API_KEY', '');
                    }
                }

                if (empty($keyToUse)) {
                    throw new Exception('Gemini API key is empty. Please enter your Google Gemini API key.', 422);
                }

                $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelToUse}:generateContent?key={$keyToUse}";
                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->withoutVerifying()
                ->timeout(30)
                ->post($url, [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt]
                            ]
                        ]
                    ]
                ]);

                $durationMs = round((microtime(true) - $startTime) * 1000);

                if ($response->successful()) {
                    $data = $response->json();
                    $reply = $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Success';
                    return response()->json([
                        'status'      => true,
                        'provider'    => 'Google Gemini',
                        'model'       => $modelToUse,
                        'latency_ms'  => $durationMs,
                        'reply'       => trim($reply),
                        'http_status' => $response->status(),
                    ]);
                } else {
                    $errText = $response->json('error.message') ?? $response->body();
                    return response()->json([
                        'status'      => false,
                        'provider'    => 'Google Gemini',
                        'model'       => $modelToUse,
                        'latency_ms'  => $durationMs,
                        'error'       => "Gemini Error ({$response->status()}): {$errText}",
                        'http_status' => $response->status(),
                    ]);
                }
            } else {
                // OpenAI standard test
                $keyToUse = !empty($apiKey) ? $apiKey : '';
                $modelToUse = !empty($model) ? $model : 'gpt-4o';

                if (empty($keyToUse)) {
                    try {
                        $agent = AiAgent::with('gatewayOptions')->where('slug', 'openai')->first();
                        if ($agent) {
                            $opts = $agent->gatewayOptions->pluck('value', 'option');
                            $keyToUse = trim($opts['openai_api_key'] ?? '');
                        }
                    } catch (Exception $e) {}
                    if (empty($keyToUse)) {
                        $keyToUse = env('OPENAI_API_KEY', '');
                    }
                }

                if (empty($keyToUse)) {
                    throw new Exception('OpenAI API key is empty. Please enter your OpenAI API key.', 422);
                }

                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $keyToUse,
                    'Content-Type'  => 'application/json',
                ])
                ->withoutVerifying()
                ->timeout(30)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model'       => $modelToUse,
                    'messages'    => [['role' => 'user', 'content' => $prompt]],
                    'max_tokens'  => 100,
                ]);

                $durationMs = round((microtime(true) - $startTime) * 1000);

                if ($response->successful()) {
                    $json = $response->json();
                    $reply = $json['choices'][0]['message']['content'] ?? 'Success';
                    return response()->json([
                        'status'      => true,
                        'provider'    => 'OpenAI',
                        'model'       => $modelToUse,
                        'latency_ms'  => $durationMs,
                        'reply'       => trim($reply),
                        'http_status' => $response->status(),
                    ]);
                } else {
                    $errText = $response->json('error.message') ?? $response->body();
                    return response()->json([
                        'status'      => false,
                        'provider'    => 'OpenAI',
                        'model'       => $modelToUse,
                        'latency_ms'  => $durationMs,
                        'error'       => "OpenAI Error ({$response->status()}): {$errText}",
                        'http_status' => $response->status(),
                    ]);
                }
            }
        } catch (Exception $e) {
            $durationMs = round((microtime(true) - $startTime) * 1000);
            return response()->json([
                'status'     => false,
                'provider'   => ucfirst($agentSlug),
                'model'      => $model,
                'latency_ms' => $durationMs,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
