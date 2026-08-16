<template>
    <div class="agent-reply-composer p-3 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-800">
        <!-- Quick Canned Responses Bar -->
        <div class="flex items-center gap-1.5 mb-2 overflow-x-auto pb-1 no-scrollbar">
            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider flex items-center gap-1 flex-shrink-0">
                <i class="lab lab-flash text-amber-500"></i> Canned:
            </span>
            <button
                v-for="(macro, idx) in cannedResponses"
                :key="idx"
                type="button"
                @click="insertCanned(macro.text)"
                class="px-2 py-0.5 text-[11px] bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md transition-colors whitespace-nowrap flex-shrink-0"
            >
                {{ macro.label }}
            </button>
        </div>

        <form @submit.prevent="handleSend" class="flex flex-col gap-2">
            <textarea
                ref="replyTextarea"
                v-model="replyText"
                rows="3"
                maxlength="4000"
                placeholder="Type customer reply... (Shift + Enter for new line)"
                @keydown.enter.exact.prevent="handleSend"
                class="w-full p-2.5 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-xs text-gray-900 dark:text-gray-100 placeholder:text-gray-400 focus:outline-none focus:border-gray-900 dark:focus:border-white resize-none transition-all"
            ></textarea>

            <div class="flex items-center justify-between">
                <label class="flex items-center gap-1.5 text-xs text-gray-600 dark:text-gray-400 cursor-pointer select-none">
                    <input
                        type="checkbox"
                        v-model="resolveAfterReply"
                        class="rounded border-gray-300 text-gray-900 focus:ring-gray-900 dark:focus:ring-white"
                    />
                    <span>Mark as Resolved after sending</span>
                </label>

                <div class="flex items-center gap-2">
                    <span class="text-[10px] text-gray-400">
                        {{ replyText.length }}/4000
                    </span>
                    <button
                        type="submit"
                        :disabled="isSending || replyText.trim().length === 0"
                        class="px-4 py-1.5 bg-gray-900 dark:bg-white text-white dark:text-gray-900 font-semibold text-xs rounded-xl hover:bg-gray-800 transition-colors disabled:opacity-40 disabled:cursor-not-allowed shadow-xs flex items-center gap-1.5"
                    >
                        <span v-if="isSending" class="w-3 h-3 border-2 border-white dark:border-gray-900 border-t-transparent rounded-full animate-spin"></span>
                        <i v-else class="lab lab-send text-xs"></i>
                        <span>Send Reply</span>
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
