<?php

namespace App\Console\Commands\Support;

use App\Support\Migration\LegacyChatAuditService;
use Illuminate\Console\Command;

class LegacyChatAuditCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'support:legacy-chat-audit {--json : Output audit results as JSON}';

    /**
     * The console command description.
     */
    protected $description = 'Perform a read-only audit of legacy chat data prior to Support domain migration';

    /**
     * Execute the console command.
     */
    public function handle(LegacyChatAuditService $auditService): int
    {
        $this->info('Starting 6ixCulture AI Support — Legacy Chat Preflight Audit...');

        $results = $auditService->audit();

        if ($this->option('json')) {
            $this->line(json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return Command::SUCCESS;
        }

        if ($results['status'] === 'missing_tables') {
            $this->warn($results['summary']);
            return Command::SUCCESS;
        }

        $this->newLine();
        $this->table(['Metric', 'Value'], [
            ['Total Legacy Conversations', $results['counts']['total_conversations']],
            ['Total Legacy Messages', $results['counts']['total_messages']],
            ['Authenticated Conversations', $results['counts']['authenticated_conversations']],
            ['Guest Conversations', $results['counts']['guest_conversations']],
            ['Missing User References', $results['counts']['missing_user_conversations']],
            ['Orphan Messages', $results['counts']['orphan_messages']],
            ['Duplicate Session Tokens', $results['counts']['duplicate_session_tokens']],
            ['Oldest Conversation', $results['timestamps']['oldest_conversation'] ?? 'N/A'],
            ['Newest Conversation', $results['timestamps']['newest_conversation'] ?? 'N/A'],
            ['Already Migrated Conversations', $results['migration_state']['migrated_conversations']],
            ['Already Migrated Messages', $results['migration_state']['migrated_messages']],
        ]);

        $this->newLine();
        $this->info('Conversation Status Breakdown:');
        foreach ($results['breakdowns']['conversation_statuses'] as $status => $count) {
            $this->line("  • {$status}: {$count}");
        }

        $this->newLine();
        $this->info('Message Sender Breakdown:');
        foreach ($results['breakdowns']['message_senders'] as $sender => $count) {
            $this->line("  • {$sender}: {$count}");
        }

        $this->newLine();
        $this->info('Legacy AI Settings:');
        $this->line('  • site_chat_ai_agent: ' . ($results['configuration']['legacy_settings']['site_chat_ai_agent'] ?? 'none'));
        $this->line('  • site_chat_ai_model: ' . ($results['configuration']['legacy_settings']['site_chat_ai_model'] ?? 'none'));
        $this->line('  • Current Target Default Agent: ' . ($results['configuration']['current_target_settings']['site_default_ai_agent_slug'] ?? 'unset'));

        return Command::SUCCESS;
    }
}
