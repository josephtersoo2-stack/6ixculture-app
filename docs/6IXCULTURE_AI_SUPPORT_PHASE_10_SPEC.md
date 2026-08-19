# 6ixCulture AI Support — Phase 10 Implementation Specification

**Repository:** `josephtersoo2-stack/6ixculture-app`  
**Branch:** `main`  
**Phase:** 10 — Production Cutover  
**Prerequisites:** Phases 0–9 approved and Phase 9 operational validation passed  
**Baseline:** Phase 9 validation commit `07ff1bb`  
**Status:** DRAFT — READY FOR IMPLEMENTATION

## 1. Authoritative Baseline

The master implementation plan defines Phase 10 as switching the old customer chat to the new Support chat and the old admin chat to the new Support console, followed by regression testing. Phase 11—not Phase 10—is where obsolete legacy files are removed.

Current approved Phase 9 baseline:

```text
Phase 9 operational validation: PASSED
Commit: 07ff1bb
Phase 9 tests: 19 passed / 111 assertions
Support suite: 136 passed / 611 assertions
Full project: 138 passed / 613 assertions
Frontend build: PASS
```

The local validation database contained zero surviving legacy conversations/messages. This must not be treated as a production assumption.

## 2. Current Repository Reality

The storefront already mounts `AiSupportWidget` in `resources/js/components/DefaultComponent.vue`.

The admin router already exposes:

```text
/admin/support
/admin/support/governance
```

through `resources/js/router/modules/supportRoutes.js`.

The new API exists under `/api/v1/support/*`.

Legacy chat routes, controllers, services, models, tables, and Vue files still exist and must remain preserved through Phase 10.

## 3. Goal

Make the enterprise Support domain the canonical runtime for all new customer and human-support traffic.

```text
Customer
  ↓
AiSupportWidget
  ↓
/api/v1/support/*
  ↓
SupportConversation
  ↓
SupportOrchestrator
```

and:

```text
Agent
  ↓
/admin/support
  ↓
Agent Support API
  ↓
SupportConversation
```

Legacy chat becomes preserved fallback/historical infrastructure, not a competing normal production write path.

## 4. Non-Negotiable Principle

**One canonical write path at a time.**

Do not create uncontrolled dual-write between:

```text
chat_conversations / chat_messages
```

and:

```text
support_conversations / support_messages
```

After cutover, normal customer and agent traffic must write only to the Support domain.

## 5. In Scope

Implement only:

- environment cutover preflight;
- final legacy delta migration;
- final migration verification;
- server-authoritative cutover state;
- customer UI canonicalization;
- admin Support Center canonicalization;
- legacy route gating;
- legacy write shutdown after final delta;
- cutover smoke tests;
- security regression;
- realtime/voice/multilingual verification;
- health/readiness indicators;
- guarded emergency rollback;
- cutover runbook;
- tests;
- documentation.

## 6. Out of Scope

Do NOT:

- delete `ChatController`;
- delete `AdminChatController`;
- delete `ChatService`;
- delete `ChatConversation`;
- delete `ChatMessage`;
- drop `chat_conversations`;
- drop `chat_messages`;
- delete legacy Vue files;
- remove legacy migrations;
- start Phase 11;
- redesign the Support domain;
- add unrelated commerce features;
- add omnichannel integrations;
- remove Phase 9 migration tooling.

## 7. Server-Authoritative Cutover State

Introduce or reuse a persistent server-side cutover state using existing application settings/configuration conventions.

Logical states:

```text
legacy
draining
support
```

### legacy
Legacy chat may operate; Support remains available for validation.

### draining
Legacy new writes are blocked. Existing data may remain readable while final delta migration and verification run.

### support
Support domain is canonical. Legacy normal writes remain blocked. Legacy code/data remain preserved.

Do not let Vue, localStorage, or browser state become authoritative.

## 8. Allowed Transition

```text
legacy
  ↓
preflight
  ↓
draining
  ↓
final delta migration
  ↓
verification
  ↓
support
```

Do not allow `legacy → support` without verification.

State changes must be authorized, audit logged, explicit, and fail-closed.

## 9. Pre-Cutover Gate

Before entering `draining`, run:

```bash
php artisan support:legacy-chat-audit --json
php artisan support:migrate-legacy-chat --dry-run --migrate-config
php artisan test --filter=Support
php artisan test
npm.cmd run build
```

Record actual environment counts and anomalies.

Do not reuse prior local zero counts.

## 10. Draining Mode

In `draining`:

- block new legacy customer messages;
- block new legacy customer conversation writes;
- block legacy request-human mutations;
- block legacy agent replies;
- block legacy status mutations;
- block legacy destructive delete;
- keep source data stable for final delta.

Use safe application responses without leaking implementation details.

## 11. Final Delta Migration

In `draining`, execute:

```bash
php artisan support:migrate-legacy-chat --apply --chunk=<appropriate>
php artisan support:verify-legacy-chat-migration --run=<RUN_ID> --json
```

Require:

```text
verification passed
unexplained mismatches = 0
unresolved conflicts = 0
migration failures = 0
duplicate target records = 0
```

If verification fails, do not activate Support mode.

## 12. Customer Cutover

Verify:

- `AiSupportWidget` is the only active normal storefront support widget;
- no active customer component calls legacy chat endpoints;
- customer Support API calls use `/api/v1/support/*`;
- authenticated identity comes from Laravel;
- guests use the approved guest-token flow;
- structured messages render;
- product/order cards work;
- handoff works;
- realtime and polling fallback work;
- voice works according to provider capability;
- language preference works.

Keep old widget files for Phase 11.

## 13. Admin Cutover

Make `/admin/support` the canonical operational support route.

Verify staff navigation points there and the old live-chat page is not the normal operational destination.

Verify:

- queue;
- conversation workspace;
- assignment;
- agent reply;
- internal notes;
- Customer 360;
- order/ticket panels;
- AI summary;
- translation;
- governance route.

Keep old admin chat files for Phase 11.

## 14. API Canonicalization

After Support activation:

```text
/api/v1/support/*
```

is the canonical support API.

No active Vue service should use legacy chat endpoints for normal production support traffic.

Do not create a second Support API namespace.

## 15. Conversation Authority

After cutover:

```text
SupportConversation
SupportMessage
```

are authoritative for all new support traffic.

Legacy `ChatConversation`/`ChatMessage` are historical/fallback-only.

Do not reverse-write new Support data into legacy tables.

## 16. Emergency Rollback Guard

A rollback from `support` to `legacy` must not be blindly allowed after meaningful Support activity.

Before rollback inspect post-cutover:

```text
new Support conversations
customer messages
agent replies
tickets
assignments
voice sessions
critical actions
```

If meaningful post-cutover activity exists:

```text
FAIL CLOSED
REQUIRE MANUAL INCIDENT / DATA RECONCILIATION PLAN
```

If no post-cutover activity exists, a controlled rollback may be allowed.

Audit all rollback attempts.

## 17. Cutover Marker

Persist:

```text
cutover state
cutover started_at
support activated_at
activated_by
baseline migration run
final delta migration run
pre-cutover counts
verification result
```

Do not expose internal cutover metadata to customers.

## 18. Guest Security

Revalidate:

- correct guest token required;
- authenticated users cannot claim arbitrary guest history;
- wrong token fails closed;
- guest realtime channel authorization remains intact;
- legacy session tokens are not modern ownership credentials;
- migration metadata/session hashes are not exposed.

## 19. Customer Authorization

Revalidate:

```text
Customer A cannot read Customer B conversation
Customer A cannot read Customer B order
Customer A cannot execute Customer B action/tool
```

Identity remains server-derived.

## 20. Agent Authorization

Revalidate:

```text
agent
 ↓
authorized scope
 ↓
department / assignment
 ↓
conversation
```

Verify scoped queues, conversation access, transfer rules, assignment eligibility, Customer 360, orders/tickets, and internal-note isolation.

## 21. Governance Authority

Revalidate:

- published-only knowledge;
- policy activation safety;
- immutable critical-action safeguards;
- tool permissions;
- Admin/Manager governance scope;
- audit redaction.

Cutover must not bypass governance.

## 22. Voice & Multilingual

Verify:

```text
STT
language detection
SupportConversation
SupportOrchestrator
TTS
```

for `en`, `yo`, `ig`, `ha` according to provider capabilities.

Verify persistent authenticated preferences, guest isolation, capability reporting, text fallback, and low-confidence action safety.

## 23. Realtime

Verify:

- customer channels;
- guest channels;
- agent conversation channels;
- department queues;
- elevated global queue;
- internal-note isolation;
- polling fallback.

Realtime is transport, not source of truth.

## 24. Legacy Admin Mutation Safety

Legacy admin reply/status/delete operations must not continue writing to legacy tables in `draining` or `support`.

Do not delete the controller in Phase 10.

## 25. Health / Readiness

Create or extend safe internal readiness output for:

```text
cutover state
support tables
AI provider configured yes/no
migration verification
realtime/fallback
voice/fallback
knowledge/policy readiness
```

Never expose credentials or hidden prompts.

## 26. Monitoring

Track safe operational signals:

```text
support API failures
AI provider failures
tool failures
conversation creation failures
message send failures
guest auth failures
agent authorization failures
realtime failures
polling fallback
voice failures
handoff failures
queue depth
```

Use existing logging/observability conventions.

## 27. Smoke Matrix

### Guest
```text
open widget
create conversation
send text
receive AI response
product search
request human
```

### Authenticated customer
```text
conversation
order lookup
tracking
language
voice
handoff
```

### Agent
```text
queue
open
assign
reply
internal note
Customer 360
orders
ticket
summary
translation
```

### Governance Admin
```text
knowledge
policy
tool permissions
audit
```

## 28. Cutover Command

Prefer one controlled command/service.

Logical interface:

```text
php artisan support:cutover --status
php artisan support:cutover --preflight
php artisan support:cutover --enter-draining
php artisan support:cutover --activate-support
php artisan support:cutover --rollback
```

Exact naming may follow project conventions.

The command must refuse unsafe transitions.

## 29. Tests

Add a Phase 10 suite covering:

- state persistence;
- transition authorization;
- `legacy → draining`;
- `draining → support`;
- activation requires migration verification;
- legacy mutations blocked in draining/support;
- Support API remains functional;
- old files/tables remain present;
- customer UI uses Support path;
- admin route uses Support Center;
- rollback safe path;
- rollback blocked after post-cutover activity;
- audit transitions;
- no secret leakage;
- customer/guest/agent isolation.

Run:

```bash
php artisan test tests/Feature/Support/<Phase10TestFile>.php
php artisan test --filter=Support
php artisan test
php artisan route:list
npm.cmd run build
```

Zero failures required.

## 30. Runbook

Create:

```text
docs/AI-SUPPORT-PHASE-10-CUTOVER-RUNBOOK.md
```

Document:

1. backup/checkpoint;
2. preflight;
3. legacy audit;
4. enter draining;
5. final delta migration;
6. verification;
7. activate Support;
8. smoke tests;
9. monitor;
10. rollback safety decision;
11. post-cutover verification;
12. Phase 11 gate.

No secrets.

## 31. Report

Create:

```text
docs/AI-SUPPORT-PHASE-10-REPORT.md
```

Include:

- implementation summary;
- cutover state;
- active customer UI;
- active admin UI;
- active API;
- legacy route gating;
- final migration results;
- smoke results;
- rollback guard;
- security verification;
- tests;
- exact counts;
- frontend build;
- environment used;
- production status;
- known limitations;
- Phase 11 recommendation.

## 32. Production Truthfulness

Differentiate:

```text
code readiness
local/staging cutover rehearsal
actual production cutover
```

If only local/staging was used, report:

```text
PHASE 10 IMPLEMENTATION: COMPLETE
CUTOVER REHEARSAL: PASSED
PRODUCTION CUTOVER: NOT EXECUTED
PRODUCTION READINESS: READY FOR REVIEW
```

Do not claim production cutover unless it actually happened in production.

## 33. Completion Criteria

Phase 10 implementation is complete only when:

- Support can be canonical for customers;
- Support Center can be canonical for agents;
- legacy writes can be drained;
- final delta is enforced before activation;
- verification is enforced;
- split-brain writes are prevented;
- rollback is guarded;
- security remains intact;
- legacy code/tables remain preserved;
- smoke tests pass;
- full regression passes;
- frontend build passes;
- runbook exists;
- report exists.

## 34. Stop Condition

After Phase 10 implementation/rehearsal/report:

# STOP.

Do not begin Phase 11.

Do not delete legacy code.

Do not drop legacy tables.

Do not remove Phase 9 migration tooling.
