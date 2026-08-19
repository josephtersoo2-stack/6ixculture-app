# 6ixCulture Enterprise AI Support — Phase 11 Legacy Removal Manifest

**Repository:** `josephtersoo2-stack/6ixculture-app`  
**Branch:** `phase11-local-cleanup` (Isolated development branch; `main` untouched at `3cf1f2d` for safe cPanel production cutover)  
**Phase:** Phase 11 — Verified Legacy Removal (Local Implementation)  
**Baseline Commit:** `3cf1f2d`  
**Safety Baseline Tag:** `phase10-final-4321f34` (`4321f34c2a404df2914dd974c97f7c79ca5f4d9b`)  
**Status:** COMPLETED LOCALLY ON FEATURE BRANCH  

---

## 1. Classification Categories & Lifecycle State

Each identified legacy token or reference across the codebase has been categorized and resolved on the `phase11-local-cleanup` branch:

1. **`REMOVED`**: Obsolete prototype runtime files, controllers, services, routes, components, and settings permanently deleted on this branch.
2. **`REPLACED REFERENCE`**: Files modified to remove dead imports, navigation links, or router references without deleting the containing file.
3. **`KEEP FOR MIGRATION/AUDIT`**: Phase 9 migration services, audit commands, data mappers, verification tooling, historical reports, and specifications preserved for auditability and compliance. Decoupled from runtime via migration-specific models.
4. **`KEEP FOR DATA RETENTION`**: Database tables (`chat_conversations`, `chat_messages`) and migration schema definitions preserved read-only until separate explicit table-drop authorization.
5. **`KEEP — NON-LEGACY AI INFRASTRUCTURE`**: Modern Support domain assets, backend AI copilot, AI agent models, and gateway options used by active application features.

---

## 2. Exhaustive Repository Inventory & Removal Matrix

### 2.1 Legacy Backend Runtime Code (Status: `REMOVED`)

| Target File | Patterns Matched | Lines / Hits | Purpose | Classification | Action Taken on `phase11-local-cleanup` |
|---|---|---|---|---|---|
| [`app/Http/Controllers/Frontend/ChatController.php`](file:///c:/xampp/htdocs/shopkingcpanel/app/Http/Controllers/Frontend/ChatController.php) | `ChatController`, `ChatService`, `ChatConversation` | 14 hits | Prototype customer chat endpoint controller | `REMOVED` | File deleted |
| [`app/Http/Controllers/Admin/AdminChatController.php`](file:///c:/xampp/htdocs/shopkingcpanel/app/Http/Controllers/Admin/AdminChatController.php) | `AdminChatController`, `ChatService`, `ChatConversation`, `ChatMessage` | 16 hits | Prototype back-office admin chat management | `REMOVED` | File deleted |
| [`app/Services/ChatService.php`](file:///c:/xampp/htdocs/shopkingcpanel/app/Services/ChatService.php) | `ChatService`, `ChatConversation`, `ChatMessage`, `chat_conversations`, `chat_messages` | 23 hits | Prototype chat business logic and dynamic table creation | `REMOVED` | File deleted |
| [`app/Models/ChatConversation.php`](file:///c:/xampp/htdocs/shopkingcpanel/app/Models/ChatConversation.php) | `ChatConversation`, `chat_conversations`, `ChatMessage` | 3 hits | Eloquent model for prototype conversations table | `REMOVED` | Replaced by `App\Support\Migration\Legacy\Models\LegacyChatConversation.php` for migration layer only |
| [`app/Models/ChatMessage.php`](file:///c:/xampp/htdocs/shopkingcpanel/app/Models/ChatMessage.php) | `ChatMessage`, `chat_messages`, `ChatConversation` | 3 hits | Eloquent model for prototype messages table | `REMOVED` | Replaced by `App\Support\Migration\Legacy\Models\LegacyChatMessage.php` for migration layer only |
| [`app/Http/Middleware/Support/GateLegacyChatMutationMiddleware.php`](file:///c:/xampp/htdocs/shopkingcpanel/app/Http/Middleware/Support/GateLegacyChatMutationMiddleware.php) | `gateLegacyChat` | 4 hits | Cutover safety middleware locking legacy mutation routes | `REMOVED` | Deleted middleware & removed alias from `bootstrap/app.php` |

---

### 2.2 Route Registrations & Endpoints (Status: `REMOVED`)

| File | Route Prefix / Pattern | Endpoints | Classification | Action Taken on `phase11-local-cleanup` |
|---|---|---|---|---|
| [`routes/api.php`](file:///c:/xampp/htdocs/shopkingcpanel/routes/api.php) | `api/admin/chat/*` | `GET /`, `GET /show/{id}`, `POST /reply/{id}`, `POST /update-status/{id}`, `DELETE /{id}` | `REMOVED` | Removed route block; canonical is `api/v1/support/agent/*` |
| [`routes/api.php`](file:///c:/xampp/htdocs/shopkingcpanel/routes/api.php) | `api/chat/*` (alias) | `ANY /history`, `ANY /send`, `ANY /request-human` | `REMOVED` | Removed route block; canonical is `api/v1/support/conversations/*` |
| [`routes/api.php`](file:///c:/xampp/htdocs/shopkingcpanel/routes/api.php) | `api/frontend/chat/*` | `ANY /history`, `ANY /send`, `ANY /request-human` | `REMOVED` | Removed route block; canonical is `api/v1/support/conversations/*` |
| [`routes/api.php`](file:///c:/xampp/htdocs/shopkingcpanel/routes/api.php) | `api/chat/*` (frontend) | `GET /history`, `POST /send`, `POST /request-human` | `REMOVED` | Removed route block; canonical is `api/v1/support/conversations/*` |

*Note: Total registered routes cleanly reduced from 581 to 565 routes.*

---

### 2.3 Legacy Frontend Assets & Router (Status: `CONFIRMED CLEAN`)

| File | Item | Classification | Current State |
|---|---|---|---|
| `resources/js/components/frontend/chat/LiveChatWidgetComponent.vue` | Obsolete frontend live-chat widget | `REMOVED` | Replaced by `AiSupportWidget.vue` |
| `resources/js/components/admin/chat/LiveChatComponent.vue` | Obsolete admin live-chat panel | `REMOVED` | Replaced by `SupportCenterComponent.vue` |
| `resources/js/router/modules/liveChatRoutes.js` | Obsolete router module | `REMOVED` | Replaced by `supportRoutes.js` |
| `resources/js/router/index.js` | Router bundle index | `REPLACED REFERENCE` | Clean (0 references to `liveChatRoutes`) |

---

### 2.4 Phase 9 Migration Ledger & Evidence (Status: `KEEP FOR MIGRATION/AUDIT`)

| Target File / Table | Purpose | Classification | Retention & Migration-Model Integration |
|---|---|---|---|
| `support_legacy_migration_runs` | Migration run records and batch logs | `KEEP FOR MIGRATION/AUDIT` | Immutable audit ledger of legacy data ingestion |
| `support_legacy_migration_items` | Entity mapping pointers (legacy ID $\to$ support ID) | `KEEP FOR MIGRATION/AUDIT` | Entity provenance and historical correlation |
| [`app/Support/Migration/Legacy/Models/LegacyChatConversation.php`](file:///c:/xampp/htdocs/shopkingcpanel/app/Support/Migration/Legacy/Models/LegacyChatConversation.php) | Migration-only representation of `chat_conversations` | `KEEP FOR MIGRATION/AUDIT` | Enables migration tooling to operate without runtime models |
| [`app/Support/Migration/Legacy/Models/LegacyChatMessage.php`](file:///c:/xampp/htdocs/shopkingcpanel/app/Support/Migration/Legacy/Models/LegacyChatMessage.php) | Migration-only representation of `chat_messages` | `KEEP FOR MIGRATION/AUDIT` | Enables migration tooling to operate without runtime models |
| [`app/Support/Migration/LegacyChatMigrationService.php`](file:///c:/xampp/htdocs/shopkingcpanel/app/Support/Migration/LegacyChatMigrationService.php) | Delta migration engine | `KEEP FOR MIGRATION/AUDIT` | Retained for delta synchronization and verification |
| [`app/Support/Migration/LegacyChatAuditService.php`](file:///c:/xampp/htdocs/shopkingcpanel/app/Support/Migration/LegacyChatAuditService.php) | Pre-cutover and post-cutover parity auditor | `KEEP FOR MIGRATION/AUDIT` | Verification of historical record integrity |
| [`app/Support/Migration/LegacyMigrationVerificationService.php`](file:///c:/xampp/htdocs/shopkingcpanel/app/Support/Migration/LegacyMigrationVerificationService.php) | Parity verifier | `KEEP FOR MIGRATION/AUDIT` | Checksum and parity verification |
| [`app/Support/Migration/LegacyMigrationRollbackService.php`](file:///c:/xampp/htdocs/shopkingcpanel/app/Support/Migration/LegacyMigrationRollbackService.php) | Migration rollback tool | `KEEP FOR MIGRATION/AUDIT` | Controlled rollback infrastructure |
| [`app/Support/Migration/SourceChecksumCalculator.php`](file:///c:/xampp/htdocs/shopkingcpanel/app/Support/Migration/SourceChecksumCalculator.php) | Checksum computation tool | `KEEP FOR MIGRATION/AUDIT` | Parity verification |
| [`app/Support/Migration/LegacyConversationMapper.php`](file:///c:/xampp/htdocs/shopkingcpanel/app/Support/Migration/LegacyConversationMapper.php) | Conversation field mapper | `KEEP FOR MIGRATION/AUDIT` | Mapping logic reference |
| [`app/Support/Migration/LegacyMessageMapper.php`](file:///c:/xampp/htdocs/shopkingcpanel/app/Support/Migration/LegacyMessageMapper.php) | Message field mapper | `KEEP FOR MIGRATION/AUDIT` | Mapping logic reference |
| [`app/Support/Migration/LegacyConfigurationMapper.php`](file:///c:/xampp/htdocs/shopkingcpanel/app/Support/Migration/LegacyConfigurationMapper.php) | Configuration mapper | `KEEP FOR MIGRATION/AUDIT` | Mapping logic reference |
| [`app/Console/Commands/Support/LegacyChatAuditCommand.php`](file:///c:/xampp/htdocs/shopkingcpanel/app/Console/Commands/Support/LegacyChatAuditCommand.php) | Migration CLI audit tool | `KEEP FOR MIGRATION/AUDIT` | Audit execution CLI |

---

### 2.5 Legacy Data Tables (Status: `KEEP FOR DATA RETENTION`)

| Table Name | Schema Migration | Classification | Decision & Policy |
|---|---|---|---|
| `chat_conversations` | `database/migrations/2026_08_13_000001_create_live_chats_table.php` | `KEEP FOR DATA RETENTION` | Preserved read-only in database; NO drop migration executed |
| `chat_messages` | `database/migrations/2026_08_13_000001_create_live_chats_table.php` | `KEEP FOR DATA RETENTION` | Preserved read-only in database; NO drop migration executed |

---

### 2.6 Retained AI & Support Core Infrastructure (Status: `KEEP — NON-LEGACY AI INFRASTRUCTURE`)

| Component / Subsystem | Files / Symbols | Classification | Functional Role |
|---|---|---|---|
| Back-Office AI Controller | [`app/Http/Controllers/Admin/AiController.php`](file:///c:/xampp/htdocs/shopkingcpanel/app/Http/Controllers/Admin/AiController.php) | `KEEP — NON-LEGACY AI INFRASTRUCTURE` | Admin AI copilot & prompt generation |
| Back-Office AI Agent Controller | [`app/Http/Controllers/Admin/AiAgentController.php`](file:///c:/xampp/htdocs/shopkingcpanel/app/Http/Controllers/Admin/AiAgentController.php) | `KEEP — NON-LEGACY AI INFRASTRUCTURE` | AI Provider configuration & gateway management |
| AI Agent Models | [`app/Models/AiAgent.php`](file:///c:/xampp/htdocs/shopkingcpanel/app/Models/AiAgent.php), [`app/Models/GatewayOption.php`](file:///c:/xampp/htdocs/shopkingcpanel/app/Models/GatewayOption.php), `AiChatHistory` | `KEEP — NON-LEGACY AI INFRASTRUCTURE` | Central AI gateway and credentials |
| AI Providers | [`app/Http/AiAgents/Agents/Openrouter.php`](file:///c:/xampp/htdocs/shopkingcpanel/app/Http/AiAgents/Agents/Openrouter.php), [`app/Http/AiAgents/Agents/Gemini.php`](file:///c:/xampp/htdocs/shopkingcpanel/app/Http/AiAgents/Agents/Gemini.php), `Openai.php` | `KEEP — NON-LEGACY AI INFRASTRUCTURE` | Core LLM integrations |
| Support Domain Runtime | `app/Support/**` (Models, Services, Controllers, Policies, Tools, Voice, Knowledge) | `KEEP — NON-LEGACY AI INFRASTRUCTURE` | Modern enterprise AI Support domain |
| Admin AI Sidebar UI | `resources/js/components/layouts/backend/BackendAiSidebarComponent.vue` | `KEEP — NON-LEGACY AI INFRASTRUCTURE` | Back-office copilot Vue component |
| Vuex AI Store | `resources/js/store/modules/ai.js` | `KEEP — NON-LEGACY AI INFRASTRUCTURE` | AI provider & chat state store |

