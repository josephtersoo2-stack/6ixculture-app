<?php

namespace App\Support\Cutover;

use App\Models\AiAgent;
use App\Support\Models\SupportAITool;
use App\Support\Models\SupportDepartment;
use App\Support\Models\SupportKnowledgeArticle;
use App\Support\Models\SupportPolicy;
use App\Support\Services\AiProviderFactory;
use App\Support\Services\Voice\VoiceCapabilityService;
use Dipokhalder\Settings\Facades\Settings;
use Illuminate\Support\Facades\Schema;

class SupportReadinessService
{
    /**
     * Compile sanitized internal health and readiness indicators.
     *
     * @return array<string, mixed>
     */
    public function getReadiness(): array
    {
        $blockers = [];
        $warnings = [];

        $cutoverStatus = SupportCutoverManager::getStatus();

        // 1. Table schema readiness (Critical Gate)
        $requiredTables = [
            'support_conversations',
            'support_messages',
            'support_departments',
            'support_policies',
            'support_ai_tools',
            'support_knowledge_articles',
            'support_tickets',
            'support_voice_sessions',
            'support_audit_logs',
            'support_customer_preferences',
            'support_legacy_migration_runs',
            'support_legacy_migration_items',
        ];

        $tablesReady = true;
        $tableStatus = [];
        foreach ($requiredTables as $table) {
            $exists = false;
            try {
                $exists = Schema::hasTable($table);
            } catch (\Throwable $e) {
                $exists = false;
            }

            $tableStatus[$table] = $exists;
            if (!$exists) {
                $tablesReady = false;
                $blockers[] = "Missing required support table: {$table}";
            }
        }

        // 2. AI Provider Configuration (Critical Gate via Runtime Factory)
        $aiProvider = AiProviderFactory::make();
        $providerName = $aiProvider->providerName();
        $providerConfigured = $aiProvider->isConfigured();

        if (!$providerConfigured) {
            $blockers[] = "Active AI provider '{$providerName}' is not configured with valid credentials.";
        }

        // 3. Governance Counts & Readiness (Critical Gate)
        $departmentCount = ($tableStatus['support_departments'] ?? false) ? SupportDepartment::where('is_active', true)->count() : 0;
        $policyCount = ($tableStatus['support_policies'] ?? false) ? SupportPolicy::where('is_active', true)->count() : 0;
        $toolCount = ($tableStatus['support_ai_tools'] ?? false) ? SupportAITool::where('is_active', true)->count() : 0;
        $publishedArticleCount = ($tableStatus['support_knowledge_articles'] ?? false) ? SupportKnowledgeArticle::where('status', 'published')->count() : 0;

        if ($departmentCount === 0) {
            $blockers[] = "No active support departments found (governance seeding required).";
        }
        if ($policyCount === 0) {
            $blockers[] = "No active support policies found (governance seeding required).";
        }
        if ($toolCount === 0) {
            $blockers[] = "No active support tools found (governance seeding required).";
        }

        $governanceReady = $departmentCount > 0 && $policyCount > 0 && $toolCount > 0;

        if ($publishedArticleCount === 0) {
            $warnings[] = "No published knowledge articles found; AI will operate with standard policy grounding.";
        }

        // 4. Voice Capabilities (Provider-Driven / Non-blocking)
        $sttReady = false;
        $ttsReady = false;
        $voiceProviderName = 'unconfigured';
        try {
            $voiceService = new VoiceCapabilityService();
            $voiceCaps = $voiceService->getCapabilities();
            $sttReady = (bool) ($voiceCaps['stt']['enabled'] ?? false);
            $ttsReady = (bool) ($voiceCaps['tts']['enabled'] ?? false);
            $voiceProviderName = $voiceCaps['stt']['provider'] ?? 'none';
        } catch (\Throwable $e) {
            $sttReady = false;
            $ttsReady = false;
        }

        if (!$sttReady || !$ttsReady) {
            $warnings[] = "Voice capabilities not fully configured (STT: " . ($sttReady ? 'ready' : 'unavailable') . ", TTS: " . ($ttsReady ? 'ready' : 'unavailable') . "); text Support remains fully functional.";
        }

        // 5. Realtime Transport & Fallback (Fallback-capable / Non-blocking)
        $broadcastDriver = config('broadcasting.default', 'log');
        $realtimeSupported = in_array($broadcastDriver, ['pusher', 'reverb', 'redis'], true);

        if (!$realtimeSupported) {
            $warnings[] = "Realtime broadcast transport '{$broadcastDriver}' is not WebSocket-ready; HTTP polling fallback active.";
        }

        // 6. Overall Status Determination
        $isReady = empty($blockers);
        if (!$isReady) {
            $status = 'blocked';
        } elseif (!empty($warnings)) {
            $status = 'degraded';
        } else {
            $status = 'ready';
        }

        return [
            'status' => $status,
            'ready' => $isReady,
            'blockers' => $blockers,
            'warnings' => $warnings,
            'cutover' => $cutoverStatus,
            'infrastructure' => [
                'support_tables_ready' => $tablesReady,
                'tables' => $tableStatus,
            ],
            'ai_readiness' => [
                'provider' => $providerName,
                'configured' => $providerConfigured,
                'provider_configured' => $providerConfigured,
                'default_agent_slug' => $providerName,
            ],
            'governance' => [
                'ready' => $governanceReady,
                'active_departments' => $departmentCount,
                'active_policies' => $policyCount,
                'active_tools' => $toolCount,
                'published_articles' => $publishedArticleCount,
            ],
            'realtime' => [
                'supported' => $realtimeSupported,
                'transport' => $broadcastDriver,
                'polling_fallback' => true,
            ],
            'voice' => [
                'stt_ready' => $sttReady,
                'tts_ready' => $ttsReady,
                'provider' => $voiceProviderName,
                'multilingual_languages' => ['en', 'yo', 'ig', 'ha'],
            ],
            'environment' => [
                'app_env' => config('app.env', 'production'),
                'debug' => (bool) config('app.debug', false),
            ],
            'queue' => [
                'driver' => config('queue.default', 'sync'),
                'connection' => config('queue.default', 'sync'),
            ],
            'cache' => [
                'store' => config('cache.default', 'file'),
            ],
            'session' => [
                'driver' => config('session.driver', 'file'),
            ],
        ];
    }
}
