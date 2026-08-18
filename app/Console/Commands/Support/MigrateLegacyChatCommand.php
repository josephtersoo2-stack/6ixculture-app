<?php

namespace App\Console\Commands\Support;

use App\Support\Migration\LegacyChatMigrationService;
use Illuminate\Console\Command;

class MigrateLegacyChatCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'support:migrate-legacy-chat
                            {--dry-run : Perform dry-run without writing Support records}
                            {--apply : Explicitly apply migration writes}
                            {--migrate-config : Migrate compatible non-secret legacy settings}
                            {--chunk=100 : Number of conversations to process per chunk}
                            {--from-id= : Minimum legacy conversation ID}
                            {--to-id= : Maximum legacy conversation ID}
                            {--only-status= : Filter by legacy conversation status (ai, human, closed)}
                            {--resume= : Resume an existing migration run by public_id}';

    /**
     * The console command description.
     */
    protected $description = 'Migrate legacy chat conversations, messages, and configuration into current Support domain';

    /**
     * Execute the console command.
     */
    public function handle(LegacyChatMigrationService $migrationService): int
    {
        $apply = (bool) $this->option('apply');
        $dryRun = (bool) $this->option('dry-run');

        if (!$apply && !$dryRun) {
            $this->warn('Neither --apply nor --dry-run specified. Defaulting to safe --dry-run mode.');
        }

        $options = [
            'apply' => $apply,
            'chunk' => (int) $this->option('chunk'),
            'from_id' => $this->option('from-id'),
            'to_id' => $this->option('to-id'),
            'only_status' => $this->option('only-status'),
            'resume' => $this->option('resume'),
            'migrate_config' => (bool) $this->option('migrate-config'),
        ];

        $modeLabel = $apply ? 'APPLY' : 'DRY-RUN';
        $this->info("Executing 6ixCulture AI Support — Legacy Chat Migration [Mode: {$modeLabel}]...");

        $result = $migrationService->migrate($options);

        $this->newLine();
        $this->info("Migration Run Public ID: {$result['run_id']}");
        $this->info("Status: {$result['status']}");

        $stats = $result['stats'];
        $this->table(['Metric', 'Count'], [
            ['Conversations Discovered', $stats['conversations_total']],
            ['Conversations Created', $stats['conversations_created']],
            ['Conversations Updated (Delta)', $stats['conversations_updated']],
            ['Conversations Skipped (Already Migrated)', $stats['conversations_skipped']],
            ['Conversations Conflicts', $stats['conversations_conflict']],
            ['Conversations Failed', $stats['conversations_failed']],
            ['Messages Created', $stats['messages_created']],
            ['Messages Skipped', $stats['messages_skipped']],
        ]);

        if (!empty($result['config_migration'])) {
            $this->newLine();
            $this->info('Configuration Migration:');
            $this->line("  • Result: " . $result['config_migration']['message']);
        }

        if (!empty($result['errors'])) {
            $this->newLine();
            $this->error('Encountered errors during migration:');
            foreach (array_slice($result['errors'], 0, 10) as $err) {
                $this->line("  • Conversation #{$err['conversation_id']}: {$err['error']}");
            }
        }

        return $result['status'] === 'failed' ? Command::FAILURE : Command::SUCCESS;
    }
}
