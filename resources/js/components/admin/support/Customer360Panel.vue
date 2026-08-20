<template>
    <div class="customer-360-panel w-full flex flex-col h-full bg-[#0E1424] border-l border-[#1F293D] overflow-y-auto thin-scrolling">
        <!-- Header -->
        <div class="px-4 py-3.5 border-b border-[#1F293D] flex items-center justify-between">
            <h3 class="text-xs font-bold text-white uppercase tracking-wider flex items-center gap-1.5">
                <i class="lab lab-user text-indigo-400"></i>
                <span>Customer Info</span>
            </h3>
            <span class="text-[10px] font-bold text-emerald-400 bg-emerald-950/60 border border-emerald-800/40 px-2 py-0.5 rounded-full">
                Live 360
            </span>
        </div>

        <template v-if="conversation">
            <!-- 1. Customer Profile Details -->
            <CustomerProfileCard
                :customer360="customer360"
            />

            <!-- 2. Recent Orders Section -->
            <CustomerOrdersPanel
                :orders="orders"
            />

            <!-- 3. Quick Actions Grid -->
            <div class="p-4 border-b border-[#1F293D] space-y-2.5 bg-[#0E1424]">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                    Quick Actions
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <router-link
                        to="/admin/online-orders"
                        class="p-2 rounded-xl border border-[#1F293D] bg-[#131B2E] hover:bg-[#1E293B] text-[11px] font-bold text-slate-200 flex items-center gap-1.5 transition-colors shadow-2xs"
                    >
                        <i class="lab lab-bag text-indigo-400"></i>
                        <span>View Order</span>
                    </router-link>

                    <button
                        type="button"
                        @click="handleQuickAction('track_order')"
                        class="p-2 rounded-xl border border-[#1F293D] bg-[#131B2E] hover:bg-[#1E293B] text-[11px] font-bold text-slate-200 flex items-center gap-1.5 transition-colors shadow-2xs"
                    >
                        <i class="lab lab-line-truck-check text-emerald-400"></i>
                        <span>Track Order</span>
                    </button>

                    <button
                        type="button"
                        @click="handleQuickAction('create_ticket')"
                        class="p-2 rounded-xl border border-[#1F293D] bg-[#131B2E] hover:bg-[#1E293B] text-[11px] font-bold text-slate-200 flex items-center gap-1.5 transition-colors shadow-2xs"
                    >
                        <i class="lab lab-ticket text-amber-400"></i>
                        <span>Create Ticket</span>
                    </button>

                    <button
                        type="button"
                        @click="handleQuickAction('assign_agent')"
                        class="p-2 rounded-xl border border-[#1F293D] bg-[#131B2E] hover:bg-[#1E293B] text-[11px] font-bold text-slate-200 flex items-center gap-1.5 transition-colors shadow-2xs"
                    >
                        <i class="lab lab-user-check text-indigo-400"></i>
                        <span>Assign Agent</span>
                    </button>
                </div>
            </div>

            <!-- 4. Support Tickets Section -->
            <CustomerTicketsPanel
                :ticket="ticket || conversation.ticket"
            />

            <!-- 5. Assignment Control -->
            <ConversationAssignment
                :conversation="conversation"
                :agents="agents"
            />
        </template>

        <div v-else class="p-8 text-center text-xs text-slate-500">
            Select a conversation to view customer details.
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
            if (actionType === 'track_order') {
                this.$store.dispatch('adminSupport/sendReply', {
                    publicId: this.conversation.id,
                    message: 'Checking the live tracking status for your order...',
                });
            } else if (actionType === 'assign_agent') {
                this.$store.dispatch('adminSupport/assignConversation', {
                    publicId: this.conversation.id,
                    agentId: 'self',
                });
            }
        },
    },
};
</script>
