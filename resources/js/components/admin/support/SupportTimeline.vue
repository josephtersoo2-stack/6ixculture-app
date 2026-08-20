<template>
    <div
        ref="timelineContainer"
        class="support-timeline flex-1 overflow-y-auto p-4 space-y-4 bg-[#0B0F19] thin-scrolling"
    >
        <!-- Welcome / Empty Messages -->
        <div v-if="!messages || messages.length === 0" class="p-8 text-center text-xs text-slate-500">
            No messages in this conversation yet.
        </div>

        <div
            v-for="msg in messages"
            :key="msg.id"
            class="flex flex-col"
        >
            <!-- 1. SYSTEM EVENT (Centered Pill) -->
            <div v-if="msg.sender_type === 'system'" class="my-2 flex items-center justify-center">
                <span class="px-3 py-1 bg-[#131B2E] border border-[#1F293D] text-slate-400 text-[11px] font-medium rounded-full shadow-2xs">
                    <i class="lab lab-info-circle text-xs mr-1"></i> {{ msg.content }}
                </span>
            </div>

            <!-- 2. INTERNAL STAFF NOTE (Highlighted Amber Box) -->
            <div v-else-if="msg.is_internal" class="my-2 p-3.5 bg-amber-950/30 border border-amber-800/60 rounded-2xl shadow-xs">
                <div class="flex items-center justify-between gap-2 mb-1 text-[11px] text-amber-300">
                    <div class="flex items-center gap-1.5 font-bold uppercase tracking-wider">
                        <i class="lab lab-lock text-xs text-amber-400"></i>
                        <span>Internal Note</span>
                        <span v-if="msg.agent" class="text-amber-400 font-medium">• {{ msg.agent.name }}</span>
                    </div>
                    <span class="text-[10px] text-amber-400/70">{{ formatTime(msg.created_at) }}</span>
                </div>
                <div class="text-xs text-amber-100 whitespace-pre-wrap leading-relaxed">
                    {{ msg.content }}
                </div>
            </div>

            <!-- 3. AGENT REPLY (Right Aligned Dark Slate Bubble) -->
            <div v-else-if="msg.sender_type === 'agent'" class="flex flex-col items-end my-1">
                <div class="flex items-center gap-1.5 mb-1 px-1 text-[10px] text-slate-500">
                    <span class="font-bold text-slate-300">{{ msg.agent ? msg.agent.name : 'You' }}</span>
                    <span>•</span>
                    <span>{{ formatTime(msg.created_at) }}</span>
                </div>
                <div class="max-w-[80%] rounded-2xl rounded-tr-none p-3.5 bg-[#131B2E] border border-[#1F293D] text-slate-100 text-xs shadow-xs leading-relaxed whitespace-pre-wrap">
                    {{ msg.content }}
                </div>
            </div>

            <!-- 4. AI ASSISTANT TURN (Left Aligned Dark Bubble with CultureAI Badge) -->
            <div v-else-if="msg.sender_type === 'ai'" class="flex flex-col items-start my-1">
                <div class="flex items-center gap-1.5 mb-1 px-1 text-[10px] text-slate-500">
                    <span class="font-bold text-emerald-400 flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        CultureAI
                    </span>
                    <span>•</span>
                    <span>{{ formatTime(msg.created_at) }}</span>
                </div>

                <div class="max-w-[85%] rounded-2xl rounded-tl-none p-3.5 bg-[#161F36] border border-[#1F293D] text-slate-200 text-xs shadow-xs leading-relaxed">
                    <div class="whitespace-pre-wrap">{{ msg.content }}</div>

                    <!-- Structured Order Card Preview if attached -->
                    <div v-if="getOrderFromPayload(msg.structured_payload || msg.payload)" class="mt-3 p-3 bg-[#0E1424] border border-[#1F293D] rounded-xl text-xs space-y-2">
                        <div class="flex items-center justify-between pb-2 border-b border-[#1F293D]">
                            <span class="font-bold text-white">Order #{{ getOrderFromPayload(msg.structured_payload || msg.payload).order_serial_no || getOrderFromPayload(msg.structured_payload || msg.payload).id }}</span>
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-emerald-950/60 text-emerald-300 border border-emerald-800/40">
                                {{ getOrderFromPayload(msg.structured_payload || msg.payload).status || 'In Transit' }}
                            </span>
                        </div>
                        <div class="text-[11px] space-y-1 text-slate-300">
                            <div v-if="getOrderFromPayload(msg.structured_payload || msg.payload).courier" class="flex justify-between">
                                <span class="text-slate-500">Courier:</span>
                                <span>{{ getOrderFromPayload(msg.structured_payload || msg.payload).courier }}</span>
                            </div>
                            <div v-if="getOrderFromPayload(msg.structured_payload || msg.payload).tracking_code" class="flex justify-between">
                                <span class="text-slate-500">Tracking ID:</span>
                                <span class="font-mono text-indigo-400">{{ getOrderFromPayload(msg.structured_payload || msg.payload).tracking_code }}</span>
                            </div>
                            <div v-if="getOrderFromPayload(msg.structured_payload || msg.payload).estimated_delivery" class="flex justify-between">
                                <span class="text-slate-500">Estimated Delivery:</span>
                                <span class="text-emerald-400">{{ getOrderFromPayload(msg.structured_payload || msg.payload).estimated_delivery }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Structured Product Card Preview if attached -->
                    <div v-if="getProductFromPayload(msg.structured_payload || msg.payload)" class="mt-3 p-2.5 bg-[#0E1424] border border-[#1F293D] rounded-xl flex items-center gap-3">
                        <div class="w-12 h-12 rounded-lg bg-[#1E293B] flex items-center justify-center text-slate-400 flex-shrink-0">
                            <i class="lab lab-bag text-lg"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h5 class="font-bold text-white text-xs truncate">{{ getProductFromPayload(msg.structured_payload || msg.payload).name }}</h5>
                            <span class="text-xs font-bold text-indigo-400">{{ getProductFromPayload(msg.structured_payload || msg.payload).formatted_price || ('₦' + (getProductFromPayload(msg.structured_payload || msg.payload).price || 0).toLocaleString()) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 5. CUSTOMER MESSAGE (Right or Left Aligned Deep Purple Bubble) -->
            <div v-else class="flex flex-col items-end my-1">
                <div class="flex items-center gap-1.5 mb-1 px-1 text-[10px] text-slate-500">
                    <span class="font-bold text-slate-300">Customer</span>
                    <span>•</span>
                    <span>{{ formatTime(msg.created_at) }}</span>
                </div>
                <div class="max-w-[85%] rounded-2xl rounded-tr-none p-3.5 bg-indigo-600 text-white text-xs shadow-xs leading-relaxed whitespace-pre-wrap">
                    <div class="flex items-end justify-between gap-3">
                        <span>{{ msg.content }}</span>
                        <span class="text-[9px] text-indigo-200 flex-shrink-0 flex items-center gap-0.5">
                            {{ formatTime(msg.created_at) }}
                            <i class="lab lab-check-double text-[10px]"></i>
                        </span>
                    </div>
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
        getOrderFromPayload(payload) {
            if (!payload) return null;
            if (payload.order) return payload.order;
            if (payload.type === 'order_status' && payload.data) return payload.data;
            return null;
        },
        getProductFromPayload(payload) {
            if (!payload) return null;
            if (payload.product) return payload.product;
            if (payload.type === 'product_details' && payload.data) return payload.data;
            return null;
        },
    },
};
</script>
