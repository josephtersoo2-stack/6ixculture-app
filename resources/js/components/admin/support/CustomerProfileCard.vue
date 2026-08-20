<template>
    <div class="customer-profile-card p-4 border-b border-[#1F293D] bg-[#0E1424]">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-full bg-indigo-600 text-white font-bold text-xs flex items-center justify-center flex-shrink-0 shadow-xs">
                {{ initials }}
            </div>
            <div class="min-w-0">
                <h3 class="text-xs font-bold text-white flex items-center gap-1.5 truncate">
                    {{ customer360?.name || 'Customer' }}
                    <span v-if="customer360?.is_guest" class="px-1.5 py-0.2 text-[8px] font-extrabold bg-[#1E293B] text-amber-400 rounded uppercase">
                        Guest
                    </span>
                </h3>
                <div class="text-[11px] text-slate-400 mt-0.5 truncate">{{ customer360?.email || 'No email registered' }}</div>
                <div class="text-[11px] text-slate-400 truncate">{{ customer360?.phone || 'No phone registered' }}</div>
            </div>
        </div>

        <!-- Metrics Grid -->
        <div class="grid grid-cols-2 gap-2 mt-3 p-2.5 bg-[#131B2E] rounded-xl border border-[#1F293D]">
            <div>
                <span class="text-[10px] uppercase font-bold text-slate-500 block tracking-wider">Total Orders</span>
                <span class="text-xs font-bold text-white">{{ customer360?.total_orders || 0 }}</span>
            </div>
            <div>
                <span class="text-[10px] uppercase font-bold text-slate-500 block tracking-wider">Lifetime Value</span>
                <span class="text-xs font-bold text-emerald-400">{{ customer360?.total_spend || '₦0.00' }}</span>
            </div>
            <div v-if="customer360?.member_since" class="col-span-2 pt-1 border-t border-[#1F293D] text-[10px] text-slate-400">
                Member Since: {{ customer360.member_since }}
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
