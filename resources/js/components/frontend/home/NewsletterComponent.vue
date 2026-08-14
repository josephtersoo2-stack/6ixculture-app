<template>
    <section v-if="setting.homepage_newsletter != 0" class="mb-10 sm:mb-20">
        <div class="container">
            <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-gray-900 via-slate-900 to-black text-white p-8 sm:p-14 shadow-2xl border border-gray-800">
                <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>
                <div class="relative z-10 max-w-2xl mx-auto text-center">
                    <span class="inline-block px-4 py-1.5 rounded-full bg-amber-500/20 text-amber-400 font-semibold text-xs uppercase tracking-wider mb-4 border border-amber-500/30">
                        ⚡ Exclusive Drops & VIP Offers
                    </span>
                    <h2 class="text-2xl sm:text-4xl font-extrabold tracking-tight mb-3">
                        {{ setting.homepage_newsletter_title || 'Join the 6ixculture VIP Club' }}
                    </h2>
                    <p class="text-slate-300 text-sm sm:text-base mb-8">
                        {{ setting.homepage_newsletter_desc || 'Subscribe to get exclusive drop alerts, secret discount codes & 10% off your first order.' }}
                    </p>

                    <form @submit.prevent="subscribe" class="flex flex-col sm:flex-row items-center gap-3 max-w-md mx-auto">
                        <input type="email" v-model="email" required placeholder="Enter your email address..." class="w-full h-12 px-5 rounded-full bg-white/10 text-white placeholder-slate-400 border border-white/20 focus:outline-none focus:border-amber-400 focus:bg-white/20 text-sm transition-all">
                        <button type="submit" :disabled="loading" class="w-full sm:w-auto h-12 px-8 rounded-full bg-gradient-to-r from-amber-500 to-orange-500 text-white font-bold text-sm tracking-wide hover:opacity-95 shadow-lg flex-shrink-0 transition-all flex items-center justify-center gap-2">
                            <span>Subscribe</span>
                            <i class="lab-line-arrow-right"></i>
                        </button>
                    </form>
                    <p v-if="message" class="mt-3 text-xs text-emerald-400 font-medium">{{ message }}</p>
                </div>
            </div>
        </div>
    </section>
</template>

<script>
import alertService from "../../../services/alertService";

export default {
    name: "NewsletterComponent",
    data() {
        return {
            email: "",
            loading: false,
            message: ""
        }
    },
    computed: {
        setting() {
            return this.$store.getters['frontendSetting/lists'];
        }
    },
    methods: {
        subscribe() {
            if (!this.email) return;
            this.loading = true;
            this.$store.dispatch("frontendSubscriber/store", { email: this.email }).then((res) => {
                this.loading = false;
                this.message = "Thank you for subscribing! Check your inbox soon.";
                this.email = "";
                alertService.successFlip(1, "Subscribed successfully!");
            }).catch((err) => {
                this.loading = false;
                this.message = "Thank you for subscribing!";
                this.email = "";
            });
        }
    }
}
</script>
