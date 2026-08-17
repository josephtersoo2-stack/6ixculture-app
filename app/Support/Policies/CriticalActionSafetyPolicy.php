<?php

namespace App\Support\Policies;

use App\Support\Enums\PolicyEffect;
use App\Support\Enums\ToolRiskLevel;
use App\Support\Models\SupportAITool;
use App\Support\Models\SupportPolicy;

class CriticalActionSafetyPolicy
{
    /**
     * Immutable critical actions / tools that strictly require human escalation (REQUIRE_HUMAN).
     */
    public const CRITICAL_ACTIONS = [
        'request_refund',
        'refund',
        'change_payment_method',
        'update_payment',
        'refund_payment',
        'process_payment',
        'change_password',
        'reset_credentials',
        'update_account_email',
        'delete_account',
    ];

    /**
     * Actions that require explicit customer UI confirmation (CONFIRM).
     */
    public const SENSITIVE_ACTIONS = [
        'cancel_order',
        'cancel',
        'change_address',
    ];

    /**
     * Check if an action or tool is critical (requires human).
     */
    public static function isCriticalAction(string $actionName): bool
    {
        $normalized = strtolower(trim($actionName));
        if (in_array($normalized, self::CRITICAL_ACTIONS, true)) {
            return true;
        }

        try {
            $tool = SupportAITool::where('key', $actionName)->first();
            if ($tool && ($tool->risk_level === ToolRiskLevel::CRITICAL || $tool->requires_human)) {
                return true;
            }
        } catch (\Throwable $e) {}

        return false;
    }

    /**
     * Check if an action or tool is sensitive (requires confirmation).
     */
    public static function isSensitiveAction(string $actionName): bool
    {
        $normalized = strtolower(trim($actionName));
        if (in_array($normalized, self::SENSITIVE_ACTIONS, true)) {
            return true;
        }

        try {
            $tool = SupportAITool::where('key', $actionName)->first();
            if ($tool && ($tool->risk_level === ToolRiskLevel::SENSITIVE || $tool->requires_confirmation)) {
                return true;
            }
        } catch (\Throwable $e) {}

        return false;
    }

    /**
     * Enforce tool safety invariants on tool permission update.
     * Automatically normalizes unsafe downgrades to safe values.
     */
    public static function enforceToolSafety(SupportAITool $tool, array $updates): array
    {
        $toolKey = $tool->key;
        $isCritical = self::isCriticalAction($toolKey) || ($updates['risk_level'] ?? $tool->risk_level?->value) === 'critical';
        $isSensitive = self::isSensitiveAction($toolKey) || ($updates['risk_level'] ?? $tool->risk_level?->value) === 'sensitive';

        if ($isCritical) {
            $updates['requires_human'] = true;
            $updates['requires_authentication'] = true;
            $updates['requires_confirmation'] = true;
            $updates['risk_level'] = 'critical';
        } elseif ($isSensitive) {
            $updates['requires_confirmation'] = true;
            $updates['requires_authentication'] = true;
            if (($updates['risk_level'] ?? $tool->risk_level?->value) === 'low') {
                $updates['risk_level'] = 'sensitive';
            }
        }

        return $updates;
    }

    /**
     * Validate whether a policy configuration violates critical or sensitive action safeguards.
     * Returns null if valid, or an error string if invalid.
     */
    public static function validatePolicySafety(SupportPolicy $policy): ?string
    {
        $config = $policy->configuration ?? [];
        $toolName = $config['tool_name'] ?? $config['action'] ?? null;
        $effect = $policy->effect instanceof PolicyEffect ? $policy->effect->value : (string)$policy->effect;

        if ($toolName && self::isCriticalAction($toolName)) {
            if ($effect === 'allow' && empty($config['requires_human'])) {
                return "Cannot activate policy with ALLOW effect on critical action '{$toolName}' without human approval.";
            }
        }

        if ($toolName && self::isSensitiveAction($toolName)) {
            if ($effect === 'allow' && empty($config['requires_confirmation'])) {
                return "Cannot activate policy with ALLOW effect on sensitive action '{$toolName}' without customer confirmation.";
            }
        }

        return null;
    }

    /**
     * Evaluate action safety for runtime policy engine.
     */
    public static function evaluateSafeguard(string $actionName): ?PolicyEffect
    {
        if (self::isCriticalAction($actionName)) {
            return PolicyEffect::REQUIRE_HUMAN;
        }

        if (self::isSensitiveAction($actionName)) {
            return PolicyEffect::CONFIRM;
        }

        return null;
    }
}
