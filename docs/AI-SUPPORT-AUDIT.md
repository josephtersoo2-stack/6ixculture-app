# 6ixCulture Enterprise AI Support System: Comprehensive Phase 0 Architectural Audit

> **Document Version**: 1.0.0  
> **Status**: Completed Read-Only Architectural Audit (Phase 0)  
> **Target Repository**: `josephtersoo2-stack/6ixculture-app`  
> **Application Framework**: Laravel 12 (PHP 8.2+) / Vue 3 (Composition & Options API) / Tailwind CSS  
> **Author**: Google Antigravity Advanced Agentic AI Engineering  

---

## 1. Executive Summary

The **6ixCulture** platform is an enterprise-scale e-commerce, POS, and inventory management system built upon a modern Laravel 12 API backend and a reactive Vue 3 Single Page Application (SPA).

This audit provides a comprehensive, read-only architectural breakdown of the existing prototype chat and AI systems. It establishes a rigorous blueprint to elevate the prototype into a production-grade **Enterprise AI Customer Care, Intelligent Shopping Assistant, Multilingual Voice, and Omnichannel Human Support Center**.

### Core Guiding Principles for Architecture
1. **Laravel Remains the Single Source of Truth**: No second e-commerce backend or isolated micro-database will be introduced.
2. **Zero Business Logic Duplication**: All core domains—products, variations, stock, orders, order items, coupons, carts, returns, and customers—are strictly accessed via controlled adapters around existing Laravel services.
3. **Hard Security Boundaries Outside LLM Prompts**: Security is never delegated solely to prompt instructions. Every data access and tool execution is authenticated, permission-checked, and ownership-verified in PHP before execution.
4. **Phased, Zero-Downtime Migration**: Legacy prototype systems remain intact while the new enterprise support domain is constructed and verified. Obsolete files are deprecated in a staged, risk-free manner.

---

## 2. Current Architecture Map

The existing codebase contains two distinct, semi-coupled AI/chat implementations:

```
                                  6ixCulture Platform Architecture
                                                 │
                   ┌─────────────────────────────┴─────────────────────────────┐
                   ▼                                                           ▼
         [Admin AI Subsystem]                                        [Customer Chat Prototype]
       (Content Generation & Tools)                                  (Live Chat & Human Handoff)
                   │                                                           │
     ┌─────────────┴─────────────┐                               ┌─────────────┴─────────────┐
     ▼                           ▼                               ▼                           ▼
[Backend Controllers]     [Frontend UI]                 [Backend Controllers]     [Frontend UI]
• AiController            • BackendAiSidebar            • ChatController          • LiveChatWidget
• AiAgentController       • AiAgentSettings             • AdminChatController     • LiveChatComponent
• AiService               • Vuex ai store               • ChatService             • liveChatRoutes
• AiAgentService                                        • ChatConversation Model
• OpenRouter/Gemini                                     • ChatMessage Model
```

### 2.1. Dual Subsystems Overview

#### Subsystem A: Admin AI & Product Copywriting (`AiController` / `AiService`)
* **Purpose**: Assists back-office administrators in generating SEO tags, product descriptions, creative product titles, and multi-modal image-to-product structured JSON parsing.
* **Storage**: Uses `ai_agents`, `gateway_options`, and `ai_chat_histories` tables.
* **LLM Layer**: Interfaces with `App\Http\AiAgents\Agents\Gemini`, `Openrouter`, and `Openai` via `AiAbstract`.
* **State**: Solid foundation for admin AI settings and gateway management; to be retained and harmonized with the new support domain.

#### Subsystem B: Customer Support Chat Prototype (`ChatController` / `ChatService`)
* **Purpose**: Provides customer-facing live chat with automated AI responses and basic human handover.
* **Storage**: Uses dynamic runtime table creation (`chat_conversations`, `chat_messages`) with 180-day automated cleanup.
* **Strengths**: Integrated with OpenRouter/Gemini fallback chains; functional session-to-user linking.
* **Limitations**: Unstructured message format (plain text only), no role-based support queuing, lack of department routing, lack of ticketing/SLA tracking, no internal staff notes, lack of server-side tool execution, and absence of audio/voice processing.

---

## 3. Existing Chat & AI File-by-File Dependency Map

| File Path | Current Role | References / Dependents | Classification | Rationale & Action Plan |
| :--- | :--- | :--- | :--- | :--- |
| `app/Http/Controllers/Frontend/ChatController.php` | Handles visitor/customer chat sessions, message sending, and human agent request. | `routes/api.php`, `LiveChatWidgetComponent.vue` | **REPLACE** | Replace with `SupportConversationController` in Phase 4. Keep active until production route switch in Phase 10. |
| `app/Http/Controllers/Admin/AdminChatController.php` | Admin console API for listing conversations, viewing history, replying, and status updates. | `routes/api.php`, `LiveChatComponent.vue` | **REPLACE** | Replace with `AdminSupportConsoleController` supporting 3-column queues, assignments, internal notes, and AI Copilot in Phase 5. |
| `app/Services/ChatService.php` | Handles DB table auto-creation, prompt assembly, conversation creation, and multi-tier LLM fallbacks. | `ChatController`, `AdminChatController` | **REPLACE** | Replaced by the modular `App\Support\` domain (Orchestrator, Tool Registry, Policy Engine, Knowledge Base). |
| `app/Models/ChatConversation.php` | Eloquent model for prototype conversations. | `ChatService`, `ChatController`, `AdminChatController` | **MIGRATE & REPLACE** | Migrate existing chat history to `SupportConversation` schema in Phase 9, then deprecate. |
| `app/Models/ChatMessage.php` | Eloquent model for prototype chat messages. | `ChatConversation`, `ChatService`, `ChatController`, `AdminChatController` | **MIGRATE & REPLACE** | Migrate messages to `SupportMessage` with structured JSON payloads in Phase 9, then deprecate. |
| `database/migrations/2026_08_13_000001_create_live_chats_table.php` | Creates `chat_conversations` and `chat_messages` tables. | Database Schema | **MIGRATE** | Replaced by structured support domain migrations in Phase 1. Existing data preserved via migration script. |
| `app/Http/Controllers/Admin/AiAgentController.php` | Manages AI Gateway credentials, dynamic OpenRouter 400+ model sync, and diagnostics testing. | `routes/api.php`, `AiAgentComponent.vue` | **KEEP & EXTEND** | Central gateway config remains authoritative for LLM credentials and provider status. |
| `app/Http/Controllers/Admin/AiController.php` | Admin product generation copilot (Title, Description, Tags, Vision). | `routes/api.php`, `BackendAiSidebarComponent.vue` | **KEEP** | Essential backend admin tooling unrelated to customer support. |
| `app/Services/AiService.php` | Factory/manager resolving `App\Http\AiAgents\Agents\*`. | `AiController` | **KEEP** | Retained for admin AI operations. |
| `app/Services/AiAgentService.php` | Business logic for AI agent listing, updating, and option persistence. | `AiAgentController` | **KEEP & EXTEND** | Retained for gateway and model configuration management. |
| `app/Services/AiAbstract.php` | Abstract class defining AI agent interface. | `Gemini`, `Openrouter`, `Openai` | **KEEP & EXTEND** | Core AI interface extended to support structured tool calling and streaming. |
| `app/Http/AiAgents/Agents/Gemini.php` | Google Gemini API integration (chat completions & multimodal vision). | `AiService`, `ChatService`, `AiAgentController` | **ADAPT & REUSE** | Reusable provider adapter for the new `AiOrchestrator`. |
| `app/Http/AiAgents/Agents/Openrouter.php` | OpenRouter API integration (400+ models, multi-turn chat). | `AiService`, `ChatService`, `AiAgentController` | **ADAPT & REUSE** | Primary multi-model provider adapter for the new `AiOrchestrator`. |
| `app/Http/AiAgents/Agents/Openai.php` | Direct OpenAI API integration. | `AiService`, `AiAgentController` | **ADAPT & REUSE** | Retained as secondary fallback provider. |
| `app/Models/AiAgent.php` | Model storing configured AI providers. | Gateway services, seeders, settings | **KEEP** | Core database model for gateway options and provider metadata. |
| `app/Models/GatewayOption.php` | Polymorphic key-value options for gateways. | `AiAgent`, payment/sms gateways | **KEEP** | Standard platform storage for encrypted credentials and options. |
| `app/Models/AiChatHistory.php` | Admin AI sidebar chat history model. | `AiController`, `AiChatHistoryService` | **KEEP** | Dedicated admin tool history storage. |
| `app/Traits/HasAiPrompt.php` | Trait for prompt localization and text formatting. | `AiAbstract` | **KEEP & EXTEND** | Retained for admin prompt formatting. |
| `resources/js/components/frontend/chat/LiveChatWidgetComponent.vue` | Prototype draggable floating chat widget for frontend visitors. | `resources/js/components/DefaultComponent.vue` | **REPLACE** | Replaced in Phase 4 with rich modular UI supporting structured cards, voice waveforms, order tracking, and product carousels. |
| `resources/js/components/admin/chat/LiveChatComponent.vue` | Prototype 2-column admin chat console. | `resources/js/router/modules/liveChatRoutes.js` | **REPLACE** | Replaced in Phase 5 with enterprise 3-column Support Center (Queue, Conversation, Customer 360). |
| `resources/js/components/admin/settings/AiAgent/AiAgentComponent.vue` | Admin UI for selecting active LLM engine, model search, and live diagnostics. | Admin Navigation | **KEEP & EXTEND** | Retained and expanded with Support AI routing controls. |
| `resources/js/components/layouts/backend/BackendAiSidebarComponent.vue` | Admin assistant sidebar. | Backend Layout Master | **KEEP** | Active admin assistant component. |
| `resources/js/router/modules/liveChatRoutes.js` | Vue router definition for `/admin/live-chat`. | `resources/js/router/index.js` | **MODIFY** | Updated to route to the new Enterprise Support Console component. |
| `routes/api.php` | API route registry for admin & frontend chat endpoints. | Laravel Kernel | **MODIFY** | Cleanly transition prototype routes to versioned `/api/v1/support/*` endpoints. |

---

## 4. Current Database Schema vs. Target Support Schema

```
EXISTING PROTOTYPE (Dynamic/Minimal)           TARGET ENTERPRISE DOMAIN (Robust/Indexed)
┌──────────────────────────────────────┐       ┌──────────────────────────────────────┐
│          chat_conversations          │       │        support_conversations         │
├──────────────────────────────────────┤       ├──────────────────────────────────────┤
│ id (PK)                              │       │ id (PK)                              │
│ session_token (varchar 64)           │       │ uuid / public_id (ulid, indexed)     │
│ user_id (FK -> users, nullable)      │       │ user_id (FK -> users, nullable)      │
│ user_name, user_email, user_phone    │       │ session_token (varchar 64, indexed)  │
│ status ('ai', 'human', 'closed')     │       │ status (enum 8 states)               │
│ ip_address                           │       │ mode ('ai', 'human', 'hybrid')       │
│ last_message_at                      │       │ priority ('low','normal','high','urg')│
│ timestamps                           │       │ language ('en', 'yo', 'ig', 'ha')    │
└──────────────────────────────────────┘       │ channel ('web', 'voice', 'whatsapp') │
                   │                           │ department_id (FK -> departments)    │
                   │ 1:N                       │ assigned_agent_id (FK -> users)      │
                   ▼                           │ ai_summary (text)                    │
┌──────────────────────────────────────┐       │ sentiment ('positive','neutral',etc) │
│            chat_messages             │       │ escalation_reason (varchar)          │
├──────────────────────────────────────┤       │ metadata (json)                      │
│ id (PK)                              │       │ timestamps & SLA counters            │
│ conversation_id (FK)                 │       └──────────────────────────────────────┘
│ sender_type ('user','ai','agent')    │                          │
│ sender_id (FK, nullable)             │                          │ 1:N
│ message (longText)                   │                          ▼
│ is_read (boolean)                    │       ┌──────────────────────────────────────┐
│ timestamps                           │       │           support_messages           │
└──────────────────────────────────────┘       ├──────────────────────────────────────┤
                                               │ id (PK)                              │
                                               │ conversation_id (FK)                 │
                                               │ sender_type ('customer','ai','agent')│
                                               │ sender_id (FK, nullable)             │
                                               │ message_type (enum 16 types)         │
                                               │ content (longText)                   │
                                               │ payload (json: product, order, cart) │
                                               │ is_internal (boolean, staff only)    │
                                               │ is_read (boolean)                    │
                                               │ tokens_used (integer)                │
                                               │ latency_ms (integer)                 │
                                               │ timestamps                           │
                                               └──────────────────────────────────────┘
```

### 4.1. Additional Target Tables in Support Domain
1. **`support_departments`**: `id`, `name`, `slug`, `description`, `is_active`, `timestamps`.
2. **`support_agent_profiles`**: `id`, `user_id` (FK), `department_id` (FK), `max_concurrent_chats`, `status` (`online`, `busy`, `away`, `offline`), `skills` (JSON), `timestamps`.
3. **`support_tickets`**: `id`, `ticket_number` (Unique), `conversation_id` (FK), `user_id` (FK), `department_id` (FK), `assigned_agent_id` (FK), `status` (`open`, `in_progress`, `waiting_customer`, `resolved`, `closed`), `priority`, `subject`, `description`, `resolved_at`, `timestamps`.
4. **`support_knowledge_articles`**: `id`, `category`, `title`, `slug`, `content` (Markdown), `tags` (JSON), `language` (`en`, `yo`, `ig`, `ha`), `is_active`, `view_count`, `timestamps`.
5. **`support_policies`**: `id`, `action_name`, `required_role`, `requires_confirmation`, `requires_human_approval`, `is_forbidden`, `rules` (JSON), `timestamps`.
6. **`support_audit_logs`**: `id`, `conversation_id` (FK), `actor_type` (`customer`, `ai`, `agent`, `system`), `actor_id` (nullable), `tool_name`, `action_type`, `input_payload` (JSON), `output_payload` (JSON), `ip_address`, `user_agent`, `created_at`.
7. **`support_voice_sessions`**: `id`, `conversation_id` (FK), `session_token`, `audio_url`, `transcript`, `detected_language`, `duration_seconds`, `status`, `timestamps`.
8. **`support_feedback`**: `id`, `conversation_id` (FK), `rating` (1-5), `comments`, `created_at`.

---

## 5. Authentication, Authorization & Identity Flow

```
                      Inbound Request to /api/v1/support/*
                                       │
                                       ▼
                     Is Bearer Token Present & Valid?
                                  /         \
                             [YES]           [NO]
                               /               \
                              ▼                 ▼
             Customer Identity Established    Guest Session Token Identified
             • user_id = auth()->id()         • user_id = null
             • Customer Scope Active          • Guest Scope Active
             • Order / Cart Scope Active      • Restricted Scope Active
                              \                 /
                               ▼               ▼
                        Tool Execution Authorization Check
                                       │
                     ┌─────────────────┴─────────────────┐
                     ▼                                   ▼
             Customer-Scoped Tool                  Public Tool
             (e.g., get_my_orders)          (e.g., search_products)
                     │                                   │
         Enforce: order.user_id === user_id         Public Catalog Access
                     │                                   │
                     └─────────────────┬─────────────────┘
                                       ▼
                             Audit Log Committed
```

### Key Security & Authorization Invariants
1. **Server-Derived Identity**: The AI agent and client application never supply arbitrary `user_id` parameters to tools. The authenticated customer ID is extracted securely on the backend via `$request->user()->id` (Sanctum).
2. **Resource Ownership Enforcement**: Every order inquiry (`get_my_order`), address lookup, or cart manipulation enforces `WHERE user_id = $authenticatedUserId`.
3. **Internal Note Shielding**: Internal support staff notes (`is_internal = true`) are strictly filtered out by Eloquent query scopes on all customer-facing endpoints.
4. **Session Continuity**: When a guest logs in during an ongoing chat, their `session_token` conversation is safely linked to their `user_id`, preserving full context without data exposure.

---

## 6. E-Commerce Domain & Business Logic Reuse Strategy

The AI support domain will **NOT** query raw database tables or re-implement business logic. Instead, it will interact exclusively through strictly typed service adapters:

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                              AI SUPPORT LAYER                                   │
│  (SupportOrchestrator ── PolicyEngine ── ToolRegistry ── ToolAdapters)          │
└──────────────────────────────────────┬──────────────────────────────────────────┘
                                       │
            ┌──────────────────────────┼──────────────────────────┐
            ▼                          ▼                          ▼
┌───────────────────────┐  ┌───────────────────────┐  ┌───────────────────────┐
│ Product Domain Layer  │  │   Order Domain Layer  │  │ Customer Domain Layer │
│ • ProductService      │  │ • FrontendOrderService│  │ • CustomerService     │
│ • VariationService    │  │ • OrderService        │  │ • AddressService      │
│ • CategoryService     │  │ • ReturnOrderService  │  │ • UserAddressService  │
│ • ReviewService       │  │ • ShippingSetupService│  │ • WishlistService     │
└───────────────────────┘  └───────────────────────┘  └───────────────────────┘
```

### Service Reuse Mapping

| Business Area | Existing Authoritative Service / Model | AI Tool Adapter Name | Reused Method / Capability |
| :--- | :--- | :--- | :--- |
| **Product Search** | `App\Services\ProductService` | `ProductToolAdapter::search` | `ProductService::list()` with full-text search, category, brand, and price filters. |
| **Stock & Variations**| `App\Services\ProductVariationService` | `ProductToolAdapter::getVariants`| `ProductVariationService::list()` ensuring real-time stock verification. |
| **Reviews & Ratings** | `App\Services\ProductReviewService` | `ProductToolAdapter::getReviews` | `ProductReviewService::list()` fetching authentic customer feedback. |
| **Order History** | `App\Services\FrontendOrderService` | `OrderToolAdapter::getMyOrders` | `FrontendOrderService::myOrder()` scoped strictly to `$user->id`. |
| **Order Details** | `App\Services\FrontendOrderService` | `OrderToolAdapter::getMyOrder` | `FrontendOrderService::show()` with strict ownership validation. |
| **Order Tracking** | `App\Services\OrderService` | `OrderToolAdapter::trackOrder` | `OrderService::show()` returning tracking number, status, delivery date. |
| **Cart Operations** | Vue Reactive Store / Cart Service | `CartToolAdapter` | Client-side reactive cart mutations dispatched via structured action payloads. |
| **Customer Profile** | `App\Services\ProfileService` | `CustomerToolAdapter::getProfile` | `ProfileService::show()` retrieving profile metadata and wallet balances. |
| **Delivery Address** | `App\Services\AddressService` | `CustomerToolAdapter::getAddresses`| `AddressService::list()` returning verified delivery addresses. |
| **Returns / Refunds** | `App\Services\ReturnOrderService` | `ReturnToolAdapter::checkEligibility`| `ReturnOrderService::list()` evaluating 7-day return window eligibility. |
| **Store Policies** | `App\Support\Knowledge\KnowledgeService` | `KnowledgeToolAdapter::query` | Semantic knowledge lookup for delivery rates, payment methods, warranty. |

---

## 7. AI Tool Registry & Controlled Tool Architecture

The AI orchestrator communicates with the LLM using standard structured Function/Tool Calling protocols (supported by OpenRouter, OpenAI, and Gemini).

### 7.1. Permitted Safe Tools
* **`search_products`**: Query product catalog by keyword, category, price range, color, or style.
* **`get_product_details`**: Retrieve comprehensive product specifications, available sizes, colors, and stock.
* **`compare_products`**: Side-by-side comparison of 2-4 products regarding specs, pricing, and ratings.
* **`check_stock`**: Real-time stock status check for specific product variations.
* **`get_my_orders`**: Retrieve authenticated customer's recent orders.
* **`get_order_status`**: Retrieve tracking milestone and shipping status for a customer order.
* **`get_store_policy`**: Query approved store knowledge on shipping, payments, returns, and FAQs.
* **`request_human_agent`**: Escalate conversation to human support queue with priority tagging.
* **`create_support_ticket`**: Generate a formal support ticket when an issue requires offline investigation.

### 7.2. Strictly Forbidden Tools (Non-Existent by Design)
* ❌ `execute_sql` / `raw_query`
* ❌ `read_database` / `list_tables`
* ❌ `read_filesystem` / `write_file`
* ❌ `execute_code` / `eval`
* ❌ `read_env` / `get_credentials`
* ❌ `list_all_customers` / `admin_override`

---

## 8. Sensitive Action Execution Matrix & Policy Engine

High-risk actions require explicit policy validation and customer confirmation before execution:

```
[Customer Intent: "Cancel Order #6IX-9821"]
                     │
                     ▼
           [Policy Engine Check]
  • Is order in 'pending' or 'confirmed' status? (Allowed)
  • Has order already shipped? (Disallowed -> Human Escalation)
  • Is user authenticated owner of order? (Enforced)
                     │
                     ▼
       [Action Confirmation Card Dispatched]
  "Are you sure you want to cancel Order #6IX-9821?
   Refund of ₦45,000 will be credited to your 6ix Culture Wallet."
   [❌ Keep Order]  [✅ Confirm Cancellation]
                     │
         (Customer clicks Confirm)
                     │
                     ▼
    [Signed Callback to OrderService::cancel]
  • State transition executed in database
  • Wallet credit processed
  • Support audit log recorded
  • Confirmation card updated in chat timeline
```

| Action Name | Risk Level | Authentication Required | Confirmation Required | Execution Authority |
| :--- | :--- | :--- | :--- | :--- |
| **Product Recommendation** | Low | No (Guest allowed) | No | AI Support Orchestrator |
| **Order Status Lookup** | Medium | Yes (or valid Tracking PIN) | No | `FrontendOrderService` |
| **Add to Cart** | Low | No (Guest/User) | No | Vue Cart Store |
| **Order Cancellation** | High | Yes (Strict Owner) | **Yes (Explicit Confirmation)** | `OrderService::cancel()` |
| **Refund Request** | High | Yes (Strict Owner) | **Yes (Staff Approval Gate)** | `ReturnAndRefundService` |
| **Address Update (Post-Order)**| High | Yes (Strict Owner) | **Yes (Eligibility Check)** | `OrderAddressService` |
| **Human Agent Transfer** | Low | No | No | `SupportQueueService` |

---

## 9. Human Support Center (3-Column Agent Console)

The admin support console (`/admin/support-center`) will be architected into a high-efficiency 3-column layout:

```
┌────────────────────────┬──────────────────────────────────┬────────────────────────┐
│  QUEUE & TRIAGE        │   CONVERSATION TIMELINE          │   CUSTOMER 360 & TOOLS │
├────────────────────────┼──────────────────────────────────┼────────────────────────┤
│ [Search & Filters...]  │ Header: Customer Name & Status   │ Customer Profile       │
│ • All (24)             │ Mode: [AI] [Human] [Hybrid]      │ • Name: Chioma Adeyemi │
│ • Needs Agent (4) 🚨   │ Agent: [Assigned to: Micheal ▼]  │ • Tier: VIP Customer   │
│ • AI Active (18)       ├──────────────────────────────────┤ • Wallet: ₦12,500.00   │
│ • Resolved (142)       │ [Customer 10:14 AM]              ├────────────────────────┤
├────────────────────────┤ Hi, can I exchange my hoodie?    │ Recent Orders          │
│ Conv #1042 — 2m ago    │                                  │ • #6IX-8921 (₦32,000)  │
│ Chioma A. [Needs Agent]│ [AI Assistant 10:14 AM]          │   Status: In Transit   │
│ "Need sizing exchange" │ Yes! We have a 7-day exchange    │ • #6IX-7710 (₦18,500)  │
├────────────────────────┤ policy on unworn items with tags.│   Status: Delivered    │
│ Conv #1041 — 5m ago    │                                  ├────────────────────────┤
│ Tunde B. [AI Active]   │ [Tool: get_my_order #6IX-8921]   │ AI Live Insights       │
│ "Tracking order #889"  │                                  │ • Sentiment: Neutral   │
├────────────────────────┤ [Internal Staff Note 10:16 AM] 🔒│ • Intent: Size Exchange│
│ Conv #1040 — 12m ago   │ Customer requested Size XL.      │ • Summary: Wants to    │
│ Guest #994 [AI Active] ├──────────────────────────────────┤   exchange Black Hoodie│
│ "Delivery to Abuja?"   │ [Composer: Reply / Internal Note]│   from L to XL.        │
│                        │ [Send Response] [⚡ AI Copilot]   │ Quick Actions:         │
│                        │                                  │ [Exchange] [Refund]    │
└────────────────────────┴──────────────────────────────────┴────────────────────────┘
```

---

## 10. Voice & Multilingual Architecture

```
                    Microphone Input (Customer Voice)
                                   │
                                   ▼
                Audio Chunking & Format Normalization (WebM/WAV)
                                   │
                                   ▼
                 Speech-To-Text Engine (Whisper / Cloud STT)
                                   │
                                   ▼
             Language Identification (English, Yoruba, Igbo, Hausa)
                                   │
                                   ▼
       Unified AI Support Pipeline (Policy Engine -> Tools -> Orchestrator)
                                   │
                                   ▼
                    Localized Structured AI Response
                                   │
                    ┌──────────────┴──────────────┐
                    ▼                             ▼
       Text Response Rendered in UI      Text-To-Speech Synthesis (TTS)
                                                  │
                                                  ▼
                                         Audio Stream to Customer
```

* **Zero Pipeline Divergence**: Voice input enters the exact same Policy Engine, Tool Registry, and Authorization pipeline as text chat.
* **Native Nigerian Language Support**: Localized prompts and translation support for Nigerian Pidgin, Yoruba, Igbo, and Hausa while keeping tool signatures language-agnostic.

---

## 11. Security Gap Analysis & Remediation Plan

| Vulnerability / Risk | Current Prototype State | Target Enterprise Architecture Remediation | Severity |
| :--- | :--- | :--- | :--- |
| **Prompt Injection / Jailbreaks** | Mitigated solely by System Prompt instructions. | **Multi-Tier Policy Engine**: Server-side validation of tool arguments, schema type enforcement, output sanitizers, and prompt boundary guards. | **CRITICAL** |
| **Unauthorized Data Access (IDOR)**| Frontend supplied user parameters. | **Strict Server-Side Auth Extraction**: Customer ID is exclusively derived from `$request->user()->id` via Sanctum middleware. | **CRITICAL** |
| **Staff Internal Notes Leakage** | No internal note separation existed in prototype. | **Database & Query Scope Isolation**: `is_internal` boolean column with default global scope excluding internal notes from customer APIs. | **HIGH** |
| **Credential Exposure in Code** | Keys were previously hardcoded in seeders/controllers. | **Environment & Database Option Storage**: Strict `env()` and encrypted `GatewayOption` storage with `.gitignore` enforcement. | **HIGH** |
| **Dynamic Table Creation in Runtime** | `ChatService::ensureTablesExist()` created tables on-the-fly during HTTP requests. | **Standard Declarative Migrations**: Full declarative database migrations executed via `php artisan migrate`. | **MEDIUM** |
| **Unbounded Token / API Abuse** | No per-session rate limits on AI chat requests. | **Laravel Rate Limiter (`throttle:support-chat`)**: IP-based and user-based limits (e.g., 30 requests/minute). | **HIGH** |

---

## 12. Proposed Target Logical Domain Structure

All new support capabilities will reside within a clean, encapsulated domain:

```
app/
├── Support/
│   ├── Contracts/
│   │   ├── AiOrchestratorInterface.php
│   │   ├── ToolInterface.php
│   │   ├── PolicyEngineInterface.php
│   │   └── KnowledgeRepositoryInterface.php
│   ├── DTOs/
│   │   ├── ChatMessageDTO.php
│   │   ├── ToolCallDTO.php
│   │   ├── ToolResultDTO.php
│   │   └── VoiceSessionDTO.php
│   ├── Enums/
│   │   ├── ConversationStatus.php
│   │   ├── ConversationMode.php
│   │   ├── MessageType.php
│   │   ├── SenderType.php
│   │   └── SupportPriority.php
│   ├── Events/
│   │   ├── SupportMessageSent.php
│   │   ├── SupportConversationAssigned.php
│   │   └── SupportEscalatedToHuman.php
│   ├── Models/
│   │   ├── SupportConversation.php
│   │   ├── SupportMessage.php
│   │   ├── SupportTicket.php
│   │   ├── SupportDepartment.php
│   │   ├── SupportAgentProfile.php
│   │   ├── SupportKnowledgeArticle.php
│   │   ├── SupportPolicy.php
│   │   ├── SupportAuditLog.php
│   │   └── SupportVoiceSession.php
│   ├── Policies/
│   │   ├── SupportConversationPolicy.php
│   │   └── SupportActionPolicyEngine.php
│   ├── Services/
│   │   ├── SupportOrchestrator.php
│   │   ├── SupportQueueService.php
│   │   ├── SupportTicketService.php
│   │   ├── KnowledgeBaseService.php
│   │   └── VoiceProcessingService.php
│   └── Tools/
│       ├── ToolRegistry.php
│       ├── Adapters/
│       │   ├── ProductToolAdapter.php
│       │   ├── OrderToolAdapter.php
│       │   ├── CartToolAdapter.php
│       │   ├── CustomerToolAdapter.php
│       │   └── ReturnToolAdapter.php
│       └── Definitions/
│           ├── SearchProductsTool.php
│           ├── GetMyOrderTool.php
│           ├── TrackOrderTool.php
│           └── CreateTicketTool.php
```

---

## 13. Phased Implementation Roadmap (Phases 0 through 12)

```
[Phase 0: Architecture Audit] ──► [Phase 1: DB & Domain Foundation] ──► [Phase 2: Orchestrator & Policy Engine]
                                                                                       │
┌──────────────────────────────────────────────────────────────────────────────────────┘
▼
[Phase 3: E-Commerce Tool Adapters] ──► [Phase 4: Customer Vue Chat UI] ──► [Phase 5: Human Support Console]
                                                                                       │
┌──────────────────────────────────────────────────────────────────────────────────────┘
▼
[Phase 6: Realtime Broadcast Events] ──► [Phase 7: Knowledge Base Admin] ──► [Phase 8: Voice & Multilingual]
                                                                                       │
┌──────────────────────────────────────────────────────────────────────────────────────┘
▼
[Phase 9: Data Migration] ──► [Phase 10: Production Route Switch] ──► [Phase 11: Security & Regression QA]
                                                                                       │
                                                                                       ▼
                                                                     [Phase 12: Legacy Cleanup & Freezing]
```

### Milestone Specifications

* **Phase 0 (Current)**: Architectural audit, dependency mapping, safety boundary definition.
* **Phase 1**: Support database migrations, Enums, Eloquent models, and seeders.
* **Phase 2**: AI Support Orchestrator, multi-model provider adapter, Policy Engine, and Tool Registry.
* **Phase 3**: Product, variation, cart, order, shipping, and ticket tool adapters.
* **Phase 4**: Customer Vue 3 floating chat widget with structured message cards (product cards, carousels, order tracking cards, action confirmations).
* **Phase 5**: 3-column Admin Support Center with triage queues, assignment, internal notes, customer 360 panel, and AI Copilot.
* **Phase 6**: Laravel event broadcasting for real-time chat, typing indicators, and queue updates.
* **Phase 7**: Support knowledge base CMS and policy administration interface.
* **Phase 8**: Voice processing (STT/TTS) and Nigerian multilingual support (English, Yoruba, Igbo, Hausa).
* **Phase 9**: Migration script transferring historical `chat_conversations` and `chat_messages` to new support domain tables.
* **Phase 10**: Production route transition and active UI component cutover.
* **Phase 11**: End-to-end automated testing (guest/auth flows, ownership checks, prompt injection resilience, sensitive action confirmations).
* **Phase 12**: Safe removal of verified-obsolete prototype files.

---

## 14. Phase 0 Audit Findings & Immediate Phase 1 Action Plan

### 14.1. Audit Findings Summary
1. **Separation of Admin vs Customer AI**: Admin content generation (`AiController`) and customer support (`ChatController`) are distinct systems and must not be conflated.
2. **Reusability of Gateway Infrastructure**: `AiAgentController` and `App\Http\AiAgents\Agents\*` provide an excellent gateway foundation for multi-model OpenRouter and Gemini execution.
3. **Database Architecture**: Current dynamic table creation in `ChatService` will be superseded by robust, indexed migrations in `App\Support\Models`.

### 14.2. Unresolved Technical Questions & Decisions
* **Realtime Broadcasting**: Will the deployment use Laravel Reverb, Pusher, or standard polling fallback? *(Defaulting to robust Pusher/Reverb driver with graceful polling fallback).*
* **Voice Transcription Service**: Will voice STT leverage OpenAI Whisper API via OpenRouter or Google Speech-To-Text? *(Orchestrator will support configurable Whisper and Gemini Audio adapters).*

### 14.3. Phase 1 Work Items (Database & Domain Foundation)
1. Generate migration `create_support_domain_tables.php` defining `support_departments`, `support_conversations`, `support_messages`, `support_tickets`, `support_agent_profiles`, `support_knowledge_articles`, `support_policies`, `support_audit_logs`, and `support_voice_sessions`.
2. Implement core Enums (`ConversationStatus`, `ConversationMode`, `MessageType`, `SenderType`, `SupportPriority`).
3. Implement Eloquent models in `app/Support/Models/` with relationships, query scopes, and type casts.
4. Create seeders for initial departments (Customer Care, Order Support, Styling & Sizing, Technical) and default knowledge base policies.
