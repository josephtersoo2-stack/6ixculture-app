<template>
    <div class="suggested-prompts px-4 py-2 border-t border-slate-100 bg-slate-50/50">
        <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-2 flex items-center gap-1">
            <i class="lab lab-sparkles text-amber-500"></i> Quick Actions
        </div>
        <div class="flex flex-wrap gap-1.5">
            <button
                v-for="(prompt, index) in prompts"
                :key="index"
                type="button"
                @click="$emit('select-prompt', prompt.text)"
                class="px-2.5 py-1 text-xs bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 rounded-full transition-colors flex items-center gap-1 shadow-xs"
            >
                <i :class="prompt.icon" class="text-xs text-slate-500"></i>
                <span>{{ prompt.label }}</span>
            </button>
        </div>
    </div>
</template>

<script>
export default {
    name: 'SuggestedPrompts',
    emits: ['select-prompt'],
    data() {
        return {
            prompts: [
                { label: 'Hoodies & Sweatshirts', text: 'Show me your latest hoodies and streetwear collection', icon: 'lab lab-bag' },
                { label: 'Track My Order', text: 'Where is my order? Can you check the tracking status?', icon: 'lab lab-truck' },
                { label: 'Return Policy', text: 'What is your 7-day return and exchange policy?', icon: 'lab lab-refresh' },
                { label: 'Sizing & Fit Guide', text: 'How do 6ixCulture streetwear sizes fit?', icon: 'lab lab-info-circle' },
            ],
        };
    },
};
</script>
