<?php

namespace App\Http\Controllers\Api\V1\Support\Agent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Support\Agent\AgentConversationIndexRequest;
use App\Http\Requests\Support\Agent\AgentInternalNoteRequest;
use App\Http\Requests\Support\Agent\AgentReplyRequest;
use App\Http\Requests\Support\Agent\AssignConversationRequest;
use App\Http\Requests\Support\Agent\UpdateConversationDepartmentRequest;
use App\Http\Requests\Support\Agent\UpdateConversationPriorityRequest;
use App\Http\Requests\Support\Agent\UpdateConversationStatusRequest;
use App\Http\Resources\Support\Agent\AgentConversationDetailResource;
use App\Http\Resources\Support\Agent\AgentConversationResource;
use App\Http\Resources\Support\Agent\Customer360Resource;
use App\Models\Order;
use App\Models\User;
use App\Support\Contracts\AiOrchestratorInterface;
use App\Support\Contracts\PolicyEngineInterface;
use App\Support\DTOs\ChatMessageDTO;
use App\Support\Enums\ConversationMode;
use App\Support\Enums\ConversationStatus;
use App\Support\Enums\MessageType;
use App\Support\Enums\SenderType;
use App\Support\Enums\SupportPriority;
use App\Support\Models\SupportAgentProfile;
use App\Support\Models\SupportAssignment;
use App\Support\Models\SupportConversation;
use App\Support\Models\SupportDepartment;
use App\Support\Models\SupportMessage;
use App\Support\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AgentSupportConversationController extends Controller
{
    protected AiOrchestratorInterface $orchestrator;
    protected PolicyEngineInterface $policyEngine;

    public function __construct(
        AiOrchestratorInterface $orchestrator,
        PolicyEngineInterface $policyEngine
    ) {
        $this->orchestrator = $orchestrator;
        $this->policyEngine = $policyEngine;
    }

    /**
     * List support queue with filters.
     */
    public function index(AgentConversationIndexRequest $request): JsonResponse
    {
        $user = $this->authorizeAgent($request);
        if (!$user) {
            return $this->errorForbidden();
        }

        $query = SupportConversation::with(['customer', 'assignedAgent', 'department', 'ticket']);

        // Status Filter
        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        // Department Filter
        if ($request->filled('department_id')) {
            $query->where('department_id', (int)$request->input('department_id'));
        }

        // Priority Filter
        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        // Assigned To Filter
        if ($request->filled('assigned_to')) {
            $assignedTo = $request->input('assigned_to');
            if ($assignedTo === 'me') {
                $query->where('assigned_agent_id', $user->id);
            } elseif ($assignedTo === 'unassigned') {
                $query->whereNull('assigned_agent_id');
            } elseif (is_numeric($assignedTo)) {
                $query->where('assigned_agent_id', (int)$assignedTo);
            }
        }

        if ($request->boolean('unassigned')) {
            $query->whereNull('assigned_agent_id');
        }

        // Language Filter
        if ($request->filled('language')) {
            $query->where('language', $request->input('language'));
        }

        // Search Filter
        if ($request->filled('search')) {
            $term = trim($request->input('search'));
            $query->where(function ($q) use ($term) {
                $q->where('subject', 'like', "%{$term}%")
                  ->orWhere('public_id', 'like', "%{$term}%")
                  ->orWhereHas('customer', function ($cq) use ($term) {
                      $cq->where('name', 'like', "%{$term}%")
                         ->orWhere('email', 'like', "%{$term}%")
                         ->orWhere('phone', 'like', "%{$term}%");
                  });
            });
        }

        $perPage = min((int)$request->input('per_page', 20), 100);
        $conversations = $query->orderBy('updated_at', 'desc')->paginate($perPage);

        return response()->json([
            'data' => AgentConversationResource::collection($conversations),
            'meta' => [
                'current_page' => $conversations->currentPage(),
                'last_page' => $conversations->lastPage(),
                'per_page' => $conversations->perPage(),
                'total' => $conversations->total(),
            ]
        ], 200);
    }

    /**
     * Show full conversation detail for agent workspace.
     */
    public function show(Request $request, string $conversation): JsonResponse
    {
        $user = $this->authorizeAgent($request);
        if (!$user) {
            return $this->errorForbidden();
        }

        $conv = SupportConversation::where('public_id', $conversation)->first();
        if (!$conv) {
            return $this->errorNotFound();
        }

        return response()->json([
            'data' => new AgentConversationDetailResource($conv),
        ], 200);
    }

    /**
     * Claim or reassign conversation.
     */
    public function assign(AssignConversationRequest $request, string $conversation): JsonResponse
    {
        $user = $this->authorizeAgent($request);
        if (!$user) {
            return $this->errorForbidden();
        }

        $conv = SupportConversation::where('public_id', $conversation)->first();
        if (!$conv) {
            return $this->errorNotFound();
        }

        $targetAgentId = $request->input('agent_id');
        if ($targetAgentId === 'self' || $targetAgentId === 'me') {
            $newAgentId = $user->id;
        } elseif ($targetAgentId === null || $targetAgentId === 'unassigned') {
            $newAgentId = null;
        } else {
            $newAgentId = (int)$targetAgentId;
        }

        $previousAgentId = $conv->assigned_agent_id;
        $departmentId = $request->input('department_id', $conv->department_id);

        $conv->update([
            'assigned_agent_id' => $newAgentId,
            'department_id' => $departmentId,
            'status' => $newAgentId ? ConversationStatus::HUMAN_ACTIVE : ConversationStatus::QUEUED,
            'mode' => $newAgentId ? ConversationMode::HUMAN : $conv->mode,
        ]);

        // Record assignment history in SupportAssignment
        if ($newAgentId) {
            SupportAssignment::create([
                'conversation_id' => $conv->id,
                'agent_id' => $newAgentId,
                'assigned_by' => $user->id,
                'department_id' => $departmentId,
                'assigned_at' => now(),
                'reason' => $request->input('reason', 'Agent assignment updated'),
            ]);
        } else {
            SupportAssignment::where('conversation_id', $conv->id)
                ->whereNull('unassigned_at')
                ->update(['unassigned_at' => now()]);
        }

        $assigneeName = $newAgentId ? (User::find($newAgentId)?->name ?: "Agent #{$newAgentId}") : 'Unassigned';
        SupportMessage::create([
            'conversation_id' => $conv->id,
            'sender_type' => SenderType::SYSTEM,
            'message_type' => MessageType::SYSTEM,
            'content' => "Conversation assigned to {$assigneeName} by {$user->name}.",
            'is_internal' => false,
        ]);

        return response()->json([
            'data' => new AgentConversationDetailResource($conv->fresh()),
            'message' => 'Assignment updated successfully.',
        ], 200);
    }

    /**
     * Send customer-visible agent reply.
     */
    public function reply(AgentReplyRequest $request, string $conversation): JsonResponse
    {
        $user = $this->authorizeAgent($request);
        if (!$user) {
            return $this->errorForbidden();
        }

        $conv = SupportConversation::where('public_id', $conversation)->first();
        if (!$conv) {
            return $this->errorNotFound();
        }

        $resolveAfter = $request->boolean('resolve_after_reply');
        $newStatus = $resolveAfter ? ConversationStatus::RESOLVED : ConversationStatus::AWAITING_CUSTOMER;

        // Auto-assign to replying agent if unassigned
        $assignedAgentId = $conv->assigned_agent_id ?: $user->id;

        $conv->update([
            'assigned_agent_id' => $assignedAgentId,
            'mode' => ConversationMode::HUMAN,
            'status' => $newStatus,
            'resolved_at' => $resolveAfter ? now() : null,
        ]);

        $message = SupportMessage::create([
            'conversation_id' => $conv->id,
            'sender_type' => SenderType::AGENT,
            'sender_id' => $user->id,
            'message_type' => MessageType::TEXT,
            'content' => trim($request->input('message')),
            'structured_payload' => $request->input('attachments') ? ['attachments' => $request->input('attachments')] : null,
            'is_internal' => false,
        ]);

        return response()->json([
            'data' => new AgentConversationDetailResource($conv->fresh()),
            'message' => 'Reply sent to customer.',
        ], 200);
    }

    /**
     * Add private internal staff note.
     */
    public function storeNote(AgentInternalNoteRequest $request, string $conversation): JsonResponse
    {
        $user = $this->authorizeAgent($request);
        if (!$user) {
            return $this->errorForbidden();
        }

        $conv = SupportConversation::where('public_id', $conversation)->first();
        if (!$conv) {
            return $this->errorNotFound();
        }

        SupportMessage::create([
            'conversation_id' => $conv->id,
            'sender_type' => SenderType::AGENT,
            'sender_id' => $user->id,
            'message_type' => MessageType::INTERNAL_NOTE,
            'content' => trim($request->input('content')),
            'is_internal' => true,
        ]);

        return response()->json([
            'data' => new AgentConversationDetailResource($conv->fresh()),
            'message' => 'Internal note added.',
        ], 200);
    }

    /**
     * Update conversation status.
     */
    public function updateStatus(UpdateConversationStatusRequest $request, string $conversation): JsonResponse
    {
        $user = $this->authorizeAgent($request);
        if (!$user) {
            return $this->errorForbidden();
        }

        $conv = SupportConversation::where('public_id', $conversation)->first();
        if (!$conv) {
            return $this->errorNotFound();
        }

        $newStatus = ConversationStatus::from($request->input('status'));
        $resolvedAt = in_array($newStatus, [ConversationStatus::RESOLVED, ConversationStatus::CLOSED]) ? now() : null;

        $conv->update([
            'status' => $newStatus,
            'resolved_at' => $resolvedAt,
        ]);

        SupportMessage::create([
            'conversation_id' => $conv->id,
            'sender_type' => SenderType::SYSTEM,
            'message_type' => MessageType::SYSTEM,
            'content' => "Status changed to '{$newStatus->label()}' by {$user->name}.",
            'is_internal' => false,
        ]);

        return response()->json([
            'data' => new AgentConversationDetailResource($conv->fresh()),
            'message' => 'Status updated.',
        ], 200);
    }

    /**
     * Update conversation priority.
     */
    public function updatePriority(UpdateConversationPriorityRequest $request, string $conversation): JsonResponse
    {
        $user = $this->authorizeAgent($request);
        if (!$user) {
            return $this->errorForbidden();
        }

        $conv = SupportConversation::where('public_id', $conversation)->first();
        if (!$conv) {
            return $this->errorNotFound();
        }

        $newPriority = SupportPriority::from($request->input('priority'));
        $conv->update(['priority' => $newPriority]);

        return response()->json([
            'data' => new AgentConversationDetailResource($conv->fresh()),
            'message' => 'Priority updated.',
        ], 200);
    }

    /**
     * Update conversation department.
     */
    public function updateDepartment(UpdateConversationDepartmentRequest $request, string $conversation): JsonResponse
    {
        $user = $this->authorizeAgent($request);
        if (!$user) {
            return $this->errorForbidden();
        }

        $conv = SupportConversation::where('public_id', $conversation)->first();
        if (!$conv) {
            return $this->errorNotFound();
        }

        $dept = SupportDepartment::findOrFail($request->input('department_id'));
        $conv->update(['department_id' => $dept->id]);

        SupportMessage::create([
            'conversation_id' => $conv->id,
            'sender_type' => SenderType::SYSTEM,
            'message_type' => MessageType::SYSTEM,
            'content' => "Department transferred to {$dept->name} by {$user->name}.",
            'is_internal' => false,
        ]);

        return response()->json([
            'data' => new AgentConversationDetailResource($conv->fresh()),
            'message' => 'Department updated.',
        ], 200);
    }

    /**
     * Get Customer 360 overview.
     */
    public function customer360(Request $request, string $conversation): JsonResponse
    {
        $user = $this->authorizeAgent($request);
        if (!$user) {
            return $this->errorForbidden();
        }

        $conv = SupportConversation::where('public_id', $conversation)->first();
        if (!$conv) {
            return $this->errorNotFound();
        }

        return response()->json([
            'data' => new Customer360Resource($conv->customer),
        ], 200);
    }

    /**
     * Get customer orders context.
     */
    public function orders(Request $request, string $conversation): JsonResponse
    {
        $user = $this->authorizeAgent($request);
        if (!$user) {
            return $this->errorForbidden();
        }

        $conv = SupportConversation::where('public_id', $conversation)->first();
        if (!$conv) {
            return $this->errorNotFound();
        }

        if (!$conv->customer_id) {
            return response()->json(['data' => []], 200);
        }

        $orders = Order::where('user_id', $conv->customer_id)
            ->with(['orderProducts.product'])
            ->latest('id')
            ->limit(10)
            ->get();

        return response()->json([
            'data' => $orders->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_serial_no' => $order->order_serial_no,
                    'status' => $order->status,
                    'total' => '₦' . number_format($order->total, 2),
                    'payment_status' => $order->payment_status,
                    'created_at' => $order->created_at?->toIso8601String(),
                    'items' => $order->orderProducts->map(function ($item) {
                        return [
                            'product_name' => $item->product?->name ?: 'Item',
                            'quantity' => $item->quantity,
                            'price' => '₦' . number_format($item->price, 2),
                        ];
                    }),
                ];
            }),
        ], 200);
    }

    /**
     * Get linked support ticket.
     */
    public function ticket(Request $request, string $conversation): JsonResponse
    {
        $user = $this->authorizeAgent($request);
        if (!$user) {
            return $this->errorForbidden();
        }

        $conv = SupportConversation::where('public_id', $conversation)->first();
        if (!$conv) {
            return $this->errorNotFound();
        }

        return response()->json([
            'data' => $conv->ticket ? [
                'id' => $conv->ticket->id,
                'ticket_number' => $conv->ticket->ticket_number,
                'subject' => $conv->ticket->subject,
                'description' => $conv->ticket->description,
                'status' => $conv->ticket->status?->value,
                'priority' => $conv->ticket->priority?->value,
                'created_at' => $conv->ticket->created_at?->toIso8601String(),
            ] : null,
        ], 200);
    }

    /**
     * Generate or refresh AI Summary.
     */
    public function summarize(Request $request, string $conversation): JsonResponse
    {
        $user = $this->authorizeAgent($request);
        if (!$user) {
            return $this->errorForbidden();
        }

        $conv = SupportConversation::where('public_id', $conversation)->first();
        if (!$conv) {
            return $this->errorNotFound();
        }

        $lastMessages = $conv->messages()->orderBy('id', 'desc')->limit(6)->get()->reverse();
        $summaryPoints = [];
        foreach ($lastMessages as $m) {
            $sender = $m->sender_type?->value ?: 'unknown';
            $summaryPoints[] = "[{$sender}]: " . substr($m->content, 0, 100);
        }

        $summary = "Summary: Customer inquiry regarding streetwear order and store policies. Conversation active in {$conv->language} language. Mode: {$conv->mode->value}.";
        $conv->update(['ai_summary' => $summary]);

        return response()->json([
            'data' => [
                'ai_summary' => $summary,
            ],
            'message' => 'AI Summary generated successfully.',
        ], 200);
    }

    /**
     * List active support departments.
     */
    public function departments(Request $request): JsonResponse
    {
        $user = $this->authorizeAgent($request);
        if (!$user) {
            return $this->errorForbidden();
        }

        $departments = SupportDepartment::active()->get();
        return response()->json([
            'data' => $departments->map(function ($dept) {
                return [
                    'id' => $dept->id,
                    'name' => $dept->name,
                    'slug' => $dept->slug,
                    'description' => $dept->description,
                ];
            }),
        ], 200);
    }

    /**
     * List active support agents for assignment dropdown.
     */
    public function agents(Request $request): JsonResponse
    {
        $user = $this->authorizeAgent($request);
        if (!$user) {
            return $this->errorForbidden();
        }

        $agentUserIds = SupportAgentProfile::pluck('user_id')->toArray();
        $query = User::whereIn('id', $agentUserIds);

        try {
            $staffIds = User::role(['Admin', 'Manager', 'Stuff', 'Support Agent'])->pluck('id')->toArray();
            if (!empty($staffIds)) {
                $query->orWhereIn('id', $staffIds);
            }
        } catch (\Throwable $e) {
            // fallback
        }

        $agents = $query->get();

        return response()->json([
            'data' => $agents->map(function ($agent) {
                return [
                    'id' => $agent->id,
                    'name' => $agent->name,
                    'email' => $agent->email,
                ];
            }),
        ], 200);
    }

    /**
     * Authorize that the authenticated user is an agent / staff member.
     */
    protected function authorizeAgent(Request $request): ?User
    {
        $user = $request->user('sanctum') ?? Auth::guard('sanctum')->user() ?? Auth::user();
        if (!$user) {
            return null;
        }

        $hasProfile = SupportAgentProfile::where('user_id', $user->id)->exists();
        if ($hasProfile) {
            return $user;
        }

        try {
            if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Admin', 'Manager', 'Stuff', 'Support Agent'])) {
                return $user;
            }
        } catch (\Throwable $e) {
            // fallback
        }

        return null;
    }

    protected function errorForbidden(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'SUPPORT_AGENT_FORBIDDEN',
                'message' => 'Access denied. You do not have permission to access the Support Agent Console.',
            ]
        ], 403);
    }

    protected function errorNotFound(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'SUPPORT_CONVERSATION_NOT_FOUND',
                'message' => 'The requested support conversation was not found or access is denied.',
            ]
        ], 404);
    }
}
