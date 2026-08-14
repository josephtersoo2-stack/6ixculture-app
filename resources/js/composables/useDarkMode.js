import { ref, onMounted } from "vue";

const isDarkMode = ref(false);

// Immediate execution to prevent flash of light theme
if (typeof window !== "undefined" && typeof document !== "undefined") {
    const savedTheme = localStorage.getItem("theme");
    if (savedTheme === "dark" || (!savedTheme && window.matchMedia("(prefers-color-scheme: dark)").matches)) {
        isDarkMode.value = true;
        document.documentElement.classList.add("dark");
    } else {
        isDarkMode.value = false;
        document.documentElement.classList.remove("dark");
    }
}

export function useDarkMode() {
    const initDarkMode = () => {
        if (typeof window === "undefined") return;
        const savedTheme = localStorage.getItem("theme");
        if (savedTheme === "dark" || (!savedTheme && window.matchMedia("(prefers-color-scheme: dark)").matches)) {
            isDarkMode.value = true;
            document.documentElement.classList.add("dark");
        } else {
            isDarkMode.value = false;
            document.documentElement.classList.remove("dark");
        }
    };

    const toggleDarkMode = () => {
        isDarkMode.value = !isDarkMode.value;
        if (isDarkMode.value) {
            document.documentElement.classList.add("dark");
            localStorage.setItem("theme", "dark");
        } else {
            document.documentElement.classList.remove("dark");
            localStorage.setItem("theme", "light");
        }
    };

    onMounted(() => {
        initDarkMode();
    });

    return {
        isDarkMode,
        toggleDarkMode,
        initDarkMode
    };
}
