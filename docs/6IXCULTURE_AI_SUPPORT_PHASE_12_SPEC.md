# 6ixCulture Enterprise AI Support — Phase 12 Specification: Production Hardening & Final Localhost Acceptance

## 1. Executive Summary & Context
- **Repository**: `josephtersoo2-stack/6ixculture-app`
- **Development Branch**: `phase12-production-hardening`
- **Base Commit**: `15591f937d22f8545384f0ef91ef75eb90a6d40f` (`phase11-local-cleanup`)
- **Main Production Candidate**: `3cf1f2d`
- **Safety Tag**: `phase10-final-4321f34` (`4321f34c2a404df2914dd974c97f7c79ca5f4d9b`)
- **Deployment Strategy**: Localhost implementation & comprehensive testing prior to cPanel production cutover. No branch merges into `main` before real cPanel deployment.

---

## 2. Hardening Objectives & Requirements

### 2.1 Security & Authorization Hardening
1. **Customer Conversation Ownership**: Customer `A` is strictly prohibited from accessing, viewing, or mutating conversations owned by Customer `B`.
2. **Guest Token Proof**: Guest conversations (`customer_id` is null) require valid matching `X-Guest-Token` header for read and mutation operations.
3. **Agent & Governance Authorization**: Staff workspace and governance endpoints enforce Spatie permissions (`support_desk`, `manage-support`, `settings`, `Admin` role) and agent profile department scoping.
4. **Internal Note Shielding**: Messages flagged `is_internal = true` are strictly shielded from all customer-facing endpoints (`/messages`, `/updates`, `SupportConversationDetailResource`).
5. **Sensitive Data Redaction**: API keys (`sk-*`, `AIzaSy*`), database credentials, passwords, tokens, and payment card details are recursively redacted via `AuditRedactionService` before storage or client output.

### 2.2 Rate Limiting & Abuse Prevention
1. **Registered Rate Limiters**:
   - `support-conversations`: 30 req/min for authenticated customers, 10 req/min for guest IPs.
   - `support-messages`: 60 req/min for authenticated customers, 30 req/min for guests.
   - `support-voice`: 30 req/min for authenticated customers, 15 req/min for guests.
   - `support-agent`: 120 req/min per staff agent.
   - `support-admin`: 60 req/min per administrator.
2. **Graceful Throttling**: Throttled requests return standard HTTP 429 status code with retry headers. Legitimate interactive traffic remains unblocked.

### 2.3 AI Provider & Action Policy Resilience
1. **Fault Isolation**: External AI provider API failures, rate limits, timeouts, or unexpected provider exceptions are trapped in `SupportOrchestrator`.
2. **Sanitized User Messaging**: Failures return polite fallback guidance ("I am currently having trouble processing your request. Please try again shortly or request a human support agent.") rather than crashing or leaking backend stack traces.
3. **Policy Engine Invariance**: LLM outputs cannot override authoritative security policies (`PolicyEffect::DENY`, `PolicyEffect::REQUIRE_HUMAN`, `PolicyEffect::CONFIRM`).
4. **Idempotency Protection**: Mutating actions (cancellations, refund requests) require explicit confirmation and idempotency safeguards.
5. **Graceful Degradation**:
   - WebSockets $\to$ HTTP polling `/updates?after_id=X`.
   - Voice STT/TTS $\to$ Safe capability status and uninterrupted text support.

### 2.4 Database Performance & Schema
1. **Production Indexes Migration**: `database/migrations/2026_08_19_000002_add_support_production_performance_indexes.php`:
   - `support_conversations`: `['customer_id', 'status']`, `['assigned_agent_id', 'status']`, `['department_id', 'status']`.
   - `support_messages`: `['conversation_id', 'is_internal', 'id']`.
   - `support_tickets`: `['customer_id', 'status']`, `['conversation_id', 'status']`.
2. **Zero-Drop Invariant**: Legacy tables `chat_conversations` and `chat_messages` are preserved for audit and historical reconciliation.

### 2.5 Observability & Health/Readiness
1. **Endpoint**: `GET /api/v1/support/health` (`SupportHealthController`).
2. **Payload**: Non-secret JSON report detailing database tables, AI provider configuration, governance seeding, realtime fallback, voice capabilities, environment mode, and queue driver.

---

## 3. Verification & Compliance
- Full test suite `SupportPhase12ProductionHardeningTest`: 20 tests, 0 failures.
- Comprehensive Support domain suite: 224 tests, 0 failures, 926 assertions.
- Route cache serialization: 0 collisions (`Routes cached successfully`).
- Frontend production bundle: Vite v6.4.2 build clean in 1m 22s.
