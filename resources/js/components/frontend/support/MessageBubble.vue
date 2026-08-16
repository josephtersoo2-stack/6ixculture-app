<template>
    <div 
        class="flex flex-col my-3"
        :class="isCustomer ? 'items-end' : 'items-start'"
    >
        <!-- Sender info -->
        <div class="flex items-center gap-1.5 mb-1 px-1 text-[11px] text-slate-400">
            <span class="font-medium capitalize" :class="isCustomer ? 'text-slate-600' : 'text-slate-500'">
                {{ isCustomer ? 'You' : (isSystem ? 'System' : 'CultureAI') }}
            </span>
            <span>•</span>
            <span>{{ formatTime(message.created_at) }}</span>
        </div>

        <!-- Bubble Container -->
        <div 
            class="max-w-[85%] rounded-2xl p-3.5 shadow-sm transition-all"
            :class="bubbleClasses"
        >
            <MessageRenderer :message="message" />
        </div>
    </div>
</template>

<script>
import MessageRenderer from './MessageRenderer.vue';

export default {
    name: 'MessageBubble',
    components: {
        MessageRenderer,
    },
    props: {
        message: {
            type: Object,
            required: true,
        },
    },
    computed: {
        isCustomer() {
            return this.message.sender === 'customer';
        },
        isSystem() {
            return this.message.sender === 'system';
        },
        bubbleClasses() {
            if (this.isCustomer) {
                return 'bg-slate-900 text-white rounded-tr-none';
            }
            if (this.isSystem) {
                return 'bg-slate-100 text-slate-800 border border-slate-200';
            }
            return 'bg-white text-slate-900 border border-slate-200/80 rounded-tl-none';
        },
    },
    methods: {
        formatTime(isoStr) {
            if (!isoStr) return '';
            try {
                const d = new Date(isoStr);
                return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            } catch (e) {
                return '';
            }
        },
    },
};
</script>
