<template>
    <LoadingComponent :props="loading" />
    <section class="mb-6 sm:mb-12 w-full">
        <div class="w-full max-w-full px-0 sm:px-4 mx-auto">
            <Swiper
                v-if="sliders.length > 0"
                dir="rtl"
                :slides-per-view="1"
                :speed="1000"
                :loop="true"
                :navigation="true"
                :pagination="{ clickable: true }"
                :autoplay="{ delay: 3500 }"
                :modules="modules"
                class="banner-swiper aspect-[16/9] w-full rounded-none sm:rounded-2xl overflow-hidden shadow-sm"
            >
                <SwiperSlide v-for="slider in sliders" :key="slider.id" class="w-full h-full">
                    <div v-if="slider.link" class="w-full h-full aspect-[16/9]">
                        <a :href="slider.link" class="block w-full h-full">
                            <img class="w-full h-full object-cover aspect-[16/9] rounded-none sm:rounded-2xl" :src="slider.image" alt="banner" >
                        </a>
                    </div>
                    <div v-else class="w-full h-full aspect-[16/9]">
                        <img class="w-full h-full object-cover aspect-[16/9] rounded-none sm:rounded-2xl" :src="slider.image" alt="banner" >
                    </div>
                </SwiperSlide>
            </Swiper>
        </div>
    </section>
</template>

<script>
import 'swiper/css';
import {Navigation, Pagination, Autoplay} from 'swiper/modules';
import {Swiper, SwiperSlide} from 'swiper/vue';
import statusEnum from "../../../enums/modules/statusEnum";
import LoadingComponent from "../components/LoadingComponent";

export default {
    name: "SliderComponent",
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
                isActive: false
            },
            sliderProps: {
                search: {
                    paginate: 0,
                    order_column: 'id',
                    order_type: 'desc',
                    status: statusEnum.ACTIVE
                }
            }
        }
    },
    computed: {
        sliders: function () {
            return this.$store.getters['frontendSlider/lists'];
        }
    },
    mounted() {
        this.loading.isActive = true;
        this.$store.dispatch("frontendSlider/lists", this.sliderProps.search).then((res) => {
            this.loading.isActive = false;
        }).catch((err) => {
            this.loading.isActive = false;
        });
    }
}
</script>
