<?php

namespace App\Support\Migration;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Support\Models\SupportAuditLog;
use App\Support\Models\SupportConversation;
use App\Support\Models\SupportLegacyMigrationItem;
use App\Support\Models\SupportLegacyMigrationRun;
use App\Support\Models\SupportMessage;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LegacyChatMigrationService
{
    /**
     * Execute migration of legacy chat data to current Support domain.
     *
     * @param array $options [
     *   'apply' => bool,
     *   'chunk' => int,
     *   'from_id' => int|null,
     *   'to_id' => int|null,
     *   'only_status' => string|null,
     *   'resume' => string|null,
     *   'migrate_config' => bool
     * ]
     * @return array
     */
    public function migrate(array $options = []): array
    {
        $apply = (bool) ($options['apply'] ?? false);
        $chunkSize = max(10, min(500, (int) ($options['chunk'] ?? 100)));
        $fromId = !empty($options['from_id']) ? (int) $options['from_id'] : null;
        $toId = !empty($options['to_id']) ? (int) $options['to_id'] : null;
        $onlyStatus = !empty($options['only_status']) ? strtolower(trim((string) $options['only_status'])) : null;
        $resumePublicId = $options['resume'] ?? null;
        $migrateConfig = (bool) ($options['migrate_config'] ?? false);

        // 1. Initialize or Resume Migration Run
        $run = null;
        if ($resumePublicId) {
            $run = SupportLegacyMigrationRun::where('public_id', $resumePublicId)->first();
        }

        if (!$run) {
            $run = SupportLegacyMigrationRun::create([
                'source' => 'legacy_chat',
                'mode' => $apply ? 'apply' : 'dry_run',
                'status' => 'running',
                'started_at' => Carbon::now(),
                'source_counts' => [],
                'result_counts' => [],
                'error_counts' => [],
                'metadata' => [
                    'options' => $options,
                ],
            ]);
        } else {
            $run->update([
                'status' => 'running',
                'mode' => $apply ? 'apply' : 'dry_run',
            ]);
        }

        $runPublicId = $run->public_id;

        // 2. Configuration Migration (if requested)
        $configResult = null;
        if ($migrateConfig) {
            $configResult = LegacyConfigurationMapper::migrate($apply);
        }

        // 3. Build Conversation Query
        $query = ChatConversation::query()->orderBy('id', 'asc');

        if ($fromId) {
            $query->where('id', '>=', $fromId);
        }
        if ($toId) {
            $query->where('id', '<=', $toId);
        }
        if ($onlyStatus) {
            $query->where('status', $onlyStatus);
        }

        $totalFound = $query->count();

        $stats = [
            'conversations_total' => $totalFound,
            'conversations_created' => 0,
            'conversations_updated' => 0,
            'conversations_skipped' => 0,
            'conversations_failed' => 0,
            'conversations_conflict' => 0,
            'messages_created' => 0,
            'messages_skipped' => 0,
            'messages_failed' => 0,
        ];

        $errors = [];

        // 4. Chunked Processing with Bounded Transactions
        $query->chunkById($chunkSize, function ($conversations) use (
            $apply,
            $run,
            $runPublicId,
            &$stats,
            &$errors
        ) {
            foreach ($conversations as $legacyConv) {
                try {
                    $this->processConversation(
                        $legacyConv,
                        $apply,
                        $run->id,
                        $runPublicId,
                        $stats
                    );
                } catch (\Throwable $e) {
                    $stats['conversations_failed']++;
                    $errors[] = [
                        'conversation_id' => $legacyConv->id,
                        'error' => $e->getMessage(),
                    ];
                    Log::error("Legacy migration failed for conversation #{$legacyConv->id}: " . $e->getMessage());

                    if ($apply) {
                        SupportLegacyMigrationItem::updateOrCreate(
                            ['source_table' => 'chat_conversations', 'source_id' => $legacyConv->id],
                            [
                                'migration_run_id' => $run->id,
                                'source_checksum' => SourceChecksumCalculator::forConversation($legacyConv),
                                'state' => 'failed',
                                'metadata' => ['error' => $e->getMessage()],
                            ]
                        );
                    }
                }
            }
        });

        // 5. Finalize Migration Run Status
        $finalStatus = 'completed';
        if ($stats['conversations_failed'] > 0) {
            $finalStatus = ($stats['conversations_created'] > 0 || $stats['conversations_updated'] > 0) ? 'partial' : 'failed';
        }

        $run->update([
            'status' => $finalStatus,
            'completed_at' => Carbon::now(),
            'source_counts' => [
                'conversations_discovered' => $totalFound,
            ],
            'result_counts' => $stats,
            'error_counts' => [
                'total_errors' => count($errors),
                'details' => array_slice($errors, 0, 50),
            ],
        ]);

        if ($apply) {
            SupportAuditLog::log([
                'actor_type' => 'system',
                'action' => "legacy_migration_{$finalStatus}",
                'resource_type' => 'migration_run',
                'resource_id' => $run->id,
                'metadata' => [
                    'run_public_id' => $runPublicId,
                    'stats' => $stats,
                    'config_migrated' => $configResult['migrated'] ?? false,
                ],
            ]);
        }

        return [
            'run_id' => $runPublicId,
            'mode' => $apply ? 'apply' : 'dry_run',
            'status' => $finalStatus,
            'stats' => $stats,
            'errors' => $errors,
            'config_migration' => $configResult,
        ];
    }

    /**
     * Process an individual conversation and its messages inside an isolated transaction.
     */
    protected function processConversation(
        ChatConversation $legacyConv,
        bool $apply,
        int $runId,
        string $runPublicId,
        array &$stats
    ): void {
        $convChecksum = SourceChecksumCalculator::forConversation($legacyConv);
        $legacyMessages = $legacyConv->messages()->orderBy('id', 'asc')->get();

        // Check if conversation was previously migrated
        $existingConvItem = SupportLegacyMigrationItem::where('source_table', 'chat_conversations')
            ->where('source_id', $legacyConv->id)
            ->first();

        if ($existingConvItem && $existingConvItem->target_id) {
            $targetConv = SupportConversation::find($existingConvItem->target_id);

            if (!$targetConv) {
                // Target was deleted or missing -> flag conflict
                $stats['conversations_conflict']++;
                return;
            }

            // Delta Check: Process any newly arrived legacy messages
            $newMsgCount = 0;
            foreach ($legacyMessages as $legacyMsg) {
                $msgChecksum = SourceChecksumCalculator::forMessage($legacyMsg);
                $existingMsgItem = SupportLegacyMigrationItem::where('source_table', 'chat_messages')
                    ->where('source_id', $legacyMsg->id)
                    ->first();

                if ($existingMsgItem) {
                    $stats['messages_skipped']++;
                    continue;
                }

                if ($apply) {
                    DB::transaction(function () use (
                        $legacyMsg,
                        $targetConv,
                        $runId,
                        $runPublicId,
                        $msgChecksum,
                        &$newMsgCount,
                        &$stats
                    ) {
                        $msgData = LegacyMessageMapper::map($legacyMsg, $targetConv->id, $targetConv->customer_id, $runPublicId);
                        $createdMsg = SupportMessage::create($msgData);

                        SupportLegacyMigrationItem::create([
                            'migration_run_id' => $runId,
                            'source_table' => 'chat_messages',
                            'source_id' => $legacyMsg->id,
                            'target_table' => 'support_messages',
                            'target_id' => $createdMsg->id,
                            'source_checksum' => $msgChecksum,
                            'state' => 'migrated',
                            'migrated_at' => Carbon::now(),
                            'metadata' => ['delta' => true],
                        ]);

                        $newMsgCount++;
                        $stats['messages_created']++;
                    });
                } else {
                    $newMsgCount++;
                    $stats['messages_created']++;
                }
            }

            if ($newMsgCount > 0) {
                if ($apply) {
                    // Update conversation aggregate timestamps
                    $targetConv->update([
                        'last_message_at' => $legacyConv->last_message_at ?? Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);
                }
                $stats['conversations_updated']++;
            } else {
                $stats['conversations_skipped']++;
            }

            return;
        }

        // New Migration
        if (!$apply) {
            $stats['conversations_created']++;
            $stats['messages_created'] += $legacyMessages->count();
            return;
        }

        DB::transaction(function () use (
            $legacyConv,
            $legacyMessages,
            $runId,
            $runPublicId,
            $convChecksum,
            &$stats
        ) {
            // 1. Create SupportConversation
            $convData = LegacyConversationMapper::map($legacyConv, $legacyMessages, $runPublicId);
            $targetConv = SupportConversation::create($convData);

            // 2. Record Conversation Item in Ledger
            SupportLegacyMigrationItem::create([
                'migration_run_id' => $runId,
                'source_table' => 'chat_conversations',
                'source_id' => $legacyConv->id,
                'target_table' => 'support_conversations',
                'target_id' => $targetConv->id,
                'source_checksum' => $convChecksum,
                'state' => 'migrated',
                'migrated_at' => Carbon::now(),
            ]);

            $stats['conversations_created']++;

            // 3. Migrate Messages
            foreach ($legacyMessages as $legacyMsg) {
                $msgChecksum = SourceChecksumCalculator::forMessage($legacyMsg);
                $msgData = LegacyMessageMapper::map($legacyMsg, $targetConv->id, $targetConv->customer_id, $runPublicId);
                $createdMsg = SupportMessage::create($msgData);

                SupportLegacyMigrationItem::create([
                    'migration_run_id' => $runId,
                    'source_table' => 'chat_messages',
                    'source_id' => $legacyMsg->id,
                    'target_table' => 'support_messages',
                    'target_id' => $createdMsg->id,
                    'source_checksum' => $msgChecksum,
                    'state' => 'migrated',
                    'migrated_at' => Carbon::now(),
                ]);

                $stats['messages_created']++;
            }
        });
    }
}
