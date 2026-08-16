<template>
    <div class="message-composer p-3 border-t border-slate-200 bg-white">
        <form @submit.prevent="handleSend" class="flex items-end gap-2">
            <div class="flex-1 relative">
                <textarea
                    ref="textarea"
                    v-model="inputContent"
                    @keydown.enter.exact.prevent="handleSend"
                    :placeholder="placeholderText"
                    :disabled="isSending"
                    rows="1"
                    maxlength="1000"
                    class="w-full pl-3 pr-8 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 placeholder:text-slate-400 focus:outline-none focus:border-slate-900 focus:ring-1 focus:ring-slate-900 resize-none max-h-24 transition-all disabled:opacity-50"
                    @input="autoResize"
                ></textarea>
                <span v-if="inputContent.length > 800" class="absolute right-2 bottom-1.5 text-[10px] text-slate-400">
                    {{ 1000 - inputContent.length }}
                </span>
            </div>

            <button
                type="submit"
                :disabled="!canSend"
                class="p-2.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl transition-colors disabled:opacity-40 disabled:cursor-not-allowed flex-shrink-0 flex items-center justify-center shadow-xs"
                title="Send Message"
            >
                <i v-if="!isSending" class="lab lab-send text-sm"></i>
                <span v-else class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
            </button>
        </form>
    </div>
</template>

<script>
export default {
    name: 'MessageComposer',
    props: {
        isSending: {
            type: Boolean,
            default: false,
        },
    },
    emits: ['send'],
    data() {
        return {
            inputContent: '',
        };
    },
    computed: {
        canSend() {
            return this.inputContent.trim().length > 0 && !this.isSending;
        },
        placeholderText() {
            return 'Ask about products, orders, sizing, or store policy...';
        },
    },
    methods: {
        handleSend() {
            if (!this.canSend) return;
            const text = this.inputContent.trim();
            this.inputContent = '';
            this.$emit('send', text);
            this.$nextTick(() => {
                this.autoResize();
            });
        },
        setMessage(text) {
            this.inputContent = text;
            this.$nextTick(() => {
                this.autoResize();
                if (this.$refs.textarea) {
                    this.$refs.textarea.focus();
                }
            });
        },
        autoResize() {
            const el = this.$refs.textarea;
            if (!el) return;
            el.style.height = 'auto';
            el.style.height = Math.min(el.scrollHeight, 96) + 'px';
        },
    },
};
</script>
