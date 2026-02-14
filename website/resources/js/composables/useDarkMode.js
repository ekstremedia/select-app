import { ref, watch } from 'vue';

const STORAGE_KEY = 'select-dark-mode';

function getInitialDark() {
    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored !== null) {
        return stored === 'true';
    }
    return window.matchMedia('(prefers-color-scheme: dark)').matches;
}

const isDark = ref(getInitialDark());

function applyDarkMode(dark) {
    document.documentElement.classList.toggle('dark', dark);
}

applyDarkMode(isDark.value);

watch(isDark, (val) => {
    applyDarkMode(val);
    localStorage.setItem(STORAGE_KEY, String(val));
});

export function useDarkMode() {
    function toggleDark() {
        isDark.value = !isDark.value;
    }

    return { isDark, toggleDark };
}
