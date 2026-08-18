# 6ixCulture AI Support — Phase 9 Implementation Specification

**Repository:** `josephtersoo2-stack/6ixculture-app`  
**Branch:** `main`  
**Phase:** 9 — Legacy Data Migration  
**Prerequisites:** Phases 0–8 approved  
**Baseline:** Phase 8 final hardening commit `6ccbcf4`  
**Status:** DRAFT — READY FOR IMPLEMENTATION

---

## 1. Authoritative Baseline

The master implementation plan defines Phase 9 as:

```text
Phase 9 — Migration

Migrate useful existing chat history and configuration.

Do not delete legacy code yet.
```

That narrow instruction must be interpreted using the architecture and security boundaries established in Phases 0–8.

The project is already operating with the new Support domain:

```text
Customer / Agent / Governance UI
            ↓
      Laravel Support API
            ↓
     SupportConversation
            ↓
       SupportMessage
            ↓
   SupportOrchestrator
            ↓
Knowledge / Policy / Tools / Voice / Realtime
```

Phase 9 does **not** redesign that system.

Phase 9 migrates useful legacy chat data into the existing Support domain in a controlled, auditable, idempotent and reversible way.

---

## 2. Legacy Source System

The legacy/prototype customer chat remains present and operational.

Primary legacy source files:

```text
app/Http/Controllers/Frontend/ChatController.php
app/Http/Controllers/Admin/AdminChatController.php
app/Services/ChatService.php
app/Models/ChatConversation.php
app/Models/ChatMessage.php
database/migrations/2026_08_13_000001_create_live_chats_table.php
```

Primary legacy tables:

```text
chat_conversations
chat_messages
```

Legacy conversation fields:

```text
id
session_token
user_id nullable
user_name nullable
user_email nullable
user_phone nullable
status                  ai | human | closed
ip_address nullable
last_message_at nullable
created_at
updated_at
```

Legacy message fields:

```text
id
conversation_id
sender_type             user | ai | agent | admin
sender_id nullable
message
is_read
created_at
updated_at
```

The legacy service also performs automatic cleanup of legacy chat records older than 180 days. Phase 9 must therefore report the actual available source dataset and must not claim historical completeness beyond the rows that still exist.

---

## 3. Target Domain

Reuse the existing Phase 1–8 Support domain.

Primary target models:

```text
App\Support\Models\SupportConversation
App\Support\Models\SupportMessage
```

Relevant target conversation fields include:

```text
public_id
customer_id nullable
guest_session_id nullable
status
mode
priority
language
channel
department_id nullable
assigned_agent_id nullable
assigned_at nullable
first_response_at nullable
resolved_at nullable
closed_at nullable
last_message_at nullable
last_customer_message_at nullable
last_agent_message_at nullable
ai_active
human_requested
escalation_reason nullable
metadata nullable
created_at
updated_at
```

Relevant target message fields include:

```text
conversation_id
sender_type
sender_id nullable
message_type
content
structured_payload nullable
language nullable
is_internal
is_read
tool_call_id nullable
reply_to_id nullable
tokens_used
latency_ms
metadata nullable
created_at
updated_at
```

Do not create a second conversation/message domain.

---

## 4. Goal

Implement an enterprise-grade migration layer that can safely transform the remaining useful legacy chat history and compatible configuration into the new Support domain.

Target flow:

```text
Legacy Chat Tables / Settings
            ↓
       Preflight Audit
            ↓
    Deterministic Mapping
            ↓
     Migration Run Ledger
            ↓
   Idempotent Chunked Import
            ↓
 SupportConversation / Message
            ↓
       Verification Pass
            ↓
    Migration Report / Checksums
```

The migration must be:

- additive;
- non-destructive to legacy data;
- repeatable;
- idempotent;
- chunked;
- resumable;
- auditable;
- reversible where safe;
- fail-closed on ambiguous ownership or mapping conflicts;
- safe for later Phase 10 cutover.

---

## 5. Non-Negotiable Phase 9 Invariant

**Replace first, remove second.**

Phase 9 must not delete, rename, disable, truncate or drop:

```text
ChatController
AdminChatController
ChatService
ChatConversation
ChatMessage
chat_conversations
chat_messages
legacy chat routes
legacy chat Vue components
legacy chat migration files
```

Legacy chat continues to exist as the rollback/source-of-truth path until Phase 10 cutover is explicitly authorized.

---

## 6. In Scope

Implement only:

- legacy chat data inventory;
- migration preflight audit;
- migration run tracking;
- deterministic conversation mapping;
- deterministic message mapping;
- preservation of source timestamps and message order;
- user/customer identity mapping;
- guest/session continuity mapping;
- status/mode mapping;
- sender mapping;
- safe legacy contact metadata handling;
- idempotent import;
- resumable/chunked execution;
- delta/catch-up support for rows added after an earlier run;
- safe configuration discovery and selective migration;
- migration verification;
- migration rollback tooling for migration-owned records where safe;
- audit logs;
- automated tests;
- documentation.

---

## 7. Explicitly Out of Scope

Do NOT implement:

- production route cutover;
- removal of legacy routes;
- removal of legacy Vue components;
- deletion of legacy tables;
- deprecation/removal of legacy models/controllers/services;
- production traffic switch;
- Phase 10 routing changes;
- Phase 11 legacy removal;
- new AI tools;
- new commerce mutations;
- new voice providers;
- new realtime architecture;
- new governance architecture;
- migration of prototype prompt text into approved policies/knowledge automatically;
- copying API keys, passwords, tokens or provider credentials into new settings;
- destructive data cleanup.

---

## 8. Read the Repository Before Coding

Before implementation, inspect the actual current code for:

```text
app/Models/ChatConversation.php
app/Models/ChatMessage.php
app/Services/ChatService.php
app/Http/Controllers/Frontend/ChatController.php
app/Http/Controllers/Admin/AdminChatController.php

app/Support/Models/SupportConversation.php
app/Support/Models/SupportMessage.php
app/Support/Models/SupportAuditLog.php
app/Support/Enums/*
app/Support/Services/*

app/Models/AiAgent.php
app/Models/GatewayOption.php
app/Support/Services/AiProviderFactory.php

routes/api.php
routes/channels.php
resources/js/**
database/migrations/**
tests/**
```

Also read all Phase 0–8 support specifications and reports.

The current codebase is authoritative where an old planning document differs from implemented reality.

---

## 9. Mandatory Preflight Audit

Create a read-only preflight auditor before writing migration data.

The audit must detect and report:

```text
legacy table existence
conversation count
message count
oldest conversation timestamp
newest conversation timestamp
oldest message timestamp
newest message timestamp
conversation counts by status
message counts by sender_type
authenticated conversation count
guest conversation count
conversations with missing users
orphan messages
duplicate session tokens
blank/invalid session tokens
unknown statuses
unknown sender types
null message content
message ordering anomalies
legacy settings detected
legacy configuration conflicts
already-migrated source rows
unresolved migration conflicts
```

Do not include secrets in the audit output.

The preflight command must be safe to run repeatedly.

Recommended command:

```bash
php artisan support:legacy-chat-audit
```

Allow machine-readable output where practical:

```bash
php artisan support:legacy-chat-audit --json
```

---

## 10. Migration Run Ledger

Do not rely only on target metadata to determine whether a row has been migrated.

Create explicit migration tracking.

Recommended logical tables/models:

```text
support_legacy_migration_runs
support_legacy_migration_items
```

Exact naming may follow repository conventions.

### Migration run fields

Recommended:

```text
id
public_id
source                 legacy_chat
mode                   audit | dry_run | apply | rollback | verify
status                 pending | running | completed | partial | failed | rolled_back
started_at
completed_at nullable
source_counts json nullable
result_counts json nullable
error_counts json nullable
checksum nullable
metadata json nullable
created_at
updated_at
```

### Migration item fields

Recommended:

```text
id
migration_run_id
source_table
source_id
target_table nullable
target_id nullable
source_checksum
state                  migrated | skipped | failed | conflict | rolled_back
migrated_at nullable
last_verified_at nullable
metadata json nullable
created_at
updated_at
```

Required unique invariant:

```text
(source_table, source_id)
```

or another equivalently strong source identity key.

Do not create duplicate Support records for the same legacy source row.

---

## 11. Source Checksums

Compute a stable checksum from the normalized source fields used by the migration.

Examples:

```text
chat_conversations:
id, session_token, user_id, user_name, user_email, user_phone,
status, ip_address, last_message_at, created_at, updated_at

chat_messages:
id, conversation_id, sender_type, sender_id, message,
is_read, created_at, updated_at
```

Use checksums to detect:

- unchanged rows;
- source rows modified after an earlier migration;
- conflicts between source changes and target changes.

A repeated run must not silently overwrite a Support record that has been changed independently after migration.

---

## 12. Idempotency

Phase 9 must be safe to execute multiple times.

Rules:

```text
same unchanged source row
    ↓
no duplicate target row

same changed source row
    ↓
controlled reconciliation

source changed + target independently changed
    ↓
CONFLICT
    ↓
no silent overwrite
```

A migration retry after failure must resume safely.

Do not use `truncate()` or “delete and re-import everything.”

---

## 13. Transaction Strategy

Do not wrap the entire dataset in one giant transaction.

Use bounded transactions per conversation or small chunk.

Recommended:

```text
chunk legacy conversations
  ↓
for each conversation
  begin transaction
    create/reconcile target conversation
    migrate ordered messages
    write migration links
    update aggregate timestamps
  commit
```

One bad conversation must not destroy the whole migration run.

Failures must be recorded and the run may finish as `partial` rather than pretending success.

---

## 14. Chunking and Resume

Support bounded processing.

Recommended options:

```bash
--chunk=100
--from-id=1
--to-id=5000
--only-status=closed
--resume=<run-public-id>
```

Exact CLI syntax may be adapted to Laravel conventions.

Never load the whole chat history into memory at once.

---

## 15. Dry Run

The migration must support a true dry-run mode.

Recommended:

```bash
php artisan support:migrate-legacy-chat --dry-run
```

Dry run must:

- execute discovery and mapping logic;
- calculate counts and conflicts;
- validate target prerequisites;
- report what would be created/updated/skipped;
- write no target Support conversations/messages;
- write no configuration changes;
- expose no secrets.

---

## 16. Explicit Apply

Do not migrate automatically from:

- application boot;
- HTTP request lifecycle;
- model boot hooks;
- queue worker startup;
- ordinary database migration execution.

Actual import must require an explicit operator action.

Recommended:

```bash
php artisan support:migrate-legacy-chat --apply
```

If `--apply` is not present, default to dry-run or refuse destructive execution.

---

## 17. Conversation Identity Mapping

### Authenticated legacy conversation

If legacy `user_id` references a valid current user:

```text
legacy user_id
   ↓
SupportConversation.customer_id
```

Do not trust `user_name`, `user_email` or `user_phone` to establish ownership when a valid user relation exists.

### Missing/deleted user

If legacy `user_id` is non-null but the user no longer exists:

```text
customer_id = null
```

Record the broken reference safely in migration metadata.

Do not assign the conversation to another user by email/name guessing.

### Guest legacy conversation

If `user_id` is null:

```text
SupportConversation.customer_id = null
```

Preserve guest continuity using a migration-safe guest identifier derived from the legacy session token.

Do not expose the raw legacy session token to clients after migration unless the existing guest authorization architecture explicitly requires and securely supports it.

---

## 18. Guest Session Mapping

The legacy session token may have been sent to the browser and should not automatically become a new privileged bearer credential.

Preferred migration rule:

```text
legacy session_token
   ↓
server-side deterministic migration mapping
   ↓
new guest_session_id / metadata reference
```

If the target guest token is used for authorization, generate a secure target token rather than blindly reusing a legacy browser token.

Preserve the original legacy session identity only as an internal migration reference/hash where useful.

Never log raw guest tokens.

---

## 19. Conversation Status / Mode Mapping

Legacy statuses are:

```text
ai
human
closed
```

Target statuses are richer.

Use deterministic mapping based on both the legacy status and message history.

### Legacy `ai`

Default semantic mapping:

```text
status            ai_active
mode              ai
ai_active         true
human_requested   false
```

### Legacy `human`

Inspect whether a legacy human agent/admin reply exists.

If no agent/admin reply exists:

```text
status            queued
mode              human
ai_active         false
human_requested   true
```

If an agent/admin reply exists:

```text
status            human_active
mode              human
ai_active         false
human_requested   true
```

### Legacy `closed`

```text
status            closed
ai_active         false
closed_at         best available terminal timestamp
```

Mode should preserve whether the conversation was AI-only or human-handled based on its message history.

### Unknown status

Do not guess.

Mark the item as a migration conflict or use a documented safe fallback only if the Phase 9 report explicitly records it.

---

## 20. Coexistence Safety for Active Legacy Conversations

Phase 9 occurs before Phase 10 production cutover.

Therefore the migration must not create a split-brain customer-support situation where the legacy customer is still talking in the old conversation while agents respond to a separate active copy in the new system.

Implement one of these safe approaches based on actual repository behavior:

### Preferred approach — historical/backfill first

- migrate closed/historical conversations during Phase 9;
- audit and track currently active legacy conversations;
- provide idempotent delta migration tooling;
- defer final activation/continuity of still-active legacy conversations to the Phase 10 cutover window.

### Alternative — shadow import

If active conversations are imported in Phase 9, they must be clearly marked as migration shadow records and must not enter normal customer/agent routing until Phase 10.

Do not silently expose duplicate live threads.

Document the chosen strategy in the Phase 9 report.

---

## 21. Message Sender Mapping

Map legacy sender types:

```text
user     → customer
ai       → ai
agent    → agent
admin    → agent
```

For `agent`/`admin` messages:

- preserve `sender_id` only when it references a valid user;
- otherwise set `sender_id = null` and retain the source sender identity in internal migration metadata.

Unknown sender types must not be guessed into an elevated actor type.

Safe fallback:

```text
sender_type = system
```

only if explicitly documented and tagged as an unknown legacy sender.

---

## 22. Message Mapping

Legacy chat messages are plain text.

Map them as:

```text
message_type        text
content             legacy.message
structured_payload  null
is_internal         false
is_read             legacy.is_read
language            null or effective migrated conversation language
```

Do not reinterpret legacy plain text as product/order/tool payloads.

Do not create synthetic tool calls from old messages.

Do not run old messages back through the LLM merely to “enrich” them.

---

## 23. Internal-Note Boundary

The legacy schema has no dedicated internal-note field.

Therefore:

```text
all migrated legacy messages → is_internal = false
```

unless repository inspection proves a specific legacy sender/message path was truly staff-internal and never customer-visible.

Do not infer internal-note status from content.

---

## 24. Timestamp Preservation

Preserve original historical chronology.

For migrated target records:

```text
created_at ← legacy.created_at
updated_at ← legacy.updated_at where semantically valid
```

Conversation aggregate timestamps should be calculated from migrated source history:

```text
last_message_at
last_customer_message_at
last_agent_message_at
first_response_at where determinable
closed_at where determinable
```

Message ordering must remain stable.

If two messages share identical timestamps, use the legacy message ID as the deterministic tie-breaker.

---

## 25. First Response Calculation

Where possible:

```text
first_response_at = timestamp of first ai/agent/admin message after the first customer message
```

Do not fabricate a first-response timestamp when the source history cannot support it.

---

## 26. Legacy Contact Metadata

Legacy conversations may contain:

```text
user_name
user_email
user_phone
ip_address
```

Rules:

### Authenticated user exists

Use `customer_id` as the authoritative identity.

Do not duplicate current customer identity data unnecessarily into migration metadata.

### Guest / missing user

Preserve only the minimum legacy contact snapshot required for operational history.

Store it in a clearly namespaced migration metadata structure that is never returned by customer-facing API resources.

Example logical metadata:

```json
{
  "legacy_migration": {
    "source": "chat_conversations",
    "source_id": 123,
    "guest_display_name": "...",
    "contact_snapshot": {
      "email": "...",
      "phone": "..."
    }
  }
}
```

If the repository has an encrypted cast/storage convention for support metadata, use it for sensitive contact snapshots.

Do not expose IP addresses in customer-facing responses.

Do not copy unnecessary PII solely for convenience.

---

## 27. Metadata Namespace

Use one consistent migration namespace.

Example:

```text
metadata.legacy_migration
```

Possible fields:

```text
source_table
source_id
source_status
source_session_hash
source_created_at
source_updated_at
migration_run
historical_only
conflict_state
```

Do not mix migration bookkeeping into arbitrary top-level metadata keys.

---

## 28. Orphan and Invalid Data Handling

### Orphan message

If a `chat_messages.conversation_id` has no source conversation:

- do not attach it to an arbitrary SupportConversation;
- mark as orphan;
- record in audit/report;
- skip unless an explicit recovery rule is proven safe.

### Missing user

Do not fail the whole conversation.

Migrate as guest/unlinked history with the broken source relation documented internally.

### Empty message

Do not manufacture content.

Either preserve valid empty records with an explicit migration marker or skip them according to a documented rule.

### Unknown status/sender

Fail closed and record.

---

## 29. Delta / Catch-Up Migration

Because legacy chat remains operational after Phase 9, the migration must support a later catch-up run.

A second run must detect:

- new conversations;
- new messages added to an already migrated conversation;
- legacy status changes;
- target conflicts.

Recommended flow:

```text
Initial historical migration
        ↓
legacy system remains live
        ↓
new legacy rows arrive
        ↓
Phase 10 freeze/cutover window
        ↓
final delta migration
        ↓
verify parity
        ↓
route cutover
```

Phase 9 must build the tooling required for that final delta.

Do not implement the Phase 10 traffic freeze/switch itself.

---

## 30. Migration Conflict Policy

A conflict exists when:

```text
source checksum changed
AND
target record has independently changed since last migration
```

Conflict behavior:

- do not silently overwrite target;
- mark migration item `conflict`;
- record safe diff metadata without secrets;
- include conflict count in run summary;
- return a non-success/partial status when unresolved conflicts exist.

---

## 31. Configuration Migration

The master plan explicitly includes useful legacy configuration.

The legacy ChatService currently references settings such as:

```text
site_chat_ai_agent
site_chat_ai_model
```

The current Support provider factory uses the current AI-agent/provider infrastructure and resolves from current support/global configuration such as:

```text
site_default_ai_agent
AiAgent
GatewayOption
```

Phase 9 must first inventory actual current settings and destination configuration.

### Rules

1. **Do not copy credentials.**
2. **Do not duplicate `AiAgent` / `GatewayOption` provider secrets.**
3. **Do not overwrite a current Support configuration that is already explicitly configured.**
4. If a legacy selected provider can be mapped to an existing `AiAgent` and the new authoritative provider setting is unset, Phase 9 may migrate the non-secret provider selection.
5. Migrate model selection only if the current Support provider layer has a clear authoritative destination key and that destination is unset.
6. If no safe target exists, record the legacy model/config value in the migration report rather than inventing a new parallel settings system.
7. Do not translate the hard-coded prototype system prompt into Phase 7 policy/knowledge automatically.
8. Do not overwrite published knowledge or active governance policies.

Recommended explicit flag:

```bash
php artisan support:migrate-legacy-chat --apply --migrate-config
```

Configuration migration should be independently testable.

---

## 32. Prototype Prompt Is Not Approved Knowledge

`ChatService::buildSystemPrompt()` contains prototype store information and security text.

Do not automatically migrate that prompt into:

```text
SupportKnowledgeArticle
SupportPolicy
SupportAIToolPermission
```

Phase 7 established explicit governance lifecycles.

Any knowledge/policy content must continue to pass those governance workflows.

At most, Phase 9 may inventory prototype prompt facts as “manual review candidates” in its report.

---

## 33. Credentials and Secrets

Never migrate or log:

```text
OPENROUTER_API_KEY
OpenAI keys
Gemini keys
GatewayOption secret values
Authorization headers
cookies
passwords
tokens
provider credentials
```

Provider credentials remain in the existing approved provider infrastructure.

Use the existing audit-redaction service where migration logs touch configuration or metadata.

---

## 34. Audit Logging

Use `SupportAuditLog` or a migration-specific audit mechanism consistent with the Support governance architecture.

Record safe events such as:

```text
legacy_migration_started
legacy_migration_completed
legacy_migration_partial
legacy_migration_failed
legacy_conversation_migrated
legacy_message_migrated
legacy_item_skipped
legacy_item_conflict
legacy_config_migrated
legacy_rollback_started
legacy_rollback_completed
```

Do not log raw message bodies unnecessarily.

Do not log raw session tokens.

Do not log credentials.

---

## 35. Verification Command

Create a verification pass separate from import.

Recommended:

```bash
php artisan support:verify-legacy-chat-migration
```

Verification must compare source and target using migration links/checksums.

Verify at minimum:

```text
mapped conversation count
mapped message count
unmapped source rows
orphan source rows
duplicate target mappings
checksum mismatches
message ordering
sender mapping
status mapping
customer ownership mapping
timestamp preservation
config migration state
conflicts
```

Return a failing exit code when required parity is not achieved.

---

## 36. Migration Metrics

The Phase 9 report must include actual numbers from the environment where commands were executed.

At minimum:

```text
legacy conversations discovered
legacy messages discovered
conversations migrated
messages migrated
conversations skipped
messages skipped
orphan messages
missing-user conversations
conflicts
failed records
configuration keys discovered
configuration keys migrated
configuration keys intentionally not migrated
verification mismatches
```

Do not invent counts.

---

## 37. Rollback / Reversibility

Legacy source data remains untouched, which is the primary rollback path.

Provide a controlled rollback mechanism for migration-created Support records where safe.

Recommended:

```bash
php artisan support:rollback-legacy-chat-migration {run_public_id}
```

Rollback may delete/revert only records proven to be owned by that migration and not independently modified by the new Support system.

Before rollback, verify:

```text
target record linked to migration run
target checksum/state still migration-owned
no new non-migrated messages exist
no ticket/assignment/voice/feedback/agent activity depends on record
no later migration run superseded it
```

If any condition fails:

```text
ABORT / CONFLICT
```

Never delete legacy source rows during rollback.

---

## 38. No Broadcast / AI Side Effects During Import

Migration must not accidentally:

- invoke `SupportOrchestrator`;
- send AI responses;
- execute tools;
- broadcast customer messages as live events;
- trigger TTS/STT;
- request human agents;
- send notifications;
- create external side effects.

Use quiet/model-safe persistence patterns where required.

If model events are essential for invariants, explicitly suppress only side-effecting listeners while preserving data consistency.

Document the chosen approach.

---

## 39. No Customer Authorization Regression

Migrated conversations must still obey all existing security boundaries:

```text
authenticated customer ownership
guest-token isolation
agent department/assignment scope
internal-note isolation
realtime channel authorization
policy engine authority
audit redaction
```

Migration metadata must never become a bypass path.

---

## 40. Legacy Active Data and Customer IDs

Do not link a guest legacy conversation to an authenticated customer based only on:

```text
name
email
phone
session token supplied later by a different user
```

Only a valid existing `user_id` relationship is authoritative during historical migration.

Any future guest-to-customer ownership transition remains governed by the hardened Phase 4 proof-of-possession flow.

---

## 41. Migration Services

Prefer dedicated services rather than putting migration logic directly inside Artisan commands.

Logical structure:

```text
app/Support/Migration/
    LegacyChatAuditService.php
    LegacyChatMigrationService.php
    LegacyConversationMapper.php
    LegacyMessageMapper.php
    LegacyConfigurationMapper.php
    LegacyMigrationVerificationService.php
    LegacyMigrationRollbackService.php
```

Exact folder placement may follow existing repository conventions.

Commands remain thin orchestration shells.

---

## 42. CLI Commands

Recommended logical command set:

```bash
php artisan support:legacy-chat-audit
php artisan support:migrate-legacy-chat --dry-run
php artisan support:migrate-legacy-chat --apply
php artisan support:migrate-legacy-chat --apply --migrate-config
php artisan support:verify-legacy-chat-migration
php artisan support:rollback-legacy-chat-migration {run_public_id}
```

Do not require a web admin endpoint for Phase 9.

Migration is an operator/deployment workflow, not a customer-facing feature.

---

## 43. File Change Boundary

Prefer changes in:

```text
app/Support/Migration/**
app/Console/Commands/**
app/Support/Models/** only where required for migration tracking
app/Support/Services/** only where integration is required
database/migrations/**
tests/Feature/Support/**
tests/Unit/Support/**
docs/**
```

Avoid unrelated ecommerce changes.

Avoid modifying the legacy chat runtime unless a narrowly justified migration-safety guard is required.

If legacy runtime code must change, explain exactly why in the Phase 9 report.

---

## 44. Database Migration Safety

Schema migrations added in Phase 9 may create migration ledger/tracking tables only.

They must not:

- rename legacy tables;
- drop legacy tables;
- alter legacy messages destructively;
- rewrite all legacy rows automatically;
- run application-level data migration automatically from `up()`.

Data migration belongs in explicit migration services/commands.

---

## 45. Tests — Preflight

Add tests for:

- missing legacy tables handled safely;
- empty legacy dataset;
- accurate counts;
- status inventory;
- sender inventory;
- orphan detection;
- duplicate session detection;
- no secrets in audit output.

---

## 46. Tests — Conversation Migration

Add tests for:

- authenticated conversation maps to correct customer;
- missing user becomes unlinked safely;
- guest remains guest;
- no email/name guessing for ownership;
- `ai` status mapping;
- `human` without agent reply maps to queued/human-requested;
- `human` with agent reply maps to human-active;
- `closed` mapping;
- unknown status fails closed;
- timestamps preserved;
- source metadata namespaced.

---

## 47. Tests — Message Migration

Add tests for:

- `user → customer`;
- `ai → ai`;
- `agent → agent`;
- `admin → agent`;
- missing sender user handled safely;
- unknown sender fails closed/safe fallback;
- message body preserved exactly;
- read state preserved;
- message order preserved;
- all legacy messages remain non-internal;
- no structured payload hallucination;
- no orchestrator/tool execution during import.

---

## 48. Tests — Idempotency / Delta

Add tests for:

- second identical run creates zero duplicate conversations;
- second identical run creates zero duplicate messages;
- new legacy message is added on delta run;
- changed legacy status reconciles safely;
- source changed + target unchanged may reconcile;
- source changed + target independently changed produces conflict;
- failed run can resume;
- chunk boundaries do not duplicate data.

---

## 49. Tests — Configuration

Add tests for:

- legacy provider selection discovered;
- current Support provider selection is not overwritten;
- safe provider migration occurs only when target is unset;
- unknown legacy provider is reported, not invented;
- model selection migrates only to an existing authoritative destination;
- provider credentials are never copied;
- prototype prompt is not auto-published as knowledge/policy;
- audit metadata is redacted.

---

## 50. Tests — Rollback

Add tests for:

- migration-owned untouched records can roll back;
- legacy rows remain intact after rollback;
- independently modified Support conversation blocks rollback;
- new Support message blocks unsafe rollback;
- later migration run blocks superseded rollback;
- rollback is idempotent.

---

## 51. Tests — Security

Add tests proving:

- migrated customer conversation cannot be accessed by another customer;
- migrated guest metadata does not expose raw session credentials;
- agent authorization remains department/assignment scoped;
- customer API does not expose migration-only PII metadata;
- migration audit does not leak tokens/secrets;
- migration cannot invoke sensitive actions.

---

## 52. Full Regression

Run:

```bash
php artisan test --filter=Support
php artisan test
php artisan route:list --path=v1/support
npm run build
```

Use the project's actual Windows command variants where necessary.

Do not declare success without executing the full project test suite.

Phase 9 must not regress Phases 1–8.

---

## 53. Migration Verification Sequence

Recommended implementation verification:

```text
1. Run legacy audit
2. Run dry-run migration
3. Review conflicts/counts
4. Run migration against test fixtures
5. Verify parity
6. Run idempotency rerun
7. Run delta test
8. Run rollback test
9. Run Support suite
10. Run full project suite
11. Run frontend build
12. Document exact results
```

If using a development database with real legacy rows, never modify them destructively.

---

## 54. Production Data Caution

Phase 9 implementation must distinguish between:

```text
code complete
```

and:

```text
production dataset migrated
```

Do not claim production migration occurred unless the command was actually run against the production database and verified.

The Phase 9 report must state the environment used:

```text
local
test
staging
production
```

No production database access is implied by repository implementation alone.

---

## 55. Documentation

Create:

```text
docs/AI-SUPPORT-PHASE-9-REPORT.md
```

The report must document:

- source legacy schema inspected;
- target Support schema used;
- migration architecture;
- migration ledger;
- field mapping;
- status mapping;
- sender mapping;
- identity rules;
- guest mapping;
- PII handling;
- configuration mapping;
- idempotency strategy;
- delta migration strategy;
- rollback strategy;
- audit/redaction;
- command usage;
- exact migration counts from executed environment;
- conflicts/failures;
- verification results;
- exact automated test results;
- full project regression result;
- legacy compatibility state;
- known limitations;
- Phase 10 recommendation.

Never invent migration counts.

---

## 56. Required Report Summary

At the end of `AI-SUPPORT-PHASE-9-REPORT.md`, include:

```text
Phase 9 Status: COMPLETE / PARTIAL / BLOCKED

Environment:
<local | test | staging | production>

Commit:
<commit hash>

Working tree:
Clean / Not clean

Legacy conversations discovered:
<count>

Legacy messages discovered:
<count>

Conversations migrated:
<count>

Messages migrated:
<count>

Conflicts:
<count>

Verification:
PASS / FAIL

Tests:
<exact results>

Legacy chat preserved:
YES / NO

Next Phase:
Phase 10 — Production Cutover
```

---

## 57. Completion Criteria

Phase 9 is complete only when:

- legacy schema is audited;
- migration tooling exists;
- migration ledger exists;
- dry-run works;
- explicit apply works;
- conversation mapping is deterministic;
- message mapping is deterministic;
- timestamps/order are preserved;
- authenticated ownership is preserved safely;
- guests are not incorrectly linked;
- legacy statuses are mapped safely;
- legacy sender types are mapped safely;
- configuration migration is non-destructive;
- secrets are not copied/logged;
- migration is idempotent;
- delta migration works;
- conflicts fail closed;
- verification command works;
- rollback is safe for migration-owned untouched records;
- migration causes no AI/tool/realtime side effects;
- customer/agent authorization remains intact;
- full Support test suite passes;
- full project test suite passes;
- frontend build passes;
- Phase 9 report is complete;
- legacy chat code and tables remain operational.

---

## 58. STOP Condition

After Phase 9 migration tooling, verification, tests and documentation are complete:

# STOP.

Do not begin:

```text
Phase 10 production cutover
route switching
legacy route removal
legacy UI removal
legacy table deletion
legacy model/controller/service deletion
production traffic migration
Phase 11 legacy cleanup
```

Phase 10 requires a separate explicit authorization checkpoint.

---

## 59. Final Architectural Rule

Phase 9 is a **data bridge**, not a cutover.

```text
Legacy data
    ↓
controlled migration
    ↓
new Support history
```

must remain separate from:

```text
Production traffic
    ↓
route switch
    ↓
new Support runtime
```

The second operation belongs to Phase 10.

**Phase 9 must leave the repository in a state where a final verified delta migration can be run immediately before Phase 10 cutover without losing legacy history or creating duplicate Support records.**
