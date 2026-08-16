<template>
    <div class="support-conversation-header px-4 py-3 border-b border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 flex items-center justify-between gap-3">
        <!-- Customer & Conversation Context -->
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-gray-900 dark:bg-white text-white dark:text-gray-900 font-black text-xs flex items-center justify-center flex-shrink-0 shadow-xs">
                {{ customerInitials }}
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">
                        {{ conversation.customer?.name || 'Customer' }}
                    </h3>
                    <span v-if="conversation.customer?.is_guest" class="px-1.5 py-0.5 text-[9px] font-bold bg-amber-100 dark:bg-amber-900/40 text-amber-900 dark:text-amber-300 rounded uppercase">
                        Guest
                    </span>
                    <span class="text-xs text-gray-400">#{{ conversation.id }}</span>
                </div>
                <div class="text-[11px] text-gray-500 flex items-center gap-2 mt-0.5">
                    <span v-if="conversation.customer?.email">{{ conversation.customer.email }}</span>
                    <span v-if="conversation.customer?.phone">• {{ conversation.customer.phone }}</span>
                    <span>• Lang: {{ conversation.language?.toUpperCase() || 'EN' }}</span>
                </div>
            </div>
        </div>

        <!-- Action Controls -->
        <div class="flex items-center gap-2 flex-wrap justify-end">
            <!-- Status Dropdown -->
            <select
                :value="conversation.status"
                @change="onStatusChange($event.target.value)"
                class="text-xs font-semibold px-2 py-1 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-200 focus:outline-none"
            >
                <option value="queued">Queued</option>
                <option value="human_active">In Progress</option>
                <option value="awaiting_customer">Awaiting Customer</option>
                <option value="awaiting_agent">Awaiting Agent</option>
                <option value="resolved">Resolved</option>
                <option value="closed">Closed</option>
            </select>

            <!-- Priority Dropdown -->
            <select
                :value="conversation.priority"
                @change="onPriorityChange($event.target.value)"
                class="text-xs font-semibold px-2 py-1 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-200 focus:outline-none"
            >
                <option value="low">Low Priority</option>
                <option value="normal">Normal Priority</option>
                <option value="high">High Priority</option>
                <option value="urgent">Urgent</option>
            </select>

            <!-- Department Dropdown -->
            <select
                :value="conversation.department?.id || ''"
                @change="onDepartmentChange($event.target.value)"
                class="text-xs font-semibold px-2 py-1 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-200 focus:outline-none"
            >
                <option value="" disabled>Select Department</option>
                <option v-for="d in departments" :key="d.id" :value="d.id">
                    {{ d.name }}
                </option>
            </select>

            <!-- Quick Self Assign -->
            <button
                v-if="!isAssignedToMe"
                type="button"
                @click="assignToMe"
                class="px-2.5 py-1 text-xs font-semibold bg-gray-900 dark:bg-white text-white dark:text-gray-900 rounded-lg hover:bg-gray-800 transition-colors shadow-xs"
            >
                Assign to Me
            </button>
            <span v-else class="px-2 py-1 text-xs font-medium bg-emerald-50 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 rounded-lg flex items-center gap-1">
                <i class="lab lab-check text-xs"></i> Assigned to You
            </span>
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
    },
};
</script>
