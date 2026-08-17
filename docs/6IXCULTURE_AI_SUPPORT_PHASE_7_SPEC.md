# 6ixCulture AI Support — Phase 7 Implementation Specification

**Repository:** `josephtersoo2-stack/6ixculture-app`  
**Phase:** 7 — Knowledge & Policy Administration  
**Prerequisites:** Phase 0–6 approved  
**Status:** DRAFT — READY FOR REVIEW

## 1. Goal

Build the authenticated administrative governance layer for Support Knowledge, AI Policies, and AI Tool Permissions.

Phase 7 turns the existing runtime knowledge, policy, and tool-permission foundations into a controlled operational system that authorized administrators can manage without editing code or database records manually.

Target architecture:

```text
Support Admin
      ↓
Admin Governance UI
      ↓
Laravel Support Admin API
      ↓
Knowledge / Policy / Tool-Permission Services
      ↓
Support Domain
      ↓
AI Runtime
```

The administrator is the governance authority. The AI is the runtime consumer.

## 2. Scope

Implement only:

- knowledge article administration
- article categories
- article draft/publish/archive lifecycle
- article versioning
- article rollback/version selection
- multilingual knowledge management for `en`, `yo`, `ig`, `ha`
- policy administration
- policy activation/deactivation
- policy simulation
- registered AI tool permission administration
- tool enable/disable and supported governance controls
- role/department applicability where already supported
- governance audit logging
- admin API authorization
- Vue admin governance UI
- validation and preview workflows
- regression/security tests
- documentation

Do NOT implement:

- new AI tools
- new sensitive mutations
- voice changes
- realtime changes
- omnichannel integrations
- legacy chat migration
- production cutover
- legacy deletion
- unrestricted prompt/system-prompt editing
- arbitrary SQL/code execution
- direct database editing from UI

## 3. Existing Runtime Architecture Must Remain Canonical

Reuse:

```text
SupportKnowledgeRepository
SupportContextAssembler
SupportConversation
SupportPolicy
SupportAITool
SupportAIToolPermission
SupportAuditLog
ToolRegistry
SupportActionPolicyEngine
existing AI provider abstraction
```

Do not create duplicate runtime repositories or policy engines.

Phase 7 is an administration layer over the existing runtime domain.

## 4. Knowledge Management

The managed knowledge base uses categories such as:

```text
Products
Shipping
Returns
Refunds
Payments
Account
Orders
Warranty
Promotions
FAQ
Store Policies
```

Adapt article/version fields to the actual Phase 3 models and migrations. Do not create duplicate article tables if an equivalent Support knowledge model already exists.

Logical article data may include:

```text
id
title
slug
category
language
content
status
version
published_at
created_by
updated_by
metadata
```

## 5. Article Lifecycle

Support at minimum:

```text
draft
published
archived
```

Rules:

- draft content never enters customer-facing AI grounding;
- only the intended published version is eligible for runtime retrieval;
- archived content is excluded;
- a draft never silently changes current production knowledge.

Workflow:

```text
Create Draft
    ↓
Edit
    ↓
Preview / Validate
    ↓
Publish
    ↓
Live AI Grounding
```

## 6. Article Versioning

Never mutate published historical versions in place.

Use:

```text
Published Version
       ↓
Create New Version
       ↓
Draft
       ↓
Review
       ↓
Publish New Version
```

Historical versions must remain readable and auditable.

Each version should retain the repository-supported equivalents of:

```text
version number
created_by
created_at
publication state
published_at
content snapshot
metadata snapshot
```

## 7. Rollback

Rollback must be non-destructive.

An authorized administrator restores an older version by creating a new current version from the selected historical content.

Audit:

```text
actor
article
source version
new version
reason
timestamp
```

Do not delete previous versions.

## 8. Multilingual Knowledge

Support:

```text
en
yo
ig
ha
```

Preserve Phase 3 runtime fallback:

```text
requested language
    ↓
matching published content
    ↓
English published fallback
```

Draft or archived translations must not become customer-visible grounding.

## 9. Knowledge Preview and Validation

Provide an admin preview that:

- renders title/category/language/content;
- indicates the version/state;
- validates required fields;
- catches duplicate/slug conflicts;
- indicates publication readiness;
- shows effective language fallback where useful.

Preview must not require a real customer AI turn.

## 10. Knowledge Safety

Knowledge content remains inert reference material.

Admin UI must not create a path where article content can:

```text
override policy
grant tool access
disable authorization
inject executable code
replace system instructions
```

The existing Phase 3 prompt-injection boundary must remain intact.

## 11. Policy Administration

Expose the existing Support Policy domain.

Support safe management operations such as:

```text
view
create
edit
enable/disable
archive where supported
```

Policies may govern:

```text
tool access
confirmation
human approval
risk
customer-data access
department/role scope
rate or usage constraints
```

Laravel policy evaluation remains authoritative.

## 12. Policy Lifecycle

Use the existing SupportPolicy state model where possible.

At minimum distinguish inactive/draft-like configuration from active policy state.

Before activation, validate:

- referenced tool exists;
- referenced permission/action is valid;
- required risk/effect data is present;
- supported scope fields are valid;
- obvious conflicts are surfaced where the domain can detect them.

## 13. Policy Simulation

Provide a simulation endpoint/UI with no real side effects.

Example:

```text
Actor: customer
Tool: request_refund
Context: authenticated customer
Expected effect: REQUIRE_HUMAN
```

Return policy outcome such as:

```text
allowed
denied
require_confirmation
require_human
```

Do not execute the underlying business operation.

## 14. Tool Permission Management

Use:

```text
SupportAITool
SupportAIToolPermission
ToolRegistry
```

Only registered backend tools may appear in the administration UI.

Supported governance fields should be based on the actual Phase 2 domain, such as:

```text
enabled
risk
confirmation requirement
human approval requirement
scope
```

Do not create tools from the browser in Phase 7.

## 15. Tool Safety

Make the distinction explicit:

```text
tool exists
    ≠
tool is permitted
```

and:

```text
permission changed
    ≠
business authorization bypassed
```

Runtime must still enforce:

```text
authenticated identity
ownership
policy engine
business service authorization
confirmation
human approval
```

## 16. Sensitive Actions

For:

```text
refund
cancel
payment changes
account changes
post-shipment address changes
```

Phase 7 may configure or display governance but must not silently remove existing hard safety controls.

Do not turn a critical action into unrestricted execution through the admin UI.

## 17. Admin Authorization

Use the existing Laravel authentication and Spatie permissions.

Exact role names/permissions must follow the repository.

Do not give normal support agents unrestricted governance authority.

At minimum, governance-sensitive operations such as:

```text
publish knowledge
activate policies
change critical tool permissions
```

must be server-authorized.

## 18. Governance Scope

Apply the same scope discipline established in Phase 5.

Authorization must be evaluated before filters and resource retrieval.

A department-limited manager must not gain access to another department's governance records merely by changing an ID in the request.

## 19. Audit Logging

Audit at minimum:

```text
knowledge created
knowledge updated
knowledge published
knowledge archived
knowledge rollback
policy created
policy updated
policy activated
policy disabled
tool permission changed
tool enabled/disabled
policy simulation executed
```

Log safe governance metadata:

```text
actor
resource
action
authorization result
before/after data where safe
timestamp
```

Never log:

```text
passwords
API keys
tokens
secret headers
provider credentials
```

## 20. Admin API

Follow current Laravel route conventions.

Logical responsibilities:

```text
GET    /api/v1/support/admin/knowledge
POST   /api/v1/support/admin/knowledge
GET    /api/v1/support/admin/knowledge/{article}
POST   /api/v1/support/admin/knowledge/{article}/versions
POST   /api/v1/support/admin/knowledge/{article}/publish
POST   /api/v1/support/admin/knowledge/{article}/archive
GET    /api/v1/support/admin/knowledge/{article}/versions
POST   /api/v1/support/admin/knowledge/{article}/rollback

GET    /api/v1/support/admin/policies
POST   /api/v1/support/admin/policies
GET    /api/v1/support/admin/policies/{policy}
PATCH  /api/v1/support/admin/policies/{policy}
POST   /api/v1/support/admin/policies/{policy}/activate
POST   /api/v1/support/admin/policies/{policy}/disable
POST   /api/v1/support/admin/policies/simulate

GET    /api/v1/support/admin/tools
GET    /api/v1/support/admin/tools/{tool}
PATCH  /api/v1/support/admin/tools/{tool}/permissions
```

Adapt names if equivalent endpoints/services already exist.

## 21. Vue Governance Center

Build within the existing admin Vue architecture.

Logical modules:

```text
SupportGovernance.vue
KnowledgeManager.vue
KnowledgeArticleEditor.vue
KnowledgeVersionHistory.vue
KnowledgePreview.vue
PolicyManager.vue
PolicyEditor.vue
PolicySimulator.vue
ToolPermissionManager.vue
ToolPermissionEditor.vue
GovernanceAuditLog.vue
```

Reuse current components, store/router/services, and styling conventions where appropriate.

## 22. Knowledge UI

Provide:

```text
search
category filter
language filter
status filter
version
last updated
author
publish state
```

Editor supports:

```text
title
slug
category
language
content
metadata where supported
save draft
preview
publish
archive
version history
rollback
```

Do not expose hidden prompts or provider credentials.

## 23. Policy UI

Show and safely edit supported fields such as:

```text
name
description
scope
tool/action
risk
effect
confirmation requirement
human approval requirement
active/inactive state
```

Use controlled domain values.

Do not accept executable code or arbitrary policy expressions from the browser.

## 24. Tool Permission UI

Display registered tools with:

```text
name
description
risk
current state
confirmation
human approval
scope
```

Only approved governance fields may be edited.

No raw prompt/code/SQL fields.

## 25. Policy Simulation UI

Clearly label:

```text
SIMULATION ONLY
```

Show:

```text
Actor
Context
Tool/action
Policy effect
Confirmation requirement
Human approval requirement
```

No real business side effect.

## 26. Runtime Consistency

When knowledge, policy, or tool-permission state changes, runtime must observe the new state according to the existing consistency model.

If the repository uses caching, invalidate affected state explicitly.

Do not introduce unnecessary distributed infrastructure.

## 27. Testing

Add backend tests for:

### Knowledge
- unauthorized governance endpoint denied;
- unauthorized publisher denied;
- authorized draft creation;
- draft excluded from public grounding;
- publish activates correct version;
- archive excludes content;
- rollback creates new version;
- language fallback remains correct;
- version history preserved.

### Policy
- unauthorized policy management denied;
- valid policy creation/update;
- invalid tool reference rejected;
- invalid activation rejected;
- simulation is side-effect free;
- simulation returns expected policy effect;
- critical safety constraints remain enforced.

### Tool permissions
- unauthorized tool governance denied;
- registered tools only;
- invalid tool ID rejected;
- permission changes audited;
- disabled tool is not runtime-authorized;
- enabling/configuring a tool does not bypass policy/ownership safeguards.

### Audit
- governance mutations create safe audit records;
- secrets are excluded.

## 28. Runtime Regression

Verify:

```text
knowledge retrieval
policy evaluation
tool registry
customer isolation
agent authorization
internal-note isolation
realtime authorization
voice authorization
```

Do not regress Phases 1–6.

## 29. Frontend Tests

Test:

```text
knowledge list/editor
draft/publish/archive
version history
rollback
language behavior
policy editor
policy simulation
tool permission UI
permission-aware controls
audit log
loading/error states
```

Backend authorization remains authoritative.

## 30. No Direct Database Editing

Do not accept:

```text
raw SQL
table names
arbitrary columns
arbitrary model updates
```

All changes use typed Laravel requests/services/domain logic.

## 31. Documentation

Create:

```text
docs/AI-SUPPORT-PHASE-7-REPORT.md
```

Include:

- knowledge lifecycle;
- versioning;
- publication behavior;
- multilingual behavior;
- rollback;
- policy administration;
- policy simulation;
- tool permission governance;
- admin roles/scope;
- API endpoints;
- Vue modules;
- audit logging;
- exact tests/results;
- runtime regression;
- known limitations;
- Phase 8 recommendation.

## 32. Completion Criteria

Phase 7 is complete only when:

- authorized admins can manage knowledge;
- drafts remain excluded from grounding;
- published versions are correct;
- archive works;
- version history works;
- rollback is non-destructive;
- multilingual fallback remains safe;
- authorized governance users can manage policies;
- policy simulation has no side effects;
- registered tool permissions can be governed;
- critical safeguards cannot be silently removed;
- governance actions are audited;
- runtime Support behavior remains correct;
- admin UI works;
- all tests pass;
- documentation is complete.

## 33. Stop Condition

After:

```text
Knowledge administration
Policy administration
Tool permission governance
Audit logging
Security tests
Runtime regression tests
Frontend governance UI
Documentation
```

are complete:

# STOP.

Do not begin:

```text
legacy migration
production cutover
legacy deletion
omn​​ichannel expansion
new unrestricted AI tools
```

Phase 8 is the next authorization checkpoint.
