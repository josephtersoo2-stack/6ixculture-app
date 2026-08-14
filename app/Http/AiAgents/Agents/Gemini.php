<?php

namespace App\Http\AiAgents\Agents;

use App\Enums\Status;
use App\Http\Requests\AiRequest;
use App\Libraries\QueryExceptionLibrary;
use App\Models\AiAgent;
use App\Services\AiAbstract;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Gemini extends AiAbstract
{
    protected string $apiKey = '';
    protected string $configuredModel = 'gemini-3.7-flash';

    public function __construct()
    {
        parent::__construct();
        $this->aiAgent = AiAgent::with('gatewayOptions')->where(['slug' => 'gemini'])->first();
        if (!blank($this->aiAgent)) {
            $this->aiAgentOption = $this->aiAgent->gatewayOptions->pluck('value', 'option');
            if (env('DEMO')) {
                $this->apiKey = env('GEMINI_API_KEY', '');
            } else {
                $this->apiKey = $this->aiAgentOption['gemini_api_key'] ?? env('GEMINI_API_KEY', '');
            }

            $selectedModel = $this->aiAgentOption['gemini_model'] ?? 'gemini-3.7-flash';
            $customModel   = trim($this->aiAgentOption['gemini_custom_model'] ?? '');

            if (!empty($customModel)) {
                $this->configuredModel = $customModel;
            } elseif ($selectedModel === 'custom') {
                $this->configuredModel = !empty($customModel) ? $customModel : 'gemini-3.7-flash';
            } else {
                $this->configuredModel = $selectedModel;
            }
        } else {
            $this->apiKey = env('GEMINI_API_KEY', '');
        }
    }

    public function status(): bool
    {
        $aiAgent = AiAgent::where(['slug' => 'gemini', 'status' => Status::ACTIVE])->first();
        if (!blank($aiAgent)) {
            return true;
        }
        return !empty(env('GEMINI_API_KEY'));
    }

    public function getApiKey(): string
    {
        if (!empty($this->apiKey)) {
            return $this->apiKey;
        }
        return env('GEMINI_API_KEY', '');
    }

    public function getModel(): string
    {
        return !empty($this->configuredModel) ? $this->configuredModel : 'gemini-3.7-flash';
    }

    /**
     * Chat completions method for multi-turn conversations
     */
    public function chatCompletions(array $messages, array $options = []): ?string
    {
        $apiKey = $this->getApiKey();
        if (empty($apiKey)) {
            throw new Exception('Gemini API key is not configured.', 422);
        }

        $model = $options['model'] ?? $this->getModel();

        $contents = [];
        $systemInstruction = null;

        foreach ($messages as $msg) {
            $role = $msg['role'] ?? 'user';
            $text = is_array($msg['content']) ? ($msg['content'][0]['text'] ?? '') : $msg['content'];

            if ($role === 'system') {
                $systemInstruction = ['parts' => [['text' => $text]]];
            } else {
                $geminiRole = ($role === 'assistant' || $role === 'ai') ? 'model' : 'user';
                $contents[] = [
                    'role'  => $geminiRole,
                    'parts' => [['text' => $text]]
                ];
            }
        }

        if (empty($contents)) {
            $contents[] = [
                'role'  => 'user',
                'parts' => [['text' => 'Hello']]
            ];
        }

        $payload = [
            'contents' => $contents,
        ];
        if ($systemInstruction) {
            $payload['systemInstruction'] = $systemInstruction;
        }

        $modelsToTry = array_values(array_unique(array_filter([
            $model,
            'gemini-2.5-flash',
            'gemini-2.0-flash',
            'gemini-1.5-flash',
            'gemini-1.5-pro',
        ])));

        $lastError = null;

        foreach ($modelsToTry as $m) {
            try {
                $url = "https://generativelanguage.googleapis.com/v1beta/models/{$m}:generateContent?key={$apiKey}";
                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                ])->withoutVerifying()->timeout(30)->post($url, $payload);

                if ($response->successful()) {
                    $data = $response->json();
                    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
                    if ($text !== null) {
                        return trim($text);
                    }
                } else {
                    $err = $response->json('error.message') ?? $response->body();
                    $lastError = "Model [{$m}]: {$err}";
                }
            } catch (Exception $e) {
                $lastError = $e->getMessage();
            }
        }

        throw new Exception("Gemini chat failed: " . $lastError, 422);
    }

    /**
     * Call Gemini API endpoint with parts.
     *
     * @param array $parts
     * @return string|null
     * @throws Exception
     */
    protected function callGeminiApi(array $parts): ?string
    {
        $apiKey = $this->getApiKey();
        if (empty($apiKey)) {
            throw new Exception('Gemini API key is not configured.', 422);
        }

        $models = array_values(array_unique(array_filter([
            $this->configuredModel,
            'gemini-2.5-flash',
            'gemini-2.0-flash',
            'gemini-1.5-flash',
            'gemini-1.5-pro',
        ])));

        $lastException = null;

        foreach ($models as $model) {
            try {
                $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                ])->withoutVerifying()->timeout(30)->post($url, [
                    'contents' => [
                        [
                            'parts' => $parts
                        ]
                    ]
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
                    if ($text !== null) {
                        return $text;
                    }
                } else {
                    $err = $response->json('error.message') ?? $response->body();
                    $lastException = new Exception("Gemini API Error ({$model}): {$err}", 422);
                }
            } catch (Exception $e) {
                $lastException = $e;
            }
        }

        throw $lastException ?? new Exception('Gemini API request failed.', 422);
    }

    public function generatePromptText(array $parts): ?string
    {
        return $this->callGeminiApi($parts);
    }

    public function generateProductPromptText(array $parts): ?string
    {
        return $this->callGeminiApi($parts);
    }

    /**
     * @throws Exception
     */
    public function name(AiRequest $aiRequest): array|string|\Illuminate\Contracts\Translation\Translator|null
    {
        try {
            $prompt = $this->buildProductNamePrompt($aiRequest->name);
            $response = $this->callGeminiApi([['text' => $prompt]]);

            return $response ?? trans('all.message.agent_is_not_active');
        } catch (Exception $exception) {
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function description(AiRequest $aiRequest): array|string|\Illuminate\Contracts\Translation\Translator|null
    {
        try {
            $prompt = $this->buildProductDescriptionPrompt($aiRequest->name);
            $response = $this->callGeminiApi([['text' => $prompt]]);

            return $response ?? trans('all.message.agent_is_not_active');
        } catch (Exception $exception) {
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function message(AiRequest $aiRequest): array|string|\Illuminate\Contracts\Translation\Translator|null
    {
        try {
            $response = $this->callGeminiApi([['text' => $aiRequest->name]]);

            return $response ?? trans('all.message.agent_is_not_active');
        } catch (Exception $exception) {
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function tags(AiRequest $aiRequest): array|string|\Illuminate\Contracts\Translation\Translator|null
    {
        try {
            $prompt = $this->buildProductTagsPrompt($aiRequest->name);
            $response = $this->callGeminiApi([['text' => $prompt]]);

            return $response ?? trans('all.message.agent_is_not_active');
        } catch (Exception $exception) {
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * Generate complete product data from image and price.
     *
     * @param Request $request
     * @return array
     * @throws Exception
     */
    public function generateProduct(Request $request): array
    {
        try {
            $price = $request->input('price') ?? $request->input('selling_price') ?? $request->input('buying_price') ?? '';
            $existingName = $request->input('name', '');

            $promptText = <<<PROMPT
You are an expert e-commerce product manager and professional copywriter.
Analyze the provided product image and the price ({$price}).

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

            $parts = [];
            $parts[] = ['text' => $promptText];

            if ($request->hasFile('image')) {
                $imageFile = $request->file('image');
                $imageData = file_get_contents($imageFile->getRealPath());
                $mimeType = $imageFile->getMimeType() ?: 'image/jpeg';
                $parts[] = [
                    'inline_data' => [
                        'mime_type' => $mimeType,
                        'data' => base64_encode($imageData)
                    ]
                ];
            } elseif ($request->input('image_base64')) {
                $base64 = $request->input('image_base64');
                if (preg_match('/^data:(image\/[a-zA-Z+]+);base64,(.+)$/', $base64, $matches)) {
                    $mimeType = $matches[1];
                    $data = $matches[2];
                } else {
                    $mimeType = 'image/jpeg';
                    $data = $base64;
                }
                $parts[] = [
                    'inline_data' => [
                        'mime_type' => $mimeType,
                        'data' => $data
                    ]
                ];
            }

            $rawResponse = $this->callGeminiApi($parts);
            if (empty($rawResponse)) {
                throw new Exception('No content generated from Gemini AI.', 422);
            }

            $cleanJson = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($rawResponse));
            $parsed = json_decode($cleanJson, true);

            if (!is_array($parsed)) {
                throw new Exception('Failed to parse Gemini AI response as JSON: ' . $rawResponse, 422);
            }

            return [
                'name' => $parsed['name'] ?? $existingName,
                'description' => $parsed['description'] ?? '',
                'tags' => is_array($parsed['tags'] ?? null) ? $parsed['tags'] : [],
                'seo_title' => $parsed['seo_title'] ?? ($parsed['name'] ?? ''),
                'seo_description' => $parsed['seo_description'] ?? '',
                'seo_meta_keywords' => is_array($parsed['seo_meta_keywords'] ?? null) ? $parsed['seo_meta_keywords'] : (is_array($parsed['tags'] ?? null) ? $parsed['tags'] : []),
            ];
        } catch (Exception $exception) {
            Log::error('Gemini generateProduct error: ' . $exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }
}
