<template>
    <section v-if="setting.homepage_lookbook != 0" class="mb-10 sm:mb-20">
        <div class="container">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-xl sm:text-3xl font-extrabold text-gray-900 dark:text-white">
                        {{ setting.homepage_lookbook_title || 'Shop The Look' }}
                    </h2>
                    <p class="text-xs sm:text-sm text-gray-500 mt-1">Tag #6ixculture on Instagram to be featured.</p>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div v-for="(item, idx) in lookbookItems" :key="idx" class="relative group rounded-2xl overflow-hidden aspect-square bg-gray-100 shadow-sm">
                    <img :src="item.image" :alt="item.title" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex flex-col justify-end p-4">
                        <span class="text-white font-bold text-sm">{{ item.title }}</span>
                        <span class="text-amber-400 text-xs font-semibold mt-0.5">{{ item.price }}</span>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<script>
export default {
    name: "LookbookComponent",
    data() {
        return {
            lookbookItems: [
                { title: "Oversized Street Hoodie", price: "₦35,000", image: "/images/product/product-1.png" },
                { title: "Cargo Track Pants", price: "₦28,000", image: "/images/product/product-2.png" },
                { title: "Graphic Print Tee", price: "₦18,000", image: "/images/product/product-3.png" },
                { title: "Signature Bucket Hat", price: "₦12,000", image: "/images/product/product-4.png" }
            ]
        }
    },
    computed: {
        setting() {
            return this.$store.getters['frontendSetting/lists'];
        }
    }
}
</script>
