# 6ixCulture AI Support — Phase 5 Implementation Specification

**Repository:** `josephtersoo2-stack/6ixculture-app`  
**Phase:** 5 — Human Support Console & Agent Workspace  
**Prerequisites:** Phases 0–4 approved  
**Status:** DRAFT — READY FOR REVIEW

## 1. Goal

Build the authenticated human-support workspace that receives customer AI escalations and lets authorized agents work the same `SupportConversation` domain safely.

Target flow:

```text
Customer → AI Support → Escalation → Support Queue → Department / Assignment → Agent Workspace
                                                                            ├─ Conversation
                                                                            ├─ Customer 360
                                                                            ├─ Orders / Tickets
                                                                            └─ Internal Notes / AI Assistance
```

Do not create a second support system.

## 2. Scope

Implement only:
- authenticated human-support API
- support queue/inbox
- department/status/priority/assignment filters
- claim/assign/unassign/reassign
- three-column Support Center
- conversation timeline
- customer-visible replies
- internal notes
- customer 360 context
- authorized order/ticket context
- priority/status controls
- AI-generated summary display
- safe, policy-governed agent assistance
- authorization, assignment, note, reply, and state-transition tests
- frontend tests/build checks
- documentation

Do **not** implement yet:
- realtime/WebSockets/Pusher/Reverb/Ably
- voice/STT/TTS
- WhatsApp/email/phone
- historical legacy chat migration
- production cutover
- legacy chatbot deletion
- automatic refunds/cancellations/payment mutations
- broad knowledge administration
- unrestricted AI tooling

## 3. Reuse Existing Support Domain

Reuse:

```text
SupportConversation
SupportMessage
SupportTicket
SupportDepartment
SupportAgentProfile
SupportAssignment
SupportPolicy
SupportAITool
SupportAIToolPermission
SupportAuditLog
SupportOrchestrator
SupportActionPolicyEngine
ToolRegistry
SupportKnowledgeRepository
SupportContextAssembler
```

Use existing Laravel authentication and Spatie permissions. Do not create a second auth/role system.

## 4. Legacy Admin Chat

Phase 0 identified `AdminChatController`, `LiveChatComponent.vue`, and `liveChatRoutes.js` as legacy/prototype candidates. Phase 5 may replace their functionality with the new Support Center, but the legacy implementation must remain until a later production cutover. Do not delete legacy chat files in this phase.

## 5. Three-Column Workspace

Use the established visual concept and, when present, `docs/6IXCULTURE_AI_SUPPORT_BACKEND_UI_TEMPLATE.png` as the reference:

```text
┌────────────────────┬───────────────────────────┬──────────────────────────┐
│ SUPPORT QUEUE      │ CONVERSATION              │ CUSTOMER 360             │
│ search/filters     │ header/timeline           │ profile                  │
│ department         │ internal notes            │ orders                   │
│ status/priority    │ agent reply               │ tickets                  │
│ assignment         │ AI summary                │ support history          │
└────────────────────┴───────────────────────────┴──────────────────────────┘
```

Adapt it to the existing 6ixCulture Vue/admin conventions.

## 6. Queue and Assignment

Queue states should cover at least:

```text
new
queued
awaiting_agent
human_active
```

Filters:

```text
department
status
priority
assigned/unassigned
agent
language
channel
updated/created time
```

Use `SupportAssignment` for assignment history. Implement claim, self-assign, authorized reassignment, unassign, and department reassignment. Server-side authorization is mandatory.

## 7. Agent Authorization

Use existing user identity plus `SupportAgentProfile`.

Agents may access only conversations permitted by support role, department, and assignment. Supervisors/admins may have broader access according to existing Spatie permissions. Never trust browser-supplied `agent_id`, `customer_id`, or authorization flags.

## 8. Conversation / Customer 360

Conversation timeline must support:

```text
customer messages
AI messages
system escalation events
agent replies
internal notes
```

Customer 360 may show safe support data such as profile, language, recent orders, open tickets, conversation history, and support tags. Never expose passwords, tokens, payment credentials, secrets, internal security data, or unrelated customers.

## 9. Internal Notes and Agent Replies

Internal notes use the established `SupportMessage` internal-message mechanism. Customers must never receive them.

Agent replies must remain ordinary customer-visible `SupportMessage` records (`sender_type=agent`, `is_internal=false`) and must use the existing conversation model.

The UI must have distinct **Reply to customer** and **Add internal note** modes.

## 10. Status / Priority / Ticket

Reuse existing enums:

```text
priority: low, normal, high, urgent
status: queued, human_active, awaiting_customer, resolved, closed
```

Reject invalid transitions server-side.

Show ticket number, status, priority, department, assignee, resolution, and currently supported SLA data where available. Do not create tickets automatically unless existing domain rules require it.

## 11. AI Assistance

Display `SupportConversation.ai_summary` as assistive data, clearly labeled as AI-generated. Do not expose chain-of-thought.

Safe assistive functions may include:

```text
summarize
suggest reply
translate
suggest relevant knowledge
identify intent
suggest next support action
```

Any actual tool execution must continue through `SupportActionPolicyEngine`, `ToolRegistry`, authorization, and audit logging. The agent remains the decision-maker.

## 12. API Boundary

Create/extend versioned endpoints following repository conventions, with logical responsibilities equivalent to:

```text
GET    /api/v1/support/agent/conversations
GET    /api/v1/support/agent/conversations/{conversation}
POST   /api/v1/support/agent/conversations/{conversation}/assign
POST   /api/v1/support/agent/conversations/{conversation}/reply
POST   /api/v1/support/agent/conversations/{conversation}/notes
PATCH  /api/v1/support/agent/conversations/{conversation}/status
PATCH  /api/v1/support/agent/conversations/{conversation}/priority
GET    /api/v1/support/agent/conversations/{conversation}/customer
GET    /api/v1/support/agent/conversations/{conversation}/orders
GET    /api/v1/support/agent/conversations/{conversation}/ticket
```

Adapt exact routes to existing conventions and avoid duplication.

## 13. Vue Console

Create/adapt logical components such as:

```text
SupportCenter.vue
SupportQueue.vue
SupportQueueFilters.vue
SupportConversationPanel.vue
SupportConversationHeader.vue
SupportTimeline.vue
InternalNoteComposer.vue
AgentReplyComposer.vue
Customer360Panel.vue
CustomerProfileCard.vue
CustomerOrdersPanel.vue
CustomerTicketsPanel.vue
ConversationAssignment.vue
ConversationStatusControl.vue
PriorityControl.vue
AiSupportSummary.vue
AgentCopilotPanel.vue
```

Reuse the existing Vue router/store/service architecture. Do not introduce a second frontend state system.

## 14. Realtime

Realtime is deferred. Do not add Pusher, Reverb, Ably, or WebSockets. Polling may be reused temporarily.

## 15. Security Tests

Prove:
- customer is denied agent endpoints;
- unauthorized users are denied Support Center access;
- agents cannot cross department boundaries without permission;
- assignment requires authorization;
- assignment history is recorded;
- internal notes are never customer-visible;
- authorized agents can create/read internal notes;
- agent replies are customer-visible;
- invalid state transitions fail safely;
- sensitive actions remain policy-governed;
- audit records contain no secrets.

## 16. Frontend Tests

Test queue/filter rendering, conversation selection, internal-note mode, reply mode, assignment, priority/status controls, Customer 360, orders, tickets, AI summary, loading/error states, and permission-aware UI.

Backend authorization remains authoritative.

## 17. File Change Rules

Prefer changes under:

```text
app/Http/Controllers/**
app/Http/Requests/**
app/Http/Resources/**
app/Support/**
routes/**
resources/js/components/admin/**
resources/js/services/**
resources/js/store/**
resources/js/router/**
tests/Feature/Support/**
tests/Unit/Support/**
docs/**
```

Avoid unrelated ecommerce modifications.

## 18. Testing

Run at minimum:

```bash
php artisan test --filter=Support
php artisan test
php artisan route:list --path=v1/support
```

Run the project's existing frontend build/test/lint commands and document exact results.

## 19. Documentation

Create:

```text
docs/AI-SUPPORT-PHASE-5-REPORT.md
```

Include architecture, queue/assignment, authorization, internal notes, Customer 360, order/ticket context, AI assistance, API routes, Vue components, security, exact test results, legacy compatibility, deferred features, and Phase 6 recommendation.

## 20. Completion Criteria

Phase 5 is complete only when the authorized Support Center works end-to-end: queue, filters, assignment, conversation timeline, customer-visible replies, internal notes, priority/status controls, Customer 360, authorized order/ticket context, AI summary, safe AI assist, security boundaries, tests, and documentation all pass.

## 21. Stop Condition

After the Human Support Console, agent authorization, queue, assignment, internal notes, Customer 360, ticket/order context, safe AI assistance, tests, and documentation are complete:

**STOP.**

Do not begin realtime, voice, omnichannel, legacy migration, production cutover, or legacy deletion. Phase 6 is the next authorization checkpoint.
