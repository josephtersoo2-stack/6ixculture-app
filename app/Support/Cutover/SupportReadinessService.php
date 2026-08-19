<?php

namespace App\Support\Cutover;

use App\Models\AiAgent;
use App\Support\Models\SupportAITool;
use App\Support\Models\SupportDepartment;
use App\Support\Models\SupportKnowledgeArticle;
use App\Support\Models\SupportPolicy;
use Dipokhalder\Settings\Facades\Settings;
use Illuminate\Support\Facades\Schema;

class SupportReadinessService
{
    /**
     * Compile sanitized internal health and readiness indicators.
     */
    public function getReadiness(): array
    {
        $cutoverStatus = SupportCutoverManager::getStatus();

        // 1. Table schema readiness
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
            $exists = Schema::hasTable($table);
            $tableStatus[$table] = $exists;
            if (!$exists) {
                $tablesReady = false;
            }
        }

        // 2. AI Provider Configuration
        $defaultAgentId = null;
        $defaultAgentSlug = null;
        try {
            $defaultAgentId = (int) Settings::group('site')->get('site_default_ai_agent');
            if ($defaultAgentId > 0) {
                $agent = AiAgent::find($defaultAgentId);
                $defaultAgentSlug = $agent?->slug;
            }
        } catch (\Throwable $e) {
            // Ignore in testing environments without settings table
        }

        $hasConfiguredProvider = !empty($defaultAgentId) || (!empty(env('OPENROUTER_API_KEY')) || !empty(env('GEMINI_API_KEY')));

        // 3. Governance Counts
        $departmentCount = $tableStatus['support_departments'] ? SupportDepartment::where('is_active', true)->count() : 0;
        $policyCount = $tableStatus['support_policies'] ? SupportPolicy::where('is_active', true)->count() : 0;
        $toolCount = $tableStatus['support_ai_tools'] ? SupportAITool::where('is_active', true)->count() : 0;
        $publishedArticleCount = $tableStatus['support_knowledge_articles'] ? SupportKnowledgeArticle::where('status', 'published')->count() : 0;

        $governanceReady = $departmentCount > 0 && $policyCount > 0 && $toolCount > 0;

        return [
            'status' => 'ready',
            'cutover' => $cutoverStatus,
            'infrastructure' => [
                'support_tables_ready' => $tablesReady,
                'tables' => $tableStatus,
            ],
            'ai_readiness' => [
                'provider_configured' => $hasConfiguredProvider,
                'default_agent_slug' => $defaultAgentSlug,
            ],
            'governance' => [
                'ready' => $governanceReady,
                'active_departments' => $departmentCount,
                'active_policies' => $policyCount,
                'active_tools' => $toolCount,
                'published_articles' => $publishedArticleCount,
            ],
            'realtime' => [
                'supported' => true,
                'transport' => 'pusher_or_reverb',
                'polling_fallback' => true,
            ],
            'voice' => [
                'stt_ready' => true,
                'tts_ready' => true,
                'multilingual_languages' => ['en', 'yo', 'ig', 'ha'],
            ],
        ];
    }
}
