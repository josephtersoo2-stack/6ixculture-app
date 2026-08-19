<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for high-traffic Support domain performance indexes.
     */
    public function up(): void
    {
        if (Schema::hasTable('support_conversations')) {
            Schema::table('support_conversations', function (Blueprint $table) {
                $table->index(['customer_id', 'status'], 'support_conv_cust_status_idx');
                $table->index(['assigned_agent_id', 'status'], 'support_conv_agent_status_idx');
                $table->index(['department_id', 'status'], 'support_conv_dept_status_idx');
            });
        }

        if (Schema::hasTable('support_messages')) {
            Schema::table('support_messages', function (Blueprint $table) {
                $table->index(['conversation_id', 'is_internal', 'id'], 'support_msg_conv_internal_id_idx');
            });
        }

        if (Schema::hasTable('support_tickets')) {
            Schema::table('support_tickets', function (Blueprint $table) {
                $table->index(['customer_id', 'status'], 'support_ticket_cust_status_idx');
                $table->index(['conversation_id', 'status'], 'support_ticket_conv_status_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('support_conversations')) {
            Schema::table('support_conversations', function (Blueprint $table) {
                $table->dropIndex('support_conv_cust_status_idx');
                $table->dropIndex('support_conv_agent_status_idx');
                $table->dropIndex('support_conv_dept_status_idx');
            });
        }

        if (Schema::hasTable('support_messages')) {
            Schema::table('support_messages', function (Blueprint $table) {
                $table->dropIndex('support_msg_conv_internal_id_idx');
            });
        }

        if (Schema::hasTable('support_tickets')) {
            Schema::table('support_tickets', function (Blueprint $table) {
                $table->dropIndex('support_ticket_cust_status_idx');
                $table->dropIndex('support_ticket_conv_status_idx');
            });
        }
    }
};
