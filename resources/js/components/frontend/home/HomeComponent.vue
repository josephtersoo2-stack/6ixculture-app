<template>
    <SliderComponent v-if="setting.homepage_hero_slider != 0" />

    <CategoryComponent v-if="setting.homepage_category_bubbles != 0" />

    <PromotionComponent v-if="setting.homepage_promo_banners != 0" />

    <ProductSectionComponent v-if="setting.homepage_trending_drops != 0" />

    <MostPopularComponent v-if="setting.homepage_best_sellers != 0" />

    <FlashSaleComponent v-if="setting.homepage_flash_sales != 0" />

    <LookbookComponent v-if="setting.homepage_lookbook != 0" />

    <BenefitComponent v-if="setting.homepage_trust_badges != 0" />

    <ProductBrandComponent />

    <NewsletterComponent v-if="setting.homepage_newsletter != 0" />
</template>

<script>
import SliderComponent from "./SliderComponent.vue";
import CategoryComponent from "./CategoryComponent.vue";
import PromotionComponent from "./PromotionComponent.vue";
import BenefitComponent from "./BenefitComponent.vue";
import ProductSectionComponent from "./ProductSectionComponent.vue";
import FlashSaleComponent from "./FlashSaleComponent.vue";
import ProductBrandComponent from "./ProductBrandComponent.vue";
import MostPopularComponent from "./MostPopularComponent.vue";
import LookbookComponent from "./LookbookComponent.vue";
import NewsletterComponent from "./NewsletterComponent.vue";

export default {
    name: "HomeComponent",
    components: {
        MostPopularComponent,
        FlashSaleComponent,
        BenefitComponent,
        SliderComponent,
        CategoryComponent,
        PromotionComponent,
        ProductSectionComponent,
        ProductBrandComponent,
        LookbookComponent,
        NewsletterComponent
    },
    computed: {
        setting: function () {
            return this.$store.getters['frontendSetting/lists'];
        }
    }
};
</script>
