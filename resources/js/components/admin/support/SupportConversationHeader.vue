<template>
    <div class="support-conversation-header px-4 py-3 border-b border-[#1F293D] bg-[#0E1424] flex items-center justify-between gap-3 flex-shrink-0">
        <!-- Customer & Conversation Context -->
        <div class="flex items-center gap-3 min-w-0">
            <div class="relative flex-shrink-0">
                <div class="w-9 h-9 rounded-full bg-[#1E293B] text-slate-100 font-bold text-xs flex items-center justify-center shadow-xs border border-[#1F293D]">
                    {{ customerInitials }}
                </div>
                <span
                    class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full border-2 border-[#0E1424]"
                    :class="conversation.customer?.is_guest ? 'bg-amber-400' : 'bg-emerald-500'"
                ></span>
            </div>

            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h3 class="text-sm font-bold text-white truncate">
                        {{ conversation.customer?.name || 'Customer' }}
                    </h3>
                    <span class="flex items-center gap-1 text-[11px] text-emerald-400 font-medium">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block"></span>
                        Online
                    </span>
                    <span v-if="conversation.customer?.is_guest" class="px-1.5 py-0.2 text-[9px] font-bold bg-[#1E293B] text-amber-400 rounded uppercase">
                        Guest
                    </span>
                    <span class="text-[11px] text-slate-500 font-mono">#{{ conversation.id }}</span>
                </div>
                <div class="text-[11px] text-slate-400 flex items-center gap-2 mt-0.5 truncate">
                    <span v-if="conversation.customer?.email" class="truncate">{{ conversation.customer.email }}</span>
                    <span v-if="conversation.customer?.phone">• {{ conversation.customer.phone }}</span>
                    <span>• {{ (conversation.language || 'en').toUpperCase() }}</span>
                </div>
            </div>
        </div>

        <!-- Action Controls -->
        <div class="flex items-center gap-2 flex-wrap justify-end flex-shrink-0">
            <!-- Department Dropdown -->
            <select
                :value="conversation.department?.id || ''"
                @change="onDepartmentChange($event.target.value)"
                class="text-xs font-medium px-2.5 py-1.5 rounded-xl border border-[#1F293D] bg-[#131B2E] text-slate-300 focus:outline-none focus:border-indigo-500"
                title="Support Department"
            >
                <option value="" disabled>Select Department</option>
                <option v-for="d in departments" :key="d.id" :value="d.id">
                    {{ d.name }}
                </option>
            </select>

            <!-- Priority Dropdown -->
            <select
                :value="conversation.priority || 'normal'"
                @change="onPriorityChange($event.target.value)"
                class="text-xs font-medium px-2.5 py-1.5 rounded-xl border border-[#1F293D] bg-[#131B2E] text-slate-300 focus:outline-none focus:border-indigo-500"
                title="Conversation Priority"
            >
                <option value="low">Low Priority</option>
                <option value="normal">Normal Priority</option>
                <option value="high">High Priority</option>
                <option value="urgent">Urgent</option>
            </select>

            <!-- Status Dropdown -->
            <select
                :value="conversation.status"
                @change="onStatusChange($event.target.value)"
                class="text-xs font-medium px-2.5 py-1.5 rounded-xl border border-[#1F293D] bg-[#131B2E] text-slate-300 focus:outline-none focus:border-indigo-500"
                title="Conversation Status"
            >
                <option value="queued">Queued</option>
                <option value="human_active">In Progress</option>
                <option value="awaiting_customer">Awaiting Customer</option>
                <option value="resolved">Resolved</option>
                <option value="closed">Closed</option>
            </select>

            <!-- Quick Assign / End Chat Buttons -->
            <button
                v-if="!isAssignedToMe"
                type="button"
                @click="assignToMe"
                class="px-3 py-1.5 text-xs font-semibold bg-[#1E293B] hover:bg-[#283548] text-slate-200 border border-[#1F293D] rounded-xl transition-colors shadow-xs flex items-center gap-1.5"
                title="Claim this conversation"
            >
                <i class="lab lab-user-check text-xs"></i>
                <span>Assign to Me</span>
            </button>

            <!-- End Chat / Resolve Button -->
            <button
                v-if="conversation.status !== 'resolved' && conversation.status !== 'closed'"
                type="button"
                @click="resolveConversation"
                class="px-3.5 py-1.5 text-xs font-bold bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl transition-colors shadow-xs flex items-center gap-1.5"
                title="Resolve and close conversation"
            >
                <span>End Chat</span>
            </button>
        </div>
    </div>
</template>

<script>
export default {
    name: 'SupportConversationHeader',
    props: {
        conversation: {
            type: Object,
            required: true,
        },
        departments: {
            type: Array,
            default: () => [],
        },
    },
    computed: {
        customerInitials() {
            const name = this.conversation.customer?.name || 'Customer';
            const parts = name.trim().split(' ');
            if (parts.length >= 2) {
                return (parts[0][0] + parts[1][0]).toUpperCase();
            }
            return name.substring(0, 2).toUpperCase();
        },
        isAssignedToMe() {
            const currentUserId = this.$store.getters.authId;
            return this.conversation.assigned_agent?.id === currentUserId;
        },
    },
    methods: {
        onStatusChange(status) {
            this.$store.dispatch('adminSupport/updateStatus', {
                publicId: this.conversation.id,
                status,
            });
        },
        onPriorityChange(priority) {
            this.$store.dispatch('adminSupport/updatePriority', {
                publicId: this.conversation.id,
                priority,
            });
        },
        onDepartmentChange(departmentId) {
            if (!departmentId) return;
            this.$store.dispatch('adminSupport/updateDepartment', {
                publicId: this.conversation.id,
                departmentId,
            });
        },
        assignToMe() {
            this.$store.dispatch('adminSupport/assignConversation', {
                publicId: this.conversation.id,
                agentId: 'self',
            });
        },
        resolveConversation() {
            this.$store.dispatch('adminSupport/updateStatus', {
                publicId: this.conversation.id,
                status: 'resolved',
                reason: 'Resolved by agent in support console',
            });
        },
    },
};
</script>
