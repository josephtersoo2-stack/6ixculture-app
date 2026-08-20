<template>
    <div class="support-queue flex flex-col h-full bg-[#0E1424] border-r border-[#1F293D] overflow-hidden">
        <!-- Header -->
        <div class="px-4 py-3 border-b border-[#1F293D] flex items-center justify-between">
            <div class="flex items-center gap-2">
                <h2 class="text-sm font-bold text-white tracking-wide">Conversations</h2>
                <span v-if="pagination.total" class="px-2 py-0.5 text-[11px] font-bold bg-[#1E293B] text-indigo-400 rounded-full">
                    {{ pagination.total }}
                </span>
            </div>
            <button
                type="button"
                @click="refreshQueue"
                class="p-1.5 text-slate-400 hover:text-white hover:bg-[#1E293B] rounded-lg transition-colors"
                title="Refresh Queue"
            >
                <i class="lab lab-refresh text-xs" :class="{ 'animate-spin': isLoading }"></i>
            </button>
        </div>

        <!-- Filter Tabs: All, Unassigned, Mine -->
        <div class="px-3 pt-2.5 pb-1 flex items-center gap-1.5 border-b border-[#1F293D]/60 bg-[#0E1424]">
            <button
                type="button"
                @click="setTab('all')"
                class="flex-1 py-1 px-2 text-[11px] font-bold rounded-lg transition-all flex items-center justify-center gap-1"
                :class="activeTab === 'all' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-400 hover:text-slate-200 hover:bg-[#161F36]'"
            >
                <span>All</span>
                <span v-if="pagination.total" class="opacity-80 font-normal">({{ pagination.total }})</span>
            </button>
            <button
                type="button"
                @click="setTab('unassigned')"
                class="flex-1 py-1 px-2 text-[11px] font-bold rounded-lg transition-all flex items-center justify-center gap-1"
                :class="activeTab === 'unassigned' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-400 hover:text-slate-200 hover:bg-[#161F36]'"
            >
                <span>Unassigned</span>
                <span v-if="unassignedCount" class="opacity-80 font-normal">({{ unassignedCount }})</span>
            </button>
            <button
                type="button"
                @click="setTab('mine')"
                class="flex-1 py-1 px-2 text-[11px] font-bold rounded-lg transition-all flex items-center justify-center gap-1"
                :class="activeTab === 'mine' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-400 hover:text-slate-200 hover:bg-[#161F36]'"
            >
                <span>Mine</span>
                <span v-if="mineCount" class="opacity-80 font-normal">({{ mineCount }})</span>
            </button>
        </div>

        <!-- Search Bar with Filter Toggle -->
        <div class="p-3 border-b border-[#1F293D]/60 bg-[#0E1424]">
            <div class="relative flex items-center gap-1.5">
                <div class="relative flex-1">
                    <i class="lab lab-search absolute left-2.5 top-2 text-slate-500 text-xs"></i>
                    <input
                        type="text"
                        v-model="searchQuery"
                        @input="debounceSearch"
                        placeholder="Search conversations..."
                        class="w-full pl-8 pr-3 py-1.5 text-xs bg-[#131B2E] border border-[#1F293D] rounded-xl text-slate-200 placeholder:text-slate-500 focus:outline-none focus:border-indigo-500 transition-colors"
                    />
                </div>
                <button
                    type="button"
                    @click="showFilters = !showFilters"
                    class="p-1.5 border rounded-xl transition-colors"
                    :class="showFilters || hasAdvancedFilters ? 'bg-indigo-600/20 border-indigo-500/50 text-indigo-400' : 'bg-[#131B2E] border-[#1F293D] text-slate-400 hover:text-white'"
                    title="Toggle Filter Options"
                >
                    <i class="lab lab-filter text-xs"></i>
                </button>
            </div>

            <!-- Advanced Filter Drawer -->
            <div v-if="showFilters" class="mt-2.5 p-2.5 bg-[#131B2E] border border-[#1F293D] rounded-xl space-y-2 text-xs">
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-[10px] uppercase font-bold text-slate-400 mb-1">Department</label>
                        <select
                            v-model="selectedDepartment"
                            @change="applyAdvancedFilters"
                            class="w-full text-xs bg-[#0E1424] border border-[#1F293D] rounded-lg px-2 py-1 text-slate-300 focus:outline-none focus:border-indigo-500"
                        >
                            <option value="">All</option>
                            <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase font-bold text-slate-400 mb-1">Status</label>
                        <select
                            v-model="selectedStatus"
                            @change="applyAdvancedFilters"
                            class="w-full text-xs bg-[#0E1424] border border-[#1F293D] rounded-lg px-2 py-1 text-slate-300 focus:outline-none focus:border-indigo-500"
                        >
                            <option value="all">All Statuses</option>
                            <option value="queued">Queued</option>
                            <option value="human_active">In Progress</option>
                            <option value="awaiting_customer">Awaiting Customer</option>
                            <option value="resolved">Resolved</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Conversations List -->
        <div class="flex-1 overflow-y-auto divide-y divide-[#1F293D]/40 thin-scrolling">
            <!-- Loading Skeleton -->
            <div v-if="isLoading && conversations.length === 0" class="p-3 space-y-2.5">
                <div v-for="i in 5" :key="i" class="animate-pulse p-3 bg-[#131B2E] rounded-xl space-y-2">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-full bg-[#1E293B]"></div>
                            <div class="w-24 h-3 bg-[#1E293B] rounded"></div>
                        </div>
                        <div class="w-8 h-2.5 bg-[#1E293B] rounded"></div>
                    </div>
                    <div class="w-full h-2.5 bg-[#1E293B] rounded"></div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else-if="conversations.length === 0" class="p-8 text-center">
                <div class="w-12 h-12 rounded-2xl bg-[#131B2E] text-slate-500 flex items-center justify-center mx-auto mb-2 text-xl">
                    <i class="lab lab-messages"></i>
                </div>
                <h4 class="text-xs font-bold text-slate-300">No active conversations</h4>
                <p class="text-[11px] text-slate-500 mt-0.5">New customer queries will appear here automatically.</p>
            </div>

            <!-- Conversation Cards -->
            <div
                v-for="conv in conversations"
                :key="conv.id"
                @click="selectConversation(conv.id)"
                class="p-3 cursor-pointer transition-all hover:bg-[#161F36] relative group border-l-4"
                :class="activeConversation?.id === conv.id ? 'bg-[#1A2238] border-indigo-500 text-white' : 'border-transparent text-slate-300'"
            >
                <div class="flex items-start justify-between gap-2 mb-1">
                    <div class="flex items-center gap-2 min-w-0">
                        <!-- Avatar with Presence Dot -->
                        <div class="relative flex-shrink-0">
                            <div class="w-8 h-8 rounded-full bg-[#1E293B] text-slate-200 font-bold text-xs flex items-center justify-center shadow-xs">
                                {{ getInitials(conv.customer?.name) }}
                            </div>
                            <span
                                class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full border-2 border-[#0E1424]"
                                :class="conv.customer?.is_guest ? 'bg-amber-400' : 'bg-emerald-500'"
                                :title="conv.customer?.is_guest ? 'Guest Customer' : 'Verified Member'"
                            ></span>
                        </div>

                        <!-- Customer Name -->
                        <div class="truncate min-w-0">
                            <div class="flex items-center gap-1.5">
                                <span class="text-xs font-bold text-slate-100 truncate">
                                    {{ conv.customer?.name || 'Customer' }}
                                </span>
                                <span v-if="conv.customer?.is_guest" class="px-1 py-0.2 text-[8px] font-extrabold bg-[#1E293B] text-amber-400 rounded uppercase">
                                    Guest
                                </span>
                            </div>
                            <div class="text-[11px] text-slate-400 truncate">
                                {{ conv.subject || 'Support Inquiry' }}
                            </div>
                        </div>
                    </div>

                    <!-- Timestamp -->
                    <span class="text-[10px] text-slate-500 flex-shrink-0 whitespace-nowrap">
                        {{ formatTimeAgo(conv.updated_at) }}
                    </span>
                </div>

                <!-- Last Message Snippet -->
                <div v-if="conv.last_message" class="text-[11px] text-slate-400 truncate pl-10 mb-1.5 flex items-center gap-1">
                    <span v-if="conv.last_message.is_internal" class="text-amber-400 font-bold">[Note]:</span>
                    <span v-else-if="conv.last_message.sender_type === 'agent'" class="font-bold text-indigo-400">Agent:</span>
                    <span v-else-if="conv.last_message.sender_type === 'ai'" class="font-bold text-emerald-400">AI:</span>
                    <span v-else class="font-semibold text-slate-300">Customer:</span>
                    <span class="truncate">{{ conv.last_message.content }}</span>
                </div>

                <!-- Footer Badges -->
                <div class="flex items-center justify-between text-[10px] pl-10 pt-1 border-t border-[#1F293D]/30">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="px-1.5 py-0.5 rounded font-bold capitalize text-[9px]" :class="statusBadgeClass(conv.status)">
                            {{ formatStatus(conv.status) }}
                        </span>
                        <span v-if="conv.priority === 'urgent' || conv.priority === 'high'" class="px-1.5 py-0.5 rounded font-bold capitalize text-[9px] bg-rose-950/60 text-rose-300 border border-rose-800/40">
                            {{ conv.priority }}
                        </span>
                    </div>

                    <div class="flex items-center gap-1 text-slate-400 text-[10px]">
                        <i class="lab lab-user text-[10px]"></i>
                        <span class="truncate max-w-[70px]">
                            {{ conv.assigned_agent ? conv.assigned_agent.name : 'Unassigned' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination Footer -->
        <div v-if="pagination.last_page > 1" class="px-3 py-2 border-t border-[#1F293D] flex items-center justify-between text-xs bg-[#0E1424]">
            <button
                type="button"
                :disabled="pagination.current_page <= 1"
                @click="changePage(pagination.current_page - 1)"
                class="px-2 py-1 bg-[#131B2E] border border-[#1F293D] text-slate-300 rounded hover:bg-[#1E293B] disabled:opacity-40"
            >
                Prev
            </button>
            <span class="text-[10px] text-slate-400">
                Page {{ pagination.current_page }} of {{ pagination.last_page }}
            </span>
            <button
                type="button"
                :disabled="pagination.current_page >= pagination.last_page"
                @click="changePage(pagination.current_page + 1)"
                class="px-2 py-1 bg-[#131B2E] border border-[#1F293D] text-slate-300 rounded hover:bg-[#1E293B] disabled:opacity-40"
            >
                Next
            </button>
        </div>
    </div>
</template>

<script>
export default {
    name: 'SupportQueue',
    props: {
        conversations: {
            type: Array,
            default: () => [],
        },
        pagination: {
            type: Object,
            default: () => ({ current_page: 1, last_page: 1, total: 0 }),
        },
        activeConversation: {
            type: Object,
            default: null,
        },
        departments: {
            type: Array,
            default: () => [],
        },
        isLoading: {
            type: Boolean,
            default: false,
        },
    },
    emits: ['select-conversation'],
    data() {
        return {
            activeTab: 'all', // 'all', 'unassigned', 'mine'
            searchQuery: '',
            searchTimeout: null,
            showFilters: false,
            selectedDepartment: '',
            selectedStatus: 'all',
        };
    },
    computed: {
        unassignedCount() {
            return this.conversations.filter(c => !c.assigned_agent).length;
        },
        mineCount() {
            const currentUserId = this.$store.getters.authId;
            return this.conversations.filter(c => c.assigned_agent?.id === currentUserId).length;
        },
        hasAdvancedFilters() {
            return this.selectedDepartment !== '' || this.selectedStatus !== 'all';
        },
    },
    methods: {
        selectConversation(id) {
            this.$emit('select-conversation', id);
        },
        refreshQueue() {
            this.$store.dispatch('adminSupport/fetchQueue', this.pagination.current_page);
        },
        changePage(page) {
            this.$store.dispatch('adminSupport/fetchQueue', page);
        },
        setTab(tab) {
            this.activeTab = tab;
            let assignedTo = '';
            let unassigned = false;

            if (tab === 'mine') {
                assignedTo = 'me';
            } else if (tab === 'unassigned') {
                unassigned = true;
            }

            this.$store.commit('adminSupport/SET_FILTERS', {
                assigned_to: assignedTo,
                unassigned,
            });
            this.$store.dispatch('adminSupport/fetchQueue', 1);
        },
        debounceSearch() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.$store.commit('adminSupport/SET_FILTERS', { search: this.searchQuery });
                this.$store.dispatch('adminSupport/fetchQueue', 1);
            }, 300);
        },
        applyAdvancedFilters() {
            this.$store.commit('adminSupport/SET_FILTERS', {
                department_id: this.selectedDepartment,
                status: this.selectedStatus,
            });
            this.$store.dispatch('adminSupport/fetchQueue', 1);
        },
        getInitials(name) {
            if (!name) return 'C';
            const parts = name.trim().split(' ');
            if (parts.length >= 2) {
                return (parts[0][0] + parts[1][0]).toUpperCase();
            }
            return name.substring(0, 2).toUpperCase();
        },
        formatTimeAgo(iso) {
            if (!iso) return '';
            try {
                const now = new Date();
                const past = new Date(iso);
                const diffMs = now - past;
                const diffMins = Math.floor(diffMs / 60000);
                if (diffMins < 1) return 'Just now';
                if (diffMins < 60) return `${diffMins}m`;
                const diffHours = Math.floor(diffMins / 60);
                if (diffHours < 24) return `${diffHours}h`;
                const diffDays = Math.floor(diffHours / 24);
                return `${diffDays}d`;
            } catch (e) {
                return '';
            }
        },
        formatStatus(status) {
            return String(status).replace(/_/g, ' ');
        },
        statusBadgeClass(status) {
            switch (status) {
                case 'queued':
                    return 'bg-amber-950/60 text-amber-300 border border-amber-800/40';
                case 'human_active':
                    return 'bg-emerald-950/60 text-emerald-300 border border-emerald-800/40';
                case 'awaiting_customer':
                    return 'bg-indigo-950/60 text-indigo-300 border border-indigo-800/40';
                case 'resolved':
                case 'closed':
                    return 'bg-[#1E293B] text-slate-400 border border-[#1F293D]';
                default:
                    return 'bg-[#1E293B] text-slate-300 border border-[#1F293D]';
            }
        },
    },
};
</script>
