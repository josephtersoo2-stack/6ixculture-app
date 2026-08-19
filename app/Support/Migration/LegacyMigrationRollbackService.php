<?php

namespace App\Support\Migration;

use App\Support\Models\SupportAuditLog;
use App\Support\Models\SupportConversation;
use App\Support\Models\SupportLegacyMigrationItem;
use App\Support\Models\SupportLegacyMigrationRun;
use App\Support\Models\SupportMessage;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LegacyMigrationRollbackService
{
    /**
     * Revert migrated target records for a specific migration run where safe.
     *
     * @param string $runPublicId
     * @return array
     */
    public function rollback(string $runPublicId): array
    {
        $run = SupportLegacyMigrationRun::where('public_id', $runPublicId)->first();

        if (!$run) {
            return [
                'success' => false,
                'message' => "Migration run '{$runPublicId}' not found.",
                'rolled_back' => ['conversations' => 0, 'messages' => 0],
            ];
        }

        if ($run->status === 'rolled_back') {
            return [
                'success' => false,
                'message' => "Migration run '{$runPublicId}' has already been rolled back.",
                'rolled_back' => ['conversations' => 0, 'messages' => 0],
            ];
        }

        $items = $run->items()->where('state', 'migrated')->get();
        $convItems = $items->where('source_table', 'chat_conversations');
        $msgItems = $items->where('source_table', 'chat_messages');

        $migratedMsgTargetIds = $msgItems->pluck('target_id')->filter()->toArray();
        $migratedConvTargetIds = $convItems->pluck('target_id')->filter()->toArray();

        // 1. Safety Checks: Ensure target conversations have not received new non-migrated messages or dependencies
        $blockers = [];

        foreach ($migratedConvTargetIds as $convId) {
            $conv = SupportConversation::find($convId);
            if (!$conv) {
                continue;
            }

            // Check for new non-migrated messages
            $nonMigratedMsgCount = SupportMessage::where('conversation_id', $convId)
                ->whereNotIn('id', $migratedMsgTargetIds)
                ->count();

            if ($nonMigratedMsgCount > 0) {
                $blockers[] = "Conversation #{$convId} has {$nonMigratedMsgCount} new non-migrated support messages.";
            }

            // Check for tickets, voice sessions, assignments
            if (\App\Support\Models\SupportTicket::where('conversation_id', $convId)->exists()) {
                $blockers[] = "Conversation #{$convId} has an associated SupportTicket.";
            }
            if (\App\Support\Models\SupportVoiceSession::where('conversation_id', $convId)->exists()) {
                $blockers[] = "Conversation #{$convId} has an associated SupportVoiceSession.";
            }
            if (\App\Support\Models\SupportAssignment::where('conversation_id', $convId)->exists()) {
                $blockers[] = "Conversation #{$convId} has an associated SupportAssignment.";
            }
        }

        if (!empty($blockers)) {
            return [
                'success' => false,
                'message' => 'Rollback blocked by subsequent Support system mutations or dependencies.',
                'blockers' => $blockers,
                'rolled_back' => ['conversations' => 0, 'messages' => 0],
            ];
        }

        // 2. Perform Safe Rollback inside Transaction
        $rolledBackConvs = 0;
        $rolledBackMsgs = 0;

        DB::transaction(function () use (
            $run,
            $convItems,
            $msgItems,
            $migratedConvTargetIds,
            $migratedMsgTargetIds,
            &$rolledBackConvs,
            &$rolledBackMsgs
        ) {
            // Delete target messages
            if (!empty($migratedMsgTargetIds)) {
                $rolledBackMsgs = SupportMessage::whereIn('id', $migratedMsgTargetIds)->delete();
            }

            // Delete target conversations
            if (!empty($migratedConvTargetIds)) {
                $rolledBackConvs = SupportConversation::whereIn('id', $migratedConvTargetIds)->delete();
            }

            // Mark ledger items as rolled_back
            $convItems->each->update(['state' => 'rolled_back']);
            $msgItems->each->update(['state' => 'rolled_back']);

            // Update run record
            $run->update([
                'status' => 'rolled_back',
                'completed_at' => Carbon::now(),
            ]);

            SupportAuditLog::log([
                'actor_type' => 'system',
                'action' => 'legacy_rollback_completed',
                'resource_type' => 'migration_run',
                'resource_id' => $run->id,
                'metadata' => [
                    'run_public_id' => $run->public_id,
                    'rolled_back_conversations' => $rolledBackConvs,
                    'rolled_back_messages' => $rolledBackMsgs,
                ],
            ]);
        });

        return [
            'success' => true,
            'message' => "Successfully rolled back migration run '{$runPublicId}'.",
            'rolled_back' => [
                'conversations' => $rolledBackConvs,
                'messages' => $rolledBackMsgs,
            ],
        ];
    }
}
