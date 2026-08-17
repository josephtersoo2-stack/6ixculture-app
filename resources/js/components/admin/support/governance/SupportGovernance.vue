<template>
    <div class="db-card p-6 bg-white rounded-2xl shadow-xs border border-slate-100 font-sans">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between pb-6 border-b border-slate-100 gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
                    <h2 class="text-xl font-black text-slate-900 tracking-tight">AI Support Governance Center</h2>
                </div>
                <p class="text-xs text-slate-500 mt-1">
                    Manage knowledge grounding, AI action policies, tool permissions, and simulate policy safety.
                </p>
            </div>

            <!-- Quick Stats & Status -->
            <div class="flex items-center gap-3">
                <div class="px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700">
                    Authority: <span class="text-emerald-700 font-bold">Admin Governed</span>
                </div>
                <button
                    type="button"
                    @click="activeTab = 'simulator'"
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition-all shadow-xs flex items-center gap-1.5"
                >
                    <i class="lab lab-play text-sm"></i>
                    <span>Policy Simulator</span>
                </button>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="flex items-center gap-2 mt-6 border-b border-slate-200 overflow-x-auto pb-px">
            <button
                type="button"
                @click="activeTab = 'knowledge'"
                :class="[
                    'px-4 py-2.5 text-xs font-bold transition-colors border-b-2 flex items-center gap-2 whitespace-nowrap',
                    activeTab === 'knowledge'
                        ? 'border-slate-950 text-slate-950'
                        : 'border-transparent text-slate-400 hover:text-slate-700'
                ]"
            >
                <i class="lab lab-book text-sm"></i>
                <span>Knowledge Grounding</span>
            </button>

            <button
                type="button"
                @click="activeTab = 'policies'"
                :class="[
                    'px-4 py-2.5 text-xs font-bold transition-colors border-b-2 flex items-center gap-2 whitespace-nowrap',
                    activeTab === 'policies'
                        ? 'border-slate-950 text-slate-950'
                        : 'border-transparent text-slate-400 hover:text-slate-700'
                ]"
            >
                <i class="lab lab-shield text-sm"></i>
                <span>Action Policies</span>
            </button>

            <button
                type="button"
                @click="activeTab = 'tools'"
                :class="[
                    'px-4 py-2.5 text-xs font-bold transition-colors border-b-2 flex items-center gap-2 whitespace-nowrap',
                    activeTab === 'tools'
                        ? 'border-slate-950 text-slate-950'
                        : 'border-transparent text-slate-400 hover:text-slate-700'
                ]"
            >
                <i class="lab lab-tool text-sm"></i>
                <span>Tool Permissions</span>
            </button>

            <button
                type="button"
                @click="activeTab = 'simulator'"
                :class="[
                    'px-4 py-2.5 text-xs font-bold transition-colors border-b-2 flex items-center gap-2 whitespace-nowrap',
                    activeTab === 'simulator'
                        ? 'border-slate-950 text-slate-950'
                        : 'border-transparent text-slate-400 hover:text-slate-700'
                ]"
            >
                <i class="lab lab-play text-sm"></i>
                <span>Policy Simulator</span>
            </button>

            <button
                type="button"
                @click="activeTab = 'audit'"
                :class="[
                    'px-4 py-2.5 text-xs font-bold transition-colors border-b-2 flex items-center gap-2 whitespace-nowrap',
                    activeTab === 'audit'
                        ? 'border-slate-950 text-slate-950'
                        : 'border-transparent text-slate-400 hover:text-slate-700'
                ]"
            >
                <i class="lab lab-receipt text-sm"></i>
                <span>Governance Audit Logs</span>
            </button>
        </div>

        <!-- Active Tab Content -->
        <div class="mt-6">
            <KnowledgeManager v-if="activeTab === 'knowledge'" />
            <PolicyManager v-else-if="activeTab === 'policies'" />
            <ToolPermissionManager v-else-if="activeTab === 'tools'" />
            <PolicySimulator v-else-if="activeTab === 'simulator'" />
            <GovernanceAuditLog v-else-if="activeTab === 'audit'" />
        </div>
    </div>
</template>

<script>
import KnowledgeManager from './KnowledgeManager.vue';
import PolicyManager from './PolicyManager.vue';
import ToolPermissionManager from './ToolPermissionManager.vue';
import PolicySimulator from './PolicySimulator.vue';
import GovernanceAuditLog from './GovernanceAuditLog.vue';

export default {
    name: 'SupportGovernance',
    components: {
        KnowledgeManager,
        PolicyManager,
        ToolPermissionManager,
        PolicySimulator,
        GovernanceAuditLog,
    },
    data() {
        return {
            activeTab: 'knowledge',
        };
    },
};
</script>
