<?php

namespace App\Http\Controllers\Api\V1\Support\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Models\SupportAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GovernanceAuditController extends Controller
{
    /**
     * List support governance audit log entries with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $this->authorizeGovernance($request);
        if (!$user) {
            return $this->errorForbidden();
        }

        $query = SupportAuditLog::query();

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('resource_type')) {
            $query->where('resource_type', $request->input('resource_type'));
        }

        if ($request->filled('actor_type')) {
            $query->where('actor_type', $request->input('actor_type'));
        }

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                  ->orWhere('resource_type', 'like', "%{$search}%")
                  ->orWhere('tool_name', 'like', "%{$search}%")
                  ->orWhere('resource_id', 'like', "%{$search}%");
            });
        }

        $perPage = max(10, min((int)$request->input('per_page', 25), 100));
        $logs = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
            'actions' => [
                'KNOWLEDGE_ARTICLE_CREATED',
                'KNOWLEDGE_ARTICLE_UPDATED',
                'KNOWLEDGE_ARTICLE_PUBLISHED',
                'KNOWLEDGE_ARTICLE_ARCHIVED',
                'KNOWLEDGE_ARTICLE_ROLLBACK',
                'POLICY_CREATED',
                'POLICY_UPDATED',
                'POLICY_ACTIVATED',
                'POLICY_DISABLED',
                'POLICY_SIMULATION_EXECUTED',
                'TOOL_PERMISSIONS_UPDATED',
            ],
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
