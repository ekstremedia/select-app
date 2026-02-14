import { nextTick } from 'vue';

describe('useDarkMode', () => {
    beforeEach(() => {
        localStorage.clear();
        vi.resetModules();
        document.documentElement.classList.remove('dark');
    });

    function mockMatchMedia(matches) {
        window.matchMedia = vi.fn().mockReturnValue({ matches });
    }

    async function loadUseDarkMode() {
        const mod = await import('../useDarkMode.js');
        return mod.useDarkMode;
    }

    it('defaults to system preference when matchMedia prefers dark', async () => {
        mockMatchMedia(true);

        const useDarkMode = await loadUseDarkMode();
        const { isDark } = useDarkMode();

        expect(isDark.value).toBe(true);
        expect(document.documentElement.classList.contains('dark')).toBe(true);
    });

    it('defaults to light when matchMedia does not prefer dark', async () => {
        mockMatchMedia(false);

        const useDarkMode = await loadUseDarkMode();
        const { isDark } = useDarkMode();

        expect(isDark.value).toBe(false);
        expect(document.documentElement.classList.contains('dark')).toBe(false);
    });

    it('toggleDark flips the value', async () => {
        mockMatchMedia(false);

        const useDarkMode = await loadUseDarkMode();
        const { isDark, toggleDark } = useDarkMode();

        expect(isDark.value).toBe(false);

        toggleDark();
        expect(isDark.value).toBe(true);

        toggleDark();
        expect(isDark.value).toBe(false);
    });

    it('toggles dark class on document.documentElement', async () => {
        mockMatchMedia(false);

        const useDarkMode = await loadUseDarkMode();
        const { toggleDark } = useDarkMode();

        expect(document.documentElement.classList.contains('dark')).toBe(false);

        toggleDark();
        await nextTick();
        expect(document.documentElement.classList.contains('dark')).toBe(true);

        toggleDark();
        await nextTick();
        expect(document.documentElement.classList.contains('dark')).toBe(false);
    });

    it('persists preference to localStorage', async () => {
        mockMatchMedia(false);

        const useDarkMode = await loadUseDarkMode();
        const { toggleDark } = useDarkMode();

        toggleDark();
        await nextTick();
        expect(localStorage.getItem('select-dark-mode')).toBe('true');

        toggleDark();
        await nextTick();
        expect(localStorage.getItem('select-dark-mode')).toBe('false');
    });

    it('restores preference from localStorage', async () => {
        mockMatchMedia(false);
        localStorage.setItem('select-dark-mode', 'true');

        const useDarkMode = await loadUseDarkMode();
        const { isDark } = useDarkMode();

        expect(isDark.value).toBe(true);
        expect(document.documentElement.classList.contains('dark')).toBe(true);
    });

    it('localStorage false overrides system dark preference', async () => {
        mockMatchMedia(true);
        localStorage.setItem('select-dark-mode', 'false');

        const useDarkMode = await loadUseDarkMode();
        const { isDark } = useDarkMode();

        expect(isDark.value).toBe(false);
        expect(document.documentElement.classList.contains('dark')).toBe(false);
    });
});
