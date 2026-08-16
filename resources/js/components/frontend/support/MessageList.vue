<template>
    <div 
        ref="scrollContainer" 
        class="message-list flex-1 overflow-y-auto p-4 space-y-2 bg-[#F8FAFC] thin-scrolling"
    >
        <!-- Initial Welcome State -->
        <div v-if="messages.length === 0" class="text-center py-6 px-4">
            <div class="w-12 h-12 rounded-2xl bg-slate-900 text-white mx-auto flex items-center justify-center font-bold text-sm tracking-wider mb-3 shadow-sm">
                6IX
            </div>
            <h4 class="text-sm font-bold text-slate-900">Welcome to 6ixCulture Support</h4>
            <p class="text-xs text-slate-500 mt-1 max-w-xs mx-auto leading-relaxed">
                I am your CultureAI shopping and support assistant. How can I help you style or manage your orders today?
            </p>
        </div>

        <!-- Message Feed -->
        <MessageBubble 
            v-for="msg in messages" 
            :key="msg.id" 
            :message="msg" 
        />

        <!-- Typing Indicator -->
        <div v-if="isTyping" class="flex items-center gap-1.5 p-3 bg-white border border-slate-200/80 rounded-2xl rounded-tl-none w-20 shadow-xs my-2">
            <span class="w-1.5 h-1.5 bg-slate-400 rounded-full animate-bounce"></span>
            <span class="w-1.5 h-1.5 bg-slate-400 rounded-full animate-bounce [animation-delay:0.2s]"></span>
            <span class="w-1.5 h-1.5 bg-slate-400 rounded-full animate-bounce [animation-delay:0.4s]"></span>
        </div>
    </div>
</template>

<script>
import MessageBubble from './MessageBubble.vue';

export default {
    name: 'MessageList',
    components: {
        MessageBubble,
    },
    props: {
        messages: {
            type: Array,
            default: () => [],
        },
        isTyping: {
            type: Boolean,
            default: false,
        },
    },
    watch: {
        messages: {
            deep: true,
            handler() {
                this.scrollToBottom();
            },
        },
        isTyping() {
            this.scrollToBottom();
        },
    },
    mounted() {
        this.scrollToBottom();
    },
    methods: {
        scrollToBottom() {
            this.$nextTick(() => {
                const el = this.$refs.scrollContainer;
                if (el) {
                    el.scrollTop = el.scrollHeight;
                }
            });
        },
    },
};
</script>
