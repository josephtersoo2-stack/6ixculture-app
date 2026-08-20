<template>
    <div class="agent-reply-composer p-3 bg-[#0E1424] border-t border-[#1F293D]">
        <!-- Quick Canned Responses Bar -->
        <div class="flex items-center gap-1.5 mb-2 overflow-x-auto pb-1 no-scrollbar">
            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1 flex-shrink-0">
                <i class="lab lab-flash text-indigo-400"></i> Canned:
            </span>
            <button
                v-for="(macro, idx) in cannedResponses"
                :key="idx"
                type="button"
                @click="insertCanned(macro.text)"
                class="px-2 py-0.5 text-[11px] bg-[#131B2E] hover:bg-[#1E293B] border border-[#1F293D] text-slate-300 rounded-lg transition-colors whitespace-nowrap flex-shrink-0"
            >
                {{ macro.label }}
            </button>
        </div>

        <form @submit.prevent="handleSend" class="flex flex-col gap-2">
            <div class="relative">
                <textarea
                    ref="replyTextarea"
                    v-model="replyText"
                    rows="3"
                    maxlength="4000"
                    placeholder="Type your message... (Shift + Enter for new line)"
                    @keydown.enter.exact.prevent="handleSend"
                    class="w-full p-3 bg-[#131B2E] border border-[#1F293D] rounded-xl text-xs text-slate-100 placeholder:text-slate-500 focus:outline-none focus:border-indigo-500 resize-none transition-all"
                ></textarea>
            </div>

            <!-- Action Toolbar & Send -->
            <div class="flex items-center justify-between">
                <!-- Left Action Icons (Attachment, Emoji, Voice, Translate) -->
                <div class="flex items-center gap-1 text-slate-400">
                    <button
                        type="button"
                        class="p-1.5 hover:text-white hover:bg-[#1E293B] rounded-lg transition-colors"
                        title="Attach file"
                    >
                        <i class="lab lab-paperclip text-sm"></i>
                    </button>
                    <button
                        type="button"
                        class="p-1.5 hover:text-white hover:bg-[#1E293B] rounded-lg transition-colors"
                        title="Insert emoji"
                    >
                        <i class="lab lab-smile text-sm"></i>
                    </button>
                    <button
                        type="button"
                        class="p-1.5 hover:text-indigo-400 hover:bg-[#1E293B] rounded-lg transition-colors"
                        title="Voice dictation"
                    >
                        <i class="lab lab-mic text-sm"></i>
                    </button>
                    <button
                        type="button"
                        class="p-1.5 hover:text-indigo-400 hover:bg-[#1E293B] rounded-lg transition-colors"
                        title="Translate message"
                    >
                        <i class="lab lab-translate text-sm"></i>
                    </button>
                </div>

                <!-- Right: Checkbox & Purple Send Button -->
                <div class="flex items-center gap-3">
                    <label class="flex items-center gap-1.5 text-xs text-slate-400 cursor-pointer select-none">
                        <input
                            type="checkbox"
                            v-model="resolveAfterReply"
                            class="rounded border-[#1F293D] bg-[#131B2E] text-indigo-600 focus:ring-indigo-500"
                        />
                        <span>Resolve after reply</span>
                    </label>

                    <button
                        type="submit"
                        :disabled="isSending || replyText.trim().length === 0"
                        class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl transition-all disabled:opacity-40 disabled:cursor-not-allowed shadow-xs flex items-center gap-1.5"
                    >
                        <span v-if="isSending" class="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                        <span v-else>Send</span>
                        <i v-if="!isSending" class="lab lab-send text-xs"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>
</template>

<script>
export default {
    name: 'AgentReplyComposer',
    props: {
        isSending: {
            type: Boolean,
            default: false,
        },
    },
    emits: ['send-reply'],
    data() {
        return {
            replyText: '',
            resolveAfterReply: false,
            cannedResponses: [
                { label: 'Order Dispatched', text: 'Hello! Your order has been dispatched with our courier service and is currently in transit. You can track updates with your tracking code.' },
                { label: 'Return Policy', text: '6ixCulture provides a 7-day return and exchange window for unworn items in original packaging with intact streetwear security tags.' },
                { label: 'Sizing Advice', text: 'Our streetwear hoodies and tees feature a contemporary relaxed/oversized fit. For a standard fit, we recommend ordering one size down.' },
            ],
        };
    },
    methods: {
        insertCanned(text) {
            this.replyText = (this.replyText ? this.replyText + '\n' : '') + text;
            this.$refs.replyTextarea?.focus();
        },
        handleSend() {
            if (!this.replyText.trim() || this.isSending) return;
            this.$emit('send-reply', {
                message: this.replyText.trim(),
                resolveAfterReply: this.resolveAfterReply,
            });
            this.replyText = '';
            this.resolveAfterReply = false;
        },
        setReplyContent(text) {
            this.replyText = text;
            this.$refs.replyTextarea?.focus();
        },
    },
};
</script>
