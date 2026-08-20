<template>
    <div class="support-conversation-panel flex-1 flex flex-col h-full bg-[#0B0F19] overflow-hidden">
        <!-- Empty State when no conversation selected -->
        <div v-if="!conversation" class="flex-1 flex flex-col items-center justify-center p-8 text-center bg-[#0B0F19]">
            <div class="w-14 h-14 rounded-2xl bg-[#131B2E] text-indigo-400 border border-[#1F293D] flex items-center justify-center mb-3 text-2xl shadow-inner">
                <i class="lab lab-messages"></i>
            </div>
            <h3 class="text-sm font-bold text-slate-100">Select a Conversation</h3>
            <p class="text-xs text-slate-400 max-w-xs mt-1">
                Choose a customer conversation from the queue on the left to start assisting.
            </p>
        </div>

        <!-- Active Conversation Workspace -->
        <template v-else>
            <!-- Conversation Header -->
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
            <div v-if="showCopilot" class="border-t border-[#1F293D] bg-[#111827]">
                <AgentCopilotPanel
                    :conversation="conversation"
                    @apply-suggestion="handleApplyCopilot"
                />
            </div>

            <!-- Composer Switcher Tabs & Bar -->
            <div class="border-t border-[#1F293D] bg-[#0E1424]">
                <div class="flex items-center justify-between px-3 pt-2 bg-[#0E1424] border-b border-[#1F293D]">
                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            @click="composerMode = 'reply'"
                            class="px-3 py-1.5 text-xs font-bold rounded-t-lg transition-all flex items-center gap-1.5"
                            :class="composerMode === 'reply' ? 'bg-[#131B2E] text-indigo-400 border-t-2 border-indigo-500 shadow-2xs' : 'text-slate-400 hover:text-slate-200'"
                        >
                            <i class="lab lab-send text-xs"></i>
                            <span>Reply</span>
                        </button>
                        <button
                            type="button"
                            @click="composerMode = 'note'"
                            class="px-3 py-1.5 text-xs font-bold rounded-t-lg transition-all flex items-center gap-1.5"
                            :class="composerMode === 'note' ? 'bg-amber-950/40 text-amber-300 border-t-2 border-amber-500 shadow-2xs' : 'text-slate-400 hover:text-amber-300'"
                        >
                            <i class="lab lab-lock text-xs"></i>
                            <span>Internal Note</span>
                        </button>
                    </div>

                    <button
                        type="button"
                        @click="showCopilot = !showCopilot"
                        class="text-[11px] font-semibold text-slate-400 hover:text-indigo-400 flex items-center gap-1 mb-1 px-2 py-0.5 rounded transition-colors"
                        :class="showCopilot ? 'text-indigo-400 bg-indigo-950/40' : ''"
                    >
                        <i class="lab lab-sparkles text-xs"></i>
                        <span>{{ showCopilot ? 'Hide AI Assist' : 'AI Copilot' }}</span>
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
