<template>
    <div class="customer-360-panel w-80 flex flex-col h-full bg-white dark:bg-gray-900 border-l border-gray-200 dark:border-gray-800 overflow-y-auto thin-scrolling">
        <!-- Header -->
        <div class="px-4 py-3.5 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between">
            <h3 class="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider flex items-center gap-1.5">
                <i class="lab lab-user-circle text-slate-500"></i>
                <span>Customer 360</span>
            </h3>
            <span class="text-[10px] font-bold text-emerald-600 bg-emerald-50 dark:bg-emerald-950/40 px-2 py-0.5 rounded-full">
                Live Context
            </span>
        </div>

        <template v-if="conversation">
            <!-- 1. Customer Profile Card -->
            <CustomerProfileCard
                :customer360="customer360"
            />

            <!-- 2. Recent Orders Panel -->
            <CustomerOrdersPanel
                :orders="orders"
            />

            <!-- 3. Support Tickets Panel -->
            <CustomerTicketsPanel
                :ticket="ticket || conversation.ticket"
            />

            <!-- 4. Assignment Control -->
            <ConversationAssignment
                :conversation="conversation"
                :agents="agents"
            />

            <!-- 5. Quick Actions Panel (Blueprint Section 9) -->
            <div class="p-4 border-t border-gray-200 dark:border-gray-800 space-y-3">
                <div class="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    Quick Actions
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <button
                        @click="handleQuickAction('track_order')"
                        class="p-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-slate-50 dark:bg-gray-800/60 hover:bg-white dark:hover:bg-gray-800 text-[11px] font-bold text-gray-700 dark:text-gray-200 flex items-center gap-1.5 transition-colors shadow-2xs"
                    >
                        <i class="lab lab-line-truck-check text-indigo-500"></i>
                        <span>Track Order</span>
                    </button>

                    <button
                        @click="handleQuickAction('initiate_return')"
                        class="p-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-slate-50 dark:bg-gray-800/60 hover:bg-white dark:hover:bg-gray-800 text-[11px] font-bold text-gray-700 dark:text-gray-200 flex items-center gap-1.5 transition-colors shadow-2xs"
                    >
                        <i class="lab lab-line-undo text-amber-500"></i>
                        <span>Initiate Return</span>
                    </button>

                    <button
                        @click="handleQuickAction('issue_refund')"
                        class="p-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-slate-50 dark:bg-gray-800/60 hover:bg-white dark:hover:bg-gray-800 text-[11px] font-bold text-gray-700 dark:text-gray-200 flex items-center gap-1.5 transition-colors shadow-2xs"
                    >
                        <i class="lab lab-fill-moneys text-emerald-500"></i>
                        <span>Issue Refund</span>
                    </button>

                    <button
                        @click="handleQuickAction('send_coupon')"
                        class="p-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-slate-50 dark:bg-gray-800/60 hover:bg-white dark:hover:bg-gray-800 text-[11px] font-bold text-gray-700 dark:text-gray-200 flex items-center gap-1.5 transition-colors shadow-2xs"
                    >
                        <i class="lab lab-fill-ticket-discount text-rose-500"></i>
                        <span>Send Coupon</span>
                    </button>
                </div>
            </div>
        </template>

        <div v-else class="p-8 text-center text-xs text-gray-400">
            Select a conversation to view customer 360 details.
        </div>
    </div>
</template>

<script>
import CustomerProfileCard from './CustomerProfileCard.vue';
import CustomerOrdersPanel from './CustomerOrdersPanel.vue';
import CustomerTicketsPanel from './CustomerTicketsPanel.vue';
import ConversationAssignment from './ConversationAssignment.vue';

export default {
    name: 'Customer360Panel',
    components: {
        CustomerProfileCard,
        CustomerOrdersPanel,
        CustomerTicketsPanel,
        ConversationAssignment,
    },
    props: {
        conversation: {
            type: Object,
            default: null,
        },
        customer360: {
            type: Object,
            default: null,
        },
        orders: {
            type: Array,
            default: () => [],
        },
        ticket: {
            type: Object,
            default: null,
        },
        agents: {
            type: Array,
            default: () => [],
        },
    },
    methods: {
        handleQuickAction(actionType) {
            if (!this.conversation) return;
            this.$store.dispatch('adminSupport/executeAction', {
                action: actionType,
                conversation_id: this.conversation.public_id,
            });
        },
    },
};
</script>
