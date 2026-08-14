<?php

namespace App\Http\Controllers\Admin;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Services\ChatService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminChatController extends AdminController
{
    protected ChatService $chatService;

    public function __construct(ChatService $chatService)
    {
        parent::__construct();
        $this->chatService = $chatService;
    }

    /**
     * List all chat conversations with latest message & status
     */
    public function index(Request $request): JsonResponse
    {
        try {
            ChatService::ensureTablesExist();

            $status = $request->input('status');
            $query = ChatConversation::with(['messages' => function ($q) {
                $q->latest('id')->limit(1);
            }])
            ->orderBy('last_message_at', 'desc');

            if (!empty($status) && $status !== 'all') {
                $query->where('status', $status);
            }

            $conversations = $query->paginate($request->input('per_page', 20));

            return response()->json([
                'status' => true,
                'data'   => $conversations,
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get specific conversation messages
     */
    public function show($id): JsonResponse
    {
        try {
            $conversation = ChatConversation::with(['user'])->findOrFail($id);
            $messages = $conversation->messages()->orderBy('id', 'asc')->get();

            // Mark all unread user messages as read
            ChatMessage::where('conversation_id', $id)
                ->where('sender_type', 'user')
                ->where('is_read', false)
                ->update(['is_read' => true]);

            return response()->json([
                'status'       => true,
                'conversation' => $conversation,
                'messages'     => $messages,
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 404);
        }
    }

    /**
     * Admin / Customer Care agent reply to customer
     */
    public function reply(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'message' => 'required|string|max:2000',
            ]);

            $conversation = ChatConversation::findOrFail($id);
            $user = Auth::guard('api')->user() ?: Auth::user();
            $adminId = $user ? $user->id : null;

            // Automatically switch status to 'human' when an admin replies
            $conversation->status = 'human';
            $conversation->save();

            $msg = $this->chatService->addMessage($conversation->id, 'agent', trim($request->input('message')), $adminId);

            return response()->json([
                'status'  => true,
                'message' => $msg,
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update conversation status (ai, human, closed)
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'status' => 'required|in:ai,human,closed',
            ]);

            $conversation = ChatConversation::findOrFail($id);
            $conversation->status = $request->input('status');
            $conversation->save();

            return response()->json([
                'status'       => true,
                'conversation' => $conversation,
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete conversation
     */
    public function destroy($id): JsonResponse
    {
        try {
            $conversation = ChatConversation::findOrFail($id);
            $conversation->delete();

            return response()->json([
                'status'  => true,
                'message' => 'Conversation deleted successfully.',
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
