<?php

namespace App\Support\Services\Voice\Adapters;

use App\Http\AiAgents\Agents\Gemini;
use App\Support\Contracts\SpeechToTextInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiSttAdapter implements SpeechToTextInterface
{
    protected string $apiKey;
    protected string $model;
    protected int $maxBytes = 10485760; // 10 MB

    public function __construct(?string $apiKey = null, string $model = 'gemini-1.5-flash')
    {
        if ($apiKey) {
            $this->apiKey = $apiKey;
        } else {
            try {
                $baseAgent = new Gemini();
                $this->apiKey = $baseAgent->getApiKey() ?: (string)env('GEMINI_API_KEY', '');
            } catch (\Throwable $e) {
                $this->apiKey = (string)env('GEMINI_API_KEY', '');
            }
        }
        $this->model = $model;
    }

    public function transcribe(UploadedFile|string $audio, string $language = 'en'): array
    {
        if (empty($this->apiKey)) {
            return [
                'transcript' => '',
                'detected_language' => $language,
                'duration_seconds' => 0.0,
                'confidence' => 0.0,
                'error' => 'Gemini API key is not configured.',
            ];
        }

        try {
            $binary = $audio instanceof UploadedFile ? file_get_contents($audio->getRealPath()) : (base64_decode($audio, true) ?: $audio);
            $mimeType = $audio instanceof UploadedFile ? ($audio->getMimeType() ?: 'audio/webm') : 'audio/webm';
            $base64Data = base64_encode($binary);

            $payload = [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => "Transcribe the following spoken audio word-for-word in its original language ({$language}). Output only the exact transcript without explanations."],
                            [
                                'inlineData' => [
                                    'mimeType' => $mimeType,
                                    'data' => $base64Data,
                                ]
                            ]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.0,
                ]
            ];

            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->withoutVerifying()
                ->timeout(30)
                ->post($url, $payload);

            if ($response->successful()) {
                $text = $response->json('candidates.0.content.parts.0.text') ?? '';
                return [
                    'transcript' => trim($text),
                    'detected_language' => $language,
                    'duration_seconds' => 0.0,
                    'confidence' => 0.95,
                    'error' => null,
                ];
            }

            return [
                'transcript' => '',
                'detected_language' => $language,
                'duration_seconds' => 0.0,
                'confidence' => 0.0,
                'error' => $response->json('error.message') ?? 'Gemini audio transcription failed.',
            ];
        } catch (\Throwable $e) {
            Log::warning('Gemini STT Exception: ' . $e->getMessage());
            return [
                'transcript' => '',
                'detected_language' => $language,
                'duration_seconds' => 0.0,
                'confidence' => 0.0,
                'error' => 'Gemini transcription service exception.',
            ];
        }
    }
}
