<template>
    <div class="customer-orders-panel p-4 border-b border-[#1F293D] bg-[#0E1424]">
        <div class="flex items-center justify-between mb-3">
            <h4 class="text-xs font-bold text-white uppercase tracking-wider flex items-center gap-1.5">
                <i class="lab lab-bag text-indigo-400"></i>
                <span>Recent Orders</span>
            </h4>
            <span v-if="orders.length" class="text-[10px] font-bold text-slate-500">
                {{ orders.length }} Recent
            </span>
        </div>

        <div v-if="orders.length === 0" class="p-4 text-center text-xs text-slate-500 bg-[#131B2E] rounded-xl border border-[#1F293D]">
            No order history found for this customer.
        </div>

        <div v-else class="space-y-2 max-h-64 overflow-y-auto thin-scrolling">
            <div
                v-for="order in orders"
                :key="order.id"
                class="p-2.5 bg-[#131B2E] border border-[#1F293D] rounded-xl text-xs space-y-1 shadow-2xs"
            >
                <div class="flex items-center justify-between">
                    <span class="font-extrabold text-white">#{{ order.order_serial_no || order.id }}</span>
                    <span class="px-1.5 py-0.5 text-[9px] font-bold rounded capitalize" :class="statusBadgeClass(order.status)">
                        {{ order.status }}
                    </span>
                </div>

                <div class="flex items-center justify-between text-[11px] text-slate-400">
                    <span>{{ formatDate(order.created_at || order.order_datetime) }}</span>
                    <span class="font-bold text-white">{{ order.total_currency_price || order.total || '₦0.00' }}</span>
                </div>

                <!-- Order items breakdown -->
                <div v-if="order.order_products && order.order_products.length" class="pt-1.5 border-t border-[#1F293D]/60 space-y-0.5">
                    <div
                        v-for="(item, idx) in order.order_products"
                        :key="idx"
                        class="flex items-center justify-between text-[10px] text-slate-400"
                    >
                        <span class="truncate max-w-[150px]">{{ item.quantity }}x {{ item.product_name }}</span>
                        <span>{{ item.price }}</span>
                    </div>
                </div>
            </div>

            <!-- View all orders link -->
            <div class="pt-1 text-center">
                <router-link
                    to="/admin/online-orders"
                    class="text-[11px] font-semibold text-indigo-400 hover:text-indigo-300 transition-colors"
                >
                    View all orders →
                </router-link>
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
                return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            } catch (e) {
                return '';
            }
        },
        statusBadgeClass(status) {
            switch (String(status).toLowerCase()) {
                case 'delivered':
                    return 'bg-emerald-950/60 text-emerald-300 border border-emerald-800/40';
                case 'in_transit':
                case 'in transit':
                case 'processing':
                case 'confirmed':
                    return 'bg-indigo-950/60 text-indigo-300 border border-indigo-800/40';
                case 'canceled':
                case 'cancelled':
                    return 'bg-rose-950/60 text-rose-300 border border-rose-800/40';
                default:
                    return 'bg-amber-950/60 text-amber-300 border border-amber-800/40';
            }
        },
    },
};
</script>
