<template>
    <div class="navbar-dark-mode-toggle flex items-center">
        <button
            @click.prevent="toggleDarkMode"
            type="button"
            class="dark-mode-toggle w-9 h-9 rounded-lg flex items-center justify-center text-primary bg-primary/5 hover:bg-primary/10 transition-all duration-300"
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
    name: "NavbarComponent",
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
