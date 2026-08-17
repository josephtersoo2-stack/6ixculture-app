<?php

namespace App\Support\Services\Voice;

class VoiceCapabilityService
{
    /**
     * Get a safe, customer-facing reporting object of current voice capabilities.
     *
     * @return array<string, mixed>
     */
    public function getCapabilities(): array
    {
        return [
            'stt' => [
                'enabled' => true,
                'provider' => 'whisper_gemini',
                'languages' => [
                    'en' => ['name' => 'English', 'native' => 'English', 'supported' => true],
                    'yo' => ['name' => 'Yoruba', 'native' => 'Yorùbá', 'supported' => true],
                    'ig' => ['name' => 'Igbo', 'native' => 'Igbo', 'supported' => true],
                    'ha' => ['name' => 'Hausa', 'native' => 'Hausa', 'supported' => true],
                ],
                'audio_formats' => ['audio/webm', 'audio/mp4', 'audio/wav', 'audio/ogg', 'audio/mpeg'],
                'max_duration_seconds' => 60,
                'code_switching' => true,
            ],
            'tts' => [
                'enabled' => true,
                'provider' => 'openai_tts',
                'languages' => [
                    'en' => ['supported' => true, 'locale' => 'en-NG', 'fallback' => 'en-US'],
                    'yo' => ['supported' => true, 'locale' => 'yo-NG', 'fallback' => 'en-NG'],
                    'ig' => ['supported' => true, 'locale' => 'ig-NG', 'fallback' => 'en-NG'],
                    'ha' => ['supported' => true, 'locale' => 'ha-NG', 'fallback' => 'en-NG'],
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
            ],
            'features' => [
                'interruption_barge_in' => true,
                'session_continuity' => true,
                'code_switching_support' => true,
                'multilingual_knowledge_grounding' => true,
            ],
        ];
    }
}
