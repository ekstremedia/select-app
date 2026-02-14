describe('useI18n', () => {
    beforeEach(() => {
        localStorage.clear();
        vi.resetModules();
    });

    async function loadUseI18n() {
        const mod = await import('../useI18n.js');
        return mod.useI18n;
    }

    it('returns Norwegian translation by default', async () => {
        const useI18n = await loadUseI18n();
        const { t } = useI18n();

        expect(t('hero.title')).toBe('SELECT');
        expect(t('hero.subtitle')).toBe('Fra #select på EFnet — nå på mobilen din');
    });

    it('returns the key if translation is missing', async () => {
        const useI18n = await loadUseI18n();
        const { t } = useI18n();

        expect(t('nonexistent.key')).toBe('nonexistent.key');
        expect(t('totally.made.up')).toBe('totally.made.up');
    });

    it('toggleLocale switches between NO and EN', async () => {
        const useI18n = await loadUseI18n();
        const { t, toggleLocale, locale } = useI18n();

        expect(locale.value).toBe('no');
        expect(t('cta.play')).toBe('Spill nå');

        toggleLocale();

        expect(locale.value).toBe('en');
        expect(t('cta.play')).toBe('Play now');

        toggleLocale();

        expect(locale.value).toBe('no');
        expect(t('cta.play')).toBe('Spill nå');
    });

    it('isNorwegian reflects current locale', async () => {
        const useI18n = await loadUseI18n();
        const { isNorwegian, toggleLocale } = useI18n();

        expect(isNorwegian.value).toBe(true);

        toggleLocale();
        expect(isNorwegian.value).toBe(false);

        toggleLocale();
        expect(isNorwegian.value).toBe(true);
    });

    it('persists locale to localStorage on toggle', async () => {
        const useI18n = await loadUseI18n();
        const { toggleLocale } = useI18n();

        expect(localStorage.getItem('select-locale')).toBeNull();

        toggleLocale();
        expect(localStorage.getItem('select-locale')).toBe('en');

        toggleLocale();
        expect(localStorage.getItem('select-locale')).toBe('no');
    });

    it('restores locale from localStorage', async () => {
        localStorage.setItem('select-locale', 'en');

        const useI18n = await loadUseI18n();
        const { locale, t, isNorwegian } = useI18n();

        expect(locale.value).toBe('en');
        expect(isNorwegian.value).toBe(false);
        expect(t('cta.play')).toBe('Play now');
    });
});
