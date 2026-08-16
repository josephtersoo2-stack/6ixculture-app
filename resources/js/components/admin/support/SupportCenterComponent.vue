<template>
    <div class="support-center-component h-[calc(100vh-80px)] flex flex-col bg-gray-100 dark:bg-gray-950 overflow-hidden rounded-2xl shadow-xs border border-gray-200 dark:border-gray-800 m-4">
        <!-- 3-Column Support Console Layout -->
        <div class="flex-1 flex flex-row h-full overflow-hidden">
            <!-- COLUMN 1: Support Queue & Inbox -->
            <div class="w-80 lg:w-96 flex-shrink-0 h-full">
                <SupportQueue
                    :conversations="conversations"
                    :pagination="pagination"
                    :active-conversation="activeConversation"
                    :departments="departments"
                    :is-loading="isLoading"
                    @select-conversation="handleSelectConversation"
                />
            </div>

            <!-- COLUMN 2: Conversation Timeline & Dual Composer -->
            <div class="flex-1 h-full min-w-0">
                <SupportConversationPanel
                    :conversation="activeConversation"
                    :departments="departments"
                    :is-action-loading="isActionLoading"
                    :is-copilot-loading="isCopilotLoading"
                />
            </div>

            <!-- COLUMN 3: Customer 360 & Order History -->
            <div class="hidden xl:block h-full">
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
    computed: {
        conversations() {
            return this.$store.getters['adminSupport/conversations'];
        },
        pagination() {
            return this.$store.getters['adminSupport/pagination'];
        },
        activeConversation() {
            return this.$store.getters['adminSupport/activeConversation'];
        },
        customer360() {
            return this.$store.getters['adminSupport/customer360'];
        },
        orders() {
            return this.$store.getters['adminSupport/orders'];
        },
        ticket() {
            return this.$store.getters['adminSupport/ticket'];
        },
        departments() {
            return this.$store.getters['adminSupport/departments'];
        },
        agents() {
            return this.$store.getters['adminSupport/agents'];
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
    },
};
</script>
