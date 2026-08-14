<template>
    <LoadingComponent :props="loading" />
    <section v-if="benefits.length > 0" class="py-10 sm:py-16 bg-gray-50/70 dark:bg-slate-900/50 border-y border-slate-200/50 dark:border-slate-800 mb-10 sm:mb-20">
        <div class="container">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 sm:gap-6">
                <div v-for="benefit in benefits" :key="benefit.id" class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-gray-100 dark:border-slate-800 shadow-xs flex flex-col items-start gap-3 hover:shadow-md transition-all">
                    <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-500 flex items-center justify-center p-2">
                        <img :src="benefit.thumb" alt="benefit" class="w-full h-full object-contain">
                    </div>
                    <div>
                        <h4 class="text-sm sm:text-base font-bold capitalize text-gray-900 dark:text-white mb-1">{{ benefit.title }}</h4>
                        <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">{{ benefit.description }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<script>
import statusEnum from "../../../enums/modules/statusEnum";
import LoadingComponent from "../components/LoadingComponent";

export default {
    name: "BenefitComponent",
    components: {
        LoadingComponent
    },
    data() {
        return {
            loading: {
                isActive: false,
            }
        }
    },
    computed: {
        benefits: function () {
            return this.$store.getters["frontendBenefit/lists"];
        },
    },
    mounted() {
        this.loading.isActive = true;
        this.$store.dispatch("frontendBenefit/lists", {
            paginate: 0,
            order_column: "id",
            order_type: "asc",
            status: statusEnum.ACTIVE,
        }).then(res => {
            this.loading.isActive = false;
        }).catch((err) => {
            this.loading.isActive = false;
        });
    }
}
</script>
