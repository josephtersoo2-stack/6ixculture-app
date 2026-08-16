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
use App\Support\Enums\ConversationMode;
use App\Support\Enums\ConversationStatus;
use App\Support\Enums\MessageType;
use App\Support\Enums\SenderType;
use App\Support\Enums\SupportPriority;
use App\Support\Models\SupportAgentProfile;
use App\Support\Models\SupportAssignment;
use App\Support\Models\SupportAuditLog;
use App\Support\Models\SupportConversation;
use App\Support\Models\SupportDepartment;
use App\Support\Models\SupportMessage;
use App\Support\Models\SupportTicket;
use App\Support\Services\AiProviderFactory;
use App\Support\Services\SupportContextAssembler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AgentSupportConversationController extends Controller
{
    protected AiOrchestratorInterface $orchestrator;
    protected PolicyEngineInterface $policyEngine;
    protected SupportContextAssembler $contextAssembler;

    public function __construct(
        AiOrchestratorInterface $orchestrator,
        PolicyEngineInterface $policyEngine,
        ?SupportContextAssembler $contextAssembler = null
    ) {
        $this->orchestrator = $orchestrator;
        $this->policyEngine = $policyEngine;
        $this->contextAssembler = $contextAssembler ?? new SupportContextAssembler();
    }

    /**
     * List support queue with authorization scoping and filters.
     */
    public function index(AgentConversationIndexRequest $request): JsonResponse
    {
        $user = $this->authorizeAgent($request);
        if (!$user) {
            return $this->errorForbidden();
        }

        $query = SupportConversation::with(['customer', 'assignedAgent', 'department', 'ticket']);

        // 1. Enforce Authorization Scope FIRST
        if (!$this->isElevatedAgent($user)) {
            $allowedDeptIds = $this->getAgentDepartmentIds($user) ?? [];
            $query->where(function ($scoped) use ($user, $allowedDeptIds) {
                $scoped->where('assigned_agent_id', $user->id)
                       ->orWhereIn('department_id', $allowedDeptIds);
            });
        }

        // 2. Query Filters within the authorized scope
        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', (int)$request->input('department_id'));
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }

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

        if ($request->filled('language')) {
            $query->where('language', $request->input('language'));
        }

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

        if (!$this->authorizeConversationAccess($user, $conv)) {
            return $this->errorForbidden('You are not authorized to view this conversation.');
        }

        return response()->json([
            'data' => new AgentConversationDetailResource($conv),
        ], 200);
    }

    /**
     * Claim or reassign conversation with strict target agent validation.
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

        if (!$this->authorizeConversationAccess($user, $conv)) {
            return $this->errorForbidden('You are not authorized to assign this conversation.');
        }

        $targetAgentId = $request->input('agent_id');
        $newAgentId = null;

        if ($targetAgentId === 'self' || $targetAgentId === 'me' || (is_numeric($targetAgentId) && (int)$targetAgentId === $user->id)) {
            $eligibility = $this->isEligibleSupportAssignee($user, $conv->department_id);
            if (!$eligibility['eligible']) {
                return response()->json([
                    'error' => [
                        'code' => $eligibility['code'],
                        'message' => $eligibility['message'],
                    ]
                ], 422);
            }
            $newAgentId = $user->id;
        } elseif ($targetAgentId === null || $targetAgentId === 'unassigned' || $targetAgentId === '') {
            $newAgentId = null;
        } elseif (is_numeric($targetAgentId)) {
            $targetUser = User::find((int)$targetAgentId);
            if (!$targetUser) {
                return response()->json([
                    'error' => [
                        'code' => 'INVALID_ASSIGNMENT_TARGET',
                        'message' => 'The target agent does not exist.',
                    ]
                ], 422);
            }

            $eligibility = $this->isEligibleSupportAssignee($targetUser, $conv->department_id);
            if (!$eligibility['eligible']) {
                return response()->json([
                    'error' => [
                        'code' => $eligibility['code'],
                        'message' => $eligibility['message'],
                    ]
                ], 422);
            }

            $newAgentId = $targetUser->id;
        }

        $previousAgentId = $conv->assigned_agent_id;
        $departmentId = $request->input('department_id', $conv->department_id);

        $conv->update([
            'assigned_agent_id' => $newAgentId,
            'department_id' => $departmentId,
            'status' => $newAgentId ? ConversationStatus::HUMAN_ACTIVE : ConversationStatus::QUEUED,
            'mode' => $newAgentId ? ConversationMode::HUMAN : $conv->mode,
        ]);

        // Record in SupportAssignment
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
        $assignMsg = SupportMessage::create([
            'conversation_id' => $conv->id,
            'sender_type' => SenderType::SYSTEM,
            'message_type' => MessageType::SYSTEM,
            'content' => "Conversation assigned to {$assigneeName} by {$user->name}.",
            'is_internal' => false,
        ]);

        try {
            broadcast(new \App\Support\Events\SupportMessageCreated($assignMsg));
            broadcast(new \App\Support\Events\SupportConversationUpdated($conv->fresh(), 'assigned'));
            broadcast(new \App\Support\Events\SupportQueueUpdated($conv->fresh(), 'assigned'));
        } catch (\Throwable $e) {}

        SupportAuditLog::log([
            'actor_type' => 'agent',
            'actor_id' => $user->id,
            'customer_id' => $conv->customer_id,
            'conversation_id' => $conv->id,
            'action' => 'ASSIGN_CONVERSATION',
            'resource_type' => 'support_conversation',
            'resource_id' => (string)$conv->id,
            'authorization_result' => 'ALLOW',
            'before_data' => ['assigned_agent_id' => $previousAgentId],
            'after_data' => ['assigned_agent_id' => $newAgentId],
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

        if (!$this->authorizeConversationAccess($user, $conv)) {
            return $this->errorForbidden('You are not authorized to reply to this conversation.');
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

        $replyMsg = SupportMessage::create([
            'conversation_id' => $conv->id,
            'sender_type' => SenderType::AGENT,
            'sender_id' => $user->id,
            'message_type' => MessageType::TEXT,
            'content' => trim($request->input('message')),
            'structured_payload' => $request->input('attachments') ? ['attachments' => $request->input('attachments')] : null,
            'is_internal' => false,
        ]);

        try {
            broadcast(new \App\Support\Events\SupportMessageCreated($replyMsg));
            broadcast(new \App\Support\Events\SupportConversationUpdated($conv->fresh(), 'agent_reply'));
        } catch (\Throwable $e) {}

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

        if (!$this->authorizeConversationAccess($user, $conv)) {
            return $this->errorForbidden('You are not authorized to add notes to this conversation.');
        }

        $noteMsg = SupportMessage::create([
            'conversation_id' => $conv->id,
            'sender_type' => SenderType::AGENT,
            'sender_id' => $user->id,
            'message_type' => MessageType::INTERNAL_NOTE,
            'content' => trim($request->input('content')),
            'is_internal' => true,
        ]);

        try {
            broadcast(new \App\Support\Events\SupportMessageCreated($noteMsg));
        } catch (\Throwable $e) {}

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

        if (!$this->authorizeConversationAccess($user, $conv)) {
            return $this->errorForbidden('You are not authorized to update this conversation status.');
        }

        $newStatus = ConversationStatus::from($request->input('status'));
        $resolvedAt = in_array($newStatus, [ConversationStatus::RESOLVED, ConversationStatus::CLOSED]) ? now() : null;

        $conv->update([
            'status' => $newStatus,
            'resolved_at' => $resolvedAt,
        ]);

        $statusMsg = SupportMessage::create([
            'conversation_id' => $conv->id,
            'sender_type' => SenderType::SYSTEM,
            'message_type' => MessageType::SYSTEM,
            'content' => "Status changed to '{$newStatus->label()}' by {$user->name}.",
            'is_internal' => false,
        ]);

        try {
            broadcast(new \App\Support\Events\SupportMessageCreated($statusMsg));
            broadcast(new \App\Support\Events\SupportConversationUpdated($conv->fresh(), 'status_changed'));
            broadcast(new \App\Support\Events\SupportQueueUpdated($conv->fresh(), 'status_changed'));
        } catch (\Throwable $e) {}

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

        if (!$this->authorizeConversationAccess($user, $conv)) {
            return $this->errorForbidden('You are not authorized to update this conversation priority.');
        }

        $newPriority = SupportPriority::from($request->input('priority'));
        $conv->update(['priority' => $newPriority]);

        try {
            broadcast(new \App\Support\Events\SupportConversationUpdated($conv->fresh(), 'priority_changed'));
        } catch (\Throwable $e) {}

        return response()->json([
            'data' => new AgentConversationDetailResource($conv->fresh()),
            'message' => 'Priority updated.',
        ], 200);
    }

    /**
     * Update conversation department with source and target department authorization.
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

        if (!$this->authorizeConversationAccess($user, $conv)) {
            return $this->errorForbidden('You are not authorized to transfer this conversation.');
        }

        $dept = SupportDepartment::find($request->input('department_id'));
        if (!$dept || !$dept->is_active) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_DEPARTMENT',
                    'message' => 'Target department does not exist or is inactive.',
                ]
            ], 422);
        }

        // Target department must be authorized for the acting user
        if (!$this->isElevatedAgent($user)) {
            $allowedDeptIds = $this->getAgentDepartmentIds($user) ?? [];
            if (!in_array($dept->id, $allowedDeptIds)) {
                return $this->errorForbidden('You are not authorized to transfer conversations to this department.');
            }
        }

        $previousDeptId = $conv->department_id;
        $updates = ['department_id' => $dept->id];

        // If assigned agent is not authorized for the new department (and not elevated), unassign so new department can claim
        if ($conv->assigned_agent_id) {
            $assignedUser = User::find($conv->assigned_agent_id);
            if ($assignedUser && !$this->isElevatedAgent($assignedUser)) {
                $assignedDeptIds = $this->getAgentDepartmentIds($assignedUser) ?? [];
                if (!in_array($dept->id, $assignedDeptIds)) {
                    $updates['assigned_agent_id'] = null;
                    $updates['status'] = ConversationStatus::QUEUED;
                    SupportAssignment::where('conversation_id', $conv->id)
                        ->whereNull('unassigned_at')
                        ->update(['unassigned_at' => now()]);
                }
            }
        }

        $conv->update($updates);

        $transferMsg = SupportMessage::create([
            'conversation_id' => $conv->id,
            'sender_type' => SenderType::SYSTEM,
            'message_type' => MessageType::SYSTEM,
            'content' => "Department transferred to {$dept->name} by {$user->name}.",
            'is_internal' => false,
        ]);

        try {
            broadcast(new \App\Support\Events\SupportMessageCreated($transferMsg));
            broadcast(new \App\Support\Events\SupportConversationUpdated($conv->fresh(), 'department_transferred'));
            broadcast(new \App\Support\Events\SupportQueueUpdated($conv->fresh(), 'department_transferred'));
        } catch (\Throwable $e) {}

        SupportAuditLog::log([
            'actor_type' => 'agent',
            'actor_id' => $user->id,
            'customer_id' => $conv->customer_id,
            'conversation_id' => $conv->id,
            'action' => 'TRANSFER_DEPARTMENT',
            'resource_type' => 'support_conversation',
            'resource_id' => (string)$conv->id,
            'authorization_result' => 'ALLOW',
            'before_data' => ['department_id' => $previousDeptId],
            'after_data' => ['department_id' => $dept->id],
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

        if (!$this->authorizeConversationAccess($user, $conv)) {
            return $this->errorForbidden('You are not authorized to view customer details for this conversation.');
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

        if (!$this->authorizeConversationAccess($user, $conv)) {
            return $this->errorForbidden('You are not authorized to view customer orders for this conversation.');
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

        if (!$this->authorizeConversationAccess($user, $conv)) {
            return $this->errorForbidden('You are not authorized to view ticket details for this conversation.');
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
     * Generate or refresh AI Summary using Support Context Assembler & Provider Abstraction.
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

        if (!$this->authorizeConversationAccess($user, $conv)) {
            return $this->errorForbidden('You are not authorized to summarize this conversation.');
        }

        try {
            // 1. Build secure bounded context via SupportContextAssembler
            $summaryContext = $this->contextAssembler->assembleForSummarization($conv);

            // 2. Query AI Provider through provider abstraction
            $provider = AiProviderFactory::make();
            $response = $provider->chat($summaryContext, []);

            if (!empty($response['error'])) {
                return response()->json([
                    'error' => [
                        'code' => 'AI_SUMMARY_PROVIDER_ERROR',
                        'message' => $response['error'],
                    ]
                ], 502);
            }

            $summaryText = trim($response['text'] ?? $response['content'] ?? '');
            if (empty($summaryText)) {
                $summaryText = "Summary generation returned empty response.";
            }

            $conv->update(['ai_summary' => $summaryText]);

            SupportAuditLog::log([
                'actor_type' => 'agent',
                'actor_id' => $user->id,
                'customer_id' => $conv->customer_id,
                'conversation_id' => $conv->id,
                'action' => 'GENERATE_AI_SUMMARY',
                'resource_type' => 'support_conversation',
                'resource_id' => (string)$conv->id,
                'authorization_result' => 'ALLOW',
                'metadata' => [
                    'provider' => $response['provider'] ?? 'unknown',
                    'model' => $response['model'] ?? 'unknown',
                    'tokens_used' => $response['tokens_used'] ?? 0,
                ],
            ]);

            return response()->json([
                'data' => [
                    'ai_summary' => $summaryText,
                ],
                'message' => 'AI Summary generated successfully.',
            ], 200);
        } catch (\Throwable $e) {
            SupportAuditLog::log([
                'actor_type' => 'agent',
                'actor_id' => $user->id,
                'customer_id' => $conv->customer_id,
                'conversation_id' => $conv->id,
                'action' => 'GENERATE_AI_SUMMARY_FAILED',
                'resource_type' => 'support_conversation',
                'resource_id' => (string)$conv->id,
                'authorization_result' => 'ERROR',
                'metadata' => ['error' => $e->getMessage()],
            ]);

            return response()->json([
                'error' => [
                    'code' => 'AI_SUMMARY_GENERATION_FAILED',
                    'message' => 'Failed to generate AI summary. The conversation remains safe and unchanged.',
                ]
            ], 500);
        }
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
     * List active support agents for assignment dropdown within the acting agent's authorized scope.
     */
    public function agents(Request $request): JsonResponse
    {
        $user = $this->authorizeAgent($request);
        if (!$user) {
            return $this->errorForbidden();
        }

        $query = User::query();

        if ($this->isElevatedAgent($user)) {
            // Elevated admins/managers can see all active agent profiles and elevated staff
            $agentUserIds = SupportAgentProfile::pluck('user_id')->toArray();
            $elevatedIds = [];
            try {
                $elevatedIds = User::role(['Admin', 'Manager'])->pluck('id')->toArray();
            } catch (\Throwable $e) {}

            $allEligibleIds = array_unique(array_merge($agentUserIds, $elevatedIds));
            $query->whereIn('id', $allEligibleIds);
        } else {
            // Department-scoped agent: only see agents who share at least one authorized department, plus elevated staff
            $actingDeptIds = $this->getAgentDepartmentIds($user) ?? [];

            $sharedDeptUserIds = SupportAgentProfile::whereHas('departments', function ($q) use ($actingDeptIds) {
                $q->whereIn('support_departments.id', $actingDeptIds);
            })->pluck('user_id')->toArray();

            $elevatedIds = [];
            try {
                $elevatedIds = User::role(['Admin', 'Manager'])->pluck('id')->toArray();
            } catch (\Throwable $e) {}

            $allEligibleIds = array_unique(array_merge($sharedDeptUserIds, $elevatedIds));
            $query->whereIn('id', $allEligibleIds);
        }

        $agents = $query->orderBy('name', 'asc')->get(['id', 'name', 'email']);

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
     * Update agent availability presence and broadcast to presence channel.
     */
    public function updatePresence(Request $request): JsonResponse
    {
        $user = $this->authorizeAgent($request);
        if (!$user) {
            return $this->errorForbidden();
        }

        $status = $request->input('status', 'online');
        $availability = $request->input('availability', 'available');

        $profile = SupportAgentProfile::where('user_id', $user->id)->first();
        if ($profile) {
            $profile->update([
                'status' => $status,
                'availability' => $availability,
            ]);
        }

        try {
            broadcast(new \App\Support\Events\SupportAgentPresenceChanged($user, $status, $availability));
        } catch (\Throwable $e) {}

        return response()->json([
            'data' => [
                'agent_id' => $user->id,
                'status' => $status,
                'availability' => $availability,
            ],
            'message' => 'Agent presence updated.',
        ], 200);
    }

    /**
     * Check if a target user is an eligible support agent assignee for a conversation department.
     */
    protected function isEligibleSupportAssignee(User $targetUser, ?int $departmentId = null): array
    {
        $isElevated = $this->isElevatedAgent($targetUser);
        $hasProfile = SupportAgentProfile::where('user_id', $targetUser->id)->exists();

        if (!$isElevated && !$hasProfile) {
            return [
                'eligible' => false,
                'code' => 'INVALID_ASSIGNMENT_TARGET',
                'message' => 'Assignment target must be an authorized support agent.',
            ];
        }

        if (!$isElevated && $departmentId !== null) {
            $targetDeptIds = $this->getAgentDepartmentIds($targetUser) ?? [];
            if (!in_array($departmentId, $targetDeptIds)) {
                return [
                    'eligible' => false,
                    'code' => 'UNAUTHORIZED_ASSIGNMENT_TARGET',
                    'message' => 'Target agent is not authorized for this conversation department.',
                ];
            }
        }

        return ['eligible' => true];
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

    /**
     * Check if user is an elevated support user (Admin or Manager).
     */
    protected function isElevatedAgent(User $user): bool
    {
        try {
            if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Admin', 'Manager'])) {
                return true;
            }
            if (method_exists($user, 'can') && ($user->can('manage-support') || $user->can('all_support'))) {
                return true;
            }
        } catch (\Throwable $e) {
            // fallback
        }

        return false;
    }

    /**
     * Retrieve authorized department IDs for an agent (or null if elevated/all).
     */
    protected function getAgentDepartmentIds(User $user): ?array
    {
        if ($this->isElevatedAgent($user)) {
            return null; // All departments permitted
        }

        $profile = SupportAgentProfile::where('user_id', $user->id)->first();
        if ($profile) {
            return $profile->departments()->pluck('support_departments.id')->toArray();
        }

        return [];
    }

    /**
     * Verify whether an authenticated agent is authorized to view or operate on a specific conversation.
     */
    protected function authorizeConversationAccess(User $user, SupportConversation $conversation): bool
    {
        if ($this->isElevatedAgent($user)) {
            return true;
        }

        // Assigned agent always has access
        if ($conversation->assigned_agent_id === $user->id) {
            return true;
        }

        // Check if conversation belongs to one of the agent's authorized departments
        $allowedDeptIds = $this->getAgentDepartmentIds($user);
        if ($conversation->department_id && in_array($conversation->department_id, $allowedDeptIds ?? [])) {
            return true;
        }

        return false;
    }

    protected function errorForbidden(string $message = 'Access denied. You do not have permission to perform this support operation.'): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'SUPPORT_AGENT_FORBIDDEN',
                'message' => $message,
            ]
        ], 403);
    }

    protected function errorNotFound(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'SUPPORT_CONVERSATION_NOT_FOUND',
                'message' => 'The requested support conversation was not found.',
            ]
        ], 404);
    }
}
