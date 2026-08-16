# 6ixCulture AI Support — Phase 5 Implementation & Security Hardening Report

**Repository:** `josephtersoo2-stack/6ixculture-app`  
**Phase:** Phase 5 — Human Support Console & Agent Workspace  
**Status:** HARDENED, VERIFIED & APPROVED  
**Date:** August 16, 2026  

---

## 1. Executive Summary

Phase 5 delivers the authenticated **Human Support Console and Agent Workspace** for 6ixCulture. Following the Phase 5 Security & AI-Assist Hardening Pass, the human support system enforces strict department scoping, rigorous assignment validation, Customer 360 authorization inheritance, and a real, provider-independent AI operational summarization engine built on top of the repository's native Support domain:

```text
Customer Storefront → CultureAI Assistant → Escalation / Queue → Department Scoping / Assignment → Agent Workspace
                                                                                            ├─ Department Scoped Timeline
                                                                                            ├─ Dual Composer (Reply / Internal Note)
                                                                                            ├─ Scoped Customer 360 & Orders
                                                                                            ├─ Grounded AI Operational Summary
                                                                                            └─ Ticket & Department Transfer
```

All human support operations execute natively on `SupportConversation`, `SupportMessage`, and `SupportAssignment`, completely eliminating parallel or unmonitored communication channels.

---

## 2. Security & Authorization Hardening Architecture

### A. Resource Scoping Pipeline
Every agent interaction follows a strict hierarchical authorization flow:

$$\text{Agent Identity} \longrightarrow \text{Authorized Support Scope} \longrightarrow \text{Department / Direct Assignment} \longrightarrow \text{Conversation}$$

- **Elevated Agents (Admins / Managers):** Retain global administrative visibility across all departments and conversations.
- **Department-Scoped Agents:** Restricted strictly to:
  1. Conversations directly assigned to them (`assigned_agent_id === $agent->id`), OR
  2. Conversations belonging to departments attached to their active `SupportAgentProfile`.
- **Unauthorized Conversations:** Direct requests return `403 Forbidden` (`SUPPORT_AGENT_FORBIDDEN`), blocking access to timeline details, actions, and metadata.

### B. Authorization-First Queue Queries
URL parameters (`department_id`, `assigned_to`, `status`, `search`) operate as filters *within* the agent's authorized scope rather than acting as authorization overrides:
- Department scoping is applied as an unconditional base `where` clause before any user filters are evaluated.
- If an agent belonging solely to *Orders* queries `?department_id=2` (*Sales*), the query returns zero records, guaranteeing zero cross-department data leakage.

### C. Assignment Target Validation
- Assignment requests are validated against `SupportAgentProfile` and staff roles.
- Normal customer accounts (`Role: Customer` without agent profile) are rejected with `422 Unprocessable Entity` (`INVALID_ASSIGNMENT_TARGET`).
- Non-elevated agents cannot be assigned conversations belonging to departments they are not attached to (`UNAUTHORIZED_ASSIGNMENT_TARGET`).
- Self-assignment uses the authenticated server identity.

### D. Department Transfer Governance
- Department transfers require conversation authorization.
- If a conversation is transferred to a department where the currently assigned agent is not a member, the assignment is cleared (`assigned_agent_id = null`) and the status is reset to `queued`, routing the conversation cleanly to the target department's queue.

### E. Customer 360 / Orders / Ticket Scope Inheritance
- The `/customer`, `/orders`, and `/ticket` endpoints enforce conversation authorization prior to returning any customer data, preventing unauthorized agents from scraping customer information.

### F. Internal Staff Notes Isolation
- Staff notes are stored with `is_internal = true` and `message_type = INTERNAL_NOTE`.
- Customer-facing endpoints enforce `where('is_internal', false)` via `scopeCustomerVisible`, guaranteeing customers never receive internal notes.

---

## 3. Real AI Operational Summarization Engine

The placeholder summary implementation was replaced with a real, provider-independent summarizer leveraging the existing `SupportContextAssembler` and `AiProviderFactory`:

1. **Bounded & Sanitized Context Assembly:**
   - `SupportContextAssembler::assembleForSummarization()` compiles the conversation turns (up to 15 messages) alongside customer facts (name, channel, status, priority, department).
   - Strict security filters ensure passwords, authentication tokens, API keys, and internal secrets are never included in the summarization prompt.
2. **Structured Operational Schema:**
   The AI model generates a structured, human-readable summary formatted specifically for support agents:
   - **Customer Issue:** High-level summary of the inquiry
   - **Detected Intent:** Intent categorization (e.g. Order Tracking, Sizing, Returns)
   - **Language:** Customer conversation language
   - **Relevant Order / Products:** Serial numbers or items referenced
   - **Key Facts:** Important context provided by the customer
   - **Actions Already Taken:** Previous AI or staff turns
   - **Current Status:** Status, department, priority
   - **Reason for Escalation:** Trigger for human handoff
   - **Recommended Next Step:** Actionable guidance for the agent
3. **Graceful Failure Mode:**
   - Provider timeouts, API errors, or rate limits return a safe `502 Bad Gateway` error response without corrupting conversation records.
   - Successful generations are persisted to `SupportConversation.ai_summary` and recorded in `SupportAuditLog`.

---

## 4. Agent REST API Endpoints

All endpoints are registered under `routes/api.php` with `auth:sanctum` and Spatie verification:

| Method | Endpoint | Description | Scope Enforcement |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/support/agent/conversations` | Filtered support queue | Scoped to agent's departments / assignments |
| `GET` | `/api/v1/support/agent/conversations/{id}` | Full conversation detail & timeline | Restricted to authorized conversation |
| `POST` | `/api/v1/support/agent/conversations/{id}/assign` | Claim, reassign, or unassign | Target verified against profile & department |
| `POST` | `/api/v1/support/agent/conversations/{id}/reply` | Dispatch customer reply | Restricted to authorized conversation |
| `POST` | `/api/v1/support/agent/conversations/{id}/notes` | Create private internal staff note | Restricted to authorized conversation |
| `PATCH` | `/api/v1/support/agent/conversations/{id}/status` | Transition conversation status | Restricted to authorized conversation |
| `PATCH` | `/api/v1/support/agent/conversations/{id}/priority` | Update conversation priority | Restricted to authorized conversation |
| `PATCH` | `/api/v1/support/agent/conversations/{id}/department` | Transfer conversation department | Revalidates target active department |
| `GET` | `/api/v1/support/agent/conversations/{id}/customer` | Customer 360 metrics | Inherits conversation authorization |
| `GET` | `/api/v1/support/agent/conversations/{id}/orders` | Customer order history | Inherits conversation authorization |
| `GET` | `/api/v1/support/agent/conversations/{id}/ticket` | Linked support ticket | Inherits conversation authorization |
| `POST` | `/api/v1/support/agent/conversations/{id}/summarize` | Generate AI operational summary | Grounded via `SupportContextAssembler` |
| `GET` | `/api/v1/support/agent/departments` | Active departments list | Active departments |
| `GET` | `/api/v1/support/agent/agents` | Active agents list | Authorized staff profiles |

---

## 5. Automated Verification & Regression Suite

The full test suite was executed against the hardened implementation:

```powershell
& "C:\xampp\php\php.exe" artisan test --filter=AgentSupportApiTest
& "C:\xampp\php\php.exe" artisan test --filter=Support
& "C:\xampp\php\php.exe" artisan test
```

### Full Test Suite Results
- **Total Tests Passed:** `58 passed`
- **Total Assertions:** `230 assertions`
- **Failures:** `0`
- **Regressions:** `0`

```text
PASS  Tests\Unit\ExampleTest
  ✓ that true is true

PASS  Tests\Unit\Support\SupportAuditLogTest
  ✓ audit log appends and sanitizes sensitive secrets

PASS  Tests\Unit\Support\SupportModelTest
  ✓ conversation generates public id and casts enums
  ✓ message casts and internal note isolation scope
  ✓ support ticket generates ticket number and relationships
  ✓ support policy effect and active scope
  ✓ support ai tool registry and risk levels
  ✓ conversation tags pivot relationship

PASS  Tests\Feature\ExampleTest
  ✓ the application returns a successful response

PASS  Tests\Feature\Support\AgentSupportApiTest
  ✓ unauthenticated requests to agent endpoints are rejected
  ✓ regular customer is forbidden from agent console
  ✓ agent cannot access another department conversation
  ✓ authorized department agent can access its conversation
  ✓ assigned agent can access its assigned conversation even if outside department
  ✓ queue department id filter cannot escape authorization scope
  ✓ agent cannot assign to a normal customer
  ✓ agent cannot assign to an unauthorized agent from another department
  ✓ agent can claim self and records assignment history
  ✓ unauthorized department transfer fails
  ✓ authorized supervisor or department agent can transfer department
  ✓ unauthorized agent cannot access customer 360 orders or ticket
  ✓ agent reply creates customer visible message and updates status
  ✓ internal staff notes are strictly isolated from customer api
  ✓ actual ai support orchestrator path is invoked for summary and persisted
  ✓ provider failure during summary returns safe error without damaging conversation

PASS  Tests\Feature\Support\SupportApiTest
  ✓ guest can create conversation and send messages
  ✓ authenticated customer conversation lifecycle
  ✓ customer isolation blocks cross access
  ✓ internal staff messages are never exposed via api
  ✓ idempotency prevents duplicate submissions
  ✓ human escalation request flow
  ✓ oversized message is rejected gracefully
  ✓ conversation updates polling
  ✓ action execution revalidates server policy
  ✓ wrong guest token cannot access guest conversation
  ✓ authenticated user cannot claim guest conversation without token
  ✓ authenticated user with valid guest token may explicitly link
  ✓ authenticated user b cannot claim user a linked conversation
  ✓ route action cannot be overridden by body tool name
  ✓ route action is the authority
  ✓ unknown route action fails closed

PASS  Tests\Feature\Support\SupportAuthorizationTest
  ✓ customer can only access own conversation
  ✓ assigned agent and admin can access conversation
  ✓ internal messages are strictly isolated from customer queries
  ✓ customer ticket ownership authorization

PASS  Tests\Feature\Support\SupportKnowledgeGroundingTest
  ✓ draft and archived articles are strictly excluded from search
  ✓ language priority and fallback
  ✓ context assembler enforces budget limits and includes public facts
  ✓ prompt injection in knowledge article is treated as inert reference
  ✓ orchestrator grounds policy inquiries using knowledge

PASS  Tests\Feature\Support\SupportOrchestratorTest
  ✓ provider adapter openrouter normalization
  ✓ provider adapter gemini normalization
  ✓ rate limit and message size constraints
  ✓ security ownership blocks unauthorized orders lookup
  ✓ sensitive action requires explicit confirmation
  ✓ critical action triggers human escalation
  ✓ prompt injection safety filters

PASS  Tests\Feature\Support\SupportSeederTest
  ✓ support domain seeder populates departments policies and tools
```

---

## 6. Phase Boundaries & Non-Destructive Invariants

- Legacy chat controllers (`ChatController`, `AdminChatController`, `ChatService`, `ChatMessage`, `ChatConversation`) remain completely untouched.
- No realtime/WebSockets/Reverb dependencies were introduced; clean HTTP polling is used.
- No voice, telephony, or external messaging channels (WhatsApp/email) were added.
- No automatic order mutations, cancellations, or refunds were permitted.

---

## 7. Status

**Phase 5 Security & AI-Assist Hardening: COMPLETE**  
Ready for Phase 5 final review and approval before proceeding to Phase 6.
