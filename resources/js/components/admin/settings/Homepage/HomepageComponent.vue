<template>
    <LoadingComponent :props="loading" />

    <div id="homepage-manager" class="db-card db-tab-div active">
        <div class="db-card-header flex items-center justify-between border-b pb-4 mb-6">
            <div>
                <h3 class="db-card-title text-xl font-bold text-gray-900 dark:text-white">🎨 Homepage Section Manager</h3>
                <p class="text-xs text-gray-500 mt-1">Control visibility, titles, banners, and options for every homepage section.</p>
            </div>
            <span class="text-xs px-3 py-1 bg-amber-500/10 text-amber-600 font-bold rounded-full border border-amber-500/20">6ixculture Customizer</span>
        </div>

        <div class="db-card-body">
            <form @submit.prevent="save">
                <div class="flex flex-col gap-6">

                    <!-- 1. Announcement Bar -->
                    <div class="p-5 border rounded-2xl bg-white dark:bg-slate-900 border-gray-200 dark:border-slate-800 shadow-sm transition-all hover:shadow-md">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-500 flex items-center justify-center font-bold text-lg">📢</div>
                                <div>
                                    <h4 class="font-bold text-base text-gray-900 dark:text-white">1. Top Announcement Bar Ticker</h4>
                                    <p class="text-xs text-gray-500">Displays a live promo ticker banner above your main header menu.</p>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="form.homepage_announcement_bar" :true-value="1" :false-value="0" class="sr-only peer">
                                <div class="w-12 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                            </label>
                        </div>
                        <div v-if="form.homepage_announcement_bar == 1" class="form-row mt-4 pt-4 border-t border-gray-100 dark:border-slate-800">
                            <div class="form-col-12 sm:form-col-6">
                                <label class="db-field-title">Announcement Message / Promo Text</label>
                                <input type="text" v-model="form.homepage_announcement_text" class="db-field-control" placeholder="e.g. 🔥 Free Delivery on orders over ₦50,000 | Get 10% OFF your first order — Use Code: WELCOME10">
                            </div>

                            <div class="form-col-12 sm:form-col-6">
                                <label class="db-field-title">Destination URL / Click Link (Optional)</label>
                                <input type="text" v-model="form.homepage_announcement_link" class="db-field-control" placeholder="e.g. /offers or https://6ixculture.com.ng">
                            </div>

                            <div class="form-col-12 sm:form-col-6">
                                <label class="db-field-title">Banner Color Theme</label>
                                <select v-model="form.homepage_announcement_bg_theme" class="db-field-control">
                                    <option value="orange_gradient">🔥 Orange/Amber Gradient (Default 6ixculture Vibe)</option>
                                    <option value="dark_luxury">🖤 Dark Luxury (Slate/Gold)</option>
                                    <option value="gold_gradient">✨ Gold & Yellow Gradient</option>
                                    <option value="emerald_gradient">🌿 Emerald Green Gradient</option>
                                </select>
                            </div>

                            <div class="form-col-12 sm:form-col-6">
                                <label class="db-field-title">Allow Customers to Dismiss (X button)</label>
                                <select v-model="form.homepage_announcement_dismiss" class="db-field-control">
                                    <option :value="1">Yes (Show close button)</option>
                                    <option :value="0">No (Always visible fixed banner)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Hero Banner Slider -->
                    <div class="p-5 border rounded-2xl bg-white dark:bg-slate-900 border-gray-200 dark:border-slate-800 shadow-sm transition-all hover:shadow-md">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-orange-500/10 text-orange-500 flex items-center justify-center font-bold">👑</div>
                                <div>
                                    <h4 class="font-bold text-base text-gray-900 dark:text-white">2. Hero Banner Slider (16:9 Full Width)</h4>
                                    <p class="text-xs text-gray-500">Main homepage carousel banners.</p>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="form.homepage_hero_slider" :true-value="1" :false-value="0" class="sr-only peer">
                                <div class="w-12 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                            </label>
                        </div>
                        <div v-if="form.homepage_hero_slider == 1" class="mt-4 pt-4 border-t border-gray-100 dark:border-slate-800 flex items-center justify-between">
                            <span class="text-xs font-semibold text-gray-600 dark:text-gray-300">Upload & Edit Hero Banner Slides:</span>
                            <router-link :to="{ name: 'admin.settings.slider' }" class="db-btn-outline py-2 px-4 text-xs font-bold rounded-xl flex items-center gap-2 border border-primary text-primary hover:bg-primary hover:text-white transition-all">
                                <i class="lab-line-plus"></i> Upload New 16:9 Banner Slide
                            </router-link>
                        </div>
                    </div>

                    <!-- 3. Category Bubbles -->
                    <div class="p-5 border rounded-2xl bg-white dark:bg-slate-900 border-gray-200 dark:border-slate-800 shadow-sm transition-all hover:shadow-md">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-blue-500/10 text-blue-500 flex items-center justify-center font-bold">🏷️</div>
                                <div>
                                    <h4 class="font-bold text-base text-gray-900 dark:text-white">3. Category Bubbles Navigation</h4>
                                    <p class="text-xs text-gray-500">Quick-jump category circles on homepage.</p>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="form.homepage_category_bubbles" :true-value="1" :false-value="0" class="sr-only peer">
                                <div class="w-12 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                            </label>
                        </div>
                        <div v-if="form.homepage_category_bubbles == 1" class="mt-4 pt-4 border-t border-gray-100 dark:border-slate-800 flex items-center justify-between">
                            <span class="text-xs font-semibold text-gray-600 dark:text-gray-300">Manage Category Thumbnails & Links:</span>
                            <router-link :to="{ name: 'admin.settings.productCategory' }" class="db-btn-outline py-2 px-4 text-xs font-bold rounded-xl flex items-center gap-2 border border-primary text-primary hover:bg-primary hover:text-white transition-all">
                                <i class="lab-line-edit"></i> Manage Categories & Icons
                            </router-link>
                        </div>
                    </div>

                    <!-- 4. Flash Sales -->
                    <div class="p-5 border rounded-2xl bg-white dark:bg-slate-900 border-gray-200 dark:border-slate-800 shadow-sm transition-all hover:shadow-md">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-red-500/10 text-red-500 flex items-center justify-center font-bold">⚡</div>
                                <div>
                                    <h4 class="font-bold text-base text-gray-900 dark:text-white">4. Flash Sales & Live Countdown Timer</h4>
                                    <p class="text-xs text-gray-500">Limited-time discounted deals block.</p>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="form.homepage_flash_sales" :true-value="1" :false-value="0" class="sr-only peer">
                                <div class="w-12 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                            </label>
                        </div>
                        <div v-if="form.homepage_flash_sales == 1" class="form-row mt-4 pt-4 border-t border-gray-100 dark:border-slate-800">
                            <div class="form-col-12 sm:form-col-6">
                                <label class="db-field-title">Section Heading</label>
                                <input type="text" v-model="form.homepage_flash_title" class="db-field-control" placeholder="e.g. Flash Sales">
                            </div>
                            <div class="form-col-12 sm:form-col-6 flex items-end">
                                <router-link :to="{ name: 'admin.promotion.list' }" class="w-full db-btn-outline py-2.5 px-4 text-xs font-bold rounded-xl flex items-center justify-center gap-2 border border-primary text-primary hover:bg-primary hover:text-white transition-all">
                                    <i class="lab-line-clock"></i> Manage Flash Sale Deals & Timer
                                </router-link>
                            </div>
                        </div>
                    </div>

                    <!-- 5. Trending Drops -->
                    <div class="p-5 border rounded-2xl bg-white dark:bg-slate-900 border-gray-200 dark:border-slate-800 shadow-sm transition-all hover:shadow-md">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-purple-500/10 text-purple-500 flex items-center justify-center font-bold">✨</div>
                                <div>
                                    <h4 class="font-bold text-base text-gray-900 dark:text-white">5. Trending Drops / Featured Products</h4>
                                    <p class="text-xs text-gray-500">Showcase curated collections or new arrivals.</p>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="form.homepage_trending_drops" :true-value="1" :false-value="0" class="sr-only peer">
                                <div class="w-12 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                            </label>
                        </div>
                        <div v-if="form.homepage_trending_drops == 1" class="form-row mt-4 pt-4 border-t border-gray-100 dark:border-slate-800">
                            <div class="form-col-12 sm:form-col-6">
                                <label class="db-field-title">Section Heading</label>
                                <input type="text" v-model="form.homepage_trending_title" class="db-field-control" placeholder="e.g. Trending Drops">
                            </div>
                            <div class="form-col-12 sm:form-col-6 flex items-end">
                                <router-link :to="{ name: 'admin.product.section.list' }" class="w-full db-btn-outline py-2.5 px-4 text-xs font-bold rounded-xl flex items-center justify-center gap-2 border border-primary text-primary hover:bg-primary hover:text-white transition-all">
                                    <i class="lab-line-grid"></i> Manage Product Sections & Items
                                </router-link>
                            </div>
                        </div>
                    </div>

                    <!-- 6. Promotional Banners -->
                    <div class="p-5 border rounded-2xl bg-white dark:bg-slate-900 border-gray-200 dark:border-slate-800 shadow-sm transition-all hover:shadow-md">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center font-bold">🖼️</div>
                                <div>
                                    <h4 class="font-bold text-base text-gray-900 dark:text-white">6. Promotional Grid Banners</h4>
                                    <p class="text-xs text-gray-500">2-column or 3-column promo image cards.</p>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="form.homepage_promo_banners" :true-value="1" :false-value="0" class="sr-only peer">
                                <div class="w-12 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                            </label>
                        </div>
                        <div v-if="form.homepage_promo_banners == 1" class="mt-4 pt-4 border-t border-gray-100 dark:border-slate-800 flex items-center justify-between">
                            <span class="text-xs font-semibold text-gray-600 dark:text-gray-300">Upload & Edit Promotional Cards:</span>
                            <router-link :to="{ name: 'admin.promotion.list' }" class="db-btn-outline py-2 px-4 text-xs font-bold rounded-xl flex items-center gap-2 border border-primary text-primary hover:bg-primary hover:text-white transition-all">
                                <i class="lab-line-image"></i> Manage Promo Banners
                            </router-link>
                        </div>
                    </div>

                    <!-- 7. Best Sellers -->
                    <div class="p-5 border rounded-2xl bg-white dark:bg-slate-900 border-gray-200 dark:border-slate-800 shadow-sm transition-all hover:shadow-md">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-500 flex items-center justify-center font-bold">🔥</div>
                                <div>
                                    <h4 class="font-bold text-base text-gray-900 dark:text-white">7. Best Sellers Showcase</h4>
                                    <p class="text-xs text-gray-500">Auto-calculated top selling products.</p>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="form.homepage_best_sellers" :true-value="1" :false-value="0" class="sr-only peer">
                                <div class="w-12 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                            </label>
                        </div>
                        <div v-if="form.homepage_best_sellers == 1" class="form-row mt-4 pt-4 border-t border-gray-100 dark:border-slate-800">
                            <div class="form-col-12 sm:form-col-6">
                                <label class="db-field-title">Section Heading</label>
                                <input type="text" v-model="form.homepage_best_title" class="db-field-control" placeholder="e.g. Best Sellers">
                            </div>
                        </div>
                    </div>

                    <!-- 8. Trust & Value Badges -->
                    <div class="p-5 border rounded-2xl bg-white dark:bg-slate-900 border-gray-200 dark:border-slate-800 shadow-sm transition-all hover:shadow-md">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-teal-500/10 text-teal-500 flex items-center justify-center font-bold">🛡️</div>
                                <div>
                                    <h4 class="font-bold text-base text-gray-900 dark:text-white">8. Trust & Value Proposition Badges</h4>
                                    <p class="text-xs text-gray-500">Delivery, returns, payment, & support guarantees.</p>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="form.homepage_trust_badges" :true-value="1" :false-value="0" class="sr-only peer">
                                <div class="w-12 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                            </label>
                        </div>
                        <div v-if="form.homepage_trust_badges == 1" class="mt-4 pt-4 border-t border-gray-100 dark:border-slate-800 flex items-center justify-between">
                            <span class="text-xs font-semibold text-gray-600 dark:text-gray-300">Edit Trust Badges & Icons:</span>
                            <router-link :to="{ name: 'admin.settings.benefit' }" class="db-btn-outline py-2 px-4 text-xs font-bold rounded-xl flex items-center gap-2 border border-primary text-primary hover:bg-primary hover:text-white transition-all">
                                <i class="lab-line-shield"></i> Manage Benefit Badges
                            </router-link>
                        </div>
                    </div>

                    <!-- Save Button -->
                    <div class="form-col-12 mt-6">
                        <button type="submit" class="db-btn py-3.5 px-10 text-white bg-primary rounded-xl font-bold hover:opacity-90 shadow-lg text-sm transition-all flex items-center gap-2">
                            <i class="lab-line-save text-lg"></i> Save All Homepage Settings
                        </button>
                    </div>

                </div>
            </form>
        </div>
    </div>
</template>

<script>
import LoadingComponent from "../../components/LoadingComponent.vue";
import alertService from "../../../../services/alertService";

export default {
    name: "HomepageComponent",
    components: { LoadingComponent },
    data() {
        return {
            loading: {
                isActive: false
            },
            form: {
                homepage_announcement_bar: 1,
                homepage_announcement_text: "🔥 Free Delivery on orders over ₦50,000 | Get 10% OFF your first order — Use Code: WELCOME10",
                homepage_announcement_link: "",
                homepage_announcement_bg_theme: "orange_gradient",
                homepage_announcement_dismiss: 1,
                homepage_hero_slider: 1,
                homepage_category_bubbles: 1,
                homepage_flash_sales: 1,
                homepage_flash_title: "",
                homepage_trending_drops: 1,
                homepage_trending_title: "",
                homepage_promo_banners: 1,
                homepage_best_sellers: 1,
                homepage_best_title: "",
                homepage_trust_badges: 1
            },
            errors: {}
        }
    },
    mounted() {
        this.fetchSettings();
    },
    methods: {
        fetchSettings() {
            this.loading.isActive = true;
            this.$store.dispatch("homepage/lists").then((res) => {
                this.loading.isActive = false;
                if (res.data && res.data.data) {
                    const data = res.data.data;
                    for (const key in this.form) {
                        if (data[key] !== undefined) {
                            this.form[key] = data[key];
                        }
                    }
                }
            }).catch((err) => {
                this.loading.isActive = false;
            });
        },
        save() {
            this.loading.isActive = true;
            this.$store.dispatch("homepage/save", this.form).then((res) => {
                this.loading.isActive = false;
                alertService.successFlip(1, "Homepage settings updated successfully!");
                this.$store.dispatch("frontendSetting/lists");
            }).catch((err) => {
                this.loading.isActive = false;
                if (err.response && err.response.data && err.response.data.errors) {
                    this.errors = err.response.data.errors;
                }
            });
        }
    }
}
</script>
