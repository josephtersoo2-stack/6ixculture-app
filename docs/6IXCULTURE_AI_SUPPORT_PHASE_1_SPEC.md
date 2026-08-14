# 6ixCulture AI Support — Phase 1 Implementation Specification

**Repository:** `josephtersoo2-stack/6ixculture-app`  
**Implementation phase:** Phase 1 — Support Database & Domain Foundation  
**Predecessor:** `docs/AI-SUPPORT-AUDIT.md`  
**Status:** APPROVED TO IMPLEMENT

---

## 1. Purpose

Phase 1 establishes the production support-domain foundation for the new 6ixCulture AI Customer Care and Shopping Assistant.

This phase is intentionally limited to:

- database schema
- Eloquent models
- enums/value boundaries
- relationships
- security scopes/policies foundation
- domain contracts/DTOs where useful
- seed data for departments/policies
- tests for the foundation

This phase must **not** yet replace the live customer chatbot, build the full AI orchestrator, implement voice, or cut over the existing UI.

The existing ecommerce application must continue operating unchanged.

---

# 2. Existing Audit Decisions

The Phase 0 audit identified two distinct AI systems:

1. Existing admin AI copilot
2. Existing customer support/live-chat prototype

The admin AI gateway and provider infrastructure remains in place.

The customer support prototype will eventually be replaced by the new Support domain.

The audit specifically identified prototype gaps around dynamic table creation, structured messages, tickets, assignments, departments, internal notes, and audit trails.

---

# 3. Files/Systems That Must Remain Stable in Phase 1

Do not alter business behavior in:

- product management
- product variations
- stock
- cart
- checkout
- orders
- payments
- shipping
- returns/refunds
- customer authentication
- existing admin AI copilot
- existing AI provider credential management

Existing services remain the source of truth.

Relevant services identified during audit include:

- `ProductService`
- `ProductVariationService`
- `ProductReviewService`
- `FrontendOrderService`
- `OrderService`
- `CustomerService`
- `AddressService`
- `ReturnOrderService`
- `ReturnAndRefundService`
- `ShippingSetupService`

Do not duplicate these services.

---

# 4. Target Domain Namespace

Create a logical Support domain under:

```text
app/Support/
```

Recommended structure:

```text
app/Support/
├── Contracts/
├── DTOs/
├── Enums/
├── Events/
├── Models/
├── Policies/
├── Services/
├── Tools/
└── SupportServiceProvider.php   # only if useful and consistent with project conventions
```

Do not create implementation-heavy AI classes yet.

Phase 1 establishes the foundation those future phases will use.

---

# 5. Core Domain Entities

Create the following logical entities.

## 5.1 SupportConversation

Purpose:

Represents a customer support conversation independent of a ticket.

Required concepts:

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
last_message_at
last_customer_message_at
last_agent_message_at
ai_active
human_requested
escalation_reason nullable
ai_summary nullable
sentiment nullable
metadata nullable
timestamps
```

Important:

Do not assume a conversation must have a ticket.

---

## 5.2 SupportMessage

Purpose:

Represents every message/event visible within a conversation.

Required concepts:

```text
conversation_id
sender_type
sender_id nullable
message_type
content nullable
structured_payload nullable
language nullable
is_internal
is_read
tool_call_id nullable
reply_to_id nullable
metadata nullable
timestamps
```

Sender types:

```text
customer
ai
agent
system
```

Message types should support at least:

```text
text
product
product_list
product_comparison
order
order_status
cart
action_confirmation
system
escalation
voice_transcript
audio
image
file
error
```

Use enums where compatible with project conventions.

---

# 6. SupportTicket

Purpose:

Represents a support issue that may be associated with a conversation.

Required concepts:

```text
public_id
conversation_id
customer_id
department_id
assigned_agent_id nullable
category
priority
status
subject
description
resolution nullable
sla_due_at nullable
resolved_at nullable
closed_at nullable
timestamps
```

Statuses:

```text
open
assigned
in_progress
waiting_customer
waiting_internal
escalated
resolved
closed
```

---

# 7. SupportDepartment

Purpose:

Defines routing/ownership groups.

Initial seed departments:

```text
General Support
Sales
Orders
Returns & Refunds
Payments
Technical Support
Wholesale
```

The names are seed data and may be edited later through admin UI.

---

# 8. SupportAgentProfile

Purpose:

Support-specific profile for a human support agent.

Do not create a second authentication system.

Reference the existing application user/admin identity.

Logical fields:

```text
user_id
display_name nullable
status
availability
department_id or pivot relationship
max_concurrent_conversations nullable
metadata nullable
timestamps
```

Support agent authentication remains the existing Laravel auth system.

---

# 9. Agent/Department Relationship

Prefer a pivot if an agent may work in multiple departments.

Example logical pivot:

```text
support_agent_department
```

Fields:

```text
agent_profile_id
department_id
is_primary
timestamps
```

Do not assume one agent can belong to only one department unless repository conventions require it.

---

# 10. SupportAssignment

Purpose:

Track explicit ownership/assignment of conversations.

This is separate from the current single `assigned_agent_id` field because assignment history matters.

Logical fields:

```text
conversation_id
agent_id
department_id nullable
assigned_by nullable
assigned_at
unassigned_at nullable
reason nullable
metadata nullable
timestamps
```

A current conversation can still expose a fast `assigned_agent_id`, while the assignment history provides auditability.

---

# 11. SupportKnowledgeArticle

Purpose:

Admin-managed support knowledge.

Logical fields:

```text
title
slug
category
language
content
status
version
published_at nullable
created_by
updated_by nullable
metadata nullable
timestamps
```

Initial categories:

```text
Products
Shipping
Returns
Refunds
Payments
Orders
Accounts
Warranty
Promotions
FAQ
Store Policies
```

Use a versioning strategy that does not destroy historical published content.

A separate version table is preferred if consistent with application patterns.

---

# 12. SupportPolicy

Purpose:

Represents business/AI support policies independently of the LLM prompt.

Examples:

```text
AI may answer shipping-policy questions.
AI may search products.
AI may add items to the cart.
Refunds require human approval.
Post-shipment address changes require verification.
Customer data is customer-scoped.
Internal staff notes are never customer-visible.
```

Logical fields:

```text
key
name
description
category
effect
configuration JSON
active
priority
created_by
updated_by
timestamps
```

Policy effects may include:

```text
allow
deny
confirm
require_verification
require_human
```

Keep the schema flexible enough for Phase 2's policy engine.

---

# 13. SupportAITool

Purpose:

Registry of AI capabilities.

This is metadata, not yet the full tool executor.

Logical fields:

```text
key
name
description
category
risk_level
input_schema JSON
output_schema JSON nullable
active
requires_authentication
requires_confirmation
requires_human
created_at
updated_at
```

Risk levels:

```text
low
normal
sensitive
critical
```

Examples:

```text
search_products
get_product
get_my_order
track_my_order
get_my_cart
add_to_cart
create_support_ticket
request_human_agent
```

Do not expose actual database capabilities.

---

# 14. SupportAIToolPermission

Purpose:

Restrict tools beyond global configuration.

Logical concepts:

```text
tool_id
role/permission reference
customer_scope
enabled
configuration JSON nullable
timestamps
```

Implementation should integrate with the existing Spatie permission system rather than create a parallel role system.

---

# 15. SupportVoiceSession

Purpose:

Foundation for future voice support.

Phase 1 only creates the data model; it does not select or integrate a provider.

Logical fields:

```text
public_id
conversation_id
customer_id nullable
language nullable
status
started_at
ended_at nullable
duration_seconds nullable
transcript_message_count
provider nullable
provider_session_id nullable
metadata nullable
timestamps
```

Statuses may include:

```text
starting
active
processing
completed
failed
cancelled
```

Do not lock the system to Whisper, Gemini Audio, OpenAI Realtime, or any other provider in Phase 1.

---

# 16. SupportAuditLog

Purpose:

Provide the foundation for security/audit tracking.

Logical fields:

```text
actor_type
actor_id nullable
customer_id nullable
conversation_id nullable
ticket_id nullable
action
resource_type nullable
resource_id nullable
tool_name nullable
authorization_result nullable
before_data nullable
after_data nullable
metadata JSON nullable
ip_address nullable
user_agent nullable
created_at
```

Never store:

- passwords
- API keys
- access tokens
- private credentials
- complete secret payloads

Audit records must be append-oriented.

---

# 17. SupportFeedback

Purpose:

Store customer satisfaction after conversation/ticket resolution.

Logical fields:

```text
conversation_id
ticket_id nullable
customer_id nullable
rating
comment nullable
language nullable
metadata nullable
timestamps
```

Suggested rating:

```text
1–5
```

Do not require feedback for guest users if the existing authentication model makes that impractical.

---

# 18. SupportConversationTag

If tags are needed during Phase 1, create a simple extensible tagging structure.

Potential fields:

```text
name
slug
type
active
timestamps
```

Conversation-to-tag relationship:

```text
support_conversation_tag
```

Use tags later for:

- refund
- payment
- product inquiry
- urgent
- VIP
- technical
- delivery delay

---

# 19. Conversation Status Enum

Create an enum representing:

```text
NEW
AI_ACTIVE
AWAITING_CUSTOMER
AWAITING_AGENT
QUEUED
HUMAN_ACTIVE
RESOLVED
CLOSED
```

If the project prefers strings in database storage, use string-backed enums.

Do not use the legacy `ai/human/closed` values for the new schema.

---

# 20. Conversation Mode Enum

```text
AI
HUMAN
HYBRID
```

---

# 21. Priority Enum

```text
LOW
NORMAL
HIGH
URGENT
```

---

# 22. Channel Enum

```text
WEB
VOICE
```

Reserve future-safe values for:

```text
WHATSAPP
EMAIL
PHONE
```

but do not implement those channels in Phase 1.

---

# 23. Message Visibility

The schema must distinguish customer-visible content from internal/system content.

Use:

```text
is_internal
```

or an equivalent explicit visibility enum.

The customer API must never return internal notes.

Do not rely on Vue filtering.

Add backend tests proving internal messages are excluded.

---

# 24. Database Design Rules

## Migrations

All new tables must be created with normal Laravel migrations.

Do not use:

```php
Schema::create(...)
```

inside runtime services/controllers.

The existing prototype's runtime table creation must not be copied.

## IDs

Use the project's established Laravel ID conventions.

Public support IDs should not expose sequential internal IDs where avoidable.

## Indexes

Add useful indexes for:

```text
public_id
customer_id
conversation_id
status
assigned_agent_id
department_id
last_message_at
created_at
language
channel
priority
ticket status
```

Do not over-index blindly.

## Foreign keys

Use foreign keys where existing project conventions permit.

Respect existing user/customer relationships.

---

# 25. Security Scope Foundation

Phase 1 must establish backend scopes/policies for:

### Customer

Can only access:

- their conversations
- their public messages
- their tickets
- their orders through existing authorized services

### Agent

Can access conversations according to:

- agent permission
- department
- assignment
- support role

### Admin

Can access support administration according to existing Spatie permissions.

### Internal messages

Never appear in customer-facing APIs.

---

# 26. No AI Implementation Yet

Do not implement:

- model prompts
- tool execution
- provider selection logic
- automatic model fallback
- RAG
- voice transcription
- TTS
- chat cutover

Those belong to later phases.

Create only the contracts/interfaces needed so later phases do not need a destructive refactor.

---

# 27. Events

Create event classes/contracts only where useful for the new domain.

Potential events:

```text
SupportConversationCreated
SupportMessageCreated
SupportConversationAssigned
SupportConversationEscalated
SupportTicketCreated
SupportTicketResolved
```

Events should not yet depend on an external broadcast provider.

Broadcast implementation is intentionally deferred.

---

# 28. Realtime Decision — DEFERRED

Do not choose Pusher, Laravel Reverb, Ably, or another provider in Phase 1.

Phase 0 identified this as an unresolved deployment decision.

Phase 1 should only define domain events and ensure they can later be broadcast.

The implementation should not require a realtime provider just to pass Phase 1 tests.

---

# 29. Voice Decision — DEFERRED

Do not select:

- OpenAI Realtime
- Whisper
- Gemini Audio
- another STT/TTS vendor

during Phase 1.

Only establish the `SupportVoiceSession` domain object and provider-neutral interfaces if needed.

The final provider decision belongs to the voice implementation phase.

---

# 30. Seed Data

Create seeders for:

### Departments

- General Support
- Sales
- Orders
- Returns & Refunds
- Payments
- Technical Support
- Wholesale

### Baseline policies

At minimum:

```text
customer_data_scope
internal_note_visibility
prompt_injection_policy
refund_requires_approval
sensitive_action_confirmation
```

Seeded policies must be conservative.

Do not seed an “allow everything” policy.

---

# 31. Tests Required in Phase 1

## Model tests

Test:

- relationships
- casts
- enums
- scopes

## Authorization tests

Test:

1. customer can see own conversation
2. customer cannot see another customer's conversation
3. customer cannot see internal messages
4. agent access follows permission boundaries
5. unauthorized user cannot access support admin resources

## Database tests

Test:

- migrations
- foreign keys
- indexes where practical
- cascade behavior
- enum/value constraints

## Audit tests

Test that sensitive domain actions can generate audit records without secrets.

---

# 32. Backward Compatibility

During Phase 1:

- existing customer chat remains operational
- existing admin chat remains operational
- existing admin AI remains operational
- existing ecommerce behavior remains operational

Do not switch routes.

Do not remove old models.

Do not remove old migrations.

Do not migrate old data yet.

---

# 33. File Change Rules

Antigravity may create:

```text
app/Support/...
database/migrations/...
database/seeders/...
tests/Feature/Support/...
tests/Unit/Support/...
docs/...
```

It may update service-provider registration if required.

It may add imports/configuration needed for the new models.

It should avoid modifying unrelated application files.

---

# 34. Completion Criteria

Phase 1 is complete only when:

- all support-domain migrations run cleanly
- `php artisan migrate` succeeds
- rollback works for the new migrations
- models instantiate correctly
- relationships work
- seeders work
- authorization foundation tests pass
- internal-message isolation tests pass
- existing application tests still pass
- no existing chat routes/UI have been switched
- no legacy chat files are deleted
- no provider-specific realtime/voice implementation has been selected

---

# 35. Required Antigravity Output

When Phase 1 finishes, produce:

```text
docs/AI-SUPPORT-PHASE-1-REPORT.md
```

The report must contain:

1. migrations created
2. models created
3. enums created
4. policies/scopes created
5. seed data created
6. tests created
7. existing tests run
8. test results
9. files changed
10. any deviations from this specification
11. any architectural concerns discovered
12. exact Phase 2 recommendations

Do not simply report “done”.

Include the actual commands/tests run and whether they passed.

---

# 36. Phase 1 Stop Condition

After completing Phase 1, STOP.

Do not proceed automatically to Phase 2.

The next approval must be given explicitly after reviewing:

`docs/AI-SUPPORT-PHASE-1-REPORT.md`

Phase 2 will begin with the AI orchestrator, provider adapter, tool registry, policy engine, and controlled ecommerce tool layer.
