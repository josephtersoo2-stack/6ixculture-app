<template>
    <div class="support-center-app min-h-screen bg-[#0B0F19] text-slate-100 font-sans flex flex-col antialiased">
        <!-- Top App Bar -->
        <header class="h-14 bg-[#0E1424] border-b border-[#1F293D] px-4 flex items-center justify-between flex-shrink-0 z-20">
            <!-- Left: Title & Logo -->
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    @click="toggleSidebar"
                    class="p-1.5 text-slate-400 hover:text-white hover:bg-[#1E293B] rounded-lg transition-colors lg:hidden"
                >
                    <i class="lab lab-menu text-lg"></i>
                </button>
                <div class="flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-lg bg-indigo-600/30 border border-indigo-500/40 text-indigo-400 font-black text-xs flex items-center justify-center shadow-xs">
                        6IX
                    </div>
                    <h1 class="text-sm font-bold text-white tracking-wide">
                        AI Support Center
                    </h1>
                </div>
            </div>

            <!-- Right: Status, Notifications & Profile -->
            <div class="flex items-center gap-4">
                <!-- Presence Indicator & Toggle -->
                <div class="flex items-center gap-2 px-2.5 py-1 bg-[#131B2E] border border-[#1F293D] rounded-full text-xs">
                    <span class="text-[11px] text-slate-400 font-medium">Status:</span>
                    <button
                        type="button"
                        @click="togglePresence"
                        class="flex items-center gap-1.5 font-semibold transition-colors"
                        :class="isAgentOnline ? 'text-emerald-400' : 'text-slate-400'"
                    >
                        <span class="w-2 h-2 rounded-full" :class="isAgentOnline ? 'bg-emerald-500 animate-pulse' : 'bg-slate-500'"></span>
                        <span>{{ isAgentOnline ? 'Online' : 'Away' }}</span>
                    </button>
                </div>

                <!-- Notification Bell -->
                <button
                    type="button"
                    class="relative p-2 text-slate-400 hover:text-white hover:bg-[#1E293B] rounded-lg transition-colors"
                    title="Notifications"
                >
                    <i class="lab lab-notification text-base"></i>
                    <span
                        v-if="unassignedCount > 0"
                        class="absolute top-1 right-1 w-4 h-4 bg-rose-500 text-white text-[9px] font-black rounded-full flex items-center justify-center border-2 border-[#0E1424]"
                    >
                        {{ unassignedCount > 9 ? '9+' : unassignedCount }}
                    </span>
                </button>

                <!-- Admin Profile Dropdown Preview -->
                <div class="flex items-center gap-2.5 pl-2 border-l border-[#1F293D]">
                    <div class="w-8 h-8 rounded-full bg-indigo-600 text-white font-bold text-xs flex items-center justify-center shadow-xs">
                        {{ userInitials }}
                    </div>
                    <div class="hidden sm:flex flex-col text-left">
                        <span class="text-xs font-bold text-slate-200 leading-tight">{{ userName }}</span>
                        <span class="text-[10px] text-indigo-400 font-medium">Support Admin</span>
                    </div>
                </div>
            </div>
        </header>

        <!-- 4-Area Main Support Workspace -->
        <div class="flex-1 flex flex-row overflow-hidden relative">
            <!-- AREA 1: Support Navigation Sidebar -->
            <aside
                class="w-56 bg-[#0E1424] border-r border-[#1F293D] flex flex-col justify-between py-4 px-2 flex-shrink-0 transition-all duration-200 z-10"
                :class="sidebarOpen ? 'absolute lg:static inset-y-0 left-0 shadow-2xl' : 'hidden lg:flex'"
            >
                <nav class="space-y-1">
                    <button
                        type="button"
                        @click="setActiveNav('dashboard')"
                        class="w-full flex items-center justify-between px-3 py-2 text-xs font-semibold rounded-xl transition-all"
                        :class="activeNav === 'dashboard' ? 'bg-indigo-600/20 text-indigo-400 border border-indigo-500/30' : 'text-slate-400 hover:text-slate-200 hover:bg-[#161F36]'"
                    >
                        <div class="flex items-center gap-2.5">
                            <i class="lab lab-dashboard text-sm"></i>
                            <span>Dashboard</span>
                        </div>
                    </button>

                    <button
                        type="button"
                        @click="setActiveNav('conversations')"
                        class="w-full flex items-center justify-between px-3 py-2 text-xs font-semibold rounded-xl transition-all"
                        :class="activeNav === 'conversations' ? 'bg-indigo-600/20 text-indigo-400 border border-indigo-500/30' : 'text-slate-400 hover:text-slate-200 hover:bg-[#161F36]'"
                    >
                        <div class="flex items-center gap-2.5">
                            <i class="lab lab-messages text-sm"></i>
                            <span>Conversations</span>
                        </div>
                        <span
                            v-if="conversations.length"
                            class="px-1.5 py-0.5 text-[10px] font-bold rounded-full"
                            :class="activeNav === 'conversations' ? 'bg-indigo-600 text-white' : 'bg-[#1E293B] text-slate-300'"
                        >
                            {{ pagination.total || conversations.length }}
                        </span>
                    </button>

                    <button
                        type="button"
                        @click="setActiveNav('agents')"
                        class="w-full flex items-center justify-between px-3 py-2 text-xs font-semibold rounded-xl transition-all"
                        :class="activeNav === 'agents' ? 'bg-indigo-600/20 text-indigo-400 border border-indigo-500/30' : 'text-slate-400 hover:text-slate-200 hover:bg-[#161F36]'"
                    >
                        <div class="flex items-center gap-2.5">
                            <i class="lab lab-users text-sm"></i>
                            <span>Agents</span>
                        </div>
                        <span v-if="agents.length" class="text-[10px] text-slate-500 font-medium">
                            {{ agents.length }}
                        </span>
                    </button>

                    <router-link
                        to="/admin/customers"
                        class="w-full flex items-center justify-between px-3 py-2 text-xs font-semibold rounded-xl text-slate-400 hover:text-slate-200 hover:bg-[#161F36] transition-all"
                    >
                        <div class="flex items-center gap-2.5">
                            <i class="lab lab-user-check text-sm"></i>
                            <span>Customers</span>
                        </div>
                    </router-link>

                    <router-link
                        to="/admin/support/governance"
                        class="w-full flex items-center justify-between px-3 py-2 text-xs font-semibold rounded-xl text-slate-400 hover:text-slate-200 hover:bg-[#161F36] transition-all"
                    >
                        <div class="flex items-center gap-2.5">
                            <i class="lab lab-book text-sm"></i>
                            <span>Knowledge Base</span>
                        </div>
                    </router-link>

                    <router-link
                        to="/admin/support/governance"
                        class="w-full flex items-center justify-between px-3 py-2 text-xs font-semibold rounded-xl text-slate-400 hover:text-slate-200 hover:bg-[#161F36] transition-all"
                    >
                        <div class="flex items-center gap-2.5">
                            <i class="lab lab-shield-security text-sm"></i>
                            <span>Governance</span>
                        </div>
                    </router-link>

                    <router-link
                        to="/admin/support/governance"
                        class="w-full flex items-center justify-between px-3 py-2 text-xs font-semibold rounded-xl text-slate-400 hover:text-slate-200 hover:bg-[#161F36] transition-all"
                    >
                        <div class="flex items-center gap-2.5">
                            <i class="lab lab-document text-sm"></i>
                            <span>Logs & Audit</span>
                        </div>
                    </router-link>
                </nav>

                <!-- Footer / Quick Link to cPanel Storefront -->
                <div class="pt-4 border-t border-[#1F293D] px-2 text-[11px] text-slate-500">
                    <div class="flex items-center justify-between">
                        <span>Storefront AI</span>
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    </div>
                </div>
            </aside>

            <!-- AREA 2: Conversation Queue -->
            <div class="w-80 lg:w-[320px] xl:w-[340px] flex-shrink-0 h-full">
                <SupportQueue
                    :conversations="conversations"
                    :pagination="pagination"
                    :active-conversation="activeConversation"
                    :departments="departments"
                    :is-loading="isLoading"
                    @select-conversation="handleSelectConversation"
                />
            </div>

            <!-- AREA 3: Active Conversation Workspace -->
            <div class="flex-1 h-full min-w-0 bg-[#0B0F19]">
                <SupportConversationPanel
                    :conversation="activeConversation"
                    :departments="departments"
                    :is-action-loading="isActionLoading"
                    :is-copilot-loading="isCopilotLoading"
                />
            </div>

            <!-- AREA 4: Customer 360 & Order History -->
            <div class="hidden xl:block w-80 lg:w-[320px] flex-shrink-0 h-full">
                <Customer360Panel
                    :conversation="activeConversation"
                    :customer360="customer360"
                    :orders="orders"
                    :ticket="ticket"
                    :agents="agents"
                />
            </div>
        </div>
    </div>
</template>

<script>
import SupportQueue from './SupportQueue.vue';
import SupportConversationPanel from './SupportConversationPanel.vue';
import Customer360Panel from './Customer360Panel.vue';

export default {
    name: 'SupportCenterComponent',
    components: {
        SupportQueue,
        SupportConversationPanel,
        Customer360Panel,
    },
    data() {
        return {
            sidebarOpen: false,
            activeNav: 'conversations',
            isAgentOnline: true,
        };
    },
    computed: {
        conversations() {
            return this.$store.getters['adminSupport/conversations'] || [];
        },
        pagination() {
            return this.$store.getters['adminSupport/pagination'] || { current_page: 1, last_page: 1, total: 0 };
        },
        activeConversation() {
            return this.$store.getters['adminSupport/activeConversation'];
        },
        customer360() {
            return this.$store.getters['adminSupport/customer360'];
        },
        orders() {
            return this.$store.getters['adminSupport/orders'] || [];
        },
        ticket() {
            return this.$store.getters['adminSupport/ticket'];
        },
        departments() {
            return this.$store.getters['adminSupport/departments'] || [];
        },
        agents() {
            return this.$store.getters['adminSupport/agents'] || [];
        },
        isLoading() {
            return this.$store.getters['adminSupport/isLoading'];
        },
        isActionLoading() {
            return this.$store.getters['adminSupport/isActionLoading'];
        },
        isCopilotLoading() {
            return this.$store.getters['adminSupport/isCopilotLoading'];
        },
        unassignedCount() {
            return this.conversations.filter(c => !c.assigned_agent).length;
        },
        userName() {
            return this.$store.getters.authName || 'Support Admin';
        },
        userInitials() {
            const name = this.userName;
            const parts = name.trim().split(' ');
            if (parts.length >= 2) {
                return (parts[0][0] + parts[1][0]).toUpperCase();
            }
            return name.substring(0, 2).toUpperCase();
        },
    },
    mounted() {
        this.$store.dispatch('adminSupport/fetchDepartments');
        this.$store.dispatch('adminSupport/fetchAgents');
        this.$store.dispatch('adminSupport/fetchQueue').then(() => {
            if (this.conversations.length > 0 && !this.activeConversation) {
                this.handleSelectConversation(this.conversations[0].id);
            }
        });
        this.$store.dispatch('adminSupport/startQueuePolling');
    },
    beforeUnmount() {
        this.$store.dispatch('adminSupport/stopQueuePolling');
    },
    methods: {
        handleSelectConversation(publicId) {
            this.$store.dispatch('adminSupport/fetchConversation', publicId);
        },
        toggleSidebar() {
            this.sidebarOpen = !this.sidebarOpen;
        },
        setActiveNav(nav) {
            this.activeNav = nav;
            if (nav === 'dashboard') {
                this.$router.push('/admin/dashboard');
            }
        },
        togglePresence() {
            this.isAgentOnline = !this.isAgentOnline;
            this.$store.dispatch('adminSupport/updatePresence', {
                status: this.isAgentOnline ? 'online' : 'busy',
                availability: this.isAgentOnline ? 5 : 0,
            });
        },
    },
};
</script>

<style scoped>
.support-center-app {
    color-scheme: dark;
}
</style>
