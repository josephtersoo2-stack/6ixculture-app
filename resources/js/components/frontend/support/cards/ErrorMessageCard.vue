<template>
    <div class="support-error-card bg-rose-50 border border-rose-200 rounded-xl p-3 my-2 text-xs text-rose-800 flex items-start gap-2.5 max-w-sm">
        <i class="lab lab-alert-circle text-base text-rose-600 flex-shrink-0 mt-0.5"></i>
        <div class="flex-1">
            <span class="font-semibold block text-rose-900">Notice</span>
            <p class="mt-0.5 leading-relaxed">{{ content }}</p>
        </div>
    </div>
</template>

<script>
export default {
    name: 'ErrorMessageCard',
    props: {
        content: {
            type: String,
            required: true,
        },
    },
};
</script>
