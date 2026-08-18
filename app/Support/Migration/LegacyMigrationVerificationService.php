<?php

namespace App\Support\Migration;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Support\Models\SupportConversation;
use App\Support\Models\SupportLegacyMigrationItem;
use App\Support\Models\SupportLegacyMigrationRun;
use App\Support\Models\SupportMessage;

class LegacyMigrationVerificationService
{
    /**
     * Run parity and integrity verification across legacy source and migrated target data.
     *
     * @param string|null $runPublicId
     * @return array
     */
    public function verify(?string $runPublicId = null): array
    {
        $mismatches = [];
        $checkedConversations = 0;
        $checkedMessages = 0;

        $itemQuery = SupportLegacyMigrationItem::where('state', 'migrated');
        if ($runPublicId) {
            $run = SupportLegacyMigrationRun::where('public_id', $runPublicId)->first();
            if ($run) {
                $itemQuery->where('migration_run_id', $run->id);
            }
        }

        $migratedConvItems = (clone $itemQuery)
            ->where('source_table', 'chat_conversations')
            ->get();

        $migratedMsgItems = (clone $itemQuery)
            ->where('source_table', 'chat_messages')
            ->get();

        // 1. Verify Conversations
        foreach ($migratedConvItems as $item) {
            $checkedConversations++;
            $legacyConv = ChatConversation::find($item->source_id);
            $targetConv = SupportConversation::find($item->target_id);

            if (!$legacyConv) {
                $mismatches[] = "Legacy conversation #{$item->source_id} not found in source database.";
                continue;
            }

            if (!$targetConv) {
                $mismatches[] = "Target SupportConversation #{$item->target_id} not found for legacy conversation #{$item->source_id}.";
                continue;
            }

            // Verify Customer Ownership Mapping
            if (!empty($legacyConv->user_id) && \App\Models\User::where('id', $legacyConv->user_id)->exists()) {
                if ($targetConv->customer_id != $legacyConv->user_id) {
                    $mismatches[] = "Customer ID mismatch on conversation #{$legacyConv->id}: expected {$legacyConv->user_id}, got {$targetConv->customer_id}.";
                }
            } else {
                if (!empty($targetConv->customer_id)) {
                    $mismatches[] = "Target conversation #{$targetConv->id} unexpectedly has customer_id {$targetConv->customer_id} for guest legacy conversation #{$legacyConv->id}.";
                }
            }

            // Verify Status Mapping
            $legacyStatus = strtolower(trim((string) $legacyConv->status));
            if ($legacyStatus === 'closed' && $targetConv->status->value !== 'closed') {
                $mismatches[] = "Status mismatch on conversation #{$legacyConv->id}: expected 'closed', got '{$targetConv->status->value}'.";
            }

            // Update item last_verified_at
            $item->update(['last_verified_at' => now()]);
        }

        // 2. Verify Messages
        foreach ($migratedMsgItems as $item) {
            $checkedMessages++;
            $legacyMsg = ChatMessage::find($item->source_id);
            $targetMsg = SupportMessage::find($item->target_id);

            if (!$legacyMsg) {
                $mismatches[] = "Legacy message #{$item->source_id} not found in source database.";
                continue;
            }

            if (!$targetMsg) {
                $mismatches[] = "Target SupportMessage #{$item->target_id} not found for legacy message #{$item->source_id}.";
                continue;
            }

            // Verify content fidelity
            if ($targetMsg->content !== (string) $legacyMsg->message) {
                $mismatches[] = "Content mismatch on message #{$legacyMsg->id}.";
            }

            // Verify sender mapping
            $legacySender = strtolower(trim((string) $legacyMsg->sender_type));
            $expectedSender = match ($legacySender) {
                'user' => 'customer',
                'ai' => 'ai',
                'agent', 'admin' => 'agent',
                default => 'system',
            };

            if ($targetMsg->sender_type->value !== $expectedSender) {
                $mismatches[] = "Sender type mismatch on message #{$legacyMsg->id}: expected '{$expectedSender}', got '{$targetMsg->sender_type->value}'.";
            }

            $item->update(['last_verified_at' => now()]);
        }

        $passed = empty($mismatches);

        return [
            'passed' => $passed,
            'checked' => [
                'conversations' => $checkedConversations,
                'messages' => $checkedMessages,
            ],
            'mismatch_count' => count($mismatches),
            'mismatches' => array_slice($mismatches, 0, 50),
        ];
    }
}
