# 6ixCulture AI Support — Phase 4 Implementation Specification

**Repository:** `josephtersoo2-stack/6ixculture-app`  
**Phase:** 4 — Customer Support API & Modernized Vue 3 Assistant UI Widget  
**Prerequisites:** Phase 0 audit, Phase 1 domain foundation, Phase 2 orchestrator/tools, and Phase 3 knowledge/context grounding approved  
**Status:** DRAFT — READY FOR REVIEW

## 1. Goal

Build the production customer-facing API boundary and the new Vue 3 AI Support Assistant UI on top of the completed Support domain.

Target flow:

```text
Customer / Vue
      ↓
Support API
      ↓
Support Conversation
      ↓
SupportOrchestrator
      ↓
Policy / Tools / Knowledge
      ↓
Structured Support Response
      ↓
Support Message
      ↓
Vue Message Renderer
```

Phase 4 is the first phase in which the new Support system becomes usable by a real customer through the modern Vue interface.

The implementation must preserve the existing legacy chatbot as a rollback path until later production cutover.

---

## 2. Scope

Implement only:

- versioned customer Support API endpoints
- conversation creation/resume
- authenticated customer and guest session handling
- message submission
- AI response persistence
- structured response serialization
- conversation history retrieval
- customer-visible message filtering
- safe polling/update mechanism for the new chat flow
- confirmation/action response protocol for already-supported policy decisions
- new Vue 3 customer support assistant widget
- structured message renderers
- product/result cards
- order/status cards
- human-handoff presentation
- loading/error/empty states
- mobile-responsive chat experience
- language-aware UI plumbing
- frontend tests
- backend API/authorization/integration tests
- documentation

Do NOT implement yet:

- human support console
- agent inbox
- agent assignment UI
- admin support dashboard
- realtime provider/WebSockets/Reverb/Pusher/Ably
- voice STT/TTS
- WhatsApp/email/phone channels
- automatic refunds
- automatic cancellations
- payment changes
- account-sensitive mutations
- legacy chatbot deletion
- final production route cutover
- broad knowledge-base administration UI
- vector/embedding infrastructure

---

## 3. Existing Architecture That Must Remain Stable

Phase 4 must build on the completed Support layers.

Do not replace:

- `SupportConversation`
- `SupportMessage`
- `SupportTicket`
- `SupportPolicy`
- `SupportAITool`
- `SupportAIToolPermission`
- `SupportAuditLog`
- `SupportOrchestrator`
- `ToolRegistry`
- `SupportActionPolicyEngine`
- `SupportKnowledgeRepository`
- `SupportContextAssembler`
- existing AI provider abstraction
- existing ecommerce services

Phase 4 is an integration layer, not a redesign of Phases 1–3.

The new API must call the existing Support domain rather than duplicating its logic.

---

## 4. API Boundary

Create a versioned customer support API under:

```text
/api/v1/support/*
```

The exact route file and controller placement must follow the existing Laravel routing conventions.

The API must provide at minimum:

```text
POST   /api/v1/support/conversations
GET    /api/v1/support/conversations
GET    /api/v1/support/conversations/{conversation}
POST   /api/v1/support/conversations/{conversation}/messages
GET    /api/v1/support/conversations/{conversation}/messages
GET    /api/v1/support/conversations/{conversation}/updates
POST   /api/v1/support/conversations/{conversation}/actions/{action}
POST   /api/v1/support/conversations/{conversation}/request-human
POST   /api/v1/support/conversations/{conversation}/resolve
```

The exact route names may be adapted to existing Laravel conventions, but the responsibilities above must exist.

Do not expose internal model/database details directly through the API.

---

## 5. Conversation Creation / Resume

The customer must be able to:

- start a new support conversation;
- resume an existing active conversation;
- retrieve their recent support conversations;
- continue the conversation after page refresh;
- continue as the authenticated customer after login;
- continue as a guest using a server-generated guest session identifier.

### Authenticated customer

Identity must come from Laravel authentication.

Never accept:

```text
user_id
customer_id
account_id
```

from the browser as authoritative identity.

### Guest customer

Guest conversations may use a secure server-generated session/public token.

Guest users must remain restricted to public capabilities.

When a guest later authenticates, account linking must be handled by the backend rather than by a client-supplied customer ID.

---

## 6. Conversation Retrieval

Customer-facing retrieval must return only data the customer is authorized to see.

The API must never return:

- internal notes
- internal staff messages
- raw tool internals
- authorization metadata
- policy internals
- provider credentials
- hidden prompts
- server diagnostics
- audit records
- other customers' data

The backend must enforce message visibility.

Vue filtering is not a security boundary.

---

## 7. Message Submission

The message endpoint must:

1. authenticate or establish guest context;
2. authorize the conversation;
3. validate message size/content;
4. persist the customer message;
5. invoke `SupportOrchestrator`;
6. persist the AI response and any structured support messages;
7. return a safe structured response;
8. record relevant audit metadata where required.

The controller must remain thin.

Business logic belongs in Support services/orchestration.

Do not recreate Phase 2 or Phase 3 orchestration inside the controller.

---

## 8. Message Request Contract

The request should support the current Phase 4 channel:

```json
{
  "message": "Where is my order?",
  "language": "en",
  "client_message_id": "optional-client-generated-id"
}
```

The exact DTO/request structure should follow existing Laravel validation conventions.

Support:

- text input
- requested language
- idempotency/client message identifier where useful

Do not accept:

- arbitrary tool names
- arbitrary action names
- arbitrary policy overrides
- customer IDs
- authorization flags
- model/provider selection
- system prompts

---

## 9. Structured Response Contract

The API must return a provider-neutral Support response.

Example:

```json
{
  "conversation": {
    "id": "public-conversation-id",
    "status": "ai_active",
    "mode": "ai",
    "language": "en"
  },
  "messages": [
    {
      "id": "message-id",
      "sender": "ai",
      "type": "text",
      "content": "I found your latest order."
    },
    {
      "id": "message-id-2",
      "sender": "ai",
      "type": "order_status",
      "payload": {
        "order_id": "1234",
        "status": "shipped"
      }
    }
  ]
}
```

The frontend must not depend on raw Eloquent model serialization.

Create explicit API resources/transformers/DTO serializers.

---

## 10. Supported Customer Message Types

The Phase 4 API must be capable of delivering at least:

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
error
```

Voice/audio message types remain reserved for the later voice phase.

File/image ingestion remains deferred unless the existing API already supports a safe attachment abstraction.

---

## 11. Product Response Contract

Product responses must provide only customer-safe fields.

Potential fields:

```text
id
name
slug
thumbnail/image
price
formatted_price
compare_at_price nullable
availability
short_description
rating nullable
review_count nullable
variants nullable
```

Never expose internal:

- cost price
- supplier information
- inventory internals
- private margins
- staff notes
- secret identifiers
- internal configuration

The actual product fields must be mapped from the existing ecommerce models/services.

---

## 12. Order Response Contract

Order information must be scoped to the authenticated customer.

Potential customer-visible fields:

```text
order_reference
order_date
status
payment_status where customer-visible
shipping_status
tracking_reference where customer-visible
items
totals where customer-visible
estimated_delivery where authoritative
```

The API must not expose internal operational fields unless explicitly customer-visible in the existing application.

Ownership must continue to be enforced by the existing ecommerce services and Laravel authorization.

---

## 13. Human Handoff Response

Phase 4 does not build the human agent console.

It must, however, establish the customer-facing handoff experience.

When the AI requests/escalates to human support, return a structured message such as:

```text
type: escalation
```

with safe customer-facing information:

```json
{
  "status": "queued",
  "message": "I've connected you with our support team."
}
```

Do not expose:

- internal routing logic
- agent IDs
- internal department metadata
- internal AI reasoning
- staff notes
- escalation confidence scores

The customer UI should make it clear that a human request has been submitted.

---

## 14. Confirmation / Action Protocol

Phase 2 already contains policy handling for confirmation and sensitive-action boundaries.

Phase 4 should only implement the customer UI/API protocol needed to display and submit an already-authorized confirmation flow.

Example:

```text
AI
 ↓
ActionConfirmation
 ↓
Customer confirms
 ↓
Support API
 ↓
Policy Engine
 ↓
Allowed next step
```

The browser must not be allowed to tell Laravel:

```text
"authorization": true
```

or:

```text
"approved": true
```

without the backend treating that as an untrusted request.

Every confirmation must be revalidated server-side.

Do not activate dangerous mutations merely to prove the confirmation UI works.

---

## 15. Conversation Updates

Because realtime infrastructure is intentionally deferred until a later phase, Phase 4 should provide a safe update mechanism without selecting a realtime provider.

The preferred temporary mechanism is:

```text
GET /conversations/{conversation}/updates
```

or an equivalent bounded polling endpoint.

It should support:

- new messages
- status changes
- escalation state
- resolution state

Do not introduce:

- Pusher
- Laravel Reverb
- Ably
- WebSockets

in Phase 4.

The API should be designed so a later realtime transport can subscribe to the same event/data model.

---

## 16. Idempotency / Duplicate Messages

The customer UI must not accidentally create duplicate customer messages because of:

- double-click
- network retry
- browser retry
- page refresh
- slow response

Use a client message identifier or equivalent idempotency mechanism where practical.

Duplicate submission must not create duplicate AI turns.

Add regression tests for duplicate requests.

---

## 17. Rate Limits and Abuse Protection

Reuse Phase 2 Support rate-limiting behavior.

Phase 4 may expose appropriate API-level throttling, but must not weaken the existing AI abuse boundary.

At minimum protect:

- conversation creation
- message submission
- updates polling
- confirmation/action endpoints

Guest access must remain more restricted than authenticated access.

Do not hard-code contradictory limits into controllers.

---

## 18. Vue 3 Customer Assistant

Build the new customer-facing support interface inside the existing Vue architecture.

The UI must feel like a 6ixCulture customer-care/shopping assistant, not a generic ChatGPT clone.

Use the existing project conventions for:

- components
- router
- store
- composables
- services
- i18n
- styling

Do not introduce a second frontend state-management architecture.

---

## 19. Recommended Vue Components

Create/adapt components logically equivalent to:

```text
AiSupportWidget.vue
AiSupportWindow.vue
ChatHeader.vue
MessageList.vue
MessageBubble.vue
MessageRenderer.vue
MessageComposer.vue
TypingIndicator.vue
SuggestedPrompts.vue
HumanHandoff.vue
ActionConfirmation.vue

ProductCard.vue
ProductCarousel.vue
ProductComparison.vue

OrderCard.vue
OrderStatusCard.vue

CartActionCard.vue

ErrorMessage.vue
EmptyState.vue
ConnectionState.vue
```

Exact paths may follow the existing Vue structure discovered during implementation.

Do not blindly create duplicate components if equivalent reusable components already exist.

---

## 20. Frontend State

Use the project's existing store convention.

The support state must manage:

```text
currentConversation
conversationList
messages
isLoading
isSending
isTyping
error
connection/polling status
humanRequested
language
pendingConfirmation
```

Do not put authorization decisions in the Vue store.

Do not store secrets in browser state.

The frontend is responsible for presentation state, not business authorization.

---

## 21. Chat Rendering Model

Use a single message renderer:

```text
MessageRenderer
      ↓
message.type
      ↓
┌───────────────────────────┐
│ text                      │
│ product                   │
│ product_list              │
│ product_comparison        │
│ order                     │
│ order_status              │
│ cart                      │
│ action_confirmation       │
│ escalation                │
│ error                     │
└───────────────────────────┘
```

The renderer chooses the appropriate Vue component.

Never render model-generated HTML as trusted Vue markup.

Structured JSON payloads are the contract between Laravel and Vue.

---

## 22. Customer Chat Experience

Initial experience should include:

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

Customers should be able to:

- type naturally;
- see assistant responses stream/appear through the supported API mechanism;
- select suggested actions;
- open product cards;
- view order status;
- request human support;
- confirm supported actions;
- continue existing conversations.

Suggested prompts must be non-authoritative UI shortcuts only.

---

## 23. Product Shopping Experience

The assistant should be capable of returning structured product results such as:

```text
Product
Black Sneaker
₦55,000

[ View Product ]
[ Add to Cart ]
```

However, Phase 4 must only expose UI controls for actions actually supported by the backend.

Do not create frontend buttons for backend capabilities that do not yet exist.

Phase 4 may render future action placeholders only if clearly disabled and documented.

---

## 24. Mobile-First Design

The widget must work on:

- desktop
- tablet
- mobile

The experience should support:

- floating launcher
- responsive chat panel/window
- touch-friendly controls
- keyboard-safe composer
- scroll-to-latest behavior
- safe long-message rendering
- accessible focus management

Avoid blocking the main shopping experience.

---

## 25. Language UI Plumbing

Phase 4 should prepare the frontend for:

```text
en
yo
ig
ha
```

This includes:

- storing requested language
- sending language preference/context to API
- receiving language-aware messages
- localizing static UI strings
- preserving the selected language between turns

Full voice language processing remains deferred.

Do not implement separate business logic per language.

---

## 26. Customer Authentication Flow

The Vue client must rely on the existing Laravel authentication mechanism.

Authenticated customer:

```text
Vue
 ↓
existing auth/session/token
 ↓
Laravel
 ↓
Support authorization
```

Guest:

```text
Vue
 ↓
guest support session identifier
 ↓
Laravel
 ↓
public-scope support access
```

Never allow the browser to impersonate another customer by changing a request identifier.

---

## 27. API Errors

Return safe structured errors.

Examples:

```json
{
  "error": {
    "code": "SUPPORT_RATE_LIMITED",
    "message": "Please wait a moment before sending another message."
  }
}
```

or:

```json
{
  "error": {
    "code": "SUPPORT_CONVERSATION_NOT_FOUND",
    "message": "This conversation is no longer available."
  }
}
```

Do not expose:

- stack traces
- database errors
- SQL
- provider keys
- filesystem paths
- internal policy details
- raw AI provider responses

Log detailed diagnostics server-side where appropriate.

---

## 28. Security Tests

Add tests proving:

### Conversation ownership

- customer can create own conversation
- customer can access own conversation
- customer cannot access another customer's conversation

### Message visibility

- customer receives customer-visible messages
- internal staff messages are excluded
- hidden/system metadata is not serialized

### Message submission

- unauthorized conversation rejected
- oversized messages rejected
- invalid payload rejected
- duplicate client message does not duplicate the turn

### Structured output

- product payload serialized correctly
- order payload serialized correctly
- escalation payload serialized safely
- confirmation payload cannot bypass authorization

### Injection

- prompt injection remains contained
- structured payload cannot inject trusted HTML
- browser cannot submit arbitrary tool calls
- browser cannot override policy decisions

### Guest isolation

- guest cannot retrieve authenticated customer data
- guest cannot retrieve another guest's conversation

---

## 29. Integration Tests

At minimum test the complete flow:

```text
POST create conversation
        ↓
POST customer message
        ↓
SupportOrchestrator
        ↓
Knowledge / Tool
        ↓
SupportMessage persisted
        ↓
API response
        ↓
GET conversation/messages
```

Test examples:

1. “What is your return policy?”
2. “Where is my order?”
3. “Show me black sneakers.”
4. Unsupported/restricted request.
5. Prompt injection attempt.
6. Customer requests human support.
7. Customer confirms a supported confirmation flow.
8. Customer refreshes and resumes conversation.

The exact AI provider used in integration tests should follow existing project test conventions and must not require production secrets for deterministic tests.

---

## 30. Legacy Chat Compatibility

Phase 4 must leave the legacy system operational.

Keep:

```text
ChatController
AdminChatController
ChatService
ChatConversation
ChatMessage
```

and their existing routes/UI as the fallback until later cutover.

Do not:

- delete legacy chat files
- remove legacy database tables
- change the existing public route globally
- migrate historical chat data

Phase 4 is an additive customer-facing integration step.

---

## 31. API/Frontend Documentation

Create/update documentation describing:

- support API endpoints
- request/response contracts
- message types
- structured payloads
- authentication behavior
- guest behavior
- polling/update mechanism
- error codes
- frontend component mapping
- legacy fallback behavior

The documentation must be understandable to the next implementation phase.

---

## 32. File Change Rules

Antigravity may create or modify files in:

```text
app/Http/Controllers/...
app/Http/Requests/...
app/Http/Resources/...
app/Support/...
routes/...
resources/js/components/...
resources/js/composables/...
resources/js/services/...
resources/js/store/...
resources/js/router/...
tests/Feature/Support/...
tests/Unit/Support/...
tests/...
docs/...
```

Only modify existing ecommerce files where the new API genuinely needs an integration point.

Do not restructure unrelated Vue or Laravel modules.

Do not modify the existing admin support console in this phase.

---

## 33. Testing Commands

At minimum run:

```bash
php artisan test --filter=Support
php artisan test
```

For the frontend, use the project's existing test/build/lint commands.

Also verify:

```bash
php artisan route:list
```

for the new support endpoints.

The phase report must include the exact commands run and their results.

---

## 34. Completion Criteria

Phase 4 is complete only when:

- customer can create a support conversation;
- authenticated customer can resume their conversation;
- guest can use public-scope support;
- customer can send a message through the new Support API;
- SupportOrchestrator is invoked through the API;
- AI responses are persisted as SupportMessage records;
- structured messages are returned through explicit API resources;
- customer cannot access another customer's conversation;
- internal messages cannot reach the customer;
- prompt injection does not bypass the server boundary;
- duplicate message submission is handled safely;
- support API errors are sanitized;
- customer Vue assistant is functional;
- product results render as structured cards;
- order status renders as structured cards;
- human handoff renders as structured UI;
- confirmation UI is backed by server-side revalidation;
- language selection/plumbing works for `en`, `yo`, `ig`, and `ha`;
- mobile layout works;
- legacy chatbot remains operational;
- realtime provider has not been introduced;
- voice provider has not been introduced;
- human console has not been introduced;
- sensitive mutations remain deferred;
- targeted support tests pass;
- full regression suite passes;
- documentation/report is complete.

---

## 35. Documentation Deliverable

Create:

```text
docs/AI-SUPPORT-PHASE-4-REPORT.md
```

The report must include:

- implementation summary
- API routes
- request/response contracts
- authentication/guest behavior
- structured message architecture
- Vue components created/modified
- frontend state management
- polling/update mechanism
- security controls
- tests and exact results
- legacy compatibility status
- known limitations
- recommendation for Phase 5

---

## 36. Stop Condition

After:

- Support API
- authentication/guest handling
- conversation/message flow
- structured response serialization
- customer Vue assistant
- message renderers
- human handoff UI
- confirmation protocol
- security/integration tests
- documentation

are complete:

**STOP.**

Do not start:

- human support console
- agent assignment
- realtime infrastructure
- voice/STT/TTS
- WhatsApp/email/phone
- legacy chatbot deletion
- production cutover
- new sensitive ecommerce mutations

The Phase 4 report is the next review checkpoint before Phase 5 is authorized.
