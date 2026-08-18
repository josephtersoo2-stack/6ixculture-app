<?php

namespace App\Console\Commands\Support;

use App\Support\Migration\LegacyMigrationVerificationService;
use Illuminate\Console\Command;

class VerifyLegacyChatMigrationCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'support:verify-legacy-chat-migration
                            {--run= : Specific migration run public_id to verify}
                            {--json : Output verification results as JSON}';

    /**
     * The console command description.
     */
    protected $description = 'Verify parity and integrity of migrated legacy chat data against current Support domain';

    /**
     * Execute the console command.
     */
    public function handle(LegacyMigrationVerificationService $verifier): int
    {
        $runId = $this->option('run');
        $this->info('Starting 6ixCulture AI Support — Legacy Migration Verification Pass...');

        $results = $verifier->verify($runId);

        if ($this->option('json')) {
            $this->line(json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return $results['passed'] ? Command::SUCCESS : Command::FAILURE;
        }

        $this->newLine();
        $this->table(['Metric', 'Value'], [
            ['Verification Status', $results['passed'] ? 'PASS' : 'FAIL'],
            ['Conversations Checked', $results['checked']['conversations']],
            ['Messages Checked', $results['checked']['messages']],
            ['Mismatch Count', $results['mismatch_count']],
        ]);

        if (!$results['passed']) {
            $this->newLine();
            $this->error('Mismatches detected:');
            foreach ($results['mismatches'] as $m) {
                $this->line("  • {$m}");
            }
            return Command::FAILURE;
        }

        $this->info('All migrated records verified with 100% parity against legacy sources.');
        return Command::SUCCESS;
    }
}
