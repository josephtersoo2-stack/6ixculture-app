# 6ixCulture AI Support — Phase 11 Legacy Removal Specification

**Repository:** `josephtersoo2-stack/6ixculture-app`  
**Branch:** `main`  
**Phase:** 11 — Verified Legacy Removal  
**Baseline:** Phase 10 final hardening commit `4321f34`  
**Status:** READY FOR GATED EXECUTION

## 1. Scope

Phase 11 removes only the obsolete customer-support prototype after the production-cutover gate passes. It must not remove retained AI/provider/admin AI infrastructure used by the Support runtime or back-office features.

## 2. Approved Baseline

```text
Phase 10 final hardening: 4321f34
Phase 10 tests: 38 passed / 162 assertions
Support suite: 174 passed / 773 assertions
Full project suite: 176 passed / 775 assertions
Frontend build: PASS
Production cutover: NOT EXECUTED in the current Phase 10 report
```

Because the current Phase 10 report still says production cutover was not executed, destructive Phase 11 removal must be self-gating.

## 3. Primary Legacy Targets

Rediscover actual current paths before deletion. Historical targets from the Phase 0 audit include:

```text
app/Http/Controllers/Frontend/ChatController.php
app/Http/Controllers/Admin/AdminChatController.php
app/Services/ChatService.php
app/Models/ChatConversation.php
app/Models/ChatMessage.php
resources/js/components/frontend/chat/LiveChatWidgetComponent.vue
resources/js/components/admin/chat/LiveChatComponent.vue
resources/js/router/modules/liveChatRoutes.js
```

Legacy route families historically include:

```text
/api/chat/*
/api/frontend/chat/*
/api/admin/chat/*
```

Legacy data tables:

```text
chat_conversations
chat_messages
```

## 4. Must Preserve

Do not remove the current Support domain or provider infrastructure:

```text
App\Support\**
SupportConversation / SupportMessage
SupportOrchestrator
SupportCutoverManager / SupportReadinessService
Support policies / tools / knowledge / voice / realtime
Support customer preferences
Phase 9 migration ledger/evidence
```

Also preserve the retained AI/provider subsystem:

```text
AiAgentController
AiController
AiService
AiAgentService
AiAbstract
Gemini / Openrouter / Openai provider implementations
AiAgent
GatewayOption
AiChatHistory
HasAiPrompt
BackendAiSidebarComponent
AiAgent settings UI
```

## 5. Hard Production Removal Gate

Before deleting any legacy source file, route, component, model, setting, or table, verify against the real target environment:

```text
cutover_state = support
verification_passed = true
support_activated_at is present
final_delta_migration_run_id is present
Support readiness is not blocked
actual production cutover has been executed
production smoke tests passed
no unresolved critical incident exists
```

If production cutover has not actually occurred:

```text
DO NOT DELETE LEGACY CODE.
```

Perform only the Phase 11 removal audit/manifest/report and stop.

## 6. Removal Audit

Search the entire repository for:

```text
ChatController
AdminChatController
ChatService
ChatConversation
ChatMessage
chat_conversations
chat_messages
LiveChatWidgetComponent
LiveChatComponent
liveChatRoutes
/api/chat
/api/frontend/chat
/api/admin/chat
site_chat_ai_agent
site_chat_ai_model
```

Search PHP, Vue/JS, router modules, services, stores, seeders, menus, permissions, tests, commands, middleware, jobs, queues, notifications, Blade, config, docs, and migrations.

Classify every hit:

```text
REMOVE
REPLACE REFERENCE
KEEP FOR MIGRATION/AUDIT
KEEP FOR DATA RETENTION
KEEP — NON-LEGACY AI INFRASTRUCTURE
```

Create `docs/AI-SUPPORT-PHASE-11-REMOVAL-MANIFEST.md` before deletion.

## 7. Verified Runtime Removal

Only if the hard gate passes:

- remove obsolete legacy customer/admin chat routes;
- remove obsolete controllers;
- remove ChatService;
- remove ChatConversation/ChatMessage models only after proving no runtime dependency remains;
- remove dead legacy Vue components;
- remove dead legacy router module;
- remove dead imports, store/service code, menu/navigation entries and permissions used only by legacy chat;
- remove obsolete legacy setting reads/writes for `site_chat_ai_agent` / `site_chat_ai_model` if no consumer remains;
- update tests and docs.

## 8. Cutover Middleware

`GateLegacyChatMutationMiddleware` may be removed only when every gated legacy route is removed and repository-wide search proves no other consumer remains. Remove its alias registration only if unused.

## 9. Phase 9 Migration Evidence

Default: **KEEP**.

Preserve:

```text
support_legacy_migration_runs
support_legacy_migration_items
migration audit evidence
migration verification history
```

Do not casually remove migration ledger/evidence needed for audit, verification, retention or incident investigation.

## 10. Legacy Data Tables

Treat `chat_conversations` and `chat_messages` separately from obsolete application code.

Before dropping them require:

1. production cutover completed;
2. final migration parity verified;
3. backup/export/checkpoint recorded;
4. retention requirements reviewed;
5. no application references remain;
6. rollback no longer requires them;
7. explicit table-drop authorization.

If any condition is missing, retain the tables read-only. It is acceptable to remove obsolete runtime code while preserving historical source tables.

If table removal is explicitly authorized later, use a proper Laravel migration and reversible restore plan—never ad hoc SQL.

## 11. Routes and UI After Removal

Legacy prototype endpoint families must no longer appear in `php artisan route:list` when runtime removal is executed.

Canonical Support remains:

```text
/api/v1/support/*
```

Customer frontend must continue mounting `AiSupportWidget`.

Admin support must remain canonical at:

```text
/admin/support
```

Do not remove `BackendAiSidebarComponent` or AI Agent settings.

## 12. Phase 11 Tests

Create `tests/Feature/Support/SupportPhase11LegacyRemovalTest.php` proving at minimum:

1. legacy customer chat routes absent;
2. legacy admin chat routes absent;
3. `/api/v1/support/*` remains;
4. customer Support conversation works;
5. customer messaging works;
6. human handoff works;
7. agent Support API works;
8. customer ownership remains enforced;
9. guest ownership remains enforced;
10. internal notes remain private;
11. policies/tools remain authoritative;
12. current AI provider remains functional;
13. voice capabilities remain;
14. realtime/polling remains;
15. no active PHP references to deleted ChatService/controllers/models;
16. no active Vue imports to deleted legacy components;
17. router no longer references legacy live-chat routes;
18. admin navigation points to `/admin/support`;
19. customer shell mounts `AiSupportWidget`;
20. Phase 9 migration evidence remains intact;
21. legacy tables retained unless explicit table-drop gate passed;
22. unrelated ecommerce functionality does not regress.

## 13. Static Verification

After cleanup repeat repository-wide searches for all legacy identifiers. Any remaining hit must be documented and classified. No active runtime reference may remain.

Allowed remaining hits may include historical documentation, migration history, archival audit code, or tests verifying absence.

## 14. Regression

Run:

```bash
php artisan test tests/Feature/Support/SupportPhase11LegacyRemovalTest.php
php artisan test --filter=Support
php artisan test
php artisan route:list
npm.cmd run build
```

Run configured lint/static analysis if available. Zero failures required.

## 15. Documentation

Create/update:

```text
docs/6IXCULTURE_AI_SUPPORT_PHASE_11_SPEC.md
docs/AI-SUPPORT-PHASE-11-REMOVAL-MANIFEST.md
docs/AI-SUPPORT-PHASE-11-REPORT.md
```

Report environment, production gate result, removed/preserved assets, data-table retention decision, residual references, test/build results, and Phase 12 readiness.

## 16. Git Safety

Record the immutable Phase 10 baseline SHA:

```text
4321f34c2a404df2914dd974c97f7c79ca5f4d9b
```

Create a safety tag if workflow permits. Never rewrite history.

## 17. Stop Condition

After verified removal/audit:

```text
no active legacy chat runtime references
canonical Support system passing
migration evidence retained
data-retention decision documented
full tests passing
frontend build passing
Phase 11 report complete
```

STOP. Do not begin Phase 12 automatically.
