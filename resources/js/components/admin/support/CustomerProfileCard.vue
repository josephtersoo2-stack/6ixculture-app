<template>
    <div class="customer-profile-card p-4 border-b border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-11 h-11 rounded-2xl bg-gray-900 dark:bg-white text-white dark:text-gray-900 font-extrabold text-sm flex items-center justify-center flex-shrink-0 shadow-xs">
                {{ initials }}
            </div>
            <div>
                <h3 class="text-xs font-bold text-gray-900 dark:text-white flex items-center gap-1.5">
                    {{ customer360?.name || 'Customer' }}
                    <span v-if="customer360?.is_guest" class="px-1.5 py-0.2 text-[9px] font-bold bg-amber-100 dark:bg-amber-900/40 text-amber-900 dark:text-amber-300 rounded uppercase">
                        Guest
                    </span>
                </h3>
                <div class="text-[11px] text-gray-500 mt-0.5">{{ customer360?.email || 'No email provided' }}</div>
                <div class="text-[11px] text-gray-500">{{ customer360?.phone || 'No phone provided' }}</div>
            </div>
        </div>

        <!-- Metrics Grid -->
        <div class="grid grid-cols-2 gap-2 mt-3 p-2.5 bg-gray-50 dark:bg-gray-800/60 rounded-xl border border-gray-100 dark:border-gray-800">
            <div>
                <span class="text-[10px] uppercase font-bold text-gray-400 block tracking-wider">Total Orders</span>
                <span class="text-xs font-bold text-gray-900 dark:text-white">{{ customer360?.total_orders || 0 }}</span>
            </div>
            <div>
                <span class="text-[10px] uppercase font-bold text-gray-400 block tracking-wider">Total Spend</span>
                <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400">{{ customer360?.total_spend || '₦0.00' }}</span>
            </div>
            <div v-if="customer360?.member_since" class="col-span-2 pt-1 border-t border-gray-200/50 dark:border-gray-700/50 text-[10px] text-gray-400">
                Customer since {{ customer360.member_since }}
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'CustomerProfileCard',
    props: {
        customer360: {
            type: Object,
            default: null,
        },
    },
    computed: {
        initials() {
            const name = this.customer360?.name || 'Customer';
            const parts = name.trim().split(' ');
            if (parts.length >= 2) {
                return (parts[0][0] + parts[1][0]).toUpperCase();
            }
            return name.substring(0, 2).toUpperCase();
        },
    },
};
</script>
