<template>
    <div class="support-product-card bg-white border border-slate-200 rounded-xl p-3 my-2 shadow-sm hover:shadow-md transition-shadow max-w-xs">
        <div class="flex items-center gap-3">
            <img 
                v-if="product.cover || product.image" 
                :src="product.cover || product.image" 
                :alt="product.name" 
                class="w-14 h-14 object-cover rounded-lg bg-slate-100 flex-shrink-0"
            />
            <div v-else class="w-14 h-14 rounded-lg bg-slate-100 flex items-center justify-center text-slate-400 flex-shrink-0">
                <i class="lab lab-bag text-xl"></i>
            </div>
            <div class="flex-1 min-w-0">
                <h4 class="text-sm font-semibold text-slate-900 truncate" :title="product.name">
                    {{ product.name }}
                </h4>
                <div class="flex items-baseline gap-2 mt-0.5">
                    <span class="text-sm font-bold text-slate-950">
                        {{ product.formatted_price || ('₦' + (product.price || 0).toLocaleString()) }}
                    </span>
                    <span v-if="product.compare_at_price" class="text-xs text-slate-400 line-through">
                        {{ '₦' + product.compare_at_price.toLocaleString() }}
                    </span>
                </div>
            </div>
        </div>
        <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-2.5">
            <router-link 
                v-if="product.slug" 
                :to="{ name: 'frontend.product.details', params: { slug: product.slug } }"
                class="text-xs font-semibold text-[#1ABC9C] hover:underline flex items-center gap-1"
            >
                View Details <i class="lab lab-arrow-right text-[10px]"></i>
            </router-link>
            <span v-if="product.sku" class="text-[10px] text-slate-400 uppercase">
                SKU: {{ product.sku }}
            </span>
        </div>
    </div>
</template>

<script>
export default {
    name: 'ProductCard',
    props: {
        product: {
            type: Object,
            required: true,
        },
    },
};
</script>
