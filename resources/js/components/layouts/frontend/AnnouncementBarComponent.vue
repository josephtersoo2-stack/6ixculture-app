<template>
    <div v-if="isVisible && (setting.homepage_announcement_bar == 1 || setting.homepage_announcement_bar === undefined)" 
         :class="[bgThemeClass, 'w-full text-xs font-semibold py-2.5 px-4 tracking-wide transition-all duration-300 relative z-40 shadow-sm']">
        <div class="container mx-auto flex items-center justify-between gap-4">
            <div class="flex-1 flex items-center justify-center gap-2 text-center">
                <i class="lab-fill-fire text-amber-300 animate-pulse text-sm"></i>
                <a v-if="setting.homepage_announcement_link" :href="setting.homepage_announcement_link" class="hover:underline flex items-center gap-1">
                    <span>{{ announcementText }}</span>
                    <i class="lab-line-arrow-right text-[10px]"></i>
                </a>
                <span v-else>{{ announcementText }}</span>
            </div>
            
            <button v-if="setting.homepage_announcement_dismiss == 1" 
                    @click="dismiss" 
                    type="button" 
                    class="opacity-75 hover:opacity-100 transition-opacity p-1 rounded-full hover:bg-black/10" 
                    title="Close Announcement">
                <i class="lab-line-circle-cross text-sm"></i>
            </button>
        </div>
    </div>
</template>

<script>
export default {
    name: "AnnouncementBarComponent",
    data() {
        return {
            isDismissed: false
        }
    },
    computed: {
        setting() {
            return this.$store.getters['frontendSetting/lists'] || {};
        },
        isVisible() {
            if (this.isDismissed) return false;
            if (sessionStorage.getItem('announcement_dismissed') === '1') return false;
            return true;
        },
        announcementText() {
            return this.setting.homepage_announcement_text || '🔥 Free Delivery on orders over ₦50,000 | Get 10% OFF your first order — Use Code: WELCOME10';
        },
        bgThemeClass() {
            const theme = this.setting.homepage_announcement_bg_theme || 'orange_gradient';
            switch (theme) {
                case 'dark_luxury':
                    return 'bg-slate-900 text-amber-400 border-b border-amber-500/20';
                case 'gold_gradient':
                    return 'bg-gradient-to-r from-amber-500 via-yellow-500 to-amber-600 text-slate-900 font-extrabold';
                case 'emerald_gradient':
                    return 'bg-gradient-to-r from-emerald-600 to-teal-700 text-white';
                case 'orange_gradient':
                default:
                    return 'bg-gradient-to-r from-amber-600 via-orange-600 to-amber-500 text-white';
            }
        }
    },
    methods: {
        dismiss() {
            this.isDismissed = true;
            sessionStorage.setItem('announcement_dismissed', '1');
        }
    }
}
</script>
