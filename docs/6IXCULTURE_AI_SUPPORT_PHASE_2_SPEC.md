# 6ixCulture AI Support — Phase 2 Implementation Specification

**Repository:** `josephtersoo2-stack/6ixculture-app`  
**Phase:** 2 — AI Orchestrator, Provider Adapter, Policy Engine & Tool Registry  
**Prerequisites:** Phase 0 audit and Phase 1 domain foundation approved  
**Status:** APPROVED TO IMPLEMENT

## 1. Goal

Build the secure server-side AI execution layer between the future customer chat UI and existing Laravel ecommerce services.

Target flow:

```text
Customer message
→ Support API
→ SupportOrchestrator
→ AI provider adapter
→ model response / tool call
→ PolicyEngine
→ ToolRegistry
→ controlled tool
→ existing Laravel business service
→ structured ToolResult
→ AI response
→ SupportMessage
```

The AI must never directly access the database, filesystem, environment, codebase, or arbitrary Laravel internals.

## 2. Scope

Implement only:

- AI orchestrator
- provider adapter abstraction
- adapters around the existing AI gateway
- policy engine
- tool registry
- tool schema validation
- structured tool-call/result pipeline
- safe error handling
- AI interaction audit logging
- initial low-risk/read-only ecommerce tools
- unit/feature/security tests
- documentation

Do **not** implement yet:

- customer Vue chat replacement
- human support console
- voice STT/TTS
- realtime provider
- WhatsApp/email/phone channels
- full knowledge-base admin UI
- sensitive mutations such as refunds/cancellations
- production cutover
- deletion of legacy chatbot

## 3. Reuse Existing AI Gateway

Reuse the existing provider foundation identified in Phase 0:

- `AiService`
- `AiAgentService`
- `AiAbstract`
- existing Gemini/OpenRouter/OpenAI adapters
- existing `AiAgent` / `GatewayOption` configuration

Do not build a second credential-management system.

Create a Support-side provider abstraction around the existing infrastructure.

The support domain must depend on an interface, not a concrete vendor.

## 4. SupportOrchestrator

Create the central `SupportOrchestrator`.

Responsibilities:

1. accept authenticated/guest conversation context
2. validate the inbound message
3. build minimal model context
4. select provider/model from safe server configuration
5. send messages to the provider
6. detect tool calls
7. pass every tool call through `PolicyEngine`
8. execute allowed tools through `ToolRegistry`
9. feed `ToolResult` back to the model when appropriate
10. produce a structured AI response
11. persist the AI message
12. persist tool/audit activity
13. return a safe DTO

Do not place ecommerce business logic in the orchestrator.

## 5. Provider Contract

Create a provider-neutral contract supporting:

```text
chat()
supportsToolCalling()
supportsStructuredOutput()
supportsStreaming()
```

Normalize vendor responses into a common format covering:

- assistant text
- tool calls
- provider errors
- token/usage metadata
- model/provider metadata
- finish reason

Streaming may remain deferred if the existing gateway does not support it cleanly.

## 6. Tool Interface and Registry

Use the Phase 1 `ToolInterface`.

Each tool defines:

```text
key()
name()
description()
inputSchema()
execute()
```

Implement `ToolRegistry` to:

- register active tools
- expose schemas
- reject unknown tools
- validate tool input
- check activation/permissions
- invoke policy evaluation
- execute permitted tools
- normalize failures

Unknown or invalid tools must fail closed.

## 7. Policy Engine

Implement the Phase 1 `PolicyEngineInterface`.

Every tool call must pass:

```text
ToolCallDTO
+
SupportConversation
+
authenticated customer context
```

through the policy engine before execution.

Support:

```text
ALLOW
DENY
CONFIRM
REQUIRE_VERIFICATION
REQUIRE_HUMAN
```

Phase 2 should fully support all five effects, but only read-only tools should actually execute automatically.

## 8. Policy Rules

At minimum enforce:

- customer ownership
- guest restrictions
- internal-information exclusion
- inactive-tool denial
- disabled permission denial
- sensitive/critical tool gating
- server-derived authentication identity

The model never makes authorization decisions.

## 9. Initial Tools — Read Only

Implement only:

### `search_products`

Inputs:

```text
query
category optional
min_price optional
max_price optional
limit optional
```

Output structured product results.

### `get_product_details`

Input:

```text
product_id
```

Verify product is active/customer-visible.

### `get_my_orders`

No arbitrary customer ID.

Authenticated customer comes from Laravel server-side auth.

### `track_my_order`

Input:

```text
order_id OR order_tracking_reference
```

Verify ownership before returning status.

## 10. Deferred Tools

Do **not** execute yet:

```text
add_to_cart
remove_from_cart
update_cart_quantity
cancel_order
request_refund
change_address
payment_change
account_change
```

They will be introduced in later phases after read-only tools are validated.

## 11. Existing Ecommerce Services

Reuse audited services such as:

```text
ProductService
ProductVariationService
ProductReviewService
FrontendOrderService
OrderService
CustomerService
AddressService
ReturnOrderService
ReturnAndRefundService
ShippingSetupService
```

Create only thin Support adapters where required. Do not duplicate ecommerce business rules.

## 12. Structured Responses

Phase 2 must normalize responses for:

```text
text
product
product_list
order
order_status
error
```

Return structured payloads for future Vue rendering.

Do not let the model generate raw Vue templates or HTML.

## 13. Context Rules

Do not reproduce the legacy behavior of putting broad customer data/recent orders into the system prompt.

Instead:

- identity remains server-side
- customer data is fetched through tools
- only necessary results are exposed to the model
- sensitive/internal fields are filtered before model context

## 14. Prompt Injection

Test attempts to:

- reveal system prompt
- expose source code
- expose database
- expose secrets
- impersonate admin
- list other customers
- run SQL

The model must receive no capability that would make these requests possible.

Prompt instructions are not the security boundary.

## 15. Logging/Audit

Record safe metadata such as:

- conversation
- message/tool call
- provider
- model
- tool
- policy result
- success/failure
- latency
- usage
- error category

Never log:

- passwords
- API keys
- access tokens
- secret headers
- credential payloads

Use Phase 1 `SupportAuditLog`.

## 16. Rate Limits

Establish the Support AI abuse boundary for:

- AI turns
- tool calls
- message size
- context size
- repeated failures

Guests should be more restricted than authenticated customers.

## 17. Tests

Test:

- provider normalization
- provider failure/fallback
- tool-call parsing
- policy allow/deny/confirm/verification/human
- unknown/inactive/disabled tools
- schema validation
- customer ownership
- guest restrictions
- prompt injection
- cross-customer access
- internal-field leakage
- simple text orchestration
- one read-only tool call
- tool failure
- safe error response

Run targeted tests plus the existing regression suite.

## 18. Legacy Safety

Do not modify or delete the existing customer chatbot during Phase 2.

Keep:

```text
ChatController
AdminChatController
ChatService
ChatConversation
ChatMessage
```

and their current routes/UI operational.

Phase 2 is additive.

## 19. Documentation

Create:

`docs/AI-SUPPORT-PHASE-2-REPORT.md`

Include:

- architecture implemented
- provider design
- orchestrator flow
- policy engine
- tool registry
- tools implemented
- security controls
- exact test commands/results
- limitations
- Phase 3 recommendations

## 20. Stop Condition

After the orchestrator, provider abstraction, policy engine, tool registry, read-only tools, structured response pipeline, security tests, and documentation are complete:

**STOP.**

Do not start customer UI, human console, voice, realtime, or sensitive mutations.
