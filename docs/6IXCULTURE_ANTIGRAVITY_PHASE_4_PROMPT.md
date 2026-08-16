# 6ixCulture AI Support — Antigravity Phase 4 Execution Prompt

**Repository:** `josephtersoo2-stack/6ixculture-app`
**Branch:** `main`
**Phase:** Phase 4 — Customer Support API & Modernized Vue 3 Assistant UI Widget

---

# 1. AUTHORIZATION

Phase 0, Phase 1, Phase 2, and Phase 3 have been reviewed and approved.

You are authorized to implement **Phase 4 only**.

Before changing code, read these documents from the repository:

```text
docs/AI-SUPPORT-AUDIT.md
docs/6IXCULTURE_AI_SUPPORT_IMPLEMENTATION_PLAN.md
docs/6IXCULTURE_AI_SUPPORT_PHASE_1_SPEC.md
docs/6IXCULTURE_AI_SUPPORT_PHASE_2_SPEC.md
docs/AI-SUPPORT-PHASE-2-REPORT.md
docs/6IXCULTURE_AI_SUPPORT_PHASE_3_SPEC.md
docs/AI-SUPPORT-PHASE-3-REPORT.md
docs/6IXCULTURE_AI_SUPPORT_PHASE_4_SPEC.md
```

Also inspect the actual repository implementation produced by Phases 1–3.

Do not rely only on the documentation.

The codebase is authoritative when implementation details differ from assumptions in documentation.

---

# 2. PRIMARY OBJECTIVE

Build the production customer-facing Support API and the new Vue 3 AI Support Assistant.

Target architecture:

```text
Customer Browser
       ↓
Vue 3 Support Assistant
       ↓
Laravel Support API
       ↓
SupportConversation
       ↓
SupportOrchestrator
       ↓
Policy Engine
       ↓
Tool Registry / Knowledge
       ↓
Existing Laravel Ecommerce Services
       ↓
Structured Support Response
       ↓
SupportMessage
       ↓
Vue Message Renderer
```

The goal is to make the new Support architecture usable by customers without prematurely replacing or deleting the existing chatbot.

---

# 3. NON-NEGOTIABLE RULE

This is a controlled Phase 4 implementation.

Do not expand the scope.

Do not redesign the Support domain created in Phases 1–3 unless a concrete integration defect makes it necessary.

Do not introduce unrelated architectural changes.

Do not "improve" unrelated ecommerce code.

Do not start Phase 5 work.

---

# 4. READ THE EXISTING CODE FIRST

Before implementation, inspect the actual repository and identify:

## Backend

```text
routes/api.php
routes/web.php if relevant

app/Support/**
app/Http/Controllers/**
app/Http/Requests/**
app/Http/Resources/**
app/Services/**
app/Models/**
app/Policies/**
app/Providers/**

existing authentication configuration
Sanctum configuration
existing API response conventions
existing exception handling
existing rate limiting
```

## Existing chat

Find and inspect:

```text
ChatController
AdminChatController
ChatService
ChatConversation
ChatMessage
legacy chat routes
legacy Vue chat components
legacy chat services/store/composables
```

Do not remove these files.

## Vue

Inspect the actual structure under:

```text
resources/js/
```

Determine the existing conventions for:

```text
components
services
store
router
composables
languages
i18n
```

Do not invent a parallel frontend architecture.

Reuse existing conventions.

---

# 5. PHASE 4 SCOPE

Implement only:

```text
Support API
Conversation creation/resume
Authenticated customer support
Guest support session
Message submission
Conversation retrieval
Customer-visible message filtering
Structured response serialization
Conversation updates/polling
Human handoff request
Safe confirmation/action protocol
Vue 3 customer assistant
Structured message renderers
Product cards
Product result lists
Order/status cards
Escalation UI
Loading/error states
Language plumbing
Mobile responsive experience
Backend tests
Frontend tests/build checks
Documentation
```

Do not implement:

```text
Human support console
Agent inbox
Agent assignment UI
Realtime provider
WebSockets
Pusher
Laravel Reverb
Ably
Voice
STT
TTS
WhatsApp
Email
Phone support
Automatic refunds
Automatic cancellations
Payment mutations
Sensitive account mutations
Legacy chatbot deletion
Production cutover
Knowledge admin UI
Vector database
Embeddings
```

---

# 6. EXISTING SUPPORT ARCHITECTURE MUST BE REUSED

Use the existing implementations from Phases 1–3.

These include:

```text
SupportConversation
SupportMessage
SupportTicket
SupportPolicy
SupportAITool
SupportAIToolPermission
SupportAuditLog

SupportOrchestrator
SupportActionPolicyEngine
ToolRegistry

SupportKnowledgeRepository
SupportContextAssembler
PublicStoreContextProvider

AiProviderInterface
existing provider adapters

existing ecommerce services
```

Do not create a second orchestrator.

Do not create a second policy engine.

Do not create a second AI provider credential system.

Do not recreate product/order business logic.

---

# 7. SUPPORT API

Create the new versioned Support API using the repository's existing Laravel conventions.

Logical responsibilities:

```text
POST   /api/v1/support/conversations

GET    /api/v1/support/conversations

GET    /api/v1/support/conversations/{conversation}

POST   /api/v1/support/conversations/{conversation}/messages

GET    /api/v1/support/conversations/{conversation}/messages

GET    /api/v1/support/conversations/{conversation}/updates

POST   /api/v1/support/conversations/{conversation}/request-human

POST   /api/v1/support/conversations/{conversation}/resolve

POST   /api/v1/support/conversations/{conversation}/actions/{action}
```

You may adapt naming to existing project route conventions, but all required responsibilities must exist.

First inspect whether any equivalent route already exists.

Avoid duplicate routes/controllers.

---

# 8. CONTROLLER DESIGN

Controllers must remain thin.

Controllers should:

```text
authenticate/resolve guest context
validate request
authorize conversation
call Support services/orchestrator
serialize response
return HTTP response
```

Controllers must NOT:

```text
build AI prompts
execute ecommerce business logic
perform tool authorization manually
query unrelated databases directly
duplicate SupportOrchestrator logic
duplicate policy engine logic
construct model-specific responses
```

---

# 9. AUTHENTICATED CUSTOMER IDENTITY

Never trust customer identity from browser input.

Do not accept arbitrary:

```text
user_id
customer_id
account_id
```

as the authoritative identity.

For authenticated requests:

```php
$request->user()
```

or the application's established authenticated-user mechanism must determine identity.

All customer-scoped tools and conversation access must use server-derived identity.

---

# 10. GUEST SUPPORT

Support public guest conversations through the existing application's session/auth conventions.

Generate a secure guest support identifier server-side where needed.

Guest users may access only public/support-safe functionality.

Guests must not gain access to:

```text
customer orders
private account data
private addresses
customer-specific tools
internal information
```

When an authenticated customer resumes a conversation, the backend must determine whether the conversation can safely be linked to that identity.

Never accept a client-supplied customer ID as proof of ownership.

---

# 11. CONVERSATION CREATION

Implement creation/resume behavior using `SupportConversation`.

The API must support:

```text
create new conversation
resume active conversation
list customer's conversations
retrieve one conversation
```

Use the Support domain relationships already created.

Do not create a new conversation model.

Do not create legacy-style dynamic tables.

---

# 12. MESSAGE SUBMISSION FLOW

Implement:

```text
POST message
      ↓
Validate
      ↓
Authorize conversation
      ↓
Persist customer SupportMessage
      ↓
SupportOrchestrator
      ↓
Tool / Knowledge processing
      ↓
Persist AI SupportMessage
      ↓
Serialize response
      ↓
Return to Vue
```

The AI response must use the Phase 2/3 pipeline.

Do not bypass it.

---

# 13. STRUCTURED API RESPONSES

Do not expose raw Eloquent models directly.

Create explicit resources/DTO serializers using repository conventions.

Responses should represent:

```text
conversation
messages
status
mode
language
structured payload
safe metadata
```

Example:

```json
{
  "conversation": {
    "id": "public-id",
    "status": "ai_active",
    "mode": "ai",
    "language": "en"
  },
  "messages": [
    {
      "id": "message-id",
      "sender": "ai",
      "type": "text",
      "content": "I found some options for you."
    },
    {
      "id": "message-id-2",
      "sender": "ai",
      "type": "product_list",
      "payload": {
        "products": []
      }
    }
  ]
}
```

The actual schema should follow the Phase 4 specification and existing repository conventions.

---

# 14. MESSAGE VISIBILITY

Customer API responses must exclude:

```text
internal notes
staff-only messages
internal tool diagnostics
audit records
policy internals
provider internals
hidden metadata
```

This must be enforced on the Laravel backend.

Do not rely on Vue to hide internal messages.

Add automated tests proving this boundary.

---

# 15. PRODUCT PAYLOADS

Use existing ecommerce product data/services.

Expose only customer-safe fields.

Possible fields:

```text
id
name
slug
image
price
formatted_price
compare_at_price
availability
short_description
rating
review_count
variants
```

Do not expose:

```text
cost
supplier
margin
private stock information
internal identifiers
staff notes
```

Do not duplicate product business rules inside Support.

---

# 16. ORDER PAYLOADS

Use the existing order services.

Enforce authenticated ownership.

Customer-visible order information may include:

```text
order_reference
order_date
status
shipping_status
payment_status if public
tracking_reference if public
items
customer-visible totals
customer-visible delivery information
```

Do not expose internal operational information.

---

# 17. HUMAN HANDOFF

Implement only the customer-facing handoff flow.

When escalation/human request occurs:

```text
AI
 ↓
Support policy
 ↓
Support escalation
 ↓
SupportConversation state
 ↓
customer-facing escalation message
```

Example structured response:

```json
{
  "type": "escalation",
  "payload": {
    "status": "queued"
  }
}
```

Customer-facing text should explain that support has been requested.

Do not build the agent console.

Do not expose:

```text
agent IDs
internal queue logic
AI reasoning
internal summaries
staff notes
internal routing metadata
```

---

# 18. CONFIRMATION FLOW

Support the existing Phase 2 policy model.

The UI may display:

```text
ActionConfirmation
```

and send a confirmation request to the backend.

However:

The browser confirmation is NOT an authorization decision.

The server must revalidate:

```text
conversation
customer
tool/action
policy
permissions
confirmation state
```

before doing anything.

Do not introduce new sensitive mutation capabilities.

---

# 19. POLLING / UPDATES

Realtime is deferred.

Provide a bounded polling/update mechanism using Laravel APIs.

Possible logical endpoint:

```text
GET /api/v1/support/conversations/{conversation}/updates
```

It should expose only customer-visible updates.

Support:

```text
new messages
conversation status
escalation state
resolution state
```

Do not introduce a realtime provider in Phase 4.

The design should remain compatible with future event broadcasting.

---

# 20. IDEMPOTENCY

Protect message submission from duplication.

The frontend may send:

```text
client_message_id
```

or an equivalent idempotency key.

A retry of the same client message must not cause multiple AI turns.

Add backend tests.

---

# 21. RATE LIMITING

Reuse the Support AI rate-limiting boundaries established in Phase 2.

Do not create contradictory hard-coded limits.

Protect:

```text
conversation creation
message submission
updates polling
confirmation/action endpoints
```

Guest users remain more restricted.

---

# 22. VUE 3 IMPLEMENTATION

Use the existing Vue 3 architecture.

First inspect existing components and determine whether pieces of the current chat can be safely reused.

The new assistant should be logically equivalent to:

```text
AiSupportWidget
AiSupportWindow
ChatHeader
MessageList
MessageBubble
MessageRenderer
MessageComposer
TypingIndicator
SuggestedPrompts
HumanHandoff
ActionConfirmation

ProductCard
ProductCarousel
ProductComparison

OrderCard
OrderStatusCard

CartActionCard

ErrorMessage
EmptyState
ConnectionState
```

Do not blindly create duplicate components if the repository already has compatible components.

---

# 23. FRONTEND STATE

Use the repository's current Vue state-management conventions.

State must manage:

```text
currentConversation
conversationList
messages
isLoading
isSending
isTyping
error
polling/update state
humanRequested
language
pendingConfirmation
```

Do not put authorization decisions into frontend state.

Do not put provider API keys/secrets into frontend state.

---

# 24. MESSAGE RENDERER

Use one central renderer:

```text
MessageRenderer
        ↓
message.type
        ↓
appropriate Vue component
```

Supported types:

```text
text
product
product_list
product_comparison
order
order_status
cart
action_confirmation
escalation
error
system where customer-safe
```

Do not render model-generated HTML.

Do not use unsafe raw HTML rendering for AI responses unless there is already a project-safe sanitized markdown/rendering mechanism.

Structured payloads remain authoritative for interactive components.

---

# 25. CUSTOMER EXPERIENCE

The first experience should resemble:

```text
Store Assistant
● Online

Hi 👋
What can I help you with today?

[ Find a product ]
[ Track my order ]
[ Return an item ]
[ Talk to support ]
```

The widget should support:

```text
natural-language input
conversation history
suggested prompts
product cards
product lists
order status
human handoff
safe confirmation UI
error recovery
mobile layout
```

The styling must follow the existing 6ixCulture design system.

Do not create a generic ChatGPT clone.

---

# 26. MOBILE SUPPORT

Verify:

```text
desktop
tablet
mobile
```

The widget must be:

```text
responsive
touch friendly
keyboard usable
scroll stable
accessible
```

The main ecommerce page must remain usable.

---

# 27. LANGUAGE SUPPORT

Prepare UI/API plumbing for:

```text
en
yo
ig
ha
```

The frontend must:

```text
store language preference
send language preference
receive localized responses
localize static UI strings
preserve selected language
```

Do not implement voice yet.

Do not create separate business logic per language.

---

# 28. ERROR HANDLING

Use safe API errors.

Example:

```json
{
  "error": {
    "code": "SUPPORT_RATE_LIMITED",
    "message": "Please wait a moment before sending another message."
  }
}
```

Never return:

```text
stack trace
SQL
filesystem path
API key
secret
provider credential
hidden prompt
internal policy
```

Log detailed server diagnostics using existing application logging conventions.

---

# 29. LEGACY CHAT MUST REMAIN OPERATIONAL

Do not delete:

```text
ChatController
AdminChatController
ChatService
ChatConversation
ChatMessage
```

Do not remove:

```text
legacy chat routes
legacy chat UI
legacy database tables
```

Do not perform the final production route cutover.

Phase 4 must be capable of operating alongside the legacy chatbot.

---

# 30. TESTING

Add backend tests for:

```text
create conversation
resume conversation
customer authorization
guest isolation
message submission
internal-message filtering
structured response serialization
product payload
order payload
human handoff
confirmation revalidation
duplicate message protection
rate limits
prompt injection
safe errors
```

Add frontend tests where the existing project supports them.

At minimum verify the important interaction states and component rendering.

Run:

```bash
php artisan test --filter=Support
php artisan test
```

Run the project's existing frontend test/build/lint commands.

Run:

```bash
php artisan route:list
```

and verify the new Support endpoints.

Do not declare success without actually running the tests.

---

# 31. DOCUMENTATION

Create:

```text
docs/AI-SUPPORT-PHASE-4-REPORT.md
```

The report must document:

```text
implementation summary
API endpoints
request/response contracts
authentication
guest sessions
structured messages
Vue components
state management
polling/update mechanism
security controls
tests
exact test results
legacy compatibility
known limitations
Phase 5 recommendation
```

Do not claim a test passed unless it was actually executed.

---

# 32. FILE CHANGE BOUNDARY

Prefer changes only in:

```text
app/Http/Controllers/**
app/Http/Requests/**
app/Http/Resources/**
app/Support/**
routes/**
resources/js/components/**
resources/js/services/**
resources/js/composables/**
resources/js/store/**
resources/js/router/**
tests/Feature/Support/**
tests/Unit/Support/**
docs/**
```

Avoid unrelated ecommerce changes.

If an unrelated file must be changed, explain why in the Phase 4 report.

---

# 33. IMPLEMENTATION ORDER

Follow this order:

## Step 1 — Repository inspection

Inspect actual implementation and existing conventions.

## Step 2 — API contracts

Define request/response DTOs/resources.

## Step 3 — Conversation endpoints

Implement:

```text
create
list
retrieve/resume
```

## Step 4 — Message endpoint

Implement:

```text
submit customer message
→ orchestrator
→ persist response
→ return structured result
```

## Step 5 — Updates endpoint

Implement safe polling/update retrieval.

## Step 6 — Human handoff endpoint/protocol

Expose only customer-safe state.

## Step 7 — Confirmation protocol

Implement UI/API confirmation flow with backend revalidation.

## Step 8 — Vue support state/services

Connect API to the existing Vue architecture.

## Step 9 — Vue assistant UI

Build the customer-facing assistant.

## Step 10 — Structured message renderers

Implement:

```text
text
products
orders
actions
handoff
errors
```

## Step 11 — Mobile/accessibility pass

Verify desktop/tablet/mobile.

## Step 12 — Security/integration tests

Run full regression suite.

## Step 13 — Documentation

Create Phase 4 report.

---

# 34. DO NOT PROCEED TO PHASE 5

Even if you discover that the human console would be easy to build, do not implement it now.

Do not implement:

```text
agent queue
agent dashboard
agent assignment UI
internal notes UI
agent copilot UI
department console
SLA dashboard
```

Those belong to Phase 5.

---

# 35. STOP CONDITION

Phase 4 is complete only when:

* Support API works;
* authenticated customer flow works;
* guest flow works;
* conversation resume works;
* customer messages work;
* AI responses work through `SupportOrchestrator`;
* structured messages are returned;
* customer authorization is enforced;
* internal messages are excluded;
* duplicate submissions are handled;
* safe errors are returned;
* human handoff works from the customer perspective;
* confirmation UI/backend protocol works without bypassing policy;
* Vue assistant is functional;
* product cards render;
* order cards render;
* escalation UI renders;
* language plumbing works;
* mobile experience works;
* regression tests pass;
* documentation report is created;
* legacy chatbot remains operational.

Then:

# STOP.

Do not proceed to Phase 5.

---

# 36. FINAL REPORT

Create:

```text
docs/AI-SUPPORT-PHASE-4-REPORT.md
```

At the end of the implementation report include:

```text
Phase 4 Status: COMPLETE / BLOCKED

Commit:
<commit hash>

Working tree:
Clean / Not clean

Tests:
<exact results>

Next Phase:
Phase 5 — Human Support Console
```

If any required Phase 4 item cannot be completed, do not silently skip it.

Document:

```text
what failed
why it failed
what remains
what must be resolved before Phase 5
```

Then stop.
