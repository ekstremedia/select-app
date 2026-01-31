import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';

import nb from './locales/nb.json';
import nn from './locales/nn.json';
import en from './locales/en.json';

const resources = {
  nb: { translation: nb },
  nn: { translation: nn },
  en: { translation: en },
};

i18n
  .use(initReactI18next)
  .init({
    resources,
    lng: 'nb', // Default to Norwegian Bokmål
    fallbackLng: 'nb',
    interpolation: {
      escapeValue: false,
    },
    react: {
      useSuspense: false,
    },
  });

export default i18n;
