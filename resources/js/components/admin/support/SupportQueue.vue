<template>
    <div class="support-queue flex flex-col h-full bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-gray-800">
        <!-- Header -->
        <div class="px-4 py-3.5 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white tracking-wide">Support Queue</h2>
                <span v-if="pagination.total" class="px-2 py-0.5 text-[11px] font-extrabold bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-full">
                    {{ pagination.total }}
                </span>
            </div>
            <button
                type="button"
                @click="refreshQueue"
                class="p-1.5 text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors"
                title="Refresh Queue"
            >
                <i class="lab lab-refresh text-sm" :class="{ 'animate-spin': isLoading }"></i>
            </button>
        </div>

        <!-- Filters -->
        <SupportQueueFilters :departments="departments" />

        <!-- Conversations List -->
        <div class="flex-1 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-800/60 thin-scrolling">
            <!-- Loading skeleton -->
            <div v-if="isLoading && conversations.length === 0" class="p-4 space-y-3">
                <div v-for="i in 5" :key="i" class="animate-pulse flex flex-col gap-2 p-3 bg-gray-50 dark:bg-gray-800/40 rounded-xl">
                    <div class="flex items-center justify-between">
                        <div class="w-24 h-3 bg-gray-200 dark:bg-gray-700 rounded"></div>
                        <div class="w-12 h-3 bg-gray-200 dark:bg-gray-700 rounded"></div>
                    </div>
                    <div class="w-40 h-3 bg-gray-200 dark:bg-gray-700 rounded"></div>
                    <div class="w-full h-2.5 bg-gray-200 dark:bg-gray-700 rounded"></div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else-if="conversations.length === 0" class="p-8 text-center">
                <i class="lab lab-inbox text-3xl text-gray-300 dark:text-gray-600 block mb-2"></i>
                <h4 class="text-xs font-semibold text-gray-700 dark:text-gray-300">No conversations found</h4>
                <p class="text-[11px] text-gray-400 mt-0.5">Try adjusting your filters or search terms.</p>
            </div>

            <!-- Cards -->
            <div
                v-for="conv in conversations"
                :key="conv.id"
                @click="selectConversation(conv.id)"
                class="p-3.5 cursor-pointer transition-all hover:bg-gray-50 dark:hover:bg-gray-800/50 relative group"
                :class="activeConversation?.id === conv.id ? 'bg-gray-100/90 dark:bg-gray-800 border-l-4 border-gray-900 dark:border-white' : ''"
            >
                <div class="flex items-start justify-between gap-2 mb-1">
                    <div class="flex items-center gap-1.5">
                        <span class="text-xs font-bold text-gray-900 dark:text-white truncate max-w-[140px]">
                            {{ conv.customer.name }}
                        </span>
                        <span v-if="conv.customer.is_guest" class="px-1.5 py-0.2 text-[9px] font-semibold bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded">
                            Guest
                        </span>
                    </div>
                    <span class="text-[10px] text-gray-400 flex-shrink-0">
                        {{ formatTime(conv.updated_at) }}
                    </span>
                </div>

                <!-- Subject / Title -->
                <div class="text-xs font-medium text-gray-800 dark:text-gray-200 truncate mb-1">
                    {{ conv.subject || 'Support Inquiry' }}
                </div>

                <!-- Last Message Excerpt -->
                <div v-if="conv.last_message" class="text-[11px] text-gray-500 dark:text-gray-400 truncate mb-2 flex items-center gap-1">
                    <span v-if="conv.last_message.is_internal" class="text-amber-600 dark:text-amber-400 font-semibold">[Note]:</span>
                    <span v-else-if="conv.last_message.sender_type === 'agent'" class="font-semibold text-slate-700 dark:text-slate-300">Agent:</span>
                    <span v-else-if="conv.last_message.sender_type === 'ai'" class="font-semibold text-[#1ABC9C]">AI:</span>
                    <span v-else class="font-semibold">Customer:</span>
                    <span>{{ conv.last_message.content }}</span>
                </div>

                <!-- Footer Badges -->
                <div class="flex items-center justify-between text-[10px] mt-1 pt-1.5 border-t border-gray-100 dark:border-gray-800/40">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <!-- Status Badge -->
                        <span class="px-1.5 py-0.5 rounded font-semibold capitalize" :class="statusBadgeClass(conv.status)">
                            {{ formatStatus(conv.status) }}
                        </span>

                        <!-- Priority Badge -->
                        <span v-if="conv.priority === 'urgent' || conv.priority === 'high'" class="px-1.5 py-0.5 rounded font-bold capitalize bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300">
                            {{ conv.priority }}
                        </span>

                        <!-- Department Tag -->
                        <span v-if="conv.department" class="text-gray-400 dark:text-gray-500">
                            {{ conv.department.name }}
                        </span>
                    </div>

                    <!-- Assignee -->
                    <div class="flex items-center gap-1 text-gray-500 dark:text-gray-400 font-medium">
                        <i class="lab lab-user text-xs"></i>
                        <span class="truncate max-w-[80px]">
                            {{ conv.assigned_agent ? conv.assigned_agent.name : 'Unassigned' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="pagination.last_page > 1" class="px-3 py-2 border-t border-gray-200 dark:border-gray-800 flex items-center justify-between text-xs bg-gray-50 dark:bg-gray-900/80">
            <button
                type="button"
                :disabled="pagination.current_page <= 1"
                @click="changePage(pagination.current_page - 1)"
                class="px-2 py-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded disabled:opacity-40"
            >
                Prev
            </button>
            <span class="text-[11px] text-gray-500">
                Page {{ pagination.current_page }} of {{ pagination.last_page }}
            </span>
            <button
                type="button"
                :disabled="pagination.current_page >= pagination.last_page"
                @click="changePage(pagination.current_page + 1)"
                class="px-2 py-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded disabled:opacity-40"
            >
                Next
            </button>
        </div>
    </div>
</template>

<script>
import SupportQueueFilters from './SupportQueueFilters.vue';

export default {
    name: 'SupportQueue',
    components: {
        SupportQueueFilters,
    },
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
        formatTime(iso) {
            if (!iso) return '';
            try {
                const date = new Date(iso);
                return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
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
                    return 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-300';
                case 'human_active':
                    return 'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-300';
                case 'awaiting_customer':
                    return 'bg-blue-100 text-blue-900 dark:bg-blue-900/40 dark:text-blue-300';
                case 'resolved':
                case 'closed':
                    return 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300';
                default:
                    return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300';
            }
        },
    },
};
</script>
