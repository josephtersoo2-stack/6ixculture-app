<?php

namespace Tests\Unit\Support;

use App\Support\Models\SupportAuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_appends_and_sanitizes_sensitive_secrets(): void
    {
        $log = SupportAuditLog::log([
            'actor_type' => 'customer',
            'actor_id' => 42,
            'action' => 'order_lookup',
            'tool_name' => 'get_my_order',
            'authorization_result' => 'allowed',
            'before_data' => [
                'order_id' => 101,
                'api_key' => 'sk-or-v1-secret-key-12345',
                'customer_password' => 'super_secret_pass',
            ],
            'after_data' => [
                'status' => 'delivered',
                'auth_token' => 'bearer_token_xyz',
            ],
        ]);

        $this->assertNotNull($log->id);
        $this->assertEquals('customer', $log->actor_type);
        $this->assertEquals('order_lookup', $log->action);

        // Verify sensitive keys are redacted
        $this->assertEquals('[REDACTED]', $log->before_data['api_key']);
        $this->assertEquals('[REDACTED]', $log->before_data['customer_password']);
        $this->assertEquals('[REDACTED]', $log->after_data['auth_token']);
        $this->assertEquals(101, $log->before_data['order_id']);
        $this->assertEquals('delivered', $log->after_data['status']);
    }
}
