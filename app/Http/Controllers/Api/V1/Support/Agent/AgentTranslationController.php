<?php

namespace App\Http\Controllers\Api\V1\Support\Agent;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Models\SupportAgentProfile;
use App\Support\Models\SupportConversation;
use App\Support\Services\Multilingual\TranslationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AgentTranslationController extends Controller
{
    protected TranslationService $translationService;

    public function __construct(?TranslationService $translationService = null)
    {
        $this->translationService = $translationService ?? new TranslationService();
    }

    /**
     * Assistive translation of message or draft response for support agents.
     */
    public function translate(Request $request, string $conversation): JsonResponse
    {
        $user = $request->user('sanctum') ?? Auth::guard('sanctum')->user() ?? Auth::user();
        if (!$user) {
            return response()->json([
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Agent authentication required.',
                ]
            ], 401);
        }

        $conv = SupportConversation::where('public_id', $conversation)->first();
        if (!$conv) {
            return response()->json([
                'error' => [
                    'code' => 'SUPPORT_CONVERSATION_NOT_FOUND',
                    'message' => 'Support conversation not found.',
                ]
            ], 404);
        }

        // Authorize agent
        if (!$this->authorizeAgent($user, $conv)) {
            return response()->json([
                'error' => [
                    'code' => 'SUPPORT_ACCESS_DENIED',
                    'message' => 'You do not have agent permissions for this conversation.',
                ]
            ], 403);
        }

        $validated = $request->validate([
            'text' => ['required', 'string', 'max:5000'],
            'target_language' => ['required', 'string', 'in:en,yo,ig,ha'],
            'source_language' => ['nullable', 'string', 'in:en,yo,ig,ha,auto'],
        ]);

        $result = $this->translationService->translate(
            $validated['text'],
            $validated['target_language'],
            $validated['source_language'] ?? null
        );

        return response()->json([
            'data' => $result,
            'message' => 'Translation generated successfully.',
        ], 200);
    }

    protected function authorizeAgent(User $user, SupportConversation $conversation): bool
    {
        try {
            if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Admin', 'Manager'])) {
                return true;
            }
        } catch (\Throwable $e) {}

        if ($conversation->assigned_agent_id && (int)$conversation->assigned_agent_id === (int)$user->id) {
            return true;
        }

        $profile = SupportAgentProfile::where('user_id', $user->id)->first();
        if ($profile && $conversation->department_id) {
            return $profile->departments()->where('support_departments.id', $conversation->department_id)->exists();
        }

        return false;
    }
}
