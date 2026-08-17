<template>
    <div class="policy-manager space-y-4">
        <!-- Filter & Action Bar -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 bg-slate-50 p-4 rounded-xl border border-slate-200">
            <div class="flex flex-wrap items-center gap-2 flex-1">
                <input
                    type="text"
                    v-model="search"
                    placeholder="Search policies..."
                    class="px-3 py-1.5 bg-white border border-slate-300 rounded-lg text-xs focus:outline-none focus:border-slate-900 min-w-[200px]"
                />

                <select
                    v-model="selectedCategory"
                    class="px-2.5 py-1.5 bg-white border border-slate-300 rounded-lg text-xs focus:outline-none focus:border-slate-900"
                >
                    <option value="">All Categories</option>
                    <option value="orders">Orders</option>
                    <option value="returns">Returns</option>
                    <option value="payments">Payments</option>
                    <option value="security">Security</option>
                    <option value="general">General</option>
                </select>
            </div>

            <button
                type="button"
                @click="openEditor(null)"
                class="px-4 py-2 bg-slate-950 hover:bg-slate-800 text-white rounded-xl text-xs font-bold transition-all shadow-xs flex items-center justify-center gap-1.5"
            >
                <i class="lab lab-add text-sm"></i>
                <span>Create Action Policy</span>
            </button>
        </div>

        <!-- Policies Table -->
        <div class="overflow-x-auto border border-slate-200 rounded-xl">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-100/75 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider text-[10px]">
                    <tr>
                        <th class="px-4 py-3">Policy Key / Name</th>
                        <th class="px-4 py-3">Category</th>
                        <th class="px-4 py-3">Effect</th>
                        <th class="px-4 py-3">Priority</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <tr v-if="isLoading" class="text-center py-8">
                        <td colspan="6" class="py-8 text-slate-400">Loading policies...</td>
                    </tr>
                    <tr v-else-if="filteredPolicies.length === 0" class="text-center py-8">
                        <td colspan="6" class="py-8 text-slate-400">No support policies found.</td>
                    </tr>
                    <tr
                        v-for="policy in filteredPolicies"
                        :key="policy.id"
                        class="hover:bg-slate-50/75 transition-colors"
                    >
                        <td class="px-4 py-3">
                            <div class="font-bold text-slate-900">{{ policy.name }}</div>
                            <div class="text-[10px] font-mono text-slate-400">{{ policy.key }}</div>
                            <p v-if="policy.description" class="text-[11px] text-slate-500 mt-0.5">{{ policy.description }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 bg-slate-100 text-slate-700 rounded-md font-semibold text-[11px]">
                                {{ policy.category }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span
                                :class="[
                                    'px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider',
                                    policy.effect === 'allow' ? 'bg-emerald-100 text-emerald-800' : '',
                                    policy.effect === 'deny' ? 'bg-rose-100 text-rose-800' : '',
                                    policy.effect === 'confirm' ? 'bg-amber-100 text-amber-800' : '',
                                    policy.effect === 'require_human' ? 'bg-purple-100 text-purple-800' : '',
                                    policy.effect === 'require_verification' ? 'bg-blue-100 text-blue-800' : '',
                                ]"
                            >
                                {{ policy.effect }}
                            </span>
                        </td>
                        <td class="px-4 py-3 font-mono text-slate-700 font-semibold">
                            {{ policy.priority }}
                        </td>
                        <td class="px-4 py-3">
                            <span
                                :class="[
                                    'px-2 py-0.5 rounded-full text-[10px] font-bold',
                                    policy.is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-500'
                                ]"
                            >
                                {{ policy.is_active ? 'Active' : 'Disabled' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right space-x-1">
                            <button
                                type="button"
                                @click="openEditor(policy)"
                                class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-md text-[11px] font-medium"
                            >
                                Edit
                            </button>
                            <button
                                type="button"
                                @click="toggleActive(policy)"
                                :class="[
                                    'px-2.5 py-1 rounded-md text-[11px] font-bold transition-colors',
                                    policy.is_active
                                        ? 'bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200'
                                        : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200'
                                ]"
                            >
                                {{ policy.is_active ? 'Disable' : 'Activate' }}
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <PolicyEditor
            v-if="isEditorOpen"
            :policy="selectedPolicy"
            @close="isEditorOpen = false"
            @saved="loadPolicies"
        />
    </div>
</template>

<script>
import PolicyEditor from './PolicyEditor.vue';

export default {
    name: 'PolicyManager',
    components: { PolicyEditor },
    data() {
        return {
            search: '',
            selectedCategory: '',
            isEditorOpen: false,
            selectedPolicy: null,
        };
    },
    computed: {
        policies() {
            return this.$store.getters['adminGovernance/policies'];
        },
        isLoading() {
            return this.$store.getters['adminGovernance/isLoading'];
        },
        filteredPolicies() {
            return this.policies.filter((p) => {
                if (this.selectedCategory && p.category !== this.selectedCategory) return false;
                if (this.search) {
                    const q = this.search.toLowerCase();
                    return p.name.toLowerCase().includes(q) || p.key.toLowerCase().includes(q);
                }
                return true;
            });
        },
    },
    mounted() {
        this.loadPolicies();
    },
    methods: {
        loadPolicies() {
            this.$store.dispatch('adminGovernance/fetchPolicies');
        },
        openEditor(policy) {
            this.selectedPolicy = policy;
            this.isEditorOpen = true;
        },
        toggleActive(policy) {
            this.$store.dispatch('adminGovernance/togglePolicy', {
                id: policy.id,
                is_active: !policy.is_active,
            });
        },
    },
};
</script>
