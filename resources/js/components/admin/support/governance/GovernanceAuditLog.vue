<template>
    <div class="governance-audit-log space-y-4">
        <!-- Filter Bar -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 bg-slate-50 p-4 rounded-xl border border-slate-200">
            <div class="flex flex-wrap items-center gap-2 flex-1">
                <input
                    type="text"
                    v-model="filters.search"
                    placeholder="Search audit actions, resources..."
                    class="px-3 py-1.5 bg-white border border-slate-300 rounded-lg text-xs focus:outline-none focus:border-slate-900 min-w-[220px]"
                />

                <select
                    v-model="filters.action"
                    @change="loadLogs"
                    class="px-2.5 py-1.5 bg-white border border-slate-300 rounded-lg text-xs focus:outline-none focus:border-slate-900"
                >
                    <option value="">All Governance Actions</option>
                    <option value="KNOWLEDGE_ARTICLE_CREATED">KNOWLEDGE_ARTICLE_CREATED</option>
                    <option value="KNOWLEDGE_ARTICLE_UPDATED">KNOWLEDGE_ARTICLE_UPDATED</option>
                    <option value="KNOWLEDGE_ARTICLE_PUBLISHED">KNOWLEDGE_ARTICLE_PUBLISHED</option>
                    <option value="KNOWLEDGE_ARTICLE_ARCHIVED">KNOWLEDGE_ARTICLE_ARCHIVED</option>
                    <option value="KNOWLEDGE_ARTICLE_ROLLBACK">KNOWLEDGE_ARTICLE_ROLLBACK</option>
                    <option value="POLICY_CREATED">POLICY_CREATED</option>
                    <option value="POLICY_UPDATED">POLICY_UPDATED</option>
                    <option value="POLICY_ACTIVATED">POLICY_ACTIVATED</option>
                    <option value="POLICY_DISABLED">POLICY_DISABLED</option>
                    <option value="POLICY_SIMULATION_EXECUTED">POLICY_SIMULATION_EXECUTED</option>
                    <option value="TOOL_PERMISSIONS_UPDATED">TOOL_PERMISSIONS_UPDATED</option>
                </select>
            </div>

            <button
                type="button"
                @click="loadLogs"
                class="px-3.5 py-1.5 bg-white border border-slate-300 hover:bg-slate-100 text-slate-700 rounded-lg text-xs font-semibold"
            >
                Refresh Logs
            </button>
        </div>

        <!-- Audit Log Table -->
        <div class="overflow-x-auto border border-slate-200 rounded-xl">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-100/75 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider text-[10px]">
                    <tr>
                        <th class="px-4 py-3">Timestamp</th>
                        <th class="px-4 py-3">Actor</th>
                        <th class="px-4 py-3">Action</th>
                        <th class="px-4 py-3">Resource</th>
                        <th class="px-4 py-3">Authorization</th>
                        <th class="px-4 py-3">Details / Snapshot</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white font-mono text-[11px]">
                    <tr v-if="isLoading" class="text-center py-8">
                        <td colspan="6" class="py-8 text-slate-400 font-sans">Loading audit records...</td>
                    </tr>
                    <tr v-else-if="auditLogs.length === 0" class="text-center py-8">
                        <td colspan="6" class="py-8 text-slate-400 font-sans">No audit records found.</td>
                    </tr>
                    <tr
                        v-for="log in auditLogs"
                        :key="log.id"
                        class="hover:bg-slate-50/75 transition-colors"
                    >
                        <td class="px-4 py-3 whitespace-nowrap text-slate-500 text-[10px]">
                            {{ formatDate(log.created_at) }}
                        </td>
                        <td class="px-4 py-3 font-sans font-semibold text-slate-800">
                            {{ log.actor_type }} #{{ log.actor_id || 'N/A' }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 bg-slate-100 text-slate-800 rounded font-bold text-[10px]">
                                {{ log.action }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-600">
                            {{ log.resource_type }} #{{ log.resource_id }}
                        </td>
                        <td class="px-4 py-3">
                            <span
                                :class="[
                                    'px-2 py-0.5 rounded text-[10px] font-bold',
                                    log.authorization_result === 'ALLOWED' || log.authorization_result === 'allow'
                                        ? 'bg-emerald-100 text-emerald-800'
                                        : 'bg-amber-100 text-amber-800'
                                ]"
                            >
                                {{ log.authorization_result || 'ALLOWED' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 max-w-xs truncate text-[10px] text-slate-500">
                            {{ formatSnapshot(log) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script>
export default {
    name: 'GovernanceAuditLog',
    data() {
        return {
            filters: {
                search: '',
                action: '',
            },
        };
    },
    computed: {
        auditLogs() {
            return this.$store.getters['adminGovernance/auditLogs'];
        },
        isLoading() {
            return this.$store.getters['adminGovernance/isLoading'];
        },
    },
    mounted() {
        this.loadLogs();
    },
    methods: {
        loadLogs() {
            this.$store.dispatch('adminGovernance/fetchAuditLogs', this.filters);
        },
        formatSnapshot(log) {
            const data = log.after_data || log.metadata || log.before_data;
            if (!data) return '—';
            return JSON.stringify(data);
        },
        formatDate(dateStr) {
            if (!dateStr) return '—';
            return new Date(dateStr).toLocaleString('en-GB', {
                day: '2-digit',
                month: 'short',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
            });
        },
    },
};
</script>
