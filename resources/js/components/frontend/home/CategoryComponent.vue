<template>
    <LoadingComponent :props="loading" />
    <section v-if="categories.length > 0" class="mb-10 sm:mb-16">
        <div class="container">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                    Browse Categories
                </h2>
                <router-link :to="{ name: 'frontend.product' }" class="text-xs sm:text-sm font-semibold text-primary hover:underline">
                    View All Categories &rarr;
                </router-link>
            </div>
            <Swiper dir="ltr" :speed="1000" :loop="false" :navigation="true" :modules="modules" class="navigate-swiper" :breakpoints="breakpoints">
                <SwiperSlide v-for="category in categories" :key="category.id" class="!w-24 sm:!w-28">
                    <router-link :to="{name: 'frontend.product', query:{ category: category.slug}}"
                                 class="flex flex-col items-center gap-2 group">
                        <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-full p-[2px] bg-gradient-to-tr from-amber-500 via-orange-500 to-amber-400 shadow-md group-hover:scale-105 transition-transform duration-300 overflow-hidden">
                            <img class="w-full h-full object-cover rounded-full bg-white" :src="category.thumb" :alt="category.name" >
                        </div>
                        <span class="text-xs sm:text-sm font-semibold capitalize text-center text-gray-800 dark:text-gray-200 group-hover:text-primary transition-colors max-w-[96px] truncate">
                            {{ category.name }}
                        </span>
                    </router-link>
                </SwiperSlide>
            </Swiper>
        </div>
    </section>
</template>

<script>
import {Navigation, Pagination, Autoplay} from 'swiper/modules';
import {Swiper, SwiperSlide} from 'swiper/vue';
import 'swiper/css';
import 'swiper/css/navigation';
import 'swiper/css/pagination';
import statusEnum from "../../../enums/modules/statusEnum";
import LoadingComponent from "../components/LoadingComponent";

export default {
    name: "CategoryComponent",
    components: {
        Swiper,
        SwiperSlide,
        LoadingComponent
    },
    setup() {
        return {
            modules: [Navigation, Pagination, Autoplay],
        }
    },
    data() {
        return {
            loading: {
                isActive: false,
            },
            breakpoints: {
                0: {slidesPerView: 'auto', spaceBetween: 16},
                640: {slidesPerView: 5, spaceBetween: 20},
                768: {slidesPerView: 6, spaceBetween: 24},
                1024: {slidesPerView: 8, spaceBetween: 24}
            },
        }
    },
    computed: {
        categories: function () {
            return this.$store.getters["frontendProductCategory/lists"];
        },
    },
    mounted() {
        this.loading.isActive = true;
        this.$store.dispatch("frontendProductCategory/lists", {
            paginate: 0,
            order_column: "id",
            order_type: "asc",
            parent_id: null,
            status: statusEnum.ACTIVE,
        }).then(res => {
            this.loading.isActive = false;
        }).catch((err) => {
            this.loading.isActive = false;
        });
    },
}
</script>
