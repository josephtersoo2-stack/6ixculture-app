<template>
    <div class="fixed bottom-6 right-6 z-50">
        <!-- Floating Trigger Button -->
        <button
            v-if="!isOpen"
            @click="isOpen = true"
            class="group relative flex items-center gap-3 px-4 py-3 rounded-full bg-gradient-to-r from-slate-950 via-slate-900 to-indigo-950 text-white shadow-2xl hover:scale-105 transition-all border border-white/20"
        >
            <div class="relative">
                <div class="w-9 h-9 rounded-full bg-gradient-to-tr from-primary to-indigo-600 flex items-center justify-center text-white text-xs font-black shadow-inner">
                    6C
                </div>
                <span class="absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full bg-emerald-500 ring-2 ring-slate-950 animate-pulse"></span>
            </div>
            <div class="text-left">
                <div class="text-[10px] font-bold uppercase tracking-wider text-emerald-400">24/7 AI Concierge</div>
                <div class="text-xs font-bold text-white">Ask 6ixCulture</div>
            </div>
        </button>

        <!-- Expanded Floating Chat Drawer -->
        <div
            v-else
            class="w-[380px] sm:w-[420px] h-[580px] rounded-3xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-2xl flex flex-col overflow-hidden animate-in fade-in slide-in-from-bottom-5 duration-200"
        >
            <!-- Drawer Header -->
            <div class="p-4 bg-slate-950 text-white flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-primary flex items-center justify-center text-xs font-black">
                        6C
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-xs font-bold">6ixCulture Concierge</h3>
                            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        </div>
                        <p class="text-[10px] text-slate-400">English • Yorùbá • Igbo • Hausa</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button @click="isOpen = false" class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors">
                        <i class="lab lab-line-cross text-base"></i>
                    </button>
                </div>
            </div>

            <!-- Messages Stream -->
            <div ref="floatingChatStream" class="flex-1 overflow-y-auto p-4 space-y-3 bg-[#F8FAFC] dark:bg-gray-950/60 text-xs thin-scrolling">
                <div class="flex items-start gap-2.5">
                    <div class="w-7 h-7 rounded-full bg-slate-900 text-white flex items-center justify-center text-[10px] font-bold shrink-0 mt-0.5">
                        6C
                    </div>
                    <div class="p-3 rounded-2xl rounded-tl-none bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-800 dark:text-gray-200 shadow-2xs leading-relaxed">
                        Hello! I'm your 6ixCulture AI shopping assistant. Ask me to track an order, find products, or check sizing!
                    </div>
                </div>

                <template v-for="(msg, mIdx) in messages" :key="mIdx">
                    <!-- User Message -->
                    <div v-if="msg.sender_type === 'customer' || msg.sender_type === 'user'" class="flex justify-end">
                        <div class="max-w-[85%] p-3 rounded-2xl rounded-tr-none bg-primary text-white shadow-2xs leading-relaxed">
                            {{ msg.content }}
                        </div>
                    </div>

                    <!-- AI Message -->
                    <div v-else class="flex items-start gap-2.5">
                        <div class="w-7 h-7 rounded-full bg-slate-900 text-white flex items-center justify-center text-[10px] font-bold shrink-0 mt-0.5">
                            6C
                        </div>
                        <div class="max-w-[85%] p-3 rounded-2xl rounded-tl-none bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-800 dark:text-gray-200 shadow-2xs leading-relaxed">
                            <p>{{ msg.content }}</p>
                        </div>
                    </div>
                </template>

                <!-- Typing indicator -->
                <div v-if="isTyping" class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-full bg-slate-900 text-white flex items-center justify-center text-[10px] font-bold shrink-0">
                        6C
                    </div>
                    <div class="px-3 py-2 rounded-2xl rounded-tl-none bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-2xs">
                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-primary animate-bounce mr-1"></span>
                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-primary animate-bounce mr-1 [animation-delay:0.2s]"></span>
                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-primary animate-bounce [animation-delay:0.4s]"></span>
                    </div>
                </div>
            </div>

            <!-- Quick Action Chips -->
            <div class="px-3 py-2 bg-white dark:bg-gray-900 border-t border-gray-100 dark:border-gray-800 flex items-center gap-1.5 overflow-x-auto thin-scrolling">
                <button
                    v-for="(chip, cIdx) in quickChips"
                    :key="cIdx"
                    @click="sendQuickPrompt(chip)"
                    class="px-2.5 py-1 rounded-full text-[11px] font-medium whitespace-nowrap bg-slate-100 text-slate-700 hover:bg-primary hover:text-white transition-all shrink-0"
                >
                    {{ chip }}
                </button>
            </div>

            <!-- Input Bar -->
            <div class="p-3 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-800">
                <form @submit.prevent="sendMessage" class="flex items-center gap-2">
                    <input
                        v-model="inputMessage"
                        type="text"
                        placeholder="Type a message..."
                        :disabled="isTyping"
                        class="flex-1 h-9 px-3 rounded-xl bg-slate-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-xs text-gray-900 dark:text-white focus:outline-none focus:border-primary"
                    />
                    <button
                        type="submit"
                        :disabled="!inputMessage.trim() || isTyping"
                        class="h-9 px-4 rounded-xl bg-primary text-white text-xs font-bold hover:bg-primary/90 disabled:opacity-50"
                    >
                        Send
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    name: 'FloatingSupportWidget',
    data() {
        return {
            isOpen: false,
            conversationPublicId: null,
            messages: [],
            inputMessage: '',
            isTyping: false,
            quickChips: [
                'Track my order',
                'What is your return policy?',
                'Connect to human agent',
                'Sizing help',
            ],
        };
    },
    mounted() {
        this.initSession();
    },
    methods: {
        async initSession() {
            try {
                const res = await axios.post('/v1/support/conversations', { channel: 'web', language: 'en' });
                if (res.data && res.data.data) {
                    this.conversationPublicId = res.data.data.public_id;
                }
            } catch (e) {
                // Silently handle
            }
        },
        async sendMessage() {
            const text = this.inputMessage.trim();
            if (!text || this.isTyping) return;

            this.messages.push({ sender_type: 'customer', content: text });
            this.inputMessage = '';
            this.isTyping = true;
            this.scrollToBottom();

            try {
                const res = await axios.post(`/v1/support/conversations/${this.conversationPublicId}/messages`, {
                    content: text,
                });
                if (res.data && res.data.data) {
                    this.messages.push(res.data.data);
                }
            } catch (err) {
                this.messages.push({
                    sender_type: 'system',
                    content: 'Thank you for your message. An agent will follow up shortly.',
                });
            } finally {
                this.isTyping = false;
                this.scrollToBottom();
            }
        },
        sendQuickPrompt(prompt) {
            this.inputMessage = prompt;
            this.sendMessage();
        },
        scrollToBottom() {
            this.$nextTick(() => {
                const el = this.$refs.floatingChatStream;
                if (el) el.scrollTop = el.scrollHeight;
            });
        },
    },
};
</script>
