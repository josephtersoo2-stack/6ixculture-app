<?php

namespace App\Support\Policies;

use App\Support\Contracts\PolicyEngineInterface;
use App\Support\DTOs\ToolCallDTO;
use App\Support\Enums\PolicyEffect;
use App\Support\Enums\ToolRiskLevel;
use App\Support\Models\SupportAITool;
use App\Support\Models\SupportConversation;
use App\Support\Models\SupportPolicy;

class SupportActionPolicyEngine implements PolicyEngineInterface
{
    public function evaluateToolCall(ToolCallDTO $call, SupportConversation $conversation): PolicyEffect
    {
        try {
            // 1. Resolve registered tool metadata from DB
            $dbTool = SupportAITool::active()->where('key', $call->name)->first();
            if (!$dbTool) {
                return PolicyEffect::DENY; // Fail-closed for inactive/unknown tools
            }

            // 2. Authentication policy check
            if ($dbTool->requires_authentication && empty($conversation->customer_id)) {
                return PolicyEffect::REQUIRE_VERIFICATION;
            }

            // 3. Human escalations / confirm policy check
            if ($dbTool->requires_human || $dbTool->risk_level === ToolRiskLevel::CRITICAL) {
                return PolicyEffect::REQUIRE_HUMAN;
            }

            if ($dbTool->requires_confirmation || $dbTool->risk_level === ToolRiskLevel::SENSITIVE) {
                return PolicyEffect::CONFIRM;
            }

            // 4. Evaluate Custom Policies in DB
            $policies = SupportPolicy::active()->ordered()->get();
            foreach ($policies as $policy) {
                $config = $policy->configuration ?? [];

                // Enforce specific action policies
                if ($policy->key === 'refund_requires_approval' && $call->name === 'request_refund') {
                    return PolicyEffect::REQUIRE_HUMAN;
                }

                if ($policy->key === 'sensitive_action_confirmation' && in_array($call->name, ['cancel_order', 'change_address'])) {
                    return PolicyEffect::CONFIRM;
                }
            }

            return PolicyEffect::ALLOW;
        } catch (\Throwable $e) {
            return PolicyEffect::DENY; // Default safety fallback
        }
    }
}
