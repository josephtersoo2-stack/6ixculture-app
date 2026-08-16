<?php

namespace App\Support\Services\Voice;

use App\Models\AiAgent;
use App\Support\Contracts\SpeechToTextInterface;
use App\Support\Contracts\TextToSpeechInterface;
use App\Support\Services\Voice\Adapters\GeminiSttAdapter;
use App\Support\Services\Voice\Adapters\OpenAiTtsAdapter;
use App\Support\Services\Voice\Adapters\OpenAiWhisperSttAdapter;
use Dipokhalder\Settings\Facades\Settings;

class VoiceProviderFactory
{
    /**
     * Resolve active Speech-To-Text adapter.
     */
    public static function makeStt(): SpeechToTextInterface
    {
        try {
            $defaultAgentId = Settings::group('site')->get('site_default_ai_agent');
            if ($defaultAgentId > 0) {
                $agent = AiAgent::find($defaultAgentId);
                if ($agent && strtolower($agent->slug) === 'gemini') {
                    return new GeminiSttAdapter();
                }
            }
        } catch (\Throwable $e) {}

        return new OpenAiWhisperSttAdapter();
    }

    /**
     * Resolve active Text-To-Speech adapter.
     */
    public static function makeTts(): TextToSpeechInterface
    {
        return new OpenAiTtsAdapter();
    }
}
