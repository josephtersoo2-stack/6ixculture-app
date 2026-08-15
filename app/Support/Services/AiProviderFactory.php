<?php

namespace App\Support\Services;

use App\Models\AiAgent;
use App\Support\Contracts\AiProviderInterface;
use App\Support\Services\Adapters\GeminiSupportAdapter;
use App\Support\Services\Adapters\OpenrouterSupportAdapter;
use Dipokhalder\Settings\Facades\Settings;

class AiProviderFactory
{
    /**
     * Resolve the active support AI provider adapter.
     */
    public static function make(): AiProviderInterface
    {
        try {
            $defaultAgentId = Settings::group('site')->get('site_default_ai_agent');
            if ($defaultAgentId > 0) {
                $agent = AiAgent::find($defaultAgentId);
                if ($agent) {
                    return self::resolveAdapterBySlug($agent->slug);
                }
            }
        } catch (\Throwable $e) {}

        // Active check fallback
        $activeAgent = AiAgent::where('status', 5)->first();
        if ($activeAgent) {
            return self::resolveAdapterBySlug($activeAgent->slug);
        }

        // Hard fallback to OpenRouter
        return new OpenrouterSupportAdapter();
    }

    private static function resolveAdapterBySlug(string $slug): AiProviderInterface
    {
        return match (strtolower($slug)) {
            'gemini' => new GeminiSupportAdapter(),
            default => new OpenrouterSupportAdapter(),
        };
    }
}
