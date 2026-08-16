<template>
    <div class="conversation-assignment p-4 bg-white dark:bg-gray-900">
        <h4 class="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider mb-2 flex items-center gap-1.5">
            <i class="lab lab-user-check text-slate-500"></i>
            <span>Agent Assignment</span>
        </h4>

        <div class="space-y-2">
            <div>
                <label class="block text-[10px] uppercase font-bold text-gray-400 mb-1">Assignee</label>
                <select
                    :value="conversation?.assigned_agent?.id || ''"
                    @change="onAssignAgent($event.target.value)"
                    class="w-full text-xs font-semibold px-2.5 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-200 focus:outline-none"
                >
                    <option value="">Unassigned</option>
                    <option v-for="agent in agents" :key="agent.id" :value="agent.id">
                        {{ agent.name }} ({{ agent.email }})
                    </option>
                </select>
            </div>

            <div class="flex items-center gap-2 pt-1">
                <button
                    type="button"
                    @click="claimSelf"
                    class="flex-1 py-1.5 text-xs font-semibold bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg transition-colors text-center"
                >
                    Claim (Self)
                </button>
                <button
                    v-if="conversation?.assigned_agent"
                    type="button"
                    @click="unassign"
                    class="py-1.5 px-3 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-lg transition-colors text-center"
                >
                    Unassign
                </button>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'ConversationAssignment',
    props: {
        conversation: {
            type: Object,
            default: null,
        },
        agents: {
            type: Array,
            default: () => [],
        },
    },
    methods: {
        onAssignAgent(agentId) {
            if (!this.conversation) return;
            this.$store.dispatch('adminSupport/assignConversation', {
                publicId: this.conversation.id,
                agentId: agentId || null,
            });
        },
        claimSelf() {
            if (!this.conversation) return;
            this.$store.dispatch('adminSupport/assignConversation', {
                publicId: this.conversation.id,
                agentId: 'self',
            });
        },
        unassign() {
            if (!this.conversation) return;
            this.$store.dispatch('adminSupport/assignConversation', {
                publicId: this.conversation.id,
                agentId: null,
            });
        },
    },
};
</script>
