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
};
</script>
