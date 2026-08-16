<template>
    <div class="support-order-card bg-white border border-slate-200 rounded-xl p-4 my-2 shadow-sm max-w-sm">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div>
                <span class="text-[11px] font-medium text-slate-400 block uppercase">Order Reference</span>
                <span class="text-sm font-bold text-slate-900">#{{ order.order_serial_no || order.id }}</span>
            </div>
            <span 
                class="px-2.5 py-1 text-xs font-semibold rounded-full capitalize"
                :class="statusBadgeClass(order.status)"
            >
                {{ formatStatus(order.status) }}
            </span>
        </div>

        <div class="py-3 text-xs space-y-2 text-slate-600">
            <div class="flex justify-between" v-if="order.total_amount || order.total">
                <span class="text-slate-400">Total Amount:</span>
                <span class="font-semibold text-slate-800">
                    {{ order.total_currency_price || ('₦' + Number(order.total_amount || order.total).toLocaleString()) }}
                </span>
            </div>
            <div class="flex justify-between" v-if="order.order_date || order.created_at">
                <span class="text-slate-400">Date Placed:</span>
                <span>{{ formatDate(order.order_date || order.created_at) }}</span>
            </div>
            <div class="flex justify-between" v-if="order.delivery_date">
                <span class="text-slate-400">Est. Delivery:</span>
                <span class="font-medium text-emerald-600">{{ order.delivery_date }}</span>
            </div>
        </div>

        <div v-if="order.order_products && order.order_products.length" class="pt-2 border-t border-slate-100">
            <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block mb-1.5">
                Items ({{ order.order_products.length }})
            </span>
            <ul class="space-y-1 text-xs text-slate-700">
                <li v-for="p in order.order_products" :key="p.id" class="truncate flex items-center justify-between">
                    <span class="truncate">{{ p.product_name || p.name }}</span>
                    <span class="text-slate-400 ml-2 font-mono">x{{ p.quantity }}</span>
                </li>
            </ul>
        </div>
    </div>
</template>

<script>
export default {
    name: 'OrderStatusCard',
    props: {
        order: {
            type: Object,
            required: true,
        },
    },
    methods: {
        formatStatus(status) {
            if (!status) return 'Processing';
            return String(status).replace(/_/g, ' ');
        },
        statusBadgeClass(status) {
            const s = String(status).toLowerCase();
            if (s.includes('delivered') || s.includes('complete')) {
                return 'bg-emerald-50 text-emerald-700 border border-emerald-200';
            }
            if (s.includes('ship') || s.includes('dispatch') || s.includes('transit')) {
                return 'bg-blue-50 text-blue-700 border border-blue-200';
            }
            if (s.includes('cancel') || s.includes('reject')) {
                return 'bg-rose-50 text-rose-700 border border-rose-200';
            }
            return 'bg-amber-50 text-amber-700 border border-amber-200';
        },
        formatDate(dateStr) {
            if (!dateStr) return '';
            try {
                return new Date(dateStr).toLocaleDateString('en-GB', {
                    day: 'numeric',
                    month: 'short',
                    year: 'numeric',
                });
            } catch (e) {
                return dateStr;
            }
        },
    },
};
</script>
