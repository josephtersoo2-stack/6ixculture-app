<?php

namespace App\Support\Services\Voice\Adapters;

use App\Http\AiAgents\Agents\Openrouter;
use App\Support\Contracts\SpeechToTextInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAiWhisperSttAdapter implements SpeechToTextInterface
{
    protected string $apiKey;
    protected string $model;
    protected int $maxBytes = 10485760; // 10 MB limit
    protected int $timeout = 30;

    public function __construct(?string $apiKey = null, string $model = 'whisper-1')
    {
        if ($apiKey) {
            $this->apiKey = $apiKey;
        } else {
            try {
                $baseAgent = new Openrouter();
                $this->apiKey = $baseAgent->getApiKey() ?: (string)env('OPENAI_API_KEY', env('OPENROUTER_API_KEY', ''));
            } catch (\Throwable $e) {
                $this->apiKey = (string)env('OPENAI_API_KEY', env('OPENROUTER_API_KEY', ''));
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
                'error' => 'STT API key is not configured.',
            ];
        }

        try {
            $client = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])
            ->withoutVerifying()
            ->timeout($this->timeout);

            if ($audio instanceof UploadedFile) {
                if ($audio->getSize() > $this->maxBytes) {
                    return [
                        'transcript' => '',
                        'detected_language' => $language,
                        'duration_seconds' => 0.0,
                        'confidence' => 0.0,
                        'error' => 'Audio file exceeds maximum allowed size (10MB).',
                    ];
                }

                $response = $client->attach(
                    'file',
                    file_get_contents($audio->getRealPath()),
                    $audio->getClientOriginalName() ?: 'audio.webm'
                )->post('https://api.openai.com/v1/audio/transcriptions', [
                    'model' => $this->model,
                    'language' => in_array($language, ['yo', 'ig', 'ha']) ? $language : 'en',
                    'response_format' => 'verbose_json',
                ]);
            } else {
                // Raw audio binary or base64
                $binary = base64_decode($audio, true) ?: $audio;
                if (strlen($binary) > $this->maxBytes) {
                    return [
                        'transcript' => '',
                        'detected_language' => $language,
                        'duration_seconds' => 0.0,
                        'confidence' => 0.0,
                        'error' => 'Audio exceeds maximum allowed size (10MB).',
                    ];
                }

                $response = $client->attach(
                    'file',
                    $binary,
                    'audio.webm'
                )->post('https://api.openai.com/v1/audio/transcriptions', [
                    'model' => $this->model,
                    'language' => in_array($language, ['yo', 'ig', 'ha']) ? $language : 'en',
                    'response_format' => 'verbose_json',
                ]);
            }

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'transcript' => trim($data['text'] ?? ''),
                    'detected_language' => $data['language'] ?? $language,
                    'duration_seconds' => (float)($data['duration'] ?? 0.0),
                    'confidence' => 0.95,
                    'error' => null,
                ];
            }

            $errMsg = $response->json('error.message') ?? 'Transcription request failed with status ' . $response->status();
            return [
                'transcript' => '',
                'detected_language' => $language,
                'duration_seconds' => 0.0,
                'confidence' => 0.0,
                'error' => $errMsg,
            ];
        } catch (\Throwable $e) {
            Log::warning('Whisper STT Exception: ' . $e->getMessage());
            return [
                'transcript' => '',
                'detected_language' => $language,
                'duration_seconds' => 0.0,
                'confidence' => 0.0,
                'error' => 'Speech-to-text service timeout or connection failure.',
            ];
        }
    }
}
