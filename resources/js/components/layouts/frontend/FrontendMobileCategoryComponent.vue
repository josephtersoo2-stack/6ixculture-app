<template>
    <aside id="mobile-category-canvas"
        @click.self="hideTarget('mobile-category-canvas', 'canvas-active')"
        class="fixed inset-0 z-30 bg-black/50 duration-500 transition-all invisible opacity-0">
        <div
            class="w-full max-w-xs h-dvh overflow-x-hidden overflow-y-auto bg-white dark:bg-slate-900 duration-500 transition-all ltr:-translate-x-full rtl:translate-x-full">
            <div class="py-4 flex items-center justify-between px-4 border-b border-slate-100 dark:border-slate-800">
                <router-link :to="{ name: 'frontend.home' }"
                    class="router-link-active router-link-exact-active flex-shrink-0">
                    <img class="h-10 sm:h-12 max-h-12 object-contain rounded-xl p-1 bg-white border border-gray-200/50 dark:border-white/20 shadow-xs transition-all" :src="setting.theme_logo" alt="logo">
                </router-link>

                <button type="button" @click.prevent="hideTarget('mobile-category-canvas', 'canvas-active')" class="w-9 h-9 rounded-full flex items-center justify-center bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-200 hover:bg-danger/20 hover:text-danger hover:rotate-90 transition-all duration-300 shadow-sm border border-slate-200 dark:border-slate-700">
                    <i class="lab-line-circle-cross text-xl"></i>
                </button>
            </div>

            <ul v-if="categories.length > 0" class="px-4">
                <li v-for="category in categories" class="border-b border-slate-100">
                    <div class="flex items-center justify-between py-3">
                        <router-link v-on:click="hideTarget('mobile-category-canvas', 'canvas-active')"
                            :to="{ name: 'frontend.product', query: { category: category.slug } }"
                            class="text-lg font-semibold capitalize">{{ category.name }}</router-link>
                        <button v-if="category.children.length > 0"
                            @click.prevent="showTarget('mobile_category_' + category.slug, '!translate-x-0')"
                            type="button">
                            <i
                                class="lab-line-chevron-right w-8 h-8 !leading-8 text-center rounded bg-primary/10 text-primary"></i>
                        </button>
                    </div>
                    <MobileMenuChildrenComponent :key="category" v-if="category.children" :parentCategory="category"
                        :categories="category.children" />
                </li>
            </ul>
        </div>
    </aside>
</template>
<script>
import targetService from "../../../services/targetService";
import MobileMenuChildrenComponent from "../../frontend/components/MobileMenuChildrenComponent.vue";

export default {
    name: "FrontendMobileCategoryComponent",
    components: { MobileMenuChildrenComponent },
    computed: {
        setting: function () {
            return this.$store.getters['frontendSetting/lists'];
        },
        categories: function () {
            return this.$store.getters['frontendProductCategory/trees'];
        },
    },
    methods: {
        showTarget: function (id, cClass) {
            targetService.showTarget(id, cClass);
        },
        hideTarget: function (id, cClass) {
            targetService.hideTarget(id, cClass);
        }
    }
}
</script>