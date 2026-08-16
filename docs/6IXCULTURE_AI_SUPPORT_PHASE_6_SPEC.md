# 6ixCulture AI Support — Phase 6 Implementation Specification

**Repository:** `josephtersoo2-stack/6ixculture-app`  
**Phase:** 6 — Realtime Support Transport & Voice Interface  
**Prerequisites:** Phase 0–5 approved  
**Status:** DRAFT — READY FOR REVIEW

## 1. Goal

Add realtime transport and voice interaction to the existing Support platform without creating a second conversation/business-logic path.

Text, realtime, and voice must converge on the same canonical SupportConversation and authorization architecture.

```text
Customer / Agent
       ↓
Realtime / Voice Transport
       ↓
Support Conversation
       ↓
SupportOrchestrator / Agent Support APIs
       ↓
Policy / Tools / Knowledge
       ↓
Support Messages / Events
       ↓
Realtime / Voice Response
```

Voice is an interface, not a separate business domain.

---

## 2. Scope

Implement only:

- realtime event transport for support conversations
- customer-side realtime updates
- agent-side realtime updates
- conversation/message events
- queue/assignment/status events
- typing/presence events where safely useful
- voice session lifecycle
- speech-to-text adapter
- text-to-speech adapter
- voice message/transcript integration
- supported-language plumbing for English, Yoruba, Igbo, Hausa
- interruption/cancel handling
- reconnect/failure handling
- authorization for realtime subscriptions
- voice authorization through the existing Support conversation
- audit/logging for voice/realtime security events
- backend tests
- frontend tests
- adapter/provider tests
- documentation

Do NOT implement:

- new commerce mutation powers
- refunds/cancellations
- legacy migration
- production cutover
- legacy chatbot deletion
- WhatsApp/email/phone channels
- knowledge/policy administration
- arbitrary new AI tools

---

## 3. Existing Architecture Must Remain Canonical

Reuse:

```text
SupportConversation
SupportMessage
SupportAgentProfile
SupportAssignment
SupportAuditLog
SupportOrchestrator
SupportActionPolicyEngine
ToolRegistry
SupportKnowledgeRepository
SupportContextAssembler
existing AI provider abstraction
existing Laravel auth
existing Vue/Vuex architecture
```

Do not create:

```text
VoiceConversation
RealtimeConversation
VoiceOrderService
```

The same SupportConversation remains authoritative.

---

## 4. Realtime Transport

Use a Laravel-compatible realtime transport consistent with the repository and current Laravel deployment.

The implementation must be abstract enough that the business layer does not depend on one broadcaster/provider.

Logical event flow:

```text
Support domain event
      ↓
broadcast/transport adapter
      ↓
authorized subscriber
      ↓
Vue support client
```

Events should be derived from existing Support domain state/events rather than allowing clients to publish authoritative support events.

### Customer events

At minimum:

```text
support.message.created
support.conversation.updated
support.conversation.escalated
support.conversation.resolved
support.typing.started
support.typing.stopped
```

### Agent events

At minimum:

```text
support.queue.updated
support.conversation.message.created
support.conversation.assigned
support.conversation.status.changed
support.conversation.priority.changed
support.conversation.department.changed
support.typing.started
support.typing.stopped
support.agent.presence.changed
```

Do not expose internal notes to customer channels.

---

## 5. Channel Authorization

Realtime channel authorization is a security boundary.

### Customer

A customer may subscribe only to conversations they own or validly possess through the existing guest-session model.

### Agent

An agent may subscribe only to conversations within the same resource scope enforced by Phase 5:

```text
Agent
 ↓
authorized support scope
 ↓
department / direct assignment
 ↓
conversation
```

### Admin/Manager

Use the existing elevated support authorization.

Do not trust public conversation IDs alone.

Do not authorize realtime subscriptions from browser-supplied customer/agent IDs.

---

## 6. Event Payloads

Realtime payloads must use explicit DTO/resource-style payloads.

Do not broadcast raw Eloquent models.

Do not include:

```text
passwords
tokens
API keys
internal prompts
provider secrets
raw audit internals
private customer data unrelated to the event
```

Customer payloads must continue to exclude:

```text
internal notes
staff-only metadata
agent-only routing details
```

Agent payloads must obey Phase 5 resource scope.

---

## 7. Event Ordering / Idempotency

Clients must tolerate:

- duplicate events
- late events
- reconnects
- out-of-order events

Use stable message/event IDs and timestamps.

The client should reconcile against the canonical conversation/message API after reconnect.

Realtime is an acceleration layer, not the source of truth.

---

## 8. Polling Fallback

Do not remove the existing polling mechanism immediately.

Maintain a safe fallback:

```text
Realtime connected
      ↓
use realtime

Realtime unavailable
      ↓
bounded polling fallback
```

The customer and agent UI must continue to function when the realtime connection fails.

---

## 9. Voice Architecture

Voice must use the same conversation pipeline as text:

```text
Microphone
    ↓
Speech-to-Text
    ↓
Language / transcript normalization
    ↓
SupportConversation
    ↓
SupportOrchestrator
    ↓
Policy / Tools / Knowledge
    ↓
Text response
    ↓
Text-to-Speech
    ↓
Audio playback
```

Never create a second voice-only business logic path.

---

## 10. Voice Session

Use the existing `SupportVoiceSession` domain model from Phase 1.

Maintain:

```text
session identity
conversation association
status
language
provider
started_at
ended_at
error state
metadata
```

Session lifecycle should support:

```text
initiating
active
paused
ending
completed
failed
```

Adapt to the existing enum/model definitions rather than adding duplicate states.

---

## 11. Speech-to-Text Adapter

Define a provider-neutral abstraction.

Logical interface:

```text
SpeechToTextInterface
```

Responsibilities:

- accept audio input;
- return transcript;
- expose detected language where supported;
- normalize provider errors;
- enforce timeout and size limits.

Do not hard-code provider-specific logic into controllers or Vue.

Do not store raw audio indefinitely unless existing retention policy explicitly permits it.

---

## 12. Text-to-Speech Adapter

Define:

```text
TextToSpeechInterface
```

Responsibilities:

- accept safe assistant text;
- return audio or a temporary audio reference;
- normalize provider failures;
- support supported languages where provider capabilities allow.

Do not send hidden prompts, internal metadata, or structured tool payloads to TTS.

Only synthesize customer/agent-visible text.

---

## 13. Voice Language Support

Initial language set:

```text
en
yo
ig
ha
```

The system should:

- detect language from speech where supported;
- honor explicit user language preference;
- maintain conversation language;
- return transcript and response in the conversation language;
- allow safe code-switching;
- fail back to English when a provider lacks the requested voice capability.

Internal intent IDs remain language-neutral.

---

## 14. Voice Interruption

Support:

```text
speak
→ customer interrupts
→ cancel current TTS
→ capture new speech
→ continue conversation
```

Cancellation must not:

- delete prior messages;
- skip policy checks;
- create duplicate turns.

An interrupted TTS response is an interface event, not an authorization event.

---

## 15. Voice Message Model

Use `SupportMessage` with the existing message types:

```text
voice_transcript
audio
text
```

Recommended sequence:

```text
customer audio
      ↓
voice_transcript message
      ↓
AI processing
      ↓
text response message
      ↓
audio response transport
```

The transcript remains part of the canonical SupportConversation history.

---

## 16. Voice Security

Voice must obey every existing Support security rule.

A caller cannot gain access to another customer's orders simply because the caller speaks their name/order number.

The backend still derives identity from:

```text
authenticated session
or
authorized guest support session
```

The AI still cannot bypass:

```text
ToolRegistry
PolicyEngine
customer ownership
sensitive-action confirmation
human approval
```

Do not treat voice biometrics as implemented identity verification unless an actual verified biometric system is deliberately introduced in a later approved phase.

---

## 17. Voice Limits / Abuse Controls

Protect:

```text
audio duration
audio size
requests per conversation
concurrent voice sessions
STT requests
TTS requests
```

Apply existing Support rate-limit principles.

Return safe errors for:

```text
too large
too long
rate limited
provider unavailable
unsupported language
invalid audio
```

---

## 18. Realtime Presence

Agent presence may support:

```text
online
busy
away
offline
```

Use the existing `SupportAgentProfile` status concepts where available.

Presence is advisory.

It must not override assignment authorization.

Do not build a separate identity/presence account system.

---

## 19. Vue Customer Realtime

Adapt the existing customer assistant.

Add logical capabilities:

```text
realtime connection status
live message arrival
typing indicator
handoff status
voice button
recording state
transcript preview
audio playback
interrupt/cancel
reconnect state
```

Do not require the customer to manually refresh.

---

## 20. Vue Agent Realtime

Adapt the Support Center:

```text
queue updates
new conversation alert
new message alert
assignment changes
status/priority changes
department transfers
agent presence
typing indicators
```

The agent console must continue to work with polling if realtime is unavailable.

---

## 21. Audio UX

Keep voice UX lightweight and accessible.

States:

```text
idle
recording
processing
speaking
interrupted
error
```

Show:

- microphone permission state;
- recording indicator;
- transcript where available;
- stop/cancel control;
- playback state;
- retry state.

Do not require a specific voice UI library if browser APIs and the project's conventions are sufficient.

---

## 22. API / Transport Boundary

Add only the APIs needed to establish/reconcile voice sessions and realtime state.

Possible responsibilities:

```text
POST /api/v1/support/conversations/{conversation}/voice/sessions
GET  /api/v1/support/conversations/{conversation}/voice/sessions/{session}
POST /api/v1/support/conversations/{conversation}/voice/sessions/{session}/end
POST /api/v1/support/conversations/{conversation}/voice/transcript
```

Adapt exact route structure to existing conventions.

Do not expose provider credentials to Vue.

Realtime authorization should live in Laravel channel/authentication code.

---

## 23. Tests

Add backend tests for:

### Realtime authorization

- customer can subscribe to own conversation;
- customer cannot subscribe to another customer's conversation;
- guest token is required for guest conversation;
- agent can subscribe only within Phase 5 scope;
- unauthorized agent cannot subscribe to restricted conversation.

### Event safety

- customer receives no internal notes;
- agent receives authorized support events;
- payload contains no secrets.

### Voice

- voice session creation authorization;
- wrong customer/agent denied;
- transcript stored as SupportMessage;
- duplicate transcript prevented where applicable;
- STT provider failure safe;
- TTS provider failure safe;
- unsupported language safe;
- interrupted playback does not create duplicate turns;
- sensitive tool calls still obey policy.

### Reconnect/fallback

- realtime failure returns UI to polling;
- reconnect reconciles messages;
- duplicate events do not duplicate messages.

---

## 24. Full Regression

Run:

```bash
php artisan test --filter=Support
php artisan test
php artisan route:list --path=v1/support
```

Run the existing frontend build/test/lint commands.

Document exact results.

---

## 25. Documentation Deliverable

Create:

```text
docs/AI-SUPPORT-PHASE-6-REPORT.md
```

Document:

- realtime transport architecture;
- channel authorization;
- event catalog;
- payload safety;
- polling fallback;
- voice session lifecycle;
- STT/TTS provider abstraction;
- supported languages;
- interruption handling;
- rate limits;
- tests;
- exact results;
- known provider limitations;
- deferred items.

---

## 26. Non-Destructive Boundary

Keep:

```text
legacy ChatController
legacy AdminChatController
ChatService
ChatConversation
ChatMessage
legacy routes/UI
```

until the later migration/cutover phase.

Do not switch production traffic away from Phase 4/5 merely because realtime/voice now exist.

Do not delete legacy files.

---

## 27. Stop Condition

Phase 6 is complete only when:

- authorized realtime customer channels work;
- authorized realtime agent channels work;
- unsafe subscriptions are rejected;
- event payloads are customer/agent scoped;
- polling fallback works;
- voice sessions use SupportConversation;
- STT is provider-neutral;
- TTS is provider-neutral;
- transcript messages are persisted;
- voice language plumbing works for supported languages;
- interruption/cancel is safe;
- voice failures are recoverable;
- existing tools/policies/ownership controls remain authoritative;
- backend and frontend tests pass;
- documentation/report is complete.

Then:

# STOP.

Do not start:

- knowledge/policy administration;
- legacy migration;
- production cutover;
- legacy deletion;
- new omnichannel integrations.
