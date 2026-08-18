<?php

namespace App\Console\Commands\Support;

use App\Support\Migration\LegacyMigrationRollbackService;
use Illuminate\Console\Command;

class RollbackLegacyChatMigrationCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'support:rollback-legacy-chat-migration {run_public_id : The public_id of the migration run to revert}';

    /**
     * The console command description.
     */
    protected $description = 'Safely rollback migration-owned Support records created in a specific migration run';

    /**
     * Execute the console command.
     */
    public function handle(LegacyMigrationRollbackService $rollbackService): int
    {
        $runPublicId = $this->argument('run_public_id');

        $this->info("Initiating safe rollback for legacy migration run '{$runPublicId}'...");

        $result = $rollbackService->rollback($runPublicId);

        if (!$result['success']) {
            $this->error("Rollback failed: {$result['message']}");
            if (!empty($result['blockers'])) {
                $this->warn('Blockers detected:');
                foreach ($result['blockers'] as $b) {
                    $this->line("  • {$b}");
                }
            }
            return Command::FAILURE;
        }

        $this->info($result['message']);
        $this->table(['Entity', 'Rolled Back Count'], [
            ['Support Conversations', $result['rolled_back']['conversations']],
            ['Support Messages', $result['rolled_back']['messages']],
        ]);

        return Command::SUCCESS;
    }
}
