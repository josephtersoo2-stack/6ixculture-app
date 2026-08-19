<?php

namespace App\Support\Cutover;

use App\Support\Migration\LegacyChatAuditService;
use App\Support\Migration\LegacyChatMigrationService;
use App\Support\Migration\LegacyMigrationVerificationService;
use App\Support\Models\SupportAssignment;
use App\Support\Models\SupportAuditLog;
use App\Support\Models\SupportConversation;
use App\Support\Models\SupportMessage;
use App\Support\Models\SupportTicket;
use App\Support\Models\SupportVoiceSession;
use Carbon\Carbon;
use Dipokhalder\Settings\Facades\Settings;
use Illuminate\Support\Facades\Log;

class SupportCutoverManager
{
    public const STATE_LEGACY = 'legacy';
    public const STATE_DRAINING = 'draining';
    public const STATE_SUPPORT = 'support';

    /**
     * Get the current persistent cutover state.
     */
    public static function getState(): string
    {
        try {
            $state = Settings::group('support')->get('cutover_state');
            if (in_array($state, [self::STATE_LEGACY, self::STATE_DRAINING, self::STATE_SUPPORT], true)) {
                return $state;
            }
        } catch (\Throwable $e) {
            Log::warning('SupportCutoverManager getState fallback: ' . $e->getMessage());
        }

        return self::STATE_LEGACY;
    }

    public static function isLegacy(): bool
    {
        return self::getState() === self::STATE_LEGACY;
    }

    public static function isDraining(): bool
    {
        return self::getState() === self::STATE_DRAINING;
    }

    public static function isSupport(): bool
    {
        return self::getState() === self::STATE_SUPPORT;
    }

    /**
     * Determine if legacy chat mutation writes are allowed.
     */
    public static function canMutateLegacy(): bool
    {
        return self::isLegacy();
    }

    /**
     * Get detailed status of the cutover subsystem.
     */
    public static function getStatus(): array
    {
        $state = self::getState();

        $metadata = [];
        try {
            $metadata = [
                'cutover_started_at' => Settings::group('support')->get('cutover_started_at'),
                'support_activated_at' => Settings::group('support')->get('support_activated_at'),
                'activated_by' => Settings::group('support')->get('activated_by'),
                'final_delta_migration_run_id' => Settings::group('support')->get('final_delta_migration_run_id'),
                'verification_passed' => (bool) Settings::group('support')->get('verification_passed'),
            ];
        } catch (\Throwable $e) {
            // Ignore settings access error in unmigrated environments
        }

        return [
            'state' => $state,
            'is_support_canonical' => $state === self::STATE_SUPPORT,
            'legacy_writes_blocked' => in_array($state, [self::STATE_DRAINING, self::STATE_SUPPORT], true),
            'metadata' => $metadata,
        ];
    }

    /**
     * Transition system into draining mode (blocking legacy mutations).
     */
    public static function enterDraining(?int $userId = null): array
    {
        $currentState = self::getState();

        if ($currentState === self::STATE_DRAINING) {
            return [
                'success' => true,
                'message' => 'System is already in draining mode.',
                'state' => self::STATE_DRAINING,
            ];
        }

        $auditService = new LegacyChatAuditService();
        $auditReport = $auditService->audit();

        $startedAt = Carbon::now()->toIso8601String();

        try {
            Settings::group('support')->set('cutover_state', self::STATE_DRAINING);
            Settings::group('support')->set('cutover_started_at', $startedAt);
            Settings::group('support')->set('activated_by', $userId);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Failed to persist draining state: ' . $e->getMessage(),
                'state' => $currentState,
            ];
        }

        SupportAuditLog::log([
            'actor_type' => $userId ? 'agent' : 'system',
            'actor_id' => $userId,
            'action' => 'support_cutover_entered_draining',
            'resource_type' => 'cutover',
            'resource_id' => null,
            'metadata' => [
                'previous_state' => $currentState,
                'audit_summary' => $auditReport['counts'] ?? [],
            ],
        ]);

        return [
            'success' => true,
            'message' => 'Successfully transitioned cutover state to draining. Legacy mutation writes are now blocked.',
            'state' => self::STATE_DRAINING,
            'audit_report' => $auditReport,
        ];
    }

    /**
     * Perform final delta migration, verify parity, and activate Support mode.
     */
    public static function activateSupport(?int $userId = null, array $options = []): array
    {
        $currentState = self::getState();

        // 1. Ensure draining mode is active first
        if ($currentState === self::STATE_LEGACY) {
            $drainResult = self::enterDraining($userId);
            if (!$drainResult['success']) {
                return $drainResult;
            }
        }

        // 2. Execute Final Delta Migration
        $migrationService = new LegacyChatMigrationService();
        $migrationResult = $migrationService->migrate([
            'apply' => true,
            'chunk' => $options['chunk'] ?? 100,
            'migrate_config' => $options['migrate_config'] ?? true,
        ]);

        if ($migrationResult['status'] === 'failed') {
            return [
                'success' => false,
                'message' => 'Final delta migration failed. Support mode activation aborted.',
                'migration' => $migrationResult,
                'state' => self::STATE_DRAINING,
            ];
        }

        // 3. Execute Migration Verification
        $verifier = new LegacyMigrationVerificationService();
        $verificationResult = $verifier->verify($migrationResult['run_id']);

        if (!$verificationResult['passed'] || $verificationResult['mismatch_count'] > 0) {
            return [
                'success' => false,
                'message' => 'Migration verification failed with mismatches. Support mode activation aborted.',
                'verification' => $verificationResult,
                'migration' => $migrationResult,
                'state' => self::STATE_DRAINING,
            ];
        }

        // 4. Activate Support Mode
        $activatedAt = Carbon::now()->toIso8601String();

        try {
            Settings::group('support')->set('cutover_state', self::STATE_SUPPORT);
            Settings::group('support')->set('support_activated_at', $activatedAt);
            Settings::group('support')->set('final_delta_migration_run_id', $migrationResult['run_id']);
            Settings::group('support')->set('verification_passed', true);
            Settings::group('support')->set('activated_by', $userId);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Failed to persist Support state: ' . $e->getMessage(),
                'state' => self::STATE_DRAINING,
            ];
        }

        SupportAuditLog::log([
            'actor_type' => $userId ? 'agent' : 'system',
            'actor_id' => $userId,
            'action' => 'support_cutover_activated',
            'resource_type' => 'cutover',
            'resource_id' => null,
            'metadata' => [
                'migration_run_id' => $migrationResult['run_id'],
                'verification_summary' => $verificationResult['checked'],
                'activated_at' => $activatedAt,
            ],
        ]);

        return [
            'success' => true,
            'message' => 'Support domain successfully activated as canonical! Legacy chat writes remain blocked.',
            'state' => self::STATE_SUPPORT,
            'migration_run_id' => $migrationResult['run_id'],
            'verification' => $verificationResult,
        ];
    }

    /**
     * Revert cutover state with strict rollback guard.
     */
    public static function rollback(?int $userId = null, bool $force = false): array
    {
        $currentState = self::getState();

        if ($currentState === self::STATE_LEGACY) {
            return [
                'success' => true,
                'message' => 'System is already in legacy mode.',
                'state' => self::STATE_LEGACY,
            ];
        }

        // Check if Support mode was active and has post-cutover activity
        if ($currentState === self::STATE_SUPPORT && !$force) {
            $activatedAtStr = Settings::group('support')->get('support_activated_at');
            $activatedAt = $activatedAtStr ? Carbon::parse($activatedAtStr) : Carbon::now()->subHours(1);

            $newConversations = SupportConversation::where('created_at', '>=', $activatedAt)->count();
            $newMessages = SupportMessage::where('created_at', '>=', $activatedAt)->count();
            $newTickets = SupportTicket::where('created_at', '>=', $activatedAt)->count();
            $newVoiceSessions = SupportVoiceSession::where('created_at', '>=', $activatedAt)->count();

            $blockers = [];
            if ($newConversations > 0) {
                $blockers[] = "{$newConversations} Support conversations created post-cutover.";
            }
            if ($newMessages > 0) {
                $blockers[] = "{$newMessages} Support messages created post-cutover.";
            }
            if ($newTickets > 0) {
                $blockers[] = "{$newTickets} Support tickets created post-cutover.";
            }
            if ($newVoiceSessions > 0) {
                $blockers[] = "{$newVoiceSessions} Support voice sessions created post-cutover.";
            }

            if (!empty($blockers)) {
                return [
                    'success' => false,
                    'message' => 'Rollback blocked: Post-cutover Support domain activity detected. Manual incident / data reconciliation required.',
                    'blockers' => $blockers,
                    'state' => self::STATE_SUPPORT,
                ];
            }
        }

        // Safe to rollback state
        try {
            Settings::group('support')->set('cutover_state', self::STATE_LEGACY);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Failed to persist rollback state: ' . $e->getMessage(),
                'state' => $currentState,
            ];
        }

        SupportAuditLog::log([
            'actor_type' => $userId ? 'agent' : 'system',
            'actor_id' => $userId,
            'action' => 'support_cutover_rolled_back',
            'resource_type' => 'cutover',
            'resource_id' => null,
            'metadata' => [
                'previous_state' => $currentState,
                'force' => $force,
            ],
        ]);

        return [
            'success' => true,
            'message' => 'Successfully rolled back cutover state to legacy.',
            'state' => self::STATE_LEGACY,
        ];
    }
}
