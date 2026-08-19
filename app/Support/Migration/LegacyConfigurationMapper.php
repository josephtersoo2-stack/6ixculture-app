<?php

namespace App\Support\Migration;

use App\Models\AiAgent;
use App\Support\Models\SupportAuditLog;
use Dipokhalder\Settings\Facades\Settings;
use Illuminate\Support\Facades\Log;

class LegacyConfigurationMapper
{
    /**
     * Inspect and discover legacy AI chat configurations.
     *
     * @return array
     */
    public static function discover(): array
    {
        $legacyAgent = null;
        $legacyModel = null;
        $currentTargetAgentId = null;
        $currentTargetAgentSlug = null;

        try {
            $legacyAgent = Settings::group('site')->get('site_chat_ai_agent');
            $legacyModel = Settings::group('site')->get('site_chat_ai_model');
            $currentTargetAgentId = (int) Settings::group('site')->get('site_default_ai_agent');

            if ($currentTargetAgentId > 0) {
                $currentTarget = AiAgent::find($currentTargetAgentId);
                $currentTargetAgentSlug = $currentTarget?->slug;
            }
        } catch (\Throwable $e) {
            Log::warning('Legacy configuration discovery note: ' . $e->getMessage());
        }

        return [
            'legacy_settings' => [
                'site_chat_ai_agent' => $legacyAgent,
                'site_chat_ai_model' => $legacyModel,
            ],
            'current_target_settings' => [
                'site_default_ai_agent_id' => $currentTargetAgentId > 0 ? $currentTargetAgentId : null,
                'site_default_ai_agent_slug' => $currentTargetAgentSlug,
            ],
            'can_migrate_provider' => empty($currentTargetAgentId) && !empty($legacyAgent),
            'prototype_prompt_governance' => 'Prototype system prompt was identified and excluded from automated knowledge/policy publishing per Phase 7/9 governance rules.',
        ];
    }

    /**
     * Migrate non-secret configuration if target is unset and apply is requested.
     *
     * @param bool $apply
     * @return array
     */
    public static function migrate(bool $apply = false): array
    {
        $discovery = self::discover();
        $migrated = false;
        $migratedKey = null;
        $targetAgentId = null;
        $message = 'No configuration changes applied.';

        if ($discovery['can_migrate_provider']) {
            $legacySlug = strtolower(trim((string) $discovery['legacy_settings']['site_chat_ai_agent']));
            $matchingAgent = AiAgent::where('slug', $legacySlug)->first();

            if ($matchingAgent) {
                $targetAgentId = (int) $matchingAgent->id;

                if ($apply) {
                    try {
                        Settings::group('site')->set('site_default_ai_agent', $targetAgentId);
                        $migrated = true;
                        $migratedKey = 'site_default_ai_agent';
                        $message = "Migrated legacy provider '{$legacySlug}' to site_default_ai_agent (Agent ID: {$targetAgentId}).";

                        SupportAuditLog::log([
                            'actor_type' => 'system',
                            'action' => 'legacy_config_migrated',
                            'resource_type' => 'setting',
                            'resource_id' => $targetAgentId,
                            'metadata' => [
                                'setting_key' => 'site_default_ai_agent',
                                'legacy_agent_slug' => $legacySlug,
                                'new_agent_id' => $targetAgentId,
                            ],
                        ]);
                    } catch (\Throwable $e) {
                        $message = 'Failed to write setting: ' . $e->getMessage();
                    }
                } else {
                    $message = "Dry-run: Would migrate legacy provider '{$legacySlug}' to site_default_ai_agent (Agent ID: {$targetAgentId}).";
                }
            } else {
                $message = "Legacy provider slug '{$legacySlug}' does not match any existing AiAgent record.";
            }
        } elseif (!empty($discovery['current_target_settings']['site_default_ai_agent_id'])) {
            $message = 'Current Support provider setting is already configured. Not overwriting.';
        }

        return [
            'discovery' => $discovery,
            'migrated' => $migrated,
            'migrated_key' => $migratedKey,
            'target_agent_id' => $targetAgentId,
            'message' => $message,
        ];
    }
}
