<?php

namespace App\Support\Services\Voice\Adapters;

use App\Http\AiAgents\Agents\Openrouter;
use App\Support\Contracts\TextToSpeechInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OpenAiTtsAdapter implements TextToSpeechInterface
{
    protected string $apiKey;
    protected string $model;
    protected string $defaultVoice;
    protected int $maxChars = 2000;

    public function __construct(?string $apiKey = null, string $model = 'tts-1', string $defaultVoice = 'nova')
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
        $this->defaultVoice = $defaultVoice;
    }

    public function synthesize(string $text, string $language = 'en', array $options = []): array
    {
        if (empty($this->apiKey)) {
            return [
                'audio_content' => null,
                'audio_url' => null,
                'format' => 'mp3',
                'duration_seconds' => 0.0,
                'language' => $language,
                'error' => 'TTS API key is not configured.',
            ];
        }

        // Clean customer text (remove markdown asterisks and URLs for speech synthesis)
        $cleanText = preg_replace('/\[([^\]]+)\]\([^\)]+\)/', '$1', $text);
        $cleanText = preg_replace('/[*_~`#]/', '', $cleanText);
        $cleanText = Str::limit(trim($cleanText), $this->maxChars, '...');

        if (empty($cleanText)) {
            return [
                'audio_content' => null,
                'audio_url' => null,
                'format' => 'mp3',
                'duration_seconds' => 0.0,
                'language' => $language,
                'error' => 'Empty text for synthesis.',
            ];
        }

        try {
            $voice = $options['voice'] ?? $this->defaultVoice;
            $format = $options['format'] ?? 'mp3';
            $speed = (float)($options['speed'] ?? 1.0);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->withoutVerifying()
            ->timeout(25)
            ->post('https://api.openai.com/v1/audio/speech', [
                'model' => $this->model,
                'input' => $cleanText,
                'voice' => $voice,
                'response_format' => $format,
                'speed' => $speed,
            ]);

            if ($response->successful()) {
                $audioData = $response->body();
                $base64 = base64_encode($audioData);

                // Estimate duration (approx 150 words per minute -> 2.5 words per sec)
                $wordCount = str_word_count($cleanText) ?: 5;
                $estimatedDuration = round($wordCount / 2.5, 1);

                return [
                    'audio_content' => 'data:audio/mp3;base64,' . $base64,
                    'audio_url' => null,
                    'format' => $format,
                    'duration_seconds' => $estimatedDuration,
                    'language' => $language,
                    'error' => null,
                ];
            }

            $errMsg = $response->json('error.message') ?? 'TTS synthesis returned HTTP ' . $response->status();
            return [
                'audio_content' => null,
                'audio_url' => null,
                'format' => $format,
                'duration_seconds' => 0.0,
                'language' => $language,
                'error' => $errMsg,
            ];
        } catch (\Throwable $e) {
            Log::warning('OpenAI TTS Exception: ' . $e->getMessage());
            return [
                'audio_content' => null,
                'audio_url' => null,
                'format' => 'mp3',
                'duration_seconds' => 0.0,
                'language' => $language,
                'error' => 'Text-to-speech service timeout or connection failure.',
            ];
        }
    }

    /**
     * Check if provider is configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Get TTS provider capabilities.
     */
    public function capabilities(): array
    {
        $configured = $this->isConfigured();

        return [
            'provider' => 'openai_tts',
            'enabled' => $configured,
            'languages' => [
                'en' => ['supported' => $configured, 'locale' => 'en-NG', 'fallback' => 'en-US'],
                'yo' => ['supported' => false, 'locale' => 'yo-NG', 'fallback' => 'en-NG'],
                'ig' => ['supported' => false, 'locale' => 'ig-NG', 'fallback' => 'en-NG'],
                'ha' => ['supported' => false, 'locale' => 'ha-NG', 'fallback' => 'en-NG'],
            ],
            'voices' => [
                ['id' => 'alloy', 'name' => 'Alloy (Neutral & Crisp)', 'gender' => 'neutral'],
                ['id' => 'echo', 'name' => 'Echo (Warm & Authoritative)', 'gender' => 'male'],
                ['id' => 'fable', 'name' => 'Fable (Expressive)', 'gender' => 'neutral'],
                ['id' => 'onyx', 'name' => 'Onyx (Deep & Professional)', 'gender' => 'male'],
                ['id' => 'nova', 'name' => 'Nova (Friendly Concierge)', 'gender' => 'female'],
                ['id' => 'shimmer', 'name' => 'Shimmer (Bright & Clear)', 'gender' => 'female'],
            ],
            'default_voice' => $this->defaultVoice,
            'speaking_rate' => [
                'min' => 0.75,
                'max' => 1.5,
                'default' => 1.0,
            ],
            'formats' => ['mp3', 'opus', 'aac'],
        ];
    }
}
