<template>
    <div class="support-escalation-card bg-emerald-50 border border-emerald-200 rounded-xl p-3.5 my-2 max-w-sm">
        <div class="flex items-center gap-3">
            <div class="p-2 bg-emerald-100 text-emerald-800 rounded-lg flex-shrink-0">
                <i class="lab lab-user-check text-lg"></i>
            </div>
            <div>
                <h4 class="text-xs font-bold text-emerald-950 uppercase tracking-wider">
                    Human Agent Connected
                </h4>
                <p class="text-xs text-emerald-800 mt-0.5 leading-relaxed">
                    {{ content || "Your inquiry has been placed in our priority support queue. A representative will join this chat momentarily." }}
                </p>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'EscalationCard',
    props: {
        content: {
            type: String,
            default: '',
        },
        payload: {
            type: Object,
            default: () => ({}),
        },
    },
};
</script>
