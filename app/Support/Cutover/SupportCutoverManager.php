<?php

namespace App\Support\Cutover;

use App\Support\Migration\LegacyChatAuditService;
use App\Support\Migration\LegacyChatMigrationService;
use App\Support\Migration\LegacyMigrationVerificationService;
use App\Support\Models\SupportAssignment;
use App\Support\Models\SupportAuditLog;
use App\Support\Models\SupportConversation;
use App\Support\Models\SupportFeedback;
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
     *
     * Transition rules:
     * - legacy -> draining: ALLOWED
     * - draining -> draining: IDEMPOTENT (ALLOWED)
     * - support -> draining: FORBIDDEN (FAILS CLOSED)
     */
    public static function enterDraining(?int $userId = null): array
    {
        $currentState = self::getState();

        // 1. Guard against illegal backward transition from support
        if ($currentState === self::STATE_SUPPORT) {
            SupportAuditLog::log([
                'actor_type' => $userId ? 'agent' : 'system',
                'actor_id' => $userId,
                'action' => 'support_cutover_invalid_transition_rejected',
                'resource_type' => 'cutover',
                'resource_id' => null,
                'metadata' => [
                    'attempted_transition' => 'support_to_draining',
                    'current_state' => self::STATE_SUPPORT,
                    'reason' => 'Direct transition from support to draining is forbidden.',
                ],
            ]);

            return [
                'success' => false,
                'message' => 'Transition rejected: Cannot move from support to draining. The Support domain is currently active.',
                'state' => self::STATE_SUPPORT,
            ];
        }

        // 2. Idempotent check
        if ($currentState === self::STATE_DRAINING) {
            return [
                'success' => true,
                'message' => 'System is already in draining mode.',
                'state' => self::STATE_DRAINING,
            ];
        }

        // 3. Normal transition: legacy -> draining
        // Critical Gate: Verify live Support readiness before locking legacy mutation writes
        $readinessService = new SupportReadinessService();
        $readiness = $readinessService->getReadiness();

        if (!$readiness['ready'] || !empty($readiness['blockers'])) {
            SupportAuditLog::log([
                'actor_type' => $userId ? 'agent' : 'system',
                'actor_id' => $userId,
                'action' => 'support_cutover_draining_blocked',
                'resource_type' => 'cutover',
                'resource_id' => null,
                'metadata' => [
                    'current_state' => $currentState,
                    'blockers' => $readiness['blockers'],
                ],
            ]);

            return [
                'success' => false,
                'message' => 'Transition to draining mode blocked: Critical Support readiness checks failed.',
                'blockers' => $readiness['blockers'],
                'state' => self::STATE_LEGACY,
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
     * Perform final delta migration, verify parity, recheck readiness, and activate Support mode.
     *
     * Transition rules:
     * - draining -> support: ALLOWED (with verified delta + readiness gate)
     * - support -> support: IDEMPOTENT (ALLOWED)
     * - legacy -> support: FORBIDDEN (must enter draining first)
     */
    public static function activateSupport(?int $userId = null, array $options = []): array
    {
        $currentState = self::getState();

        // 1. Idempotent check if already in support mode
        if ($currentState === self::STATE_SUPPORT) {
            return [
                'success' => true,
                'message' => 'Support domain is already active and canonical.',
                'state' => self::STATE_SUPPORT,
                'is_already_active' => true,
            ];
        }

        // 2. Reject direct transition from legacy (must be draining first)
        if ($currentState === self::STATE_LEGACY) {
            return [
                'success' => false,
                'message' => 'Cannot activate Support directly from legacy mode. System must first enter draining mode after preflight verification.',
                'state' => self::STATE_LEGACY,
            ];
        }

        // 3. Re-verify current readiness immediately before activation (Pre-Migration Gate)
        $readinessService = new SupportReadinessService();
        $readiness = $readinessService->getReadiness();

        if (!$readiness['ready'] || !empty($readiness['blockers'])) {
            SupportAuditLog::log([
                'actor_type' => $userId ? 'agent' : 'system',
                'actor_id' => $userId,
                'action' => 'support_cutover_activation_blocked',
                'resource_type' => 'cutover',
                'resource_id' => null,
                'metadata' => [
                    'current_state' => $currentState,
                    'phase' => 'pre_migration_readiness',
                    'blockers' => $readiness['blockers'],
                ],
            ]);

            return [
                'success' => false,
                'message' => 'Support activation blocked: Critical readiness checks failed.',
                'blockers' => $readiness['blockers'],
                'state' => self::STATE_DRAINING,
            ];
        }

        // 4. Execute Final Delta Migration
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

        // 5. Execute Migration Verification Gate
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

        // 6. Re-verify critical readiness after final delta and verification (Post-Migration Final Gate)
        $finalReadiness = $readinessService->getReadiness();
        if (!$finalReadiness['ready'] || !empty($finalReadiness['blockers'])) {
            SupportAuditLog::log([
                'actor_type' => $userId ? 'agent' : 'system',
                'actor_id' => $userId,
                'action' => 'support_cutover_activation_blocked',
                'resource_type' => 'cutover',
                'resource_id' => null,
                'metadata' => [
                    'current_state' => $currentState,
                    'phase' => 'post_migration_readiness',
                    'migration_run_id' => $migrationResult['run_id'],
                    'verification_passed' => $verificationResult['passed'],
                    'blockers' => $finalReadiness['blockers'],
                ],
            ]);

            return [
                'success' => false,
                'message' => 'Support activation blocked: Final post-migration readiness checks failed.',
                'blockers' => $finalReadiness['blockers'],
                'state' => self::STATE_DRAINING,
                'migration' => $migrationResult,
                'verification' => $verificationResult,
            ];
        }

        // 7. Activate Support Mode
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
     * Centralized, state-independent evaluation of post-activation activity.
     * Evaluates whether reverting to legacy would strand or destroy post-cutover work.
     *
     * @return array<string, mixed>
     */
    public static function evaluateRollbackSafety(): array
    {
        $activatedAtStr = null;
        try {
            $activatedAtStr = Settings::group('support')->get('support_activated_at');
        } catch (\Throwable $e) {}

        $blockers = [];
        $counts = [
            'conversations' => 0,
            'messages' => 0,
            'tickets' => 0,
            'voice_sessions' => 0,
            'assignments' => 0,
            'feedback' => 0,
            'domain_audit_actions' => 0,
        ];

        if ($activatedAtStr) {
            $activatedAt = Carbon::parse($activatedAtStr);

            try {
                $counts['conversations'] = SupportConversation::where('created_at', '>=', $activatedAt)->count();
                $counts['messages'] = SupportMessage::where('created_at', '>=', $activatedAt)->count();
                $counts['tickets'] = SupportTicket::where('created_at', '>=', $activatedAt)->count();
                $counts['voice_sessions'] = SupportVoiceSession::where('created_at', '>=', $activatedAt)->count();
                $counts['assignments'] = SupportAssignment::where('created_at', '>=', $activatedAt)->count();
                $counts['feedback'] = SupportFeedback::where('created_at', '>=', $activatedAt)->count();

                // Check domain audit actions (excluding internal cutover and migration administrative lifecycle events)
                $counts['domain_audit_actions'] = SupportAuditLog::where('created_at', '>=', $activatedAt)
                    ->where('action', 'not like', 'legacy_migration_%')
                    ->where('action', 'not like', 'legacy_chat_%')
                    ->where('action', 'not like', 'support_cutover_%')
                    ->count();
            } catch (\Throwable $e) {
                Log::warning('evaluateRollbackSafety query warning: ' . $e->getMessage());
            }

            if ($counts['conversations'] > 0) {
                $blockers[] = "{$counts['conversations']} Support conversations created post-cutover.";
            }
            if ($counts['messages'] > 0) {
                $blockers[] = "{$counts['messages']} Support messages created post-cutover.";
            }
            if ($counts['tickets'] > 0) {
                $blockers[] = "{$counts['tickets']} Support tickets created post-cutover.";
            }
            if ($counts['voice_sessions'] > 0) {
                $blockers[] = "{$counts['voice_sessions']} Support voice sessions created post-cutover.";
            }
            if ($counts['assignments'] > 0) {
                $blockers[] = "{$counts['assignments']} Support agent assignments created post-cutover.";
            }
            if ($counts['feedback'] > 0) {
                $blockers[] = "{$counts['feedback']} Support feedback records created post-cutover.";
            }
            if ($counts['domain_audit_actions'] > 0) {
                $blockers[] = "{$counts['domain_audit_actions']} Support operational actions executed post-cutover.";
            }
        }

        return [
            'safe' => empty($blockers),
            'blockers' => $blockers,
            'counts' => $counts,
            'activation_timestamp' => $activatedAtStr,
            'cutover_generation' => Settings::group('support')->get('final_delta_migration_run_id'),
        ];
    }

    /**
     * Revert cutover state with strict, unbypassable rollback guard.
     * Automated rollback always FAILS CLOSED if meaningful post-cutover activity occurred.
     */
    public static function rollback(?int $userId = null): array
    {
        $currentState = self::getState();

        if ($currentState === self::STATE_LEGACY) {
            return [
                'success' => true,
                'message' => 'System is already in legacy mode.',
                'state' => self::STATE_LEGACY,
            ];
        }

        // Centralized safety check evaluating all post-activation domain activity
        $safety = self::evaluateRollbackSafety();

        if (!$safety['safe']) {
            SupportAuditLog::log([
                'actor_type' => $userId ? 'agent' : 'system',
                'actor_id' => $userId,
                'action' => 'support_cutover_rollback_blocked',
                'resource_type' => 'cutover',
                'resource_id' => null,
                'metadata' => [
                    'current_state' => $currentState,
                    'blockers' => $safety['blockers'],
                    'counts' => $safety['counts'],
                    'activation_timestamp' => $safety['activation_timestamp'],
                ],
            ]);

            return [
                'success' => false,
                'message' => 'Rollback blocked: Post-cutover Support domain activity detected. Automated rollback is forbidden to prevent data loss.',
                'blockers' => $safety['blockers'],
                'counts' => $safety['counts'],
                'state' => $currentState,
            ];
        }

        // Safe to rollback state to legacy
        try {
            Settings::group('support')->set('cutover_state', self::STATE_LEGACY);
            Settings::group('support')->set('support_activated_at', null);
            Settings::group('support')->set('cutover_started_at', null);
            Settings::group('support')->set('activated_by', null);
            Settings::group('support')->set('final_delta_migration_run_id', null);
            Settings::group('support')->set('verification_passed', false);
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
            ],
        ]);

        return [
            'success' => true,
            'message' => 'Successfully rolled back cutover state to legacy.',
            'state' => self::STATE_LEGACY,
        ];
    }
}
