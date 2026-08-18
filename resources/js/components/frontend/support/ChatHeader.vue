<template>
    <div class="chat-header px-4 py-3 bg-slate-950 text-white flex items-center justify-between border-b border-slate-800 rounded-t-2xl select-none">
        <!-- Assistant Info -->
        <div class="flex items-center gap-2.5">
            <div class="relative">
                <div class="w-8 h-8 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center font-bold text-xs tracking-wider text-[#1ABC9C]">
                    6IX
                </div>
                <span class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-emerald-500 border-2 border-slate-950 rounded-full"></span>
            </div>
            <div>
                <h3 class="text-xs font-bold text-white tracking-wide flex items-center gap-1.5">
                    CultureAI
                    <span class="text-[10px] font-normal text-slate-400">Assistant</span>
                </h3>
                <span class="text-[10px] text-slate-400 flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block animate-pulse"></span>
                    {{ modeLabel }}
                </span>
            </div>
        </div>

        <!-- Controls -->
        <div class="flex items-center gap-2">
            <!-- Language Selector -->
            <select
                :value="language"
                @change="onLanguageChange($event.target.value)"
                class="bg-slate-900 text-slate-300 text-[11px] font-medium border border-slate-800 rounded-lg px-2 py-1 focus:outline-none focus:border-slate-600 cursor-pointer"
                title="Select Support Language"
            >
                <option v-for="l in availableLanguages" :key="l.code" :value="l.code">
                    {{ l.label }}
                </option>
            </select>

            <!-- Human Handoff button -->
            <button
                v-if="!isHumanMode"
                type="button"
                @click="onHumanHandoff"
                class="px-2 py-1 text-[11px] font-medium text-slate-300 hover:text-white bg-slate-900 hover:bg-slate-800 border border-slate-800 rounded-lg transition-colors flex items-center gap-1"
                title="Speak with a human agent"
            >
                <i class="lab lab-user text-xs"></i>
                <span class="hidden sm:inline">Human Agent</span>
            </button>

            <!-- Close Button -->
            <button
                type="button"
                @click="$emit('close')"
                class="p-1 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition-colors"
                title="Close chat"
            >
                <i class="lab lab-close text-sm"></i>
            </button>
        </div>
    </div>
</template>

<script>
export default {
    name: 'ChatHeader',
    props: {
        conversation: {
            type: Object,
            default: null,
        },
        language: {
            type: String,
            default: 'en',
        },
    },
    emits: ['close', 'language-change', 'request-human'],
    computed: {
        modeLabel() {
            if (!this.conversation) return 'Online';
            if (this.conversation.status === 'queued') return 'Queued for Agent';
            if (this.conversation.mode === 'hybrid' || this.conversation.mode === 'human') return 'Agent Assigned';
            return 'AI Online';
        },
        isHumanMode() {
            return this.conversation?.mode === 'hybrid' || this.conversation?.mode === 'human' || this.conversation?.status === 'queued';
        },
        availableLanguages() {
            const caps = this.$store.getters['frontendSupport/capabilities'];
            const ttsLangs = caps?.tts?.languages || {};

            const map = {
                en: { name: 'English', native: 'English' },
                yo: { name: 'Yoruba', native: 'Yorùbá' },
                ig: { name: 'Igbo', native: 'Igbo' },
                ha: { name: 'Hausa', native: 'Hausa' },
            };

            return ['en', 'yo', 'ig', 'ha'].map((code) => {
                const native = map[code]?.native || code.toUpperCase();
                const isNativeTts = ttsLangs[code]?.supported === true;
                return {
                    code,
                    native,
                    label: isNativeTts ? `${native} (${code.toUpperCase()})` : `${native} (${code.toUpperCase()}) • Fallback Voice`,
                };
            });
        },
    },
    methods: {
        onLanguageChange(lang) {
            this.$emit('language-change', lang);
        },
        onHumanHandoff() {
            this.$emit('request-human');
        },
    },
};
</script>
