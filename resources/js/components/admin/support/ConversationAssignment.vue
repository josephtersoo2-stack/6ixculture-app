<template>
    <div class="conversation-assignment p-4 bg-[#0E1424]">
        <h4 class="text-xs font-bold text-white uppercase tracking-wider mb-2 flex items-center gap-1.5">
            <i class="lab lab-user-check text-indigo-400"></i>
            <span>Agent Assignment</span>
        </h4>

        <div class="space-y-2">
            <div>
                <label class="block text-[10px] uppercase font-bold text-slate-400 mb-1">Assignee</label>
                <select
                    :value="conversation?.assigned_agent?.id || ''"
                    @change="onAssignAgent($event.target.value)"
                    class="w-full text-xs font-semibold px-2.5 py-1.5 rounded-xl border border-[#1F293D] bg-[#131B2E] text-slate-200 focus:outline-none focus:border-indigo-500"
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
                    class="flex-1 py-1.5 text-xs font-semibold bg-[#131B2E] hover:bg-[#1E293B] border border-[#1F293D] text-slate-200 rounded-xl transition-colors text-center"
                >
                    Claim (Self)
                </button>
                <button
                    v-if="conversation?.assigned_agent"
                    type="button"
                    @click="unassign"
                    class="py-1.5 px-3 text-xs font-semibold text-rose-400 hover:bg-rose-950/40 border border-rose-900/30 rounded-xl transition-colors text-center"
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
