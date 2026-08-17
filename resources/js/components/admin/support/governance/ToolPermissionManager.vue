<template>
    <div class="tool-permission-manager space-y-4">
        <!-- Banner -->
        <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between">
            <p class="text-xs text-slate-600 font-medium">
                Govern safety parameters for registered backend AI tools. Tool registration is defined strictly in the backend catalog.
            </p>
        </div>

        <!-- Tools Table -->
        <div class="overflow-x-auto border border-slate-200 rounded-xl">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-100/75 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider text-[10px]">
                    <tr>
                        <th class="px-4 py-3">Tool Key / Name</th>
                        <th class="px-4 py-3">Risk Level</th>
                        <th class="px-4 py-3">Requires Auth</th>
                        <th class="px-4 py-3">UI Confirm</th>
                        <th class="px-4 py-3">Human Escalation</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <tr v-if="isLoading" class="text-center py-8">
                        <td colspan="7" class="py-8 text-slate-400">Loading tool catalog...</td>
                    </tr>
                    <tr
                        v-for="tool in tools"
                        :key="tool.key"
                        class="hover:bg-slate-50/75 transition-colors"
                    >
                        <td class="px-4 py-3">
                            <div class="font-bold text-slate-900">{{ tool.name }}</div>
                            <div class="text-[10px] font-mono text-slate-400">{{ tool.key }}</div>
                            <p class="text-[11px] text-slate-500 mt-0.5">{{ tool.description }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <span
                                :class="[
                                    'px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider',
                                    tool.risk_level === 'critical' ? 'bg-rose-100 text-rose-800' : '',
                                    tool.risk_level === 'sensitive' ? 'bg-amber-100 text-amber-800' : '',
                                    tool.risk_level === 'normal' ? 'bg-blue-100 text-blue-800' : '',
                                    tool.risk_level === 'low' ? 'bg-emerald-100 text-emerald-800' : '',
                                ]"
                            >
                                {{ tool.risk_level }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span :class="tool.requires_authentication ? 'text-emerald-700 font-bold' : 'text-slate-400'">
                                {{ tool.requires_authentication ? 'Yes' : 'No' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span :class="tool.requires_confirmation ? 'text-amber-700 font-bold' : 'text-slate-400'">
                                {{ tool.requires_confirmation ? 'Yes' : 'No' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span :class="tool.requires_human ? 'text-purple-700 font-bold' : 'text-slate-400'">
                                {{ tool.requires_human ? 'Yes' : 'No' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span
                                :class="[
                                    'px-2 py-0.5 rounded-full text-[10px] font-bold',
                                    tool.is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-500'
                                ]"
                            >
                                {{ tool.is_active ? 'Active' : 'Disabled' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button
                                type="button"
                                @click="openEditor(tool)"
                                class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-md text-[11px] font-medium"
                            >
                                Configure
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Tool Permission Edit Modal -->
        <div v-if="editingTool" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 backdrop-blur-xs p-4">
            <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-md overflow-hidden flex flex-col">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                    <h3 class="text-sm font-bold text-slate-900">Configure: {{ editingTool.name }}</h3>
                    <button @click="editingTool = null" class="text-slate-400 hover:text-slate-600">&times;</button>
                </div>

                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Risk Level</label>
                        <select
                            v-model="toolForm.risk_level"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:outline-none"
                        >
                            <option value="low">Low</option>
                            <option value="normal">Normal</option>
                            <option value="sensitive">Sensitive</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>

                    <div class="space-y-2">
                        <label class="flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                            <input type="checkbox" v-model="toolForm.is_active" class="w-4 h-4 accent-slate-900 rounded" />
                            <span>Tool is Active</span>
                        </label>
                        <label class="flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                            <input type="checkbox" v-model="toolForm.requires_authentication" class="w-4 h-4 accent-slate-900 rounded" />
                            <span>Requires Authenticated Customer</span>
                        </label>
                        <label class="flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                            <input type="checkbox" v-model="toolForm.requires_confirmation" class="w-4 h-4 accent-slate-900 rounded" />
                            <span>Requires UI Action Confirmation Card</span>
                        </label>
                        <label class="flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                            <input type="checkbox" v-model="toolForm.requires_human" class="w-4 h-4 accent-slate-900 rounded" />
                            <span>Requires Human Agent Escalation</span>
                        </label>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-end gap-2 bg-slate-50">
                    <button
                        type="button"
                        @click="editingTool = null"
                        class="px-4 py-2 bg-white border border-slate-300 hover:bg-slate-100 text-slate-700 rounded-xl text-xs font-bold"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="savePermissions"
                        :disabled="isSaving"
                        class="px-5 py-2 bg-slate-950 hover:bg-slate-800 text-white rounded-xl text-xs font-bold"
                    >
                        Save Governance Settings
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'ToolPermissionManager',
    data() {
        return {
            editingTool: null,
            toolForm: {
                risk_level: 'normal',
                is_active: true,
                requires_authentication: false,
                requires_confirmation: false,
                requires_human: false,
            },
        };
    },
    computed: {
        tools() {
            return this.$store.getters['adminGovernance/tools'];
        },
        isLoading() {
            return this.$store.getters['adminGovernance/isLoading'];
        },
        isSaving() {
            return this.$store.getters['adminGovernance/isSaving'];
        },
    },
    mounted() {
        this.loadTools();
    },
    methods: {
        loadTools() {
            this.$store.dispatch('adminGovernance/fetchTools');
        },
        openEditor(tool) {
            this.editingTool = tool;
            this.toolForm = {
                risk_level: tool.risk_level,
                is_active: Boolean(tool.is_active),
                requires_authentication: Boolean(tool.requires_authentication),
                requires_confirmation: Boolean(tool.requires_confirmation),
                requires_human: Boolean(tool.requires_human),
            };
        },
        savePermissions() {
            if (!this.editingTool) return;
            this.$store.dispatch('adminGovernance/updateToolPermissions', {
                id: this.editingTool.id || this.editingTool.key,
                permissions: this.toolForm,
            }).then(() => {
                this.editingTool = null;
            }).catch((err) => {
                alert(err?.response?.data?.error?.message || 'Failed to update tool permissions.');
            });
        },
    },
};
</script>
