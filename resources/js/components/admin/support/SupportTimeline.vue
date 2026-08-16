<template>
    <div
        ref="timelineContainer"
        class="support-timeline flex-1 overflow-y-auto p-4 space-y-3 bg-[#F8FAFC] dark:bg-gray-950 thin-scrolling"
    >
        <!-- Welcome / Empty Messages -->
        <div v-if="!messages || messages.length === 0" class="p-8 text-center text-xs text-gray-400">
            No messages in this conversation yet.
        </div>

        <div
            v-for="msg in messages"
            :key="msg.id"
            class="flex flex-col"
        >
            <!-- 1. SYSTEM EVENT -->
            <div v-if="msg.sender_type === 'system'" class="my-2 flex items-center justify-center">
                <span class="px-3 py-1 bg-gray-200/70 dark:bg-gray-800 text-gray-600 dark:text-gray-400 text-[11px] font-medium rounded-full shadow-2xs">
                    <i class="lab lab-info-circle text-xs mr-1"></i> {{ msg.content }}
                </span>
            </div>

            <!-- 2. INTERNAL STAFF NOTE (Highlighted Amber Box) -->
            <div v-else-if="msg.is_internal" class="my-2 p-3.5 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 rounded-xl shadow-xs">
                <div class="flex items-center justify-between gap-2 mb-1 text-[11px] text-amber-800 dark:text-amber-300">
                    <div class="flex items-center gap-1.5 font-bold uppercase tracking-wider">
                        <i class="lab lab-lock text-xs"></i>
                        <span>Internal Note</span>
                        <span v-if="msg.agent">• {{ msg.agent.name }}</span>
                    </div>
                    <span class="text-[10px] text-amber-700/70 dark:text-amber-400/70">{{ formatTime(msg.created_at) }}</span>
                </div>
                <div class="text-xs text-amber-950 dark:text-amber-100 whitespace-pre-wrap leading-relaxed">
                    {{ msg.content }}
                </div>
            </div>

            <!-- 3. AGENT REPLY (Right aligned Slate Bubble) -->
            <div v-else-if="msg.sender_type === 'agent'" class="flex flex-col items-end my-1">
                <div class="flex items-center gap-1.5 mb-0.5 px-1 text-[10px] text-gray-400">
                    <span class="font-bold text-gray-700 dark:text-gray-300">{{ msg.agent ? msg.agent.name : 'You' }}</span>
                    <span>•</span>
                    <span>{{ formatTime(msg.created_at) }}</span>
                </div>
                <div class="max-w-[80%] rounded-2xl rounded-tr-none p-3 bg-gray-900 dark:bg-gray-800 text-white text-xs shadow-xs leading-relaxed whitespace-pre-wrap">
                    {{ msg.content }}
                </div>
            </div>

            <!-- 4. AI ASSISTANT TURN (Left aligned with CultureAI badge) -->
            <div v-else-if="msg.sender_type === 'ai'" class="flex flex-col items-start my-1">
                <div class="flex items-center gap-1.5 mb-0.5 px-1 text-[10px] text-gray-400">
                    <span class="font-bold text-[#1ABC9C] flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-[#1ABC9C]"></span>
                        CultureAI
                    </span>
                    <span>•</span>
                    <span>{{ formatTime(msg.created_at) }}</span>
                </div>
                <div class="max-w-[85%] rounded-2xl rounded-tl-none p-3 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-900 dark:text-gray-100 text-xs shadow-xs leading-relaxed">
                    <div class="whitespace-pre-wrap">{{ msg.content }}</div>

                    <!-- Structured Payload Preview if present -->
                    <div v-if="msg.payload" class="mt-2 pt-2 border-t border-gray-100 dark:border-gray-800 text-[11px] text-gray-500">
                        <span class="font-semibold block text-[10px] text-gray-400 uppercase">Attached Payload:</span>
                        <pre class="overflow-x-auto bg-gray-50 dark:bg-gray-950 p-1.5 rounded mt-1 text-[10px] text-gray-700 dark:text-gray-300">{{ JSON.stringify(msg.payload, null, 2) }}</pre>
                    </div>
                </div>
            </div>

            <!-- 5. CUSTOMER MESSAGE (Left aligned White Bubble) -->
            <div v-else class="flex flex-col items-start my-1">
                <div class="flex items-center gap-1.5 mb-0.5 px-1 text-[10px] text-gray-400">
                    <span class="font-bold text-gray-700 dark:text-gray-300">Customer</span>
                    <span>•</span>
                    <span>{{ formatTime(msg.created_at) }}</span>
                </div>
                <div class="max-w-[85%] rounded-2xl rounded-tl-none p-3 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-900 dark:text-gray-100 text-xs shadow-xs leading-relaxed whitespace-pre-wrap">
                    {{ msg.content }}
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'SupportTimeline',
    props: {
        messages: {
            type: Array,
            default: () => [],
        },
    },
    watch: {
        messages: {
            deep: true,
            handler() {
                this.scrollToBottom();
            },
        },
    },
    mounted() {
        this.scrollToBottom();
    },
    methods: {
        scrollToBottom() {
            this.$nextTick(() => {
                const el = this.$refs.timelineContainer;
                if (el) {
                    el.scrollTop = el.scrollHeight;
                }
            });
        },
        formatTime(iso) {
            if (!iso) return '';
            try {
                const d = new Date(iso);
                return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            } catch (e) {
                return '';
            }
        },
    },
};
</script>
