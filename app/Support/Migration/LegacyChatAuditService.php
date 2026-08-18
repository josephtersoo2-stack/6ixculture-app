<?php

namespace App\Support\Migration;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use App\Support\Models\SupportLegacyMigrationItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LegacyChatAuditService
{
    /**
     * Run full preflight read-only audit of legacy chat data.
     *
     * @return array
     */
    public function audit(): array
    {
        $hasConvTable = Schema::hasTable('chat_conversations');
        $hasMsgTable = Schema::hasTable('chat_messages');
        $hasLedgerTable = Schema::hasTable('support_legacy_migration_items');

        if (!$hasConvTable || !$hasMsgTable) {
            return [
                'status' => 'missing_tables',
                'tables' => [
                    'chat_conversations' => $hasConvTable,
                    'chat_messages' => $hasMsgTable,
                ],
                'summary' => 'Legacy chat database tables do not exist in the current environment.',
            ];
        }

        // 1. Basic Counts & Timestamps
        $totalConversations = ChatConversation::count();
        $totalMessages = ChatMessage::count();

        $oldestConversation = ChatConversation::orderBy('created_at', 'asc')->first()?->created_at?->toIso8601String();
        $newestConversation = ChatConversation::orderBy('created_at', 'desc')->first()?->created_at?->toIso8601String();

        $oldestMessage = ChatMessage::orderBy('created_at', 'asc')->first()?->created_at?->toIso8601String();
        $newestMessage = ChatMessage::orderBy('created_at', 'desc')->first()?->created_at?->toIso8601String();

        // 2. Status Breakdown
        $statusCounts = ChatConversation::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        // 3. Sender Breakdown
        $senderCounts = ChatMessage::select('sender_type', DB::raw('count(*) as total'))
            ->groupBy('sender_type')
            ->pluck('total', 'sender_type')
            ->toArray();

        // 4. Authenticated vs Guest Conversations
        $authenticatedCount = ChatConversation::whereNotNull('user_id')->count();
        $guestCount = ChatConversation::whereNull('user_id')->count();

        // 5. Missing User References
        $missingUserCount = 0;
        if ($authenticatedCount > 0) {
            $existingUserIds = User::pluck('id')->toArray();
            $missingUserCount = ChatConversation::whereNotNull('user_id')
                ->whereNotIn('user_id', $existingUserIds)
                ->count();
        }

        // 6. Orphan Messages (conversation_id does not exist)
        $existingConvIds = ChatConversation::pluck('id')->toArray();
        $orphanMessageCount = 0;
        if ($totalMessages > 0) {
            $orphanMessageCount = ChatMessage::whereNotIn('conversation_id', $existingConvIds)->count();
        }

        // 7. Duplicate & Blank Session Tokens
        $blankSessionTokens = ChatConversation::whereNull('session_token')
            ->orWhere('session_token', '')
            ->count();

        $duplicateSessionTokens = ChatConversation::select('session_token', DB::raw('count(*) as count'))
            ->whereNotNull('session_token')
            ->where('session_token', '!=', '')
            ->groupBy('session_token')
            ->having('count', '>', 1)
            ->count();

        // 8. Migration Ledger Status
        $migratedConversations = 0;
        $migratedMessages = 0;
        if ($hasLedgerTable) {
            $migratedConversations = SupportLegacyMigrationItem::where('source_table', 'chat_conversations')
                ->where('state', 'migrated')
                ->count();
            $migratedMessages = SupportLegacyMigrationItem::where('source_table', 'chat_messages')
                ->where('state', 'migrated')
                ->count();
        }

        // 9. Configuration Audit
        $configDiscovery = LegacyConfigurationMapper::discover();

        return [
            'status' => 'ready',
            'tables' => [
                'chat_conversations' => true,
                'chat_messages' => true,
                'support_legacy_migration_items' => $hasLedgerTable,
            ],
            'counts' => [
                'total_conversations' => $totalConversations,
                'total_messages' => $totalMessages,
                'authenticated_conversations' => $authenticatedCount,
                'guest_conversations' => $guestCount,
                'missing_user_conversations' => $missingUserCount,
                'orphan_messages' => $orphanMessageCount,
                'blank_session_tokens' => $blankSessionTokens,
                'duplicate_session_tokens' => $duplicateSessionTokens,
            ],
            'timestamps' => [
                'oldest_conversation' => $oldestConversation,
                'newest_conversation' => $newestConversation,
                'oldest_message' => $oldestMessage,
                'newest_message' => $newestMessage,
            ],
            'breakdowns' => [
                'conversation_statuses' => $statusCounts,
                'message_senders' => $senderCounts,
            ],
            'migration_state' => [
                'migrated_conversations' => $migratedConversations,
                'migrated_messages' => $migratedMessages,
                'unmigrated_conversations' => max(0, $totalConversations - $migratedConversations),
                'unmigrated_messages' => max(0, $totalMessages - $migratedMessages),
            ],
            'configuration' => $configDiscovery,
        ];
    }
}
