<template>
    <div class="ai-support-widget z-50 fixed bottom-5 right-5 sm:bottom-6 sm:right-6 font-sans">
        <!-- Floating Launcher Button -->
        <button
            v-if="!isOpen"
            type="button"
            @click="handleToggle"
            class="group relative flex items-center gap-2.5 px-4 py-3.5 bg-slate-950 text-white rounded-full shadow-2xl hover:bg-slate-900 transition-all hover:scale-105 active:scale-95 border border-slate-800 focus:outline-none"
            aria-label="Open 6ixCulture AI Support"
        >
            <div class="relative">
                <div class="w-7 h-7 rounded-full bg-slate-800 flex items-center justify-center font-black text-xs text-[#1ABC9C]">
                    6IX
                </div>
                <span class="absolute top-0 right-0 w-2.5 h-2.5 bg-emerald-500 rounded-full border border-slate-950"></span>
            </div>
            <span class="text-xs font-bold tracking-wide">CultureAI Support</span>

            <!-- Unread Badge -->
            <span 
                v-if="unreadCount > 0" 
                class="absolute -top-1.5 -right-1.5 w-5 h-5 bg-rose-600 text-white text-[10px] font-extrabold rounded-full flex items-center justify-center border-2 border-white shadow-sm animate-pulse"
            >
                {{ unreadCount }}
            </span>
        </button>

        <!-- Main Support Window Container -->
        <div
            v-if="isOpen"
            class="fixed sm:static bottom-0 right-0 w-full sm:w-[390px] h-[90vh] sm:h-[580px] max-h-[100vh] bg-white rounded-t-3xl sm:rounded-2xl shadow-2xl border border-slate-200 flex flex-col overflow-hidden animate-in fade-in slide-in-from-bottom-5 duration-200"
        >
            <!-- Header -->
            <ChatHeader
                :conversation="conversation"
                :language="language"
                @close="handleClose"
                @language-change="handleLanguageChange"
                @request-human="handleHumanHandoff"
            />

            <!-- Message List -->
            <MessageList
                :messages="messages"
                :is-typing="isTyping"
            />

            <!-- Suggested Quick Action Prompts -->
            <SuggestedPrompts
                v-if="messages.length <= 4 && !isSending"
                @select-prompt="handlePromptSelect"
            />

            <!-- Message Input Composer -->
            <MessageComposer
                ref="composer"
                :is-sending="isSending"
                @send="handleSendMessage"
            />
        </div>
    </div>
</template>

<script>
import ChatHeader from './ChatHeader.vue';
import MessageList from './MessageList.vue';
import SuggestedPrompts from './SuggestedPrompts.vue';
import MessageComposer from './MessageComposer.vue';

export default {
    name: 'AiSupportWidget',
    components: {
        ChatHeader,
        MessageList,
        SuggestedPrompts,
        MessageComposer,
    },
    computed: {
        isOpen() {
            return this.$store.getters['frontendSupport/isOpen'];
        },
        conversation() {
            return this.$store.getters['frontendSupport/conversation'];
        },
        messages() {
            return this.$store.getters['frontendSupport/messages'];
        },
        isSending() {
            return this.$store.getters['frontendSupport/isSending'];
        },
        isTyping() {
            return this.$store.getters['frontendSupport/isTyping'];
        },
        language() {
            return this.$store.getters['frontendSupport/language'];
        },
        unreadCount() {
            return this.$store.getters['frontendSupport/unreadCount'];
        },
    },
    methods: {
        handleToggle() {
            this.$store.dispatch('frontendSupport/toggleWidget');
        },
        handleClose() {
            this.$store.dispatch('frontendSupport/closeWidget');
        },
        handleLanguageChange(lang) {
            this.$store.dispatch('frontendSupport/setLanguage', lang);
        },
        handleHumanHandoff() {
            this.$store.dispatch('frontendSupport/requestHumanHandoff');
        },
        handleSendMessage(text) {
            this.$store.dispatch('frontendSupport/sendMessage', text);
        },
        handlePromptSelect(promptText) {
            if (this.$refs.composer) {
                this.$refs.composer.setMessage(promptText);
            }
            this.handleSendMessage(promptText);
        },
    },
};
</script>
