<template>
    <div v-if="summary || isGenerating" class="ai-support-summary p-3 bg-slate-900 text-white border-b border-slate-800">
        <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <div class="w-5 h-5 rounded-lg bg-[#1ABC9C] text-slate-950 flex items-center justify-center font-black text-[10px]">
                    AI
                </div>
                <span class="text-xs font-bold text-white tracking-wide">CultureAI Conversation Summary</span>
            </div>

            <div class="flex items-center gap-2">
                <button
                    type="button"
                    @click="$emit('refresh-summary')"
                    :disabled="isGenerating"
                    class="text-[11px] text-slate-300 hover:text-white flex items-center gap-1 bg-slate-800 hover:bg-slate-700 px-2 py-0.5 rounded transition-colors disabled:opacity-40"
                    title="Regenerate summary from recent turns"
                >
                    <i class="lab lab-refresh text-xs" :class="{ 'animate-spin': isGenerating }"></i>
                    <span>{{ isGenerating ? 'Summarizing...' : 'Regenerate' }}</span>
                </button>
            </div>
        </div>

        <div class="mt-2 text-xs text-slate-300 bg-slate-950/60 p-2.5 rounded-lg border border-slate-800 leading-relaxed font-sans">
            <p v-if="summary">{{ summary }}</p>
            <p v-else class="text-slate-400 italic">Analyzing customer conversation...</p>
        </div>
    </div>
</template>

<script>
export default {
    name: 'AiSupportSummary',
    props: {
        summary: {
            type: String,
            default: '',
        },
        isGenerating: {
            type: Boolean,
            default: false,
        },
    },
    emits: ['refresh-summary'],
};
</script>
