<template>
    <div class="support-conversation-panel flex-1 flex flex-col h-full bg-white dark:bg-gray-900 overflow-hidden">
        <!-- Empty State when no conversation selected -->
        <div v-if="!conversation" class="flex-1 flex flex-col items-center justify-center p-8 text-center bg-gray-50/50 dark:bg-gray-950">
            <div class="w-14 h-14 rounded-2xl bg-gray-200 dark:bg-gray-800 text-gray-400 flex items-center justify-center mb-3 text-2xl shadow-inner">
                <i class="lab lab-messages"></i>
            </div>
            <h3 class="text-sm font-bold text-gray-800 dark:text-gray-200">Select a Conversation</h3>
            <p class="text-xs text-gray-500 max-w-xs mt-1">
                Choose an escalated customer conversation from the queue on the left to start assisting.
            </p>
        </div>

        <!-- Active Conversation Workspace -->
        <template v-else>
            <!-- Header -->
            <SupportConversationHeader
                :conversation="conversation"
                :departments="departments"
            />

            <!-- AI Summary Bar -->
            <AiSupportSummary
                :summary="conversation.ai_summary"
                :is-generating="isCopilotLoading"
                @refresh-summary="handleGenerateSummary"
            />

            <!-- Timeline -->
            <SupportTimeline
                :messages="conversation.messages"
            />

            <!-- Copilot Assistance (collapsible) -->
            <div v-if="showCopilot" class="border-t border-gray-200 dark:border-gray-800">
                <AgentCopilotPanel
                    :conversation="conversation"
                    @apply-suggestion="handleApplyCopilot"
                />
            </div>

            <!-- Composer Switcher Tabs & Bar -->
            <div class="border-t border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
                <div class="flex items-center justify-between px-3 pt-2 bg-gray-50 dark:bg-gray-950/60 border-b border-gray-200 dark:border-gray-800">
                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            @click="composerMode = 'reply'"
                            class="px-3 py-1.5 text-xs font-bold rounded-t-lg transition-all flex items-center gap-1.5"
                            :class="composerMode === 'reply' ? 'bg-white dark:bg-gray-900 text-gray-900 dark:text-white border-t-2 border-gray-900 dark:border-white shadow-2xs' : 'text-gray-500 hover:text-gray-900'"
                        >
                            <i class="lab lab-send text-xs"></i>
                            <span>Reply to Customer</span>
                        </button>
                        <button
                            type="button"
                            @click="composerMode = 'note'"
                            class="px-3 py-1.5 text-xs font-bold rounded-t-lg transition-all flex items-center gap-1.5"
                            :class="composerMode === 'note' ? 'bg-amber-50 dark:bg-amber-950/40 text-amber-900 dark:text-amber-300 border-t-2 border-amber-500 shadow-2xs' : 'text-gray-500 hover:text-amber-700'"
                        >
                            <i class="lab lab-lock text-xs"></i>
                            <span>Internal Staff Note</span>
                        </button>
                    </div>

                    <button
                        type="button"
                        @click="showCopilot = !showCopilot"
                        class="text-[11px] font-semibold text-gray-500 hover:text-gray-900 dark:hover:text-white flex items-center gap-1 mb-1"
                        :class="showCopilot ? 'text-amber-600 dark:text-amber-400' : ''"
                    >
                        <i class="lab lab-sparkles text-xs"></i>
                        <span>{{ showCopilot ? 'Hide Copilot' : 'AI Copilot' }}</span>
                    </button>
                </div>

                <!-- Active Composer -->
                <AgentReplyComposer
                    v-if="composerMode === 'reply'"
                    ref="replyComposer"
                    :is-sending="isActionLoading"
                    @send-reply="handleSendReply"
                />
                <InternalNoteComposer
                    v-else
                    :is-saving="isActionLoading"
                    @add-note="handleAddNote"
                />
            </div>
        </template>
    </div>
</template>

<script>
import SupportConversationHeader from './SupportConversationHeader.vue';
import SupportTimeline from './SupportTimeline.vue';
import AiSupportSummary from './AiSupportSummary.vue';
import AgentCopilotPanel from './AgentCopilotPanel.vue';
import AgentReplyComposer from './AgentReplyComposer.vue';
import InternalNoteComposer from './InternalNoteComposer.vue';

export default {
    name: 'SupportConversationPanel',
    components: {
        SupportConversationHeader,
        SupportTimeline,
        AiSupportSummary,
        AgentCopilotPanel,
        AgentReplyComposer,
        InternalNoteComposer,
    },
    props: {
        conversation: {
            type: Object,
            default: null,
        },
        departments: {
            type: Array,
            default: () => [],
        },
        isActionLoading: {
            type: Boolean,
            default: false,
        },
        isCopilotLoading: {
            type: Boolean,
            default: false,
        },
    },
    data() {
        return {
            composerMode: 'reply', // 'reply' or 'note'
            showCopilot: false,
        };
    },
    methods: {
        handleSendReply({ message, resolveAfterReply }) {
            if (!this.conversation) return;
            this.$store.dispatch('adminSupport/sendReply', {
                publicId: this.conversation.id,
                message,
                resolveAfterReply,
            });
        },
        handleAddNote(content) {
            if (!this.conversation) return;
            this.$store.dispatch('adminSupport/addInternalNote', {
                publicId: this.conversation.id,
                content,
            });
        },
        handleGenerateSummary() {
            if (!this.conversation) return;
            this.$store.dispatch('adminSupport/generateAiSummary', this.conversation.id);
        },
        handleApplyCopilot(text) {
            this.composerMode = 'reply';
            this.$nextTick(() => {
                this.$refs.replyComposer?.setReplyContent(text);
            });
        },
    },
};
</script>
