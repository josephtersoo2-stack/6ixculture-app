<template>
    <div class="header-dark-mode-toggle flex items-center">
        <button
            @click.prevent="toggleDarkMode"
            type="button"
            class="dark-mode-toggle flex items-center justify-center w-9 h-9 rounded-full bg-gray-100 dark:bg-slate-800 text-slate-700 dark:text-amber-400 hover:text-primary transition-all duration-300"
            :title="isDarkMode ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
            aria-label="Toggle Dark Mode"
        >
            <i v-if="isDarkMode" class="fa-solid fa-sun text-lg text-amber-400"></i>
            <i v-else class="fa-solid fa-moon text-lg"></i>
        </button>
    </div>
</template>

<script>
import { useDarkMode } from "../../../composables/useDarkMode";

export default {
    name: "HeaderComponent",
    setup() {
        const { isDarkMode, toggleDarkMode, initDarkMode } = useDarkMode();
        return {
            isDarkMode,
            toggleDarkMode,
            initDarkMode
        };
    }
};
</script>
