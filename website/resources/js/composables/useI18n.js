import { ref, computed } from 'vue';

const translations = {
    no: {
        'nav.language': 'EN',
        'hero.title': 'SELECT',
        'hero.subtitle': 'Fra #select på EFnet — nå på mobilen din',
        'hero.description': 'Det klassiske akronym-spillet der kreativitet og humor vinner',
        'how.title': 'Slik spiller du',
        'how.step1.title': 'Få et akronym',
        'how.step1.desc': 'Delectus gir deg tilfeldige bokstaver, f.eks. "TIHWP"',
        'how.step2.title': 'Lag en setning',
        'how.step2.desc': 'Hvert ord må starte med riktig bokstav',
        'how.step3.title': 'Stem på den beste',
        'how.step3.desc': 'Alle stemmer på sin favoritt — men ikke på sin egen!',
        'how.step4.title': 'Vinn runden!',
        'how.step4.desc': 'Flest stemmer gir poeng. Mest poeng vinner spillet!',
        'example.title': 'Eksempel',
        'example.prompt': 'Lag en setning med bokstavene:',
        'example.answer': '"Tidlig Innsats HjelperWorkflowen Perfekt"',
        'cta.play': 'Spill nå',
        'cta.download': 'Last ned appen',
        'footer.tagline': 'Delectus — spillmesteren fra #select på EFnet',
    },
    en: {
        'nav.language': 'NO',
        'hero.title': 'SELECT',
        'hero.subtitle': 'From #select on EFnet — now on your phone',
        'hero.description': 'The classic acronym game where creativity and humor wins',
        'how.title': 'How to play',
        'how.step1.title': 'Get an acronym',
        'how.step1.desc': 'Delectus gives you random letters, e.g. "TIHWP"',
        'how.step2.title': 'Create a sentence',
        'how.step2.desc': 'Each word must start with the correct letter',
        'how.step3.title': 'Vote for the best',
        'how.step3.desc': "Everyone votes for their favorite — but not their own!",
        'how.step4.title': 'Win the round!',
        'how.step4.desc': 'Most votes earn points. Most points wins the game!',
        'example.title': 'Example',
        'example.prompt': 'Create a sentence with the letters:',
        'example.answer': '"This Is How We Play"',
        'cta.play': 'Play now',
        'cta.download': 'Get the app',
        'footer.tagline': 'Delectus — the game master from #select on EFnet',
    },
};

const STORAGE_KEY = 'select-locale';

const locale = ref(localStorage.getItem(STORAGE_KEY) || 'no');

export function useI18n() {
    function t(key) {
        return translations[locale.value]?.[key] ?? key;
    }

    function toggleLocale() {
        locale.value = locale.value === 'no' ? 'en' : 'no';
        localStorage.setItem(STORAGE_KEY, locale.value);
    }

    const isNorwegian = computed(() => locale.value === 'no');

    return { locale, t, toggleLocale, isNorwegian };
}
