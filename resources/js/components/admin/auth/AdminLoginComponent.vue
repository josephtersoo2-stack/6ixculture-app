<template>
    <LoadingComponent :props="loading" />
    <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8 bg-white p-8 rounded-2xl shadow-card">
            <div class="text-center">
                <router-link :to="{ name: 'admin.dashboard' }">
                    <img class="mx-auto h-14 w-auto object-contain mb-4 rounded-xl p-1 bg-white border border-gray-200/50 dark:border-white/20 shadow-xs transition-all" :src="setting.theme_logo" alt="Logo" />
                </router-link>
                <h2 class="text-2xl font-bold text-primary mb-2">
                    {{ $t('label.sign_in') }}
                </h2>
                <p class="text-sm font-medium text-text">
                    Sign in to access admin panel
                </p>
                <div v-if="errors.validation"
                    class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 mt-4 rounded relative" role="alert">
                    <span class="block sm:inline">{{ errors.validation }}</span>
                    <span class="absolute top-0 bottom-0 right-0 px-4 py-3 cursor-pointer" @click="close">
                        <i class="lab lab-close-circle-line margin-top-5-px"></i>
                    </span>
                </div>
            </div>
            <form class="mt-8 space-y-6" @submit.prevent="login">
                <div class="space-y-4">
                    <div>
                        <label for="adminEmail" class="text-sm font-medium capitalize mb-1 field-title required">
                            {{ $t('label.email') }}
                        </label>
                        <input v-model="form.email" :class="errors.email ? 'invalid' : ''" id="adminEmail"
                            type="text"
                            class="w-full h-12 px-4 rounded-lg text-base border border-[#D9DBE9] hover:border-primary/30 focus-within:border-primary/30 transition-all duration-500" />
                        <small class="db-field-alert" v-if="errors.email">{{ errors.email[0] }}</small>
                    </div>
                    <div>
                        <label for="adminPassword" class="text-sm font-medium capitalize mb-1 field-title required">
                            {{ $t('label.password') }}
                        </label>
                        <input v-model="form.password" :class="errors.password ? 'invalid' : ''" id="adminPassword"
                            type="password"
                            class="w-full h-12 px-4 rounded-lg text-base border border-[#D9DBE9] hover:border-primary/30 focus-within:border-primary/30 transition-all duration-500" />
                        <small class="db-field-alert" v-if="errors.password">{{ errors.password[0] }}</small>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="adminRemember" class="custom-checkbox">
                        <label for="adminRemember" class="text-sm capitalize cursor-pointer whitespace-nowrap">
                            {{ $t('label.remember_me') }}
                        </label>
                    </div>
                </div>

                <div>
                    <button type="submit"
                        class="font-bold text-center w-full h-12 leading-12 rounded-full bg-primary text-white capitalize">
                        {{ $t('label.sign_in') }}
                    </button>
                </div>

                <div v-if="demo === 'true' || demo === 'TRUE' || demo === 'True' || demo === '1' || demo === 1"
                    class="mt-6">
                    <h2 class="mb-4 text-center text-sm font-medium text-heading">{{ $t('message.for_quick_demo') }}</h2>
                    <nav class="grid grid-cols-3 gap-2">
                        <button type="button" @click.prevent="setupCredit('admin')"
                            class="click-to-prop w-full h-9 leading-9 rounded-lg text-center text-xs capitalize text-white bg-orange-500"
                            id="adminClick">
                            {{ $t('label.admin') }}
                        </button>
                        <button type="button" @click.prevent="setupCredit('manager')"
                            class="click-to-prop w-full h-9 leading-9 rounded-lg text-center text-xs capitalize text-white bg-sky-600"
                            id="branchManagerClick">
                            {{ $t('label.manager') }}
                        </button>
                        <button type="button" @click.prevent="setupCredit('posOperator')"
                            class="click-to-prop w-full h-9 leading-9 rounded-lg text-center text-xs capitalize text-white bg-purple-500"
                            id="posOperatorClick">
                            {{ $t('label.pos_operator') }}
                        </button>
                    </nav>
                </div>
            </form>
        </div>
    </div>
</template>

<script>
import router from "../../../router";
import LoadingComponent from "../components/LoadingComponent.vue";
import alertService from "../../../services/alertService";
import appService from "../../../services/appService";
import ENV from "../../../config/env";

export default {
    name: "AdminLoginComponent",
    components: { LoadingComponent },
    data() {
        return {
            loading: {
                isActive: false,
            },
            form: {
                email: "",
                password: ""
            },
            errors: {},
            demo: ENV.DEMO,
        }
    },
    computed: {
        setting: function () {
            return this.$store.getters['frontendSetting/lists'];
        }
    },
    mounted() {
        this.loading.isActive = true;
        this.$store.dispatch('frontendSetting/lists').then(res => {
            this.loading.isActive = false;
        }).catch((err) => {
            this.loading.isActive = false;
        });
    },
    methods: {
        login: function () {
            try {
                this.loading.isActive = true;
                this.$store.dispatch('login', { ...this.form, portal: 'admin' }).then((res) => {
                    this.loading.isActive = false;
                    alertService.success(res.data.message);
                    router.push({ name: "admin.dashboard" });
                    setTimeout(() => {
                        appService.recursiveRouter(router.options.routes, this.$store.getters.authPermission);
                    }, 1000);
                }).catch((err) => {
                    this.loading.isActive = false;
                    if (err.response && err.response.data && err.response.data.errors) {
                        this.errors = err.response.data.errors;
                    } else if (err.response && err.response.data && err.response.data.message) {
                        this.errors = { validation: err.response.data.message };
                    }
                });
            } catch (err) {
                this.loading.isActive = false;
            }
        },
        close: function () {
            this.errors = {};
        },
        setupCredit: function (e) {
            if (e === 'admin') {
                this.form.email = 'admin@example.com';
                this.form.password = '123456';
            } else if (e === 'manager') {
                this.form.email = 'manager@example.com';
                this.form.password = '123456';
            } else if (e === 'posOperator') {
                this.form.email = 'posoperator@example.com';
                this.form.password = '123456';
            }
        }
    }
}
</script>
