<template>
    <div class="customer-tickets-panel p-4 border-b border-[#1F293D] bg-[#0E1424]">
        <div class="flex items-center justify-between mb-3">
            <h4 class="text-xs font-bold text-white uppercase tracking-wider flex items-center gap-1.5">
                <i class="lab lab-ticket text-indigo-400"></i>
                <span>Linked Ticket</span>
            </h4>
        </div>

        <div v-if="!ticket" class="p-3 text-center text-xs text-slate-500 bg-[#131B2E] rounded-xl border border-[#1F293D]">
            No formal ticket linked to this conversation.
        </div>

        <div v-else class="p-3 bg-[#131B2E] border border-[#1F293D] rounded-xl text-xs space-y-1.5 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="font-extrabold text-white">#{{ ticket.ticket_number }}</span>
                <span class="px-1.5 py-0.5 text-[9px] font-bold rounded capitalize bg-indigo-950/60 text-indigo-300 border border-indigo-800/40">
                    {{ ticket.status }}
                </span>
            </div>
            <div class="text-xs font-semibold text-slate-200">
                {{ ticket.subject }}
            </div>
            <p v-if="ticket.description" class="text-[11px] text-slate-400 line-clamp-2">
                {{ ticket.description }}
            </p>
        </div>
    </div>
</template>

<script>
export default {
    name: 'CustomerTicketsPanel',
    props: {
        ticket: {
            type: Object,
            default: null,
        },
    },
};
</script>
