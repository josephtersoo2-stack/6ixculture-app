<template>
    <div class="agent-copilot-panel p-3.5 bg-slate-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800">
        <div class="flex items-center justify-between mb-2">
            <div class="flex items-center gap-1.5 text-xs font-bold text-gray-800 dark:text-gray-200">
                <i class="lab lab-sparkles text-amber-500"></i>
                <span>Agent Copilot Assistance</span>
            </div>
            <span class="text-[10px] text-gray-400 font-medium">Policy-Governed</span>
        </div>

        <!-- Suggestion Chips -->
        <div class="space-y-1.5">
            <div
                v-for="(tip, idx) in copilotSuggestions"
                :key="idx"
                class="p-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg text-xs flex items-start justify-between gap-2 shadow-2xs hover:border-gray-400 transition-colors"
            >
                <div class="flex items-start gap-2">
                    <i :class="tip.icon" class="text-sm text-slate-500 mt-0.5"></i>
                    <div>
                        <span class="font-semibold text-gray-900 dark:text-white block text-[11px]">{{ tip.title }}</span>
                        <p class="text-[11px] text-gray-600 dark:text-gray-300 mt-0.5 leading-snug">{{ tip.description }}</p>
                    </div>
                </div>
                <button
                    v-if="tip.actionText"
                    type="button"
                    @click="$emit('apply-suggestion', tip.actionPayload)"
                    class="px-2 py-1 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 text-[10px] font-semibold rounded transition-colors flex-shrink-0"
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
