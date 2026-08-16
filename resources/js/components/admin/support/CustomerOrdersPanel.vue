<template>
    <div class="customer-orders-panel p-4 border-b border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
        <div class="flex items-center justify-between mb-3">
            <h4 class="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider flex items-center gap-1.5">
                <i class="lab lab-bag text-slate-500"></i>
                <span>Recent Orders</span>
            </h4>
            <span v-if="orders.length" class="text-[10px] font-bold text-gray-400">
                {{ orders.length }} Recent
            </span>
        </div>

        <div v-if="orders.length === 0" class="p-4 text-center text-xs text-gray-400 bg-gray-50 dark:bg-gray-800/40 rounded-xl">
            No order history found for this customer.
        </div>

        <div v-else class="space-y-2.5 max-h-60 overflow-y-auto thin-scrolling">
            <div
                v-for="order in orders"
                :key="order.id"
                class="p-2.5 bg-gray-50 dark:bg-gray-800/60 border border-gray-100 dark:border-gray-800 rounded-xl text-xs space-y-1.5 shadow-2xs"
            >
                <div class="flex items-center justify-between">
                    <span class="font-extrabold text-gray-900 dark:text-white">#{{ order.order_serial_no }}</span>
                    <span class="px-1.5 py-0.5 text-[9px] font-bold rounded capitalize" :class="statusBadgeClass(order.status)">
                        {{ order.status }}
                    </span>
                </div>

                <div class="flex items-center justify-between text-[11px] text-gray-500">
                    <span>{{ formatDate(order.created_at) }}</span>
                    <span class="font-bold text-gray-900 dark:text-white">{{ order.total }}</span>
                </div>

                <!-- Order items breakdown -->
                <div v-if="order.items && order.items.length" class="pt-1.5 border-t border-gray-200/50 dark:border-gray-700/50 space-y-0.5">
                    <div
                        v-for="(item, idx) in order.items"
                        :key="idx"
                        class="flex items-center justify-between text-[10px] text-gray-600 dark:text-gray-400"
                    >
                        <span class="truncate max-w-[150px]">{{ item.quantity }}x {{ item.product_name }}</span>
                        <span>{{ item.price }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'CustomerOrdersPanel',
    props: {
        orders: {
            type: Array,
            default: () => [],
        },
    },
    methods: {
        formatDate(iso) {
            if (!iso) return '';
            try {
                const d = new Date(iso);
                return d.toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' });
            } catch (e) {
                return '';
            }
        },
        statusBadgeClass(status) {
            switch (String(status).toLowerCase()) {
                case 'delivered':
                    return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300';
                case 'processing':
                case 'confirmed':
                    return 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300';
                case 'canceled':
                case 'cancelled':
                    return 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300';
                default:
                    return 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300';
            }
        },
    },
};
</script>
