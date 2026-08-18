<?php

namespace App\Support\Services\Voice;

use App\Support\Contracts\SpeechToTextInterface;
use App\Support\Contracts\TextToSpeechInterface;

class VoiceCapabilityService
{
    protected ?SpeechToTextInterface $stt;
    protected ?TextToSpeechInterface $tts;

    public function __construct(
        ?SpeechToTextInterface $stt = null,
        ?TextToSpeechInterface $tts = null
    ) {
        $this->stt = $stt;
        $this->tts = $tts;
    }

    /**
     * Get a safe, customer-facing reporting object of current voice capabilities derived from active providers.
     *
     * @return array<string, mixed>
     */
    public function getCapabilities(): array
    {
        $sttInstance = $this->stt ?? (app()->bound(SpeechToTextInterface::class) ? app(SpeechToTextInterface::class) : VoiceProviderFactory::makeStt());
        $ttsInstance = $this->tts ?? (app()->bound(TextToSpeechInterface::class) ? app(TextToSpeechInterface::class) : VoiceProviderFactory::makeTts());

        $sttCaps = method_exists($sttInstance, 'capabilities') ? $sttInstance->capabilities() : [
            'provider' => 'stt_default',
            'enabled' => method_exists($sttInstance, 'isConfigured') ? $sttInstance->isConfigured() : true,
            'languages' => [
                'en' => ['name' => 'English', 'native' => 'English', 'supported' => true],
                'yo' => ['name' => 'Yoruba', 'native' => 'Yorùbá', 'supported' => true],
                'ig' => ['name' => 'Igbo', 'native' => 'Igbo', 'supported' => true],
                'ha' => ['name' => 'Hausa', 'native' => 'Hausa', 'supported' => true],
            ],
            'audio_formats' => ['audio/webm', 'audio/mp4', 'audio/wav', 'audio/ogg', 'audio/mpeg'],
            'max_duration_seconds' => 60,
            'code_switching' => true,
        ];

        $ttsCaps = method_exists($ttsInstance, 'capabilities') ? $ttsInstance->capabilities() : [
            'provider' => 'tts_default',
            'enabled' => method_exists($ttsInstance, 'isConfigured') ? $ttsInstance->isConfigured() : true,
            'languages' => [
                'en' => ['supported' => true, 'locale' => 'en-NG', 'fallback' => 'en-US'],
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
            'default_voice' => 'nova',
            'speaking_rate' => [
                'min' => 0.75,
                'max' => 1.5,
                'default' => 1.0,
            ],
            'formats' => ['mp3', 'opus', 'aac'],
        ];

        // Sanitize: strictly strip any credential or sensitive data
        $this->sanitize($sttCaps);
        $this->sanitize($ttsCaps);

        return [
            'stt' => $sttCaps,
            'tts' => $ttsCaps,
            'features' => [
                'interruption_barge_in' => true,
                'session_continuity' => true,
                'code_switching_support' => (bool)($sttCaps['code_switching'] ?? true),
                'multilingual_knowledge_grounding' => true,
            ],
        ];
    }

    /**
     * Remove any accidental secrets or internal endpoints.
     */
    protected function sanitize(array &$data): void
    {
        $forbiddenKeys = [
            'api_key', 'apiKey', 'key', 'secret', 'token', 'auth', 'authorization',
            'endpoint', 'url', 'internal_url', 'password', 'client_secret'
        ];

        foreach ($data as $key => &$value) {
            if (in_array(strtolower((string)$key), $forbiddenKeys, true)) {
                unset($data[$key]);
                continue;
            }
            if (is_array($value)) {
                $this->sanitize($value);
            }
        }
    }
}
