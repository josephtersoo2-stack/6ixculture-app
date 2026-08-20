<template>
    <div class="support-queue-filters p-3.5 border-b border-[#1F293D] bg-[#0E1424]">
        <!-- Search input -->
        <div class="relative mb-3">
            <i class="lab lab-search absolute left-3 top-2.5 text-slate-500 text-sm"></i>
            <input
                type="text"
                v-model="localFilters.search"
                @input="debounceSearch"
                placeholder="Search by customer, subject, ID..."
                class="w-full pl-9 pr-3 py-1.5 text-xs bg-[#131B2E] border border-[#1F293D] rounded-xl text-slate-200 placeholder:text-slate-500 focus:outline-none focus:border-indigo-500 transition-all"
            />
        </div>

        <!-- Status Pills Tabs -->
        <div class="flex items-center gap-1 overflow-x-auto pb-1.5 mb-2.5 text-[11px] font-medium no-scrollbar">
            <button
                v-for="tab in statusTabs"
                :key="tab.value"
                type="button"
                @click="setStatus(tab.value)"
                class="px-2.5 py-1 rounded-lg whitespace-nowrap transition-colors"
                :class="localFilters.status === tab.value ? 'bg-indigo-600 text-white font-semibold shadow-xs' : 'text-slate-400 hover:text-white hover:bg-[#161F36]'"
            >
                {{ tab.label }}
            </button>
        </div>

        <!-- Secondary Dropdowns Grid -->
        <div class="grid grid-cols-2 gap-2">
            <!-- Department Dropdown -->
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Department</label>
                <select
                    v-model="localFilters.department_id"
                    @change="applyFilters"
                    class="w-full text-xs bg-[#131B2E] border border-[#1F293D] rounded-lg px-2 py-1 text-slate-300 focus:outline-none focus:border-indigo-500"
                >
                    <option value="">All Departments</option>
                    <option v-for="dept in departments" :key="dept.id" :value="dept.id">
                        {{ dept.name }}
                    </option>
                </select>
            </div>

            <!-- Priority Dropdown -->
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Priority</label>
                <select
                    v-model="localFilters.priority"
                    @change="applyFilters"
                    class="w-full text-xs bg-[#131B2E] border border-[#1F293D] rounded-lg px-2 py-1 text-slate-300 focus:outline-none focus:border-indigo-500"
                >
                    <option value="">All Priorities</option>
                    <option value="urgent">Urgent</option>
                    <option value="high">High</option>
                    <option value="normal">Normal</option>
                    <option value="low">Low</option>
                </select>
            </div>

            <!-- Assignment Filter -->
            <div class="col-span-2 flex items-center justify-between pt-1">
                <div class="flex items-center gap-1.5">
                    <button
                        type="button"
                        @click="setAssignedFilter('all')"
                        class="px-2 py-0.5 text-[10px] font-medium rounded-lg"
                        :class="!localFilters.assigned_to && !localFilters.unassigned ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:text-white'"
                    >
                        All
                    </button>
                    <button
                        type="button"
                        @click="setAssignedFilter('me')"
                        class="px-2 py-0.5 text-[10px] font-medium rounded-lg"
                        :class="localFilters.assigned_to === 'me' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:text-white'"
                    >
                        Assigned to Me
                    </button>
                    <button
                        type="button"
                        @click="setAssignedFilter('unassigned')"
                        class="px-2 py-0.5 text-[10px] font-medium rounded-lg"
                        :class="localFilters.unassigned ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:text-white'"
                    >
                        Unassigned
                    </button>
                </div>

                <button
                    v-if="hasActiveFilters"
                    type="button"
                    @click="resetFilters"
                    class="text-[10px] text-rose-400 hover:text-rose-300 font-semibold"
                >
                    Reset
                </button>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'SupportQueueFilters',
    props: {
        departments: {
            type: Array,
            default: () => [],
        },
    },
    data() {
        return {
            localFilters: {
                status: 'all',
                department_id: '',
                priority: '',
                assigned_to: '',
                unassigned: false,
                search: '',
            },
            searchTimeout: null,
            statusTabs: [
                { label: 'All Active', value: 'all' },
                { label: 'Queued', value: 'queued' },
                { label: 'In Progress', value: 'human_active' },
                { label: 'Waiting on Customer', value: 'awaiting_customer' },
                { label: 'Resolved', value: 'resolved' },
            ],
        };
    },
    computed: {
        hasActiveFilters() {
            return this.localFilters.status !== 'all' ||
                this.localFilters.department_id !== '' ||
                this.localFilters.priority !== '' ||
                this.localFilters.assigned_to !== '' ||
                this.localFilters.unassigned ||
                this.localFilters.search !== '';
        },
    },
    methods: {
        setStatus(status) {
            this.localFilters.status = status;
            this.applyFilters();
        },
        setAssignedFilter(type) {
            if (type === 'me') {
                this.localFilters.assigned_to = 'me';
                this.localFilters.unassigned = false;
            } else if (type === 'unassigned') {
                this.localFilters.assigned_to = '';
                this.localFilters.unassigned = true;
            } else {
                this.localFilters.assigned_to = '';
                this.localFilters.unassigned = false;
            }
            this.applyFilters();
        },
        debounceSearch() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.applyFilters();
            }, 300);
        },
        applyFilters() {
            this.$store.commit('adminSupport/SET_FILTERS', this.localFilters);
            this.$store.dispatch('adminSupport/fetchQueue', 1);
        },
        resetFilters() {
            this.localFilters = {
                status: 'all',
                department_id: '',
                priority: '',
                assigned_to: '',
                unassigned: false,
                search: '',
            };
            this.applyFilters();
        },
    },
};
</script>
