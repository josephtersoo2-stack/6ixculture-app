<template>
    <div class="customer-tickets-panel p-4 border-b border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
        <div class="flex items-center justify-between mb-3">
            <h4 class="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider flex items-center gap-1.5">
                <i class="lab lab-ticket text-slate-500"></i>
                <span>Linked Ticket</span>
            </h4>
        </div>

        <div v-if="!ticket" class="p-4 text-center text-xs text-gray-400 bg-gray-50 dark:bg-gray-800/40 rounded-xl">
            No formal ticket linked to this conversation.
        </div>

        <div v-else class="p-3 bg-gray-50 dark:bg-gray-800/60 border border-gray-100 dark:border-gray-800 rounded-xl text-xs space-y-1.5 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="font-extrabold text-gray-900 dark:text-white">#{{ ticket.ticket_number }}</span>
                <span class="px-1.5 py-0.5 text-[9px] font-bold rounded capitalize bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">
                    {{ ticket.status }}
                </span>
            </div>
            <div class="text-xs font-semibold text-gray-800 dark:text-gray-200">
                {{ ticket.subject }}
            </div>
            <p v-if="ticket.description" class="text-[11px] text-gray-500 line-clamp-2">
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
