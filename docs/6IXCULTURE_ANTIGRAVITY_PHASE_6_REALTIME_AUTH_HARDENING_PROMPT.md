# 6ixCulture AI Support — Phase 6 Realtime Authorization Hardening Prompt

**Repository:** `josephtersoo2-stack/6ixculture-app`  
**Phase:** Phase 6 — Realtime Support Transport & Voice Interface  
**Status:** HARDENING REQUIRED BEFORE PHASE 6 APPROVAL

Phase 6 is substantially complete, but two realtime authorization gaps remain.

This is a targeted security hardening pass only. Do not start Phase 7.

## 1. Read first

Read:

```text
docs/AI-SUPPORT-AUDIT.md
docs/6IXCULTURE_AI_SUPPORT_IMPLEMENTATION_PLAN.md
docs/6IXCULTURE_AI_SUPPORT_PHASE_1_SPEC.md
docs/6IXCULTURE_AI_SUPPORT_PHASE_2_SPEC.md
docs/AI-SUPPORT-PHASE-2-REPORT.md
docs/6IXCULTURE_AI_SUPPORT_PHASE_3_SPEC.md
docs/AI-SUPPORT-PHASE-3-REPORT.md
docs/6IXCULTURE_AI_SUPPORT_PHASE_4_SPEC.md
docs/AI-SUPPORT-PHASE-4-REPORT.md
docs/6IXCULTURE_AI_SUPPORT_PHASE_5_SPEC.md
docs/AI-SUPPORT-PHASE-5-REPORT.md
docs/6IXCULTURE_AI_SUPPORT_PHASE_6_SPEC.md
docs/AI-SUPPORT-PHASE-6-REPORT.md
```

Inspect:

```text
routes/channels.php
app/Support/Events/SupportMessageCreated.php
app/Support/Events/SupportConversationUpdated.php
app/Support/Events/SupportQueueUpdated.php
app/Support/Events/SupportAgentPresenceChanged.php
app/Http/Controllers/Api/V1/Support/Voice/SupportVoiceController.php
tests/Feature/Support/RealtimeAuthorizationTest.php
tests/Feature/Support/SupportVoiceTest.php
```

Do not rely only on the Phase 6 report.

## 2. Issue A — global agent queue channel is too broad

Current:

```text
support.agent.queue
```

is authorized for any user with a SupportAgentProfile.

That violates the Phase 5 scope boundary.

### Required rule

Elevated Admin/Manager users may subscribe to the global queue where existing permissions grant global support visibility.

Department-scoped agents must NOT receive the global queue.

They should instead subscribe only to:

```text
support.agent.department.{departmentId}
```

for departments in their authorized SupportAgentProfile department set.

Change the global queue callback so non-elevated department-scoped agents are denied.

The existing department-specific queue callback must continue enforcing department membership.

## 3. Issue B — guest realtime authorization is missing

The REST voice/customer APIs validate:

```text
X-Guest-Token
```

against:

```text
conversation.guest_session_id
```

The realtime private conversation channel currently requires an authenticated `$user`, so guest realtime authorization is not actually implemented.

### Required invariant

```text
guest realtime subscription
        ↓
valid guest token proof
        ↓
conversation.guest_session_id match
        ↓
authorize subscription
```

Wrong token, missing token, or a token belonging to another conversation must be denied.

Do not weaken the authenticated customer channel.

Use a Laravel-compatible guest realtime authentication mechanism consistent with the existing broadcasting stack. A dedicated guest channel such as:

```text
support.guest.conversation.{publicId}
```

is acceptable.

Never expose guest tokens in broadcast payloads or logs.

## 4. Preserve authenticated customer channels

For:

```text
support.conversation.{publicId}
```

keep:

```text
conversation.customer_id === authenticated_user.id
```

for authenticated customers, plus the existing Phase 5 agent/admin authorization.

Do not authorize solely from a public conversation ID.

## 5. Event payload safety

Review:

```text
SupportMessageCreated
SupportConversationUpdated
SupportQueueUpdated
SupportAgentPresenceChanged
```

Customer/guest events must never contain:

```text
internal notes
staff routing details
private audit data
provider credentials
tokens
secret metadata
```

Agent events must respect Phase 5 scope.

Never rely on Vue to hide sensitive content.

## 6. Queue event routing

Review `SupportQueueUpdated`.

Do not send department-specific queue information to every support agent through a global channel.

Prefer:

```text
global queue → elevated global-support users only
department queue → authorized department members
conversation channel → authorized conversation members
```

## 7. Presence channel

Review:

```text
support.agent.presence
```

It should expose only the minimum information required by the support console.

Do not include customer data, internal notes, or unnecessary routing information.

Presence remains advisory, not authoritative.

## 8. Required realtime tests

Extend:

```text
tests/Feature/Support/RealtimeAuthorizationTest.php
```

Add tests for:

1. Department-scoped agent denied global queue.
2. Elevated Admin allowed global queue.
3. Elevated Manager allowed global queue.
4. Authorized department agent allowed department queue.
5. Non-member denied another department queue.
6. Guest with valid token allowed guest conversation channel.
7. Wrong guest token denied.
8. Guest token cannot authorize another conversation.
9. Authenticated customer cannot access another customer's conversation.
10. Agent cannot access another department conversation.
11. Internal note event is not delivered to customer/guest channel.
12. Customer-visible event reaches authorized customer and authorized agent only.

## 9. Guest token security

Never:

```text
log guest token
broadcast guest token
include guest token in event payload
return guest token through realtime events
```

Use constant-time token comparison when comparing the credential directly.

Follow the Phase 4 guest-token security model.

## 10. Voice boundary

Do not redesign the voice pipeline.

Preserve:

```text
audio
 ↓
STT
 ↓
VOICE_TRANSCRIPT SupportMessage
 ↓
SupportOrchestrator
 ↓
TTS
```

Only change voice code when required to keep authorization consistent.

## 11. No new features

Do not implement:

```text
new voice providers
new realtime providers
WhatsApp
email
phone
legacy migration
production cutover
legacy deletion
knowledge administration
policy administration
new commerce mutations
```

## 12. Verification

Run:

```bash
php artisan test --filter=RealtimeAuthorizationTest
php artisan test --filter=SupportVoiceTest
php artisan test --filter=Support
php artisan test
php artisan route:list --path=v1/support
```

Run the existing frontend build/test/lint commands.

Record exact results.

## 13. Documentation

Update:

```text
docs/AI-SUPPORT-PHASE-6-REPORT.md
```

Add:

```text
Phase 6 Realtime Authorization Hardening
```

Document:

- global queue restriction;
- department queue behavior;
- guest realtime authentication;
- customer/agent channel scope;
- internal event isolation;
- regression tests;
- exact final results.

Do not claim guest realtime support unless the implementation and tests prove it.

## 14. Commit

Use:

```text
fix(support): harden phase 6 realtime channel authorization
```

Push to:

```text
origin main
```

only after all tests pass.

## 15. Final stop condition

When:

- global queue is restricted to proper elevated scope;
- department queue access is enforced;
- guest realtime subscriptions require valid guest proof;
- cross-conversation guest access is denied;
- customer/agent channel scope remains correct;
- internal notes remain isolated;
- all regression tests pass;
- the report is updated;
- the commit is pushed;

then:

```text
PHASE 6 SECURITY HARDENING = COMPLETE
```

STOP.

Do not start Phase 7.
