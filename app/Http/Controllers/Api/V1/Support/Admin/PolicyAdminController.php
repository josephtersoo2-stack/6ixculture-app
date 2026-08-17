<?php

namespace App\Http\Controllers\Api\V1\Support\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\DTOs\ToolCallDTO;
use App\Support\Enums\ConversationMode;
use App\Support\Enums\ConversationStatus;
use App\Support\Enums\PolicyEffect;
use App\Support\Enums\SupportPriority;
use App\Support\Enums\ToolRiskLevel;
use App\Support\Models\SupportAITool;
use App\Support\Models\SupportAuditLog;
use App\Support\Models\SupportConversation;
use App\Support\Models\SupportPolicy;
use App\Support\Policies\SupportActionPolicyEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PolicyAdminController extends Controller
{
    /**
     * List all support action policies with category, active status, and priority ordering.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $this->authorizeGovernance($request);
        if (!$user) {
            return $this->errorForbidden();
        }

        $query = SupportPolicy::with(['creator:id,name,email', 'editor:id,name,email']);

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('key', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $policies = $query->orderBy('priority', 'desc')->orderBy('id', 'asc')->get();

        return response()->json([
            'data' => $policies,
            'effects' => ['allow', 'deny', 'confirm', 'require_verification', 'require_human'],
            'categories' => ['orders', 'returns', 'payments', 'account', 'security', 'general'],
        ], 200);
    }

    /**
     * Create a new support policy.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $this->authorizeGovernance($request);
        if (!$user) {
            return $this->errorForbidden();
        }

        $validated = $request->validate([
            'key' => 'required|string|max:64|unique:support_policies,key',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|string|max:50',
            'effect' => 'required|string|in:allow,deny,confirm,require_verification,require_human',
            'configuration' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'priority' => 'nullable|integer',
        ]);

        $policy = SupportPolicy::create([
            'key' => Str::slug($validated['key'], '_'),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'],
            'effect' => $validated['effect'],
            'configuration' => $validated['configuration'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'priority' => $validated['priority'] ?? 0,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        SupportAuditLog::log([
            'actor_type' => 'admin',
            'actor_id' => $user->id,
            'action' => 'POLICY_CREATED',
            'resource_type' => 'support_policy',
            'resource_id' => (string)$policy->id,
            'authorization_result' => 'ALLOWED',
            'after_data' => [
                'key' => $policy->key,
                'name' => $policy->name,
                'effect' => $policy->effect?->value ?? $policy->effect,
                'is_active' => $policy->is_active,
                'priority' => $policy->priority,
            ],
        ]);

        return response()->json([
            'data' => $policy->fresh(['creator:id,name,email', 'editor:id,name,email']),
            'message' => 'Policy created successfully.',
        ], 201);
    }

    /**
     * Get details of a single policy.
     */
    public function show(Request $request, $id): JsonResponse
    {
        $user = $this->authorizeGovernance($request);
        if (!$user) {
            return $this->errorForbidden();
        }

        $policy = SupportPolicy::with(['creator:id,name,email', 'editor:id,name,email'])->find($id);
        if (!$policy) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Policy not found.']], 404);
        }

        return response()->json(['data' => $policy], 200);
    }

    /**
     * Update an existing policy.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = $this->authorizeGovernance($request);
        if (!$user) {
            return $this->errorForbidden();
        }

        $policy = SupportPolicy::find($id);
        if (!$policy) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Policy not found.']], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'sometimes|required|string|max:50',
            'effect' => 'sometimes|required|string|in:allow,deny,confirm,require_verification,require_human',
            'configuration' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
            'priority' => 'sometimes|integer',
        ]);

        $beforeData = [
            'name' => $policy->name,
            'effect' => $policy->effect?->value ?? $policy->effect,
            'is_active' => $policy->is_active,
            'priority' => $policy->priority,
        ];

        $policy->update(array_merge($validated, ['updated_by' => $user->id]));

        SupportAuditLog::log([
            'actor_type' => 'admin',
            'actor_id' => $user->id,
            'action' => 'POLICY_UPDATED',
            'resource_type' => 'support_policy',
            'resource_id' => (string)$policy->id,
            'authorization_result' => 'ALLOWED',
            'before_data' => $beforeData,
            'after_data' => [
                'name' => $policy->name,
                'effect' => $policy->effect?->value ?? $policy->effect,
                'is_active' => $policy->is_active,
                'priority' => $policy->priority,
            ],
        ]);

        return response()->json([
            'data' => $policy->fresh(['creator:id,name,email', 'editor:id,name,email']),
            'message' => 'Policy updated successfully.',
        ], 200);
    }

    /**
     * Activate a policy.
     */
    public function activate(Request $request, $id): JsonResponse
    {
        $user = $this->authorizeGovernance($request);
        if (!$user) {
            return $this->errorForbidden();
        }

        $policy = SupportPolicy::find($id);
        if (!$policy) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Policy not found.']], 404);
        }

        $policy->update(['is_active' => true, 'updated_by' => $user->id]);

        SupportAuditLog::log([
            'actor_type' => 'admin',
            'actor_id' => $user->id,
            'action' => 'POLICY_ACTIVATED',
            'resource_type' => 'support_policy',
            'resource_id' => (string)$policy->id,
            'authorization_result' => 'ALLOWED',
            'after_data' => ['is_active' => true],
        ]);

        return response()->json([
            'data' => $policy,
            'message' => 'Policy activated.',
        ], 200);
    }

    /**
     * Disable a policy.
     */
    public function disable(Request $request, $id): JsonResponse
    {
        $user = $this->authorizeGovernance($request);
        if (!$user) {
            return $this->errorForbidden();
        }

        $policy = SupportPolicy::find($id);
        if (!$policy) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Policy not found.']], 404);
        }

        $policy->update(['is_active' => false, 'updated_by' => $user->id]);

        SupportAuditLog::log([
            'actor_type' => 'admin',
            'actor_id' => $user->id,
            'action' => 'POLICY_DISABLED',
            'resource_type' => 'support_policy',
            'resource_id' => (string)$policy->id,
            'authorization_result' => 'ALLOWED',
            'after_data' => ['is_active' => false],
        ]);

        return response()->json([
            'data' => $policy,
            'message' => 'Policy disabled.',
        ], 200);
    }

    /**
     * Side-effect-free policy simulator that evaluates policies against simulated tool calls.
     */
    public function simulate(Request $request): JsonResponse
    {
        $user = $this->authorizeGovernance($request);
        if (!$user) {
            return $this->errorForbidden();
        }

        $validated = $request->validate([
            'actor_type' => 'required|string|in:customer,guest,agent',
            'tool_name' => 'required|string',
            'arguments' => 'nullable|array',
            'is_authenticated' => 'nullable|boolean',
        ]);

        $actorType = $validated['actor_type'];
        $toolName = $validated['tool_name'];
        $args = $validated['arguments'] ?? [];
        $isAuth = $validated['is_authenticated'] ?? ($actorType === 'customer' || $actorType === 'agent');

        // Create virtual conversation for simulation
        $simConversation = new SupportConversation([
            'customer_id' => $isAuth ? 999999 : null,
            'status' => ConversationStatus::AI_ACTIVE,
            'mode' => ConversationMode::AI,
            'priority' => SupportPriority::NORMAL,
        ]);

        $toolCall = new ToolCallDTO('sim_' . uniqid(), $toolName, $args);
        $engine = new SupportActionPolicyEngine();
        $effect = $engine->evaluateToolCall($toolCall, $simConversation);

        // Check if tool exists in DB
        $dbTool = SupportAITool::where('key', $toolName)->first();

        $warnings = [];
        if (!$dbTool) {
            $warnings[] = "Tool '{$toolName}' is not registered in the backend tool catalog.";
        } elseif (!$dbTool->is_active) {
            $warnings[] = "Tool '{$toolName}' is currently inactive.";
        }

        if ($dbTool && $dbTool->risk_level === ToolRiskLevel::CRITICAL) {
            $warnings[] = "Tool has CRITICAL risk level and always requires human escalation.";
        }

        SupportAuditLog::log([
            'actor_type' => 'admin',
            'actor_id' => $user->id,
            'action' => 'POLICY_SIMULATION_EXECUTED',
            'resource_type' => 'support_policy_simulation',
            'resource_id' => $toolName,
            'authorization_result' => $effect->value,
            'metadata' => [
                'actor_type' => $actorType,
                'tool_name' => $toolName,
                'arguments' => $args,
                'effect' => $effect->value,
                'is_simulation' => true,
            ],
        ]);

        return response()->json([
            'data' => [
                'simulation' => true,
                'badge' => 'SIMULATION ONLY',
                'tool_name' => $toolName,
                'actor_type' => $actorType,
                'is_authenticated' => $isAuth,
                'policy_effect' => $effect->value,
                'requires_confirmation' => ($effect === PolicyEffect::CONFIRM),
                'requires_human' => ($effect === PolicyEffect::REQUIRE_HUMAN),
                'requires_verification' => ($effect === PolicyEffect::REQUIRE_VERIFICATION),
                'is_allowed' => ($effect === PolicyEffect::ALLOW),
                'is_denied' => ($effect === PolicyEffect::DENY),
                'tool_registered' => (bool)$dbTool,
                'tool_risk_level' => $dbTool?->risk_level?->value ?? 'unknown',
                'warnings' => $warnings,
                'evaluated_at' => now()->toIso8601String(),
            ]
        ], 200);
    }

    protected function authorizeGovernance(Request $request): ?User
    {
        $user = $request->user('sanctum') ?? Auth::guard('sanctum')->user() ?? Auth::user();
        if (!$user) {
            return null;
        }

        try {
            if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Admin', 'Manager'])) {
                return $user;
            }
            if (method_exists($user, 'can') && ($user->can('manage-support') || $user->can('all_support') || $user->can('support_governance'))) {
                return $user;
            }
        } catch (\Throwable $e) {}

        return null;
    }

    protected function errorForbidden(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'SUPPORT_GOVERNANCE_FORBIDDEN',
                'message' => 'You do not have administrative permissions to govern AI Support knowledge and policies.',
            ]
        ], 403);
    }
}
