<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\ChatService;
use App\Models\ChatConversation;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    protected ChatService $chatService;

    public function __construct(ChatService $chatService)
    {
        $this->chatService = $chatService;
    }

    /**
     * Get or initialize session conversation and return messages
     */
    public function getHistory(Request $request): JsonResponse
    {
        try {
            $sessionToken = $request->header('X-Chat-Session') ?: $request->input('session_token');
            if (empty($sessionToken)) {
                $sessionToken = Str::random(32);
            }

            $user = Auth::guard('api')->user() ?: Auth::user();
            $userId = $user ? $user->id : null;
            $meta = [
                'name'  => $user ? $user->name : $request->input('user_name'),
                'email' => $user ? $user->email : $request->input('user_email'),
                'phone' => $user ? $user->phone : $request->input('user_phone'),
            ];

            $conversation = $this->chatService->getOrCreateConversation($sessionToken, $userId, $meta);
            $messages = $conversation->messages()->select('id', 'sender_type', 'message', 'created_at')->get();

            return response()->json([
                'status'        => true,
                'session_token' => $conversation->session_token,
                'conversation'  => [
                    'id'     => $conversation->id,
                    'status' => $conversation->status,
                ],
                'messages'      => $messages,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send message from user and get AI response (if in AI mode)
     */
    public function sendMessage(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'message' => 'required|string|max:2000',
            ]);

            $sessionToken = $request->header('X-Chat-Session') ?: $request->input('session_token');
            if (empty($sessionToken)) {
                $sessionToken = Str::random(32);
            }

            $user = Auth::guard('api')->user() ?: Auth::user();
            $userId = $user ? $user->id : null;
            $meta = [
                'name'  => $user ? $user->name : $request->input('user_name'),
                'email' => $user ? $user->email : $request->input('user_email'),
                'phone' => $user ? $user->phone : $request->input('user_phone'),
            ];

            $conversation = $this->chatService->getOrCreateConversation($sessionToken, $userId, $meta);
            $userMessageText = trim($request->input('message'));

            // 1. Save user message into database
            $userMsg = $this->chatService->addMessage($conversation->id, 'user', $userMessageText, $userId);

            $aiMessage = null;

            // 2. If status is 'ai', generate automated AI response
            if ($conversation->status === 'ai') {
                try {
                    $aiResponseText = $this->chatService->generateAiResponse($conversation, $userMessageText, $user);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('ChatController generateAiResponse error: ' . $e->getMessage());
                    $aiResponseText = "Hello! I am CultureAI, your assistant for 6ix Culture. How can I assist you with your fashion shopping or orders today?";
                }

                $aiMsg = $this->chatService->addMessage($conversation->id, 'ai', $aiResponseText);
                $aiMessage = [
                    'id'          => $aiMsg->id,
                    'sender_type' => 'ai',
                    'message'     => $aiMsg->message,
                    'created_at'  => $aiMsg->created_at,
                ];
            }

            return response()->json([
                'status'        => true,
                'session_token' => $conversation->session_token,
                'conversation'  => [
                    'id'     => $conversation->id,
                    'status' => $conversation->status,
                ],
                'user_message'  => [
                    'id'          => $userMsg->id,
                    'sender_type' => 'user',
                    'message'     => $userMsg->message,
                    'created_at'  => $userMsg->created_at,
                ],
                'ai_message'    => $aiMessage,
                'status_mode'   => $conversation->status,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Switch conversation mode to human support
     */
    public function requestHuman(Request $request): JsonResponse
    {
        try {
            $sessionToken = $request->header('X-Chat-Session') ?: $request->input('session_token');
            if (!$sessionToken) {
                return response()->json(['status' => false, 'message' => 'Invalid session'], 400);
            }

            $conversation = ChatConversation::where('session_token', $sessionToken)->first();
            if ($conversation) {
                $conversation->status = 'human';
                $conversation->save();

                $systemNote = "A customer care representative has been notified and will respond shortly. Please leave your inquiry below!";
                $msg = $this->chatService->addMessage($conversation->id, 'ai', $systemNote);

                return response()->json([
                    'status'  => true,
                    'message' => $systemNote,
                    'ai_message' => [
                        'id'          => $msg->id,
                        'sender_type' => 'ai',
                        'message'     => $msg->message,
                        'created_at'  => $msg->created_at,
                    ]
                ]);
            }

            return response()->json(['status' => false, 'message' => 'Conversation not found'], 404);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
