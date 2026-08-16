<template>
    <div class="support-action-confirmation bg-amber-50/80 border border-amber-200 rounded-xl p-4 my-2 max-w-sm">
        <div class="flex items-start gap-3">
            <div class="p-2 bg-amber-100 text-amber-800 rounded-lg flex-shrink-0">
                <i class="lab lab-alert-triangle text-lg"></i>
            </div>
            <div>
                <h4 class="text-xs font-bold text-amber-900 uppercase tracking-wider">
                    Action Confirmation Required
                </h4>
                <p class="text-xs text-amber-800 mt-1 leading-relaxed">
                    {{ payload.message || 'Please confirm whether you would like to proceed with this request.' }}
                </p>
                <div v-if="payload.arguments" class="mt-2 text-[11px] text-amber-900/80 bg-amber-100/50 p-2 rounded">
                    <div v-for="(val, key) in payload.arguments" :key="key">
                        <span class="font-semibold capitalize">{{ formatKey(key) }}:</span> {{ val }}
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-3.5 flex items-center justify-end gap-2 border-t border-amber-200/60 pt-3">
            <button 
                type="button" 
                @click="handleCancel" 
                :disabled="isProcessing"
                class="px-3 py-1.5 text-xs font-medium text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors disabled:opacity-50"
            >
                Cancel
            </button>
            <button 
                type="button" 
                @click="handleConfirm" 
                :disabled="isProcessing"
                class="px-3.5 py-1.5 text-xs font-semibold text-white bg-slate-900 rounded-lg hover:bg-slate-800 transition-colors shadow-sm disabled:opacity-50 flex items-center gap-1.5"
            >
                <span v-if="isProcessing" class="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                <span>Confirm & Proceed</span>
            </button>
        </div>
    </div>
</template>

<script>
export default {
    name: 'ActionConfirmationCard',
    props: {
        payload: {
            type: Object,
            required: true,
        },
    },
    data() {
        return {
            isProcessing: false,
        };
    },
    methods: {
        formatKey(k) {
            return String(k).replace(/_/g, ' ');
        },
        handleConfirm() {
            this.isProcessing = true;
            this.$store.dispatch('frontendSupport/executeAction', {
                toolName: this.payload.tool_name,
                args: this.payload.arguments,
                confirmed: true,
            }).finally(() => {
                this.isProcessing = false;
            });
        },
        handleCancel() {
            this.isProcessing = true;
            this.$store.dispatch('frontendSupport/executeAction', {
                toolName: this.payload.tool_name,
                args: this.payload.arguments,
                confirmed: false,
            }).finally(() => {
                this.isProcessing = false;
            });
        },
    },
};
</script>
