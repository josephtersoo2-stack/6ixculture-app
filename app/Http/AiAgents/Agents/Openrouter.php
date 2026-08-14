<?php

namespace App\Http\AiAgents\Agents;

use App\Enums\Status;
use App\Http\Requests\AiRequest;
use App\Libraries\QueryExceptionLibrary;
use App\Models\AiAgent;
use App\Services\AiAbstract;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Openrouter extends AiAbstract
{
    protected string $apiKey = '';
    protected string $baseUrl = 'https://openrouter.ai/api/v1';
    protected string $configuredModel = 'openai/gpt-4o-mini';

    public function __construct()
    {
        parent::__construct();
        $this->aiAgent = AiAgent::with('gatewayOptions')->where(['slug' => 'openrouter'])->first();
        if (!blank($this->aiAgent)) {
            $this->aiAgentOption = $this->aiAgent->gatewayOptions->pluck('value', 'option');
            
            $dbKey = trim($this->aiAgentOption['openrouter_api_key'] ?? '');
            $this->apiKey = !empty($dbKey) ? $dbKey : env('OPENROUTER_API_KEY', '');

            $selectedModel = $this->aiAgentOption['openrouter_model'] ?? 'openai/gpt-4o-mini';
            $customModel   = trim($this->aiAgentOption['openrouter_custom_model'] ?? '');

            if (!empty($customModel)) {
                $this->configuredModel = $customModel;
            } elseif ($selectedModel === 'custom') {
                $this->configuredModel = !empty($customModel) ? $customModel : 'openai/gpt-4o-mini';
            } else {
                $this->configuredModel = $selectedModel;
            }
        } else {
            $this->apiKey = env('OPENROUTER_API_KEY', '');
        }
    }

    public function status(): bool
    {
        $aiAgent = AiAgent::where(['slug' => 'openrouter', 'status' => Status::ACTIVE])->first();
        if (!blank($aiAgent)) {
            return true;
        }
        return !empty($this->apiKey);
    }

    public function getApiKey(): string
    {
        return !empty($this->apiKey) ? $this->apiKey : env('OPENROUTER_API_KEY', '');
    }

    public function getModel(): string
    {
        return !empty($this->configuredModel) ? $this->configuredModel : 'openai/gpt-4o-mini';
    }

    /**
     * Fetch all available models directly from OpenRouter API
     */
    public static function fetchModels(): array
    {
        return Cache::remember('openrouter_available_models', 3600, function () {
            try {
                $response = Http::withoutVerifying()
                    ->timeout(20)
                    ->get('https://openrouter.ai/api/v1/models');

                if ($response->successful()) {
                    $raw = $response->json('data') ?? [];
                    $models = [];
                    foreach ($raw as $m) {
                        $models[] = [
                            'id'             => $m['id'],
                            'name'           => $m['name'] ?? $m['id'],
                            'description'    => $m['description'] ?? '',
                            'context_length' => $m['context_length'] ?? 0,
                            'pricing'        => $m['pricing'] ?? [],
                        ];
                    }
                    return $models;
                }
            } catch (Exception $e) {
                Log::warning('OpenRouter fetchModels exception: ' . $e->getMessage());
            }
            return [];
        });
    }

    /**
     * Send chat completions request to OpenRouter
     *
     * @param array $messages
     * @param array $options
     * @return string|null
     * @throws Exception
     */
    public function chatCompletions(array $messages, array $options = []): ?string
    {
        $apiKey = $this->getApiKey();
        if (empty($apiKey)) {
            throw new Exception('OpenRouter API key is not configured. Please enter your sk-or-v1-... key in Settings > AI Agent.', 422);
        }

        $chosenModel = $options['model'] ?? $this->getModel();
        $modelsToTry = array_values(array_unique(array_filter([
            $chosenModel,
            'openai/gpt-4o-mini',
            'openai/gpt-4o',
            'deepseek/deepseek-chat',
            'google/gemini-2.5-flash',
            'anthropic/claude-3-haiku',
        ])));

        $lastError = null;

        foreach ($modelsToTry as $model) {
            try {
                $payload = [
                    'model'       => $model,
                    'messages'    => $messages,
                    'temperature' => $options['temperature'] ?? 0.7,
                    'max_tokens'  => $options['max_tokens'] ?? 2048,
                ];

                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'HTTP-Referer'  => 'https://6ixculture.com.ng',
                    'X-Title'       => '6ix Culture E-Commerce Store',
                    'Content-Type'  => 'application/json',
                ])
                ->withoutVerifying()
                ->timeout(45)
                ->post($this->baseUrl . '/chat/completions', $payload);

                if ($response->successful()) {
                    $json = $response->json();
                    $content = $json['choices'][0]['message']['content'] ?? null;
                    if (!empty($content)) {
                        return trim($content);
                    }
                }

                $errorBody = $response->body();
                Log::warning("OpenRouter model [{$model}] failed with status {$response->status()}: {$errorBody}");
                $lastError = "Model [{$model}] error ({$response->status()}): {$errorBody}";
            } catch (Exception $e) {
                Log::warning("OpenRouter exception on model [{$model}]: " . $e->getMessage());
                $lastError = $e->getMessage();
            }
        }

        throw new Exception("OpenRouter API request failed: " . $lastError, 422);
    }

    /**
     * Generate product name
     */
    public function name(AiRequest $aiRequest): array|string|\Illuminate\Contracts\Translation\Translator|null
    {
        try {
            $prompt = $this->buildProductNamePrompt($aiRequest->name);
            $messages = [
                ['role' => 'user', 'content' => $prompt]
            ];
            $response = $this->chatCompletions($messages);
            return $response ?: trans('all.message.agent_is_not_active');
        } catch (Exception $exception) {
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * Generate product description
     */
    public function description(AiRequest $aiRequest): array|string|\Illuminate\Contracts\Translation\Translator|null
    {
        try {
            $prompt = $this->buildProductDescriptionPrompt($aiRequest->description);
            $messages = [
                ['role' => 'user', 'content' => $prompt]
            ];
            $response = $this->chatCompletions($messages);
            return $response ?: trans('all.message.agent_is_not_active');
        } catch (Exception $exception) {
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * Generate notification / SMS message
     */
    public function message(AiRequest $aiRequest): array|string|\Illuminate\Contracts\Translation\Translator|null
    {
        try {
            $prompt = $this->buildMessagePrompt($aiRequest->message);
            $messages = [
                ['role' => 'user', 'content' => $prompt]
            ];
            $response = $this->chatCompletions($messages);
            return $response ?: trans('all.message.agent_is_not_active');
        } catch (Exception $exception) {
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * Generate product tags
     */
    public function tags(AiRequest $aiRequest): array|string|\Illuminate\Contracts\Translation\Translator|null
    {
        try {
            $prompt = $this->buildProductTagsPrompt($aiRequest->tags);
            $messages = [
                ['role' => 'user', 'content' => $prompt]
            ];
            $response = $this->chatCompletions($messages);
            return $response ?: trans('all.message.agent_is_not_active');
        } catch (Exception $exception) {
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * Generate complete e-commerce product package (Name, HTML description, tags, SEO metadata)
     */
    public function generateProduct(Request $request): array
    {
        try {
            $price = $request->input('price') ?? $request->input('selling_price') ?? $request->input('buying_price') ?? '';
            $existingName = $request->input('name', '');

            $promptText = <<<PROMPT
You are an expert e-commerce product manager and professional copywriter.
Analyze the product information and the price ({$price}).

Task: Generate complete e-commerce product details:
1. Product Name: A creative, appealing, concise product title (3-8 words).
2. Description: High quality HTML description starting with <h2><b>[Product Title]</b></h2>, followed by introductory paragraph, "Key Highlights:" with bold feature titles, and "Details & Specifications:" in bullet points (<ol>/<li>). No markdown code fences.
3. Tags: An array of 5-7 concise, searchable product tags.
4. SEO Title: An optimized title for search engines (under 60 characters).
5. SEO Description: A compelling meta description for search engines (under 160 characters).
6. SEO Meta Keywords: An array of 5-7 relevant SEO keywords.

CRITICAL INSTRUCTION:
- Language: The entire response MUST be written in language "{$this->langName}" (Code: {$this->langCode}).
- Output Format: Return ONLY a valid, raw JSON object with NO markdown code block formatting (do NOT use ```json or ```).

Required JSON format:
{
  "name": "Product Name Here",
  "description": "<h2><b>Product Name</b></h2><p>Description...</p>",
  "tags": ["tag1", "tag2", "tag3", "tag4", "tag5"],
  "seo_title": "SEO Title Here",
  "seo_description": "SEO Description Here",
  "seo_meta_keywords": ["keyword1", "keyword2", "keyword3", "keyword4", "keyword5"]
}
PROMPT;

            $userContent = [];
            $userContent[] = ['type' => 'text', 'text' => $promptText];

            // Support vision if image is uploaded
            if ($request->hasFile('image')) {
                $imageFile = $request->file('image');
                $imageData = file_get_contents($imageFile->getRealPath());
                $mimeType = $imageFile->getMimeType() ?: 'image/jpeg';
                $base64Url = "data:{$mimeType};base64," . base64_encode($imageData);
                $userContent[] = [
                    'type' => 'image_url',
                    'image_url' => ['url' => $base64Url]
                ];
            } elseif ($request->input('image_base64')) {
                $base64Url = $request->input('image_base64');
                $userContent[] = [
                    'type' => 'image_url',
                    'image_url' => ['url' => $base64Url]
                ];
            }

            $messages = [
                ['role' => 'user', 'content' => $userContent]
            ];

            $rawResponse = $this->chatCompletions($messages, ['temperature' => 0.4]);
            if (empty($rawResponse)) {
                throw new Exception('No content generated from OpenRouter AI.', 422);
            }

            $cleanJson = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($rawResponse));
            $parsed = json_decode($cleanJson, true);

            if (!is_array($parsed)) {
                throw new Exception('Failed to parse OpenRouter AI response as JSON: ' . $rawResponse, 422);
            }

            return [
                'name'              => $parsed['name'] ?? $existingName,
                'description'       => $parsed['description'] ?? '',
                'tags'              => is_array($parsed['tags'] ?? null) ? $parsed['tags'] : [],
                'seo_title'         => $parsed['seo_title'] ?? ($parsed['name'] ?? ''),
                'seo_description'   => $parsed['seo_description'] ?? '',
                'seo_meta_keywords' => is_array($parsed['seo_meta_keywords'] ?? null) ? $parsed['seo_meta_keywords'] : (is_array($parsed['tags'] ?? null) ? $parsed['tags'] : []),
            ];
        } catch (Exception $exception) {
            Log::error('OpenRouter generateProduct error: ' . $exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }
}
