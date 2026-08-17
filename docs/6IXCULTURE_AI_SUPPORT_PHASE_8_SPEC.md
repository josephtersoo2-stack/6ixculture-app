# 6ixCulture AI Support — Phase 8 Implementation Specification

**Repository:** `josephtersoo2-stack/6ixculture-app`
**Phase:** 8 — Advanced Voice & Multilingual Experience
**Prerequisites:** Phases 0–7 approved
**Status:** DRAFT — READY FOR REVIEW

## 1. Important Baseline

The original master plan labels Phase 8 as:

```text
Voice + Multilingual
- STT
- TTS
- language detection
- language preference
- English
- Yoruba
- Igbo
- Hausa
```

However, the actual repository already delivered the core of that scope during Phase 6.

Phase 6 currently includes:

```text
STT abstraction
TTS abstraction
OpenAI Whisper STT
Gemini STT
OpenAI TTS
Voice sessions
voice transcripts
voice interruption
en / yo / ig / ha plumbing
customer voice UI
agent realtime UI
realtime authorization
polling fallback
```

Therefore Phase 8 must **not rebuild Phase 6**.

Phase 8 is the advanced voice/multilingual layer on top of that completed foundation.

---

## 2. Goal

Improve the already-working voice and multilingual experience into a mature production-ready conversational interface.

Target:

```text
Customer
   ↓
Voice/Text
   ↓
Language Detection + Preference
   ↓
Speech / Text Normalization
   ↓
SupportConversation
   ↓
SupportOrchestrator
   ↓
Knowledge / Policy / Tools
   ↓
Localized Response
   ↓
TTS / Text
```

The same SupportConversation and authorization architecture remains canonical.

---

## 3. Scope

Implement only:

- advanced language detection and confidence handling
- explicit language preference management
- language switching during an active conversation
- code-switching support
- multilingual fallback UX
- voice pronunciation/locale configuration
- TTS voice selection where provider capability supports it
- transcript normalization
- speech confidence/error handling
- partial/temporary transcript UX where provider/browser support allows
- advanced interruption/barge-in handling
- voice retry/recovery
- voice session continuity
- multilingual quick replies/system messages
- multilingual human handoff state
- customer language preference persistence
- agent-side language/translation assistance improvements
- voice accessibility improvements
- provider capability reporting
- telemetry for language/voice failures
- tests
- documentation

Do NOT implement:

- new commerce mutations
- new AI tools
- WhatsApp/email/phone
- legacy migration
- production cutover
- legacy deletion
- new realtime architecture
- replacement of existing STT/TTS abstractions
- unrestricted provider-specific logic in controllers

---

## 4. Existing Providers

Reuse the Phase 6 provider abstractions.

Current repository implementation includes:

```text
SpeechToTextInterface
TextToSpeechInterface
VoiceProviderFactory
```

Available provider adapters include:

```text
OpenAI Whisper STT
Gemini STT
OpenAI TTS
```

Provider selection remains configuration-driven.

Do not hard-code providers into Vue or controllers.

---

## 5. Language Preference

Persist a customer language preference using the existing customer/support profile architecture.

Supported:

```text
en
yo
ig
ha
```

Rules:

```text
explicit user preference
        ↓
conversation language
        ↓
voice/text response language
```

The system should preserve the language preference across conversations where the existing customer model supports it.

---

## 6. Language Detection

Improve detection beyond a single static value.

The system should distinguish:

```text
detected_language
language_confidence
requested_language
effective_language
```

If confidence is low:

```text
ask/offer confirmation
```

Do not silently switch a customer's language based on an uncertain detection result.

---

## 7. Code Switching

Support conversations such as:

```text
English + Yoruba
English + Igbo
English + Hausa
```

without changing internal intent/tool IDs.

Example:

```text
TRACK_ORDER
```

remains language-neutral.

The model may respond in the user's effective language while backend tools remain unchanged.

---

## 8. Language Switching

Customer should be able to switch explicitly:

```text
English
Yorùbá
Igbo
Hausa
```

during an active conversation.

Language change must not:

- create a new conversation;
- lose history;
- invalidate tool authorization;
- duplicate turns.

Persist the new effective language on the existing conversation.

---

## 9. Multilingual Knowledge Integration

Reuse Phase 3/7 knowledge behavior:

```text
requested language
 ↓
published matching knowledge
 ↓
English published fallback
```

Do not allow language enhancements to expose:

```text
draft knowledge
archived knowledge
internal notes
policy internals
```

---

## 10. TTS Voice/Locale Configuration

Expose only provider-supported customer-safe voice options.

Logical configuration:

```text
language
locale
voice
speaking_rate
```

Do not expose arbitrary provider parameters to customers.

If a requested language/voice is unavailable:

```text
requested language
 ↓
supported regional voice
 ↓
safe fallback
```

The fallback should be visible to the client when useful.

---

## 11. Transcript Normalization

Normalize STT output for:

```text
punctuation
spacing
language tags
common speech disfluencies
safe token limits
```

Do not alter customer intent.

Preserve the original transcript where required for audit/history.

---

## 12. Speech Confidence

Where the provider exposes confidence:

Store/use:

```text
confidence score
detected language
provider
```

If confidence is too low:

```text
ask for repetition
```

rather than executing a potentially dangerous intent.

A low-confidence transcript must never weaken policy requirements.

---

## 13. Advanced Interruption / Barge-In

Support:

```text
assistant speaking
 ↓
customer starts speaking
 ↓
TTS cancellation
 ↓
new capture
 ↓
new turn
```

Prevent:

- duplicate SupportMessages
- duplicated tool calls
- stale audio continuing after cancellation
- lost conversation state

---

## 14. Voice Session Continuity

Support recovery from:

```text
browser refresh
temporary connection loss
provider timeout
audio permission changes
mobile interruption
```

A session must reconcile to the canonical SupportConversation.

Do not create duplicate conversations.

---

## 15. Customer UX

Improve existing customer voice controls:

```text
idle
recording
processing
speaking
interrupted
retrying
fallback
error
```

Provide:

- clear language indicator;
- microphone permission state;
- transcript preview;
- retry/cancel;
- voice availability;
- playback controls;
- accessible labels.

---

## 16. Multilingual Quick Replies

Localize:

```text
Find a product
Track my order
Returns & refunds
Shipping
Talk to a human
```

and relevant system messages for:

```text
en
yo
ig
ha
```

Do not use machine-generated UI text at runtime without reviewable localization resources.

---

## 17. Human Handoff

When escalation occurs, preserve effective language and expose it to the agent workspace.

Agent should see:

```text
Customer language
Detected language
Translation available
```

Customer should see localized escalation text.

The handoff remains the same SupportConversation.

---

## 18. Agent Translation Assistance

Improve Phase 5/6 agent assistance with:

```text
translate customer message
translate agent reply
show original
show translated version
```

Translation is assistive.

The agent chooses what is sent to the customer.

Do not automatically send machine translations without agent visibility for agent-authored messages.

---

## 19. Accessibility

Ensure voice controls support:

```text
keyboard navigation
screen-reader labels
visible state changes
reduced-motion preferences
focus handling
error announcements
```

Do not make voice the only way to interact.

---

## 20. Provider Capability Reporting

Expose a safe capability object to the frontend, for example:

```json
{
  "stt": {
    "en": true,
    "yo": true,
    "ig": true,
    "ha": true
  },
  "tts": {
    "en": true,
    "yo": false,
    "ig": false,
    "ha": false
  }
}
```

The actual values must come from provider capability/configuration, not hard-coded UI assumptions.

Do not expose credentials or provider secrets.

---

## 21. Error and Fallback Strategy

Handle separately:

```text
STT unavailable
TTS unavailable
language unsupported
low confidence
microphone denied
provider timeout
audio invalid
realtime disconnected
```

Preferred behavior:

```text
Voice failure
   ↓
preserve transcript/history
   ↓
offer text fallback
   ↓
continue conversation
```

Do not silently lose a customer's turn.

---

## 22. Telemetry

Record safe metrics for:

```text
language detection failures
STT failures
TTS failures
unsupported language requests
low-confidence turns
interruption events
voice session failures
fallback-to-text events
```

Do not record raw audio or sensitive transcripts in telemetry unless existing retention rules explicitly allow it.

---

## 23. Security

Preserve Phase 1–7 invariants:

```text
customer ownership
guest token authorization
agent scope
tool registry
policy engine
critical action safety
internal-note isolation
realtime channel authorization
audit redaction
```

A language or voice feature must never become an alternative authorization path.

---

## 24. API

Extend existing voice/language endpoints only where required.

Possible logical responsibilities:

```text
GET  /api/v1/support/voice/capabilities
GET  /api/v1/support/conversations/{conversation}/language
POST /api/v1/support/conversations/{conversation}/language
POST /api/v1/support/conversations/{conversation}/voice/preferences
```

Adapt to actual repository routes.

Do not duplicate Phase 6 voice-session APIs.

---

## 25. Vue Components

Adapt existing components rather than rebuilding:

```text
AiSupportWidget
VoiceButton
VoiceWaveform
MessageComposer
SupportStatus
```

Add logical modules where needed:

```text
LanguageSelector
VoicePreferences
VoiceCapabilityNotice
TranscriptPreview
TranslationToggle
VoiceErrorState
```

Use existing Vuex/store/service architecture.

---

## 26. Tests

Add backend tests for:

### Language

- preference persistence;
- language switch;
- low-confidence detection;
- code switching;
- fallback to English;
- unsupported language response.

### Voice

- capability endpoint;
- provider fallback;
- TTS locale selection;
- STT confidence handling;
- interrupted turn safety;
- session recovery;
- duplicate prevention.

### Agent translation

- authorized agent can request translation;
- customer cannot access agent-only translation tools;
- original content preserved.

### Security

- language endpoint respects conversation authorization;
- guest language access requires guest proof;
- voice cannot bypass policy;
- low-confidence voice cannot trigger sensitive action.

---

## 27. Frontend Tests

Test:

```text
language selector
language persistence
voice states
capability handling
unsupported-language fallback
transcript preview
interrupt
retry
translation toggle
accessibility states
```

---

## 28. Regression

Run:

```bash
php artisan test --filter=Support
php artisan test
php artisan route:list --path=v1/support
```

Run frontend build/test/lint.

Phase 8 must not regress Phases 1–7.

---

## 29. Documentation

Create:

```text
docs/AI-SUPPORT-PHASE-8-REPORT.md
```

Document:

- current provider matrix;
- language capabilities;
- preference behavior;
- language detection;
- code switching;
- TTS voice configuration;
- interruption;
- recovery;
- fallback;
- translation assistance;
- accessibility;
- tests;
- exact results;
- remaining provider limitations.

---

## 30. Completion Criteria

Phase 8 is complete only when:

- language preferences persist;
- language switching works;
- low-confidence detection is handled safely;
- code switching works;
- multilingual UI is localized;
- provider capability reporting works;
- TTS/STT fallback behavior works;
- transcript normalization works;
- interruption is reliable;
- voice sessions recover safely;
- agent translation assistance works;
- accessibility requirements are met;
- security boundaries remain intact;
- full regression suite passes;
- documentation is complete.

---

## 31. Stop Condition

After the advanced voice/multilingual experience, tests, and documentation are complete:

# STOP.

Do not begin:

- Phase 9 migration
- production cutover
- legacy deletion
- omnichannel expansion
- new unrestricted AI tools

Phase 9 is the next authorization checkpoint.
