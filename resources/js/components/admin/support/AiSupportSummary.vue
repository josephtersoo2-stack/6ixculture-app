<template>
    <div v-if="summary || isGenerating" class="ai-support-summary p-3 bg-[#0E1424] text-slate-100 border-b border-[#1F293D]">
        <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <div class="w-5 h-5 rounded-lg bg-indigo-600/30 border border-indigo-500/40 text-indigo-400 flex items-center justify-center font-black text-[10px]">
                    AI
                </div>
                <span class="text-xs font-bold text-white tracking-wide">CultureAI Conversation Summary</span>
            </div>

            <div class="flex items-center gap-2">
                <button
                    type="button"
                    @click="$emit('refresh-summary')"
                    :disabled="isGenerating"
                    class="text-[11px] text-indigo-300 hover:text-white flex items-center gap-1 bg-[#131B2E] hover:bg-[#1E293B] border border-[#1F293D] px-2.5 py-1 rounded-xl transition-colors disabled:opacity-40"
                    title="Regenerate summary"
                >
                    <i class="lab lab-refresh text-xs" :class="{ 'animate-spin': isGenerating }"></i>
                    <span>{{ isGenerating ? 'Summarizing...' : 'Regenerate' }}</span>
                </button>
            </div>
        </div>

        <div class="mt-2 text-xs text-slate-300 bg-[#131B2E] p-2.5 rounded-xl border border-[#1F293D] leading-relaxed">
            <p v-if="summary">{{ summary }}</p>
            <p v-else class="text-slate-500 italic">Analyzing customer conversation turns...</p>
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
