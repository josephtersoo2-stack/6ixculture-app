<?php

namespace App\Console\Commands\Support;

use App\Support\Cutover\SupportCutoverManager;
use App\Support\Cutover\SupportReadinessService;
use App\Support\Migration\LegacyChatAuditService;
use App\Support\Migration\LegacyChatMigrationService;
use Illuminate\Console\Command;

class SupportCutoverCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'support:cutover 
                            {--status : Show current cutover status and readiness summary}
                            {--preflight : Run preflight checks and dry-run without state mutation}
                            {--enter-draining : Transition system state to draining to block legacy writes}
                            {--activate-support : Run final delta migration, verify parity, and activate Support mode}
                            {--rollback : Safely revert cutover state to legacy (strictly blocked if post-cutover activity exists)}
                            {--chunk=100 : Batch size for migration runs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage server-authoritative cutover from legacy chat to 6ixCulture AI Support domain';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('preflight')) {
            return $this->handlePreflight();
        }

        if ($this->option('enter-draining')) {
            return $this->handleEnterDraining();
        }

        if ($this->option('activate-support')) {
            return $this->handleActivateSupport();
        }

        if ($this->option('rollback')) {
            return $this->handleRollback();
        }

        // Default: display status
        return $this->handleStatus();
    }

    private function handleStatus(): int
    {
        $status = SupportCutoverManager::getStatus();
        $readinessService = new SupportReadinessService();
        $readiness = $readinessService->getReadiness();

        $this->info("6ixCulture AI Support — Cutover Status");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Current State', strtoupper($status['state'])],
                ['Readiness Status', strtoupper($readiness['status'])],
                ['Support Domain Canonical', $status['is_support_canonical'] ? 'YES' : 'NO'],
                ['Legacy Writes Blocked', $status['legacy_writes_blocked'] ? 'YES' : 'NO'],
                ['Cutover Started At', $status['metadata']['cutover_started_at'] ?? 'N/A'],
                ['Support Activated At', $status['metadata']['support_activated_at'] ?? 'N/A'],
                ['Final Delta Run ID', $status['metadata']['final_delta_migration_run_id'] ?? 'N/A'],
                ['Verification Passed', ($status['metadata']['verification_passed'] ?? false) ? 'YES' : 'NO'],
                ['Support Tables Ready', $readiness['infrastructure']['support_tables_ready'] ? 'YES' : 'NO'],
                ['AI Provider Configured', $readiness['ai_readiness']['provider_configured'] ? 'YES' : 'NO'],
                ['Active Departments', $readiness['governance']['active_departments']],
                ['Active Policies', $readiness['governance']['active_policies']],
                ['Active Tools', $readiness['governance']['active_tools']],
                ['Published Articles', $readiness['governance']['published_articles']],
                ['Voice STT Ready', $readiness['voice']['stt_ready'] ? 'YES' : 'NO'],
                ['Voice TTS Ready', $readiness['voice']['tts_ready'] ? 'YES' : 'NO'],
                ['Realtime Supported', $readiness['realtime']['supported'] ? 'YES' : 'NO (Polling Active)'],
            ]
        );

        if (!empty($readiness['warnings'])) {
            $this->warn("\nReadiness Warnings:");
            foreach ($readiness['warnings'] as $warning) {
                $this->line("  ⚠ {$warning}");
            }
        }

        if (!empty($readiness['blockers'])) {
            $this->error("\nReadiness Blockers:");
            foreach ($readiness['blockers'] as $blocker) {
                $this->line("  ✖ {$blocker}");
            }
        }

        return Command::SUCCESS;
    }

    private function handlePreflight(): int
    {
        $this->info("Running 6ixCulture AI Support — Pre-Cutover Preflight Checks...");

        // 1. Audit
        $auditService = new LegacyChatAuditService();
        $audit = $auditService->audit();

        $this->line("• Legacy source audit completed.");
        $this->table(
            ['Source Metric', 'Count'],
            [
                ['Total Conversations', $audit['counts']['total_conversations']],
                ['Total Messages', $audit['counts']['total_messages']],
                ['Authenticated Conversations', $audit['counts']['authenticated_conversations']],
                ['Guest Conversations', $audit['counts']['guest_conversations']],
                ['Missing User References', $audit['counts']['missing_user_conversations']],
                ['Orphan Messages', $audit['counts']['orphan_messages']],
            ]
        );

        // 2. Migration Dry-Run
        $migrationService = new LegacyChatMigrationService();
        $dryRun = $migrationService->migrate([
            'apply' => false,
            'chunk' => (int) $this->option('chunk'),
            'migrate_config' => true,
        ]);

        $this->line("• Migration dry-run completed (status: {$dryRun['status']}).");

        if ($dryRun['status'] === 'failed') {
            $this->error("Migration dry-run failed. Cutover cannot proceed.");
            return Command::FAILURE;
        }

        // 3. Readiness Evaluation
        $readinessService = new SupportReadinessService();
        $readiness = $readinessService->getReadiness();

        $this->info("\nPreflight Summary:");
        $this->line("  • Status: " . strtoupper($readiness['status']));
        $this->line("  • Support Tables: " . ($readiness['infrastructure']['support_tables_ready'] ? 'PASS' : 'FAIL'));
        $this->line("  • AI Provider: " . ($readiness['ai_readiness']['provider_configured'] ? 'PASS' : 'FAIL'));
        $this->line("  • Governance Seeding: " . ($readiness['governance']['ready'] ? 'PASS' : 'FAIL'));
        $this->line("  • Voice STT / TTS: " . ($readiness['voice']['stt_ready'] ? 'CONFIGURED' : 'OPTIONAL_UNCONFIGURED'));
        $this->line("  • Realtime Transport: " . ($readiness['realtime']['supported'] ? 'ACTIVE' : 'POLLING_FALLBACK'));
        $this->line("  • Current Cutover State: " . strtoupper(SupportCutoverManager::getState()));

        if (!empty($readiness['warnings'])) {
            $this->warn("\nWarnings (Non-blocking):");
            foreach ($readiness['warnings'] as $warning) {
                $this->line("  ⚠ {$warning}");
            }
        }

        if (!$readiness['ready'] || !empty($readiness['blockers'])) {
            $this->error("\nPreflight FAILED — Critical Blockers Detected:");
            foreach ($readiness['blockers'] as $blocker) {
                $this->line("  ✖ {$blocker}");
            }
            $this->error("\nCutover sequence cannot proceed until blockers are resolved.");
            return Command::FAILURE;
        }

        $this->info("\nPreflight PASSED. System is ready to proceed with: php artisan support:cutover --enter-draining");

        return Command::SUCCESS;
    }

    private function handleEnterDraining(): int
    {
        $this->info("Evaluating Readiness & Migration Dry-Run Before Entering Draining Mode...");

        // 1. Audit & Migration Dry-Run Check
        $migrationService = new LegacyChatMigrationService();
        $dryRun = $migrationService->migrate([
            'apply' => false,
            'chunk' => (int) $this->option('chunk'),
            'migrate_config' => true,
        ]);

        if ($dryRun['status'] === 'failed') {
            $this->error("Migration dry-run failed. Cannot enter draining mode.");
            return Command::FAILURE;
        }

        $result = SupportCutoverManager::enterDraining();

        if ($result['success']) {
            $this->info($result['message']);
            return Command::SUCCESS;
        }

        $this->error($result['message']);
        if (!empty($result['blockers'])) {
            $this->error("\nCritical Readiness Blockers:");
            foreach ($result['blockers'] as $blocker) {
                $this->line("  ✖ {$blocker}");
            }
        }
        return Command::FAILURE;
    }

    private function handleActivateSupport(): int
    {
        $this->info("Executing Final Delta Migration & Activating Support Domain...");

        $result = SupportCutoverManager::activateSupport(null, [
            'chunk' => (int) $this->option('chunk'),
            'migrate_config' => true,
        ]);

        if ($result['success']) {
            $this->info($result['message']);
            if (!empty($result['is_already_active'])) {
                return Command::SUCCESS;
            }

            $this->table(
                ['Result Item', 'Value'],
                [
                    ['Activated State', strtoupper($result['state'])],
                    ['Final Delta Run ID', $result['migration_run_id'] ?? 'N/A'],
                    ['Verification Passed', ($result['verification']['passed'] ?? false) ? 'YES' : 'NO'],
                    ['Mismatches', $result['verification']['mismatch_count'] ?? 0],
                ]
            );
            return Command::SUCCESS;
        }

        $this->error($result['message']);
        if (!empty($result['blockers'])) {
            $this->error("\nActivation Blockers:");
            foreach ($result['blockers'] as $blocker) {
                $this->line("  ✖ {$blocker}");
            }
        }
        if (!empty($result['verification']['mismatches'])) {
            $this->error("Verification mismatches: " . json_encode($result['verification']['mismatches']));
        }
        return Command::FAILURE;
    }

    private function handleRollback(): int
    {
        $this->info("Evaluating Rollback Safety & Executing Controlled Rollback...");

        $result = SupportCutoverManager::rollback();

        if ($result['success']) {
            $this->info($result['message']);
            return Command::SUCCESS;
        }

        $this->error($result['message']);
        if (!empty($result['blockers'])) {
            $this->warn("\nRollback Blockers (Fail-Closed Data Protection):");
            foreach ($result['blockers'] as $blocker) {
                $this->line("  • {$blocker}");
            }
        }
        return Command::FAILURE;
    }
}
