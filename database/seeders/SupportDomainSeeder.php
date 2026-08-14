<?php

namespace Database\Seeders;

use App\Support\Enums\PolicyEffect;
use App\Support\Enums\ToolRiskLevel;
use App\Support\Models\SupportAITool;
use App\Support\Models\SupportDepartment;
use App\Support\Models\SupportPolicy;
use Illuminate\Database\Seeder;

class SupportDomainSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedDepartments();
        $this->seedPolicies();
        $this->seedAiTools();
    }

    private function seedDepartments(): void
    {
        $departments = [
            [
                'name' => 'General Support',
                'slug' => 'general-support',
                'description' => 'General inquiries, platform assistance, and store information.',
            ],
            [
                'name' => 'Sales',
                'slug' => 'sales',
                'description' => 'Product recommendations, pre-purchase inquiries, styling, and sizing.',
            ],
            [
                'name' => 'Orders',
                'slug' => 'orders',
                'description' => 'Order status, tracking, delivery scheduling, and address modifications.',
            ],
            [
                'name' => 'Returns & Refunds',
                'slug' => 'returns-refunds',
                'description' => '7-day returns, size exchanges, defect reports, and refund management.',
            ],
            [
                'name' => 'Payments',
                'slug' => 'payments',
                'description' => 'Payment failures, bank transfers, wallet credits, and coupon inquiries.',
            ],
            [
                'name' => 'Technical Support',
                'slug' => 'technical-support',
                'description' => 'Account access, login issues, app bugs, and checkout errors.',
            ],
            [
                'name' => 'Wholesale',
                'slug' => 'wholesale',
                'description' => 'Bulk ordering, corporate merchandising, and retail partnerships.',
            ],
        ];

        foreach ($departments as $dept) {
            SupportDepartment::updateOrCreate(
                ['slug' => $dept['slug']],
                [
                    'name' => $dept['name'],
                    'description' => $dept['description'],
                    'is_active' => true,
                ]
            );
        }
    }

    private function seedPolicies(): void
    {
        $policies = [
            [
                'key' => 'customer_data_scope',
                'name' => 'Customer Data Scope & Ownership Enforcement',
                'description' => 'Ensures customer data is strictly scoped to authenticated user ID.',
                'category' => 'security',
                'effect' => PolicyEffect::ALLOW,
                'priority' => 100,
                'configuration' => [
                    'enforce_ownership' => true,
                    'disallow_cross_tenant' => true,
                ],
            ],
            [
                'key' => 'internal_note_visibility',
                'name' => 'Internal Note Confidentiality Isolation',
                'description' => 'Strictly prevents staff internal notes from appearing in customer-facing APIs.',
                'category' => 'security',
                'effect' => PolicyEffect::DENY,
                'priority' => 100,
                'configuration' => [
                    'customer_facing' => false,
                ],
            ],
            [
                'key' => 'prompt_injection_policy',
                'name' => 'Prompt Injection & Safety Boundary Defense',
                'description' => 'Blocks attempts by user input to override system rules or policy constraints.',
                'category' => 'ai_safety',
                'effect' => PolicyEffect::DENY,
                'priority' => 95,
                'configuration' => [
                    'block_override_prompts' => true,
                    'sanitize_tool_inputs' => true,
                ],
            ],
            [
                'key' => 'refund_requires_approval',
                'name' => 'Refund Human Approval Gate',
                'description' => 'Mandates human support supervisor review before financial refunds are approved.',
                'category' => 'financial',
                'effect' => PolicyEffect::REQUIRE_HUMAN,
                'priority' => 90,
                'configuration' => [
                    'require_receipt' => true,
                    'max_auto_refund' => 0,
                ],
            ],
            [
                'key' => 'sensitive_action_confirmation',
                'name' => 'Sensitive Action Explicit Customer Confirmation',
                'description' => 'Requires explicit visual card confirmation before order cancellations or modifications.',
                'category' => 'security',
                'effect' => PolicyEffect::CONFIRM,
                'priority' => 85,
                'configuration' => [
                    'requires_explicit_confirmation_card' => true,
                ],
            ],
            [
                'key' => 'shipping_policy_grounding',
                'name' => 'Store Policy & Knowledge Grounding',
                'description' => 'Ensures shipping, returns, and payment answers are grounded in approved store articles.',
                'category' => 'knowledge',
                'effect' => PolicyEffect::ALLOW,
                'priority' => 80,
                'configuration' => [
                    'ground_in_knowledge_base' => true,
                ],
            ],
        ];

        foreach ($policies as $pol) {
            SupportPolicy::updateOrCreate(
                ['key' => $pol['key']],
                [
                    'name' => $pol['name'],
                    'description' => $pol['description'],
                    'category' => $pol['category'],
                    'effect' => $pol['effect'],
                    'priority' => $pol['priority'],
                    'configuration' => $pol['configuration'],
                    'is_active' => true,
                ]
            );
        }
    }

    private function seedAiTools(): void
    {
        $tools = [
            [
                'key' => 'search_products',
                'name' => 'Search Products',
                'description' => 'Search fashion catalog by query, category, color, or price range.',
                'category' => 'product',
                'risk_level' => ToolRiskLevel::LOW,
                'requires_authentication' => false,
                'requires_confirmation' => false,
                'requires_human' => false,
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Search keywords or product name'],
                        'category' => ['type' => 'string', 'description' => 'Category slug or name'],
                        'max_price' => ['type' => 'number', 'description' => 'Maximum price in NGN'],
                    ],
                ],
            ],
            [
                'key' => 'get_product_details',
                'name' => 'Get Product Details',
                'description' => 'Retrieve detailed information, available sizes, colors, and stock for a product.',
                'category' => 'product',
                'risk_level' => ToolRiskLevel::LOW,
                'requires_authentication' => false,
                'requires_confirmation' => false,
                'requires_human' => false,
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'product_id' => ['type' => 'integer', 'description' => 'Product database ID'],
                        'slug' => ['type' => 'string', 'description' => 'Product URL slug'],
                    ],
                ],
            ],
            [
                'key' => 'get_my_orders',
                'name' => 'Get My Orders',
                'description' => 'Retrieve list of recent orders belonging to the authenticated customer.',
                'category' => 'order',
                'risk_level' => ToolRiskLevel::NORMAL,
                'requires_authentication' => true,
                'requires_confirmation' => false,
                'requires_human' => false,
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'description' => 'Number of orders to retrieve (max 10)'],
                    ],
                ],
            ],
            [
                'key' => 'track_my_order',
                'name' => 'Track Order',
                'description' => 'Retrieve real-time shipping milestone and tracking information for an order.',
                'category' => 'order',
                'risk_level' => ToolRiskLevel::NORMAL,
                'requires_authentication' => false,
                'requires_confirmation' => false,
                'requires_human' => false,
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'order_number' => ['type' => 'string', 'description' => 'Order serial or tracking code'],
                    ],
                    'required' => ['order_number'],
                ],
            ],
            [
                'key' => 'create_support_ticket',
                'name' => 'Create Support Ticket',
                'description' => 'Create a formal support ticket for complex issues requiring follow-up.',
                'category' => 'support',
                'risk_level' => ToolRiskLevel::NORMAL,
                'requires_authentication' => false,
                'requires_confirmation' => false,
                'requires_human' => false,
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'subject' => ['type' => 'string', 'description' => 'Ticket subject'],
                        'description' => ['type' => 'string', 'description' => 'Issue description'],
                        'department' => ['type' => 'string', 'description' => 'Target department slug'],
                    ],
                    'required' => ['subject', 'description'],
                ],
            ],
            [
                'key' => 'request_human_agent',
                'name' => 'Request Human Support Agent',
                'description' => 'Transfer the live conversation to a human support agent.',
                'category' => 'support',
                'risk_level' => ToolRiskLevel::LOW,
                'requires_authentication' => false,
                'requires_confirmation' => false,
                'requires_human' => false,
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'reason' => ['type' => 'string', 'description' => 'Reason for escalation'],
                        'department' => ['type' => 'string', 'description' => 'Target department slug'],
                    ],
                ],
            ],
            [
                'key' => 'cancel_order',
                'name' => 'Cancel Customer Order',
                'description' => 'Initiate cancellation of an unshipped order with customer confirmation.',
                'category' => 'order',
                'risk_level' => ToolRiskLevel::SENSITIVE,
                'requires_authentication' => true,
                'requires_confirmation' => true,
                'requires_human' => false,
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'order_id' => ['type' => 'integer', 'description' => 'Order ID to cancel'],
                        'reason' => ['type' => 'string', 'description' => 'Cancellation reason'],
                    ],
                    'required' => ['order_id'],
                ],
            ],
            [
                'key' => 'request_refund',
                'name' => 'Request Financial Refund',
                'description' => 'Submit a refund request for human supervisor review and processing.',
                'category' => 'financial',
                'risk_level' => ToolRiskLevel::CRITICAL,
                'requires_authentication' => true,
                'requires_confirmation' => true,
                'requires_human' => true,
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'order_id' => ['type' => 'integer', 'description' => 'Order ID for refund'],
                        'amount' => ['type' => 'number', 'description' => 'Refund amount in NGN'],
                        'reason' => ['type' => 'string', 'description' => 'Detailed reason'],
                    ],
                    'required' => ['order_id', 'reason'],
                ],
            ],
        ];

        foreach ($tools as $tool) {
            SupportAITool::updateOrCreate(
                ['key' => $tool['key']],
                [
                    'name' => $tool['name'],
                    'description' => $tool['description'],
                    'category' => $tool['category'],
                    'risk_level' => $tool['risk_level'],
                    'requires_authentication' => $tool['requires_authentication'],
                    'requires_confirmation' => $tool['requires_confirmation'],
                    'requires_human' => $tool['requires_human'],
                    'input_schema' => $tool['input_schema'],
                    'is_active' => true,
                ]
            );
        }
    }
}
