<template>
    <div class="agent-copilot-panel p-3.5 bg-[#0E1424] border-b border-[#1F293D]">
        <div class="flex items-center justify-between mb-2">
            <div class="flex items-center gap-1.5 text-xs font-bold text-slate-200">
                <i class="lab lab-sparkles text-indigo-400"></i>
                <span>Agent Copilot Assistance</span>
            </div>
            <span class="text-[10px] text-slate-500 font-medium">Policy-Governed</span>
        </div>

        <!-- Suggestion Chips -->
        <div class="space-y-1.5">
            <div
                v-for="(tip, idx) in copilotSuggestions"
                :key="idx"
                class="p-2.5 bg-[#131B2E] border border-[#1F293D] rounded-xl text-xs flex items-start justify-between gap-2 shadow-2xs hover:border-indigo-500/50 transition-colors"
            >
                <div class="flex items-start gap-2 min-w-0">
                    <i :class="tip.icon" class="text-sm text-indigo-400 mt-0.5 flex-shrink-0"></i>
                    <div class="min-w-0">
                        <span class="font-semibold text-white block text-[11px] truncate">{{ tip.title }}</span>
                        <p class="text-[11px] text-slate-400 mt-0.5 leading-snug">{{ tip.description }}</p>
                    </div>
                </div>
                <button
                    v-if="tip.actionText"
                    type="button"
                    @click="$emit('apply-suggestion', tip.actionPayload)"
                    class="px-2.5 py-1 bg-[#1E293B] hover:bg-indigo-600 hover:text-white text-indigo-300 text-[10px] font-bold rounded-lg border border-[#1F293D] transition-colors flex-shrink-0"
                >
                    {{ tip.actionText }}
                </button>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'AgentCopilotPanel',
    props: {
        conversation: {
            type: Object,
            default: null,
        },
    },
    emits: ['apply-suggestion'],
    data() {
        return {
            copilotSuggestions: [
                {
                    icon: 'lab lab-truck',
                    title: 'Track Order & Share Delivery SLA',
                    description: 'Verify current dispatch state and reassure the customer with tracking code.',
                    actionText: 'Use Response',
                    actionPayload: 'I have verified your order status. It is currently processed and on track for delivery. Please let me know if you need any additional assistance.',
                },
                {
                    icon: 'lab lab-shield-check',
                    title: 'Return Policy Reminder',
                    description: '7-day policy applies to unworn streetwear with tags attached.',
                    actionText: 'Use Policy',
                    actionPayload: 'Please note that 6ixCulture returns are accepted within 7 days of delivery provided all tags and original streetwear packaging are intact.',
                },
            ],
        };
    },
};
</script>
