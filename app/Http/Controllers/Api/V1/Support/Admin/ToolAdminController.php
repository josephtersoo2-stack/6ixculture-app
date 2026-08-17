<?php

namespace App\Http\Controllers\Api\V1\Support\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Enums\ToolRiskLevel;
use App\Support\Models\SupportAITool;
use App\Support\Models\SupportAIToolPermission;
use App\Support\Models\SupportAuditLog;
use App\Support\Tools\ToolRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ToolAdminController extends Controller
{
    protected ToolRegistry $toolRegistry;

    public function __construct(?ToolRegistry $toolRegistry = null)
    {
        $this->toolRegistry = $toolRegistry ?? new ToolRegistry();
    }

    /**
     * List all registered backend AI tools and their current governance permission configurations.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $this->authorizeGovernance($request);
        if (!$user) {
            return $this->errorForbidden();
        }

        $registeredBackendTools = $this->toolRegistry->all();
        $dbTools = SupportAITool::with('permissions')->get()->keyBy('key');

        $tools = [];
        foreach ($registeredBackendTools as $key => $backendTool) {
            $db = $dbTools->get($key);
            $tools[] = [
                'id' => $db?->id,
                'key' => $key,
                'name' => $db?->name ?? $backendTool->name(),
                'description' => $db?->description ?? $backendTool->description(),
                'category' => $db?->category ?? 'general',
                'risk_level' => $db?->risk_level?->value ?? ($backendTool->riskLevel()->value ?? 'normal'),
                'is_active' => $db ? (bool)$db->is_active : true,
                'requires_authentication' => $db ? (bool)$db->requires_authentication : false,
                'requires_confirmation' => $db ? (bool)$db->requires_confirmation : false,
                'requires_human' => $db ? (bool)$db->requires_human : false,
                'input_schema' => $db?->input_schema ?? $backendTool->inputSchema(),
                'permissions' => $db?->permissions ?? [],
            ];
        }

        return response()->json([
            'data' => $tools,
            'risk_levels' => ['low', 'normal', 'sensitive', 'critical'],
        ], 200);
    }

    /**
     * Get details of a single registered tool.
     */
    public function show(Request $request, $id): JsonResponse
    {
        $user = $this->authorizeGovernance($request);
        if (!$user) {
            return $this->errorForbidden();
        }

        $tool = SupportAITool::with('permissions')->where('id', $id)->orWhere('key', $id)->first();
        if (!$tool) {
            return response()->json(['error' => ['code' => 'TOOL_NOT_FOUND', 'message' => 'Tool not found in registered catalog.']], 404);
        }

        return response()->json(['data' => $tool], 200);
    }

    /**
     * Update governance permissions and safety configuration for a registered tool.
     */
    public function updatePermissions(Request $request, $id): JsonResponse
    {
        $user = $this->authorizeGovernance($request);
        if (!$user) {
            return $this->errorForbidden();
        }

        $tool = SupportAITool::where('id', $id)->orWhere('key', $id)->first();
        if (!$tool) {
            return response()->json(['error' => ['code' => 'TOOL_NOT_FOUND', 'message' => 'Tool not found in registered catalog.']], 404);
        }

        $validated = $request->validate([
            'is_active' => 'sometimes|boolean',
            'risk_level' => 'sometimes|string|in:low,normal,sensitive,critical',
            'requires_authentication' => 'sometimes|boolean',
            'requires_confirmation' => 'sometimes|boolean',
            'requires_human' => 'sometimes|boolean',
        ]);

        $beforeData = [
            'is_active' => $tool->is_active,
            'risk_level' => $tool->risk_level?->value,
            'requires_authentication' => $tool->requires_authentication,
            'requires_confirmation' => $tool->requires_confirmation,
            'requires_human' => $tool->requires_human,
        ];

        // Enforce immutable centralized critical action safety policy
        $validated = \App\Support\Policies\CriticalActionSafetyPolicy::enforceToolSafety($tool, $validated);

        $tool->update($validated);

        SupportAuditLog::log([
            'actor_type' => 'admin',
            'actor_id' => $user->id,
            'action' => 'TOOL_PERMISSIONS_UPDATED',
            'resource_type' => 'support_ai_tool',
            'resource_id' => (string)$tool->id,
            'tool_name' => $tool->key,
            'authorization_result' => 'ALLOWED',
            'before_data' => $beforeData,
            'after_data' => [
                'is_active' => $tool->is_active,
                'risk_level' => $tool->risk_level?->value,
                'requires_authentication' => $tool->requires_authentication,
                'requires_confirmation' => $tool->requires_confirmation,
                'requires_human' => $tool->requires_human,
            ],
        ]);

        return response()->json([
            'data' => $tool->fresh(['permissions']),
            'message' => "Permissions for tool '{$tool->name}' updated successfully.",
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
