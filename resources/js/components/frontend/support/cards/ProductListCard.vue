<template>
    <div class="support-product-list-card my-2">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">
                Matching Products ({{ products.length }})
            </span>
        </div>
        <div class="flex gap-2.5 overflow-x-auto pb-2 thin-scrolling">
            <ProductCard 
                v-for="item in products" 
                :key="item.id || item.slug" 
                :product="item" 
                class="flex-shrink-0 w-60"
            />
        </div>
    </div>
</template>

<script>
import ProductCard from './ProductCard.vue';

export default {
    name: 'ProductListCard',
    components: {
        ProductCard,
    },
    props: {
        products: {
            type: Array,
            default: () => [],
        },
    },
};
</script>
