import './bootstrap';
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createPinia } from 'pinia';
import PrimeVue from 'primevue/config';
import ToastService from 'primevue/toastservice';
import ConfirmationService from 'primevue/confirmationservice';
import Aura from '@primeuix/themes/aura';
import AppLayout from './layouts/AppLayout.vue';
import { setupAuthGuard } from './composables/useAuthGuard.js';
import { useAuthStore } from './stores/authStore.js';

createInertiaApp({
    resolve: async (name) => {
        const page = await resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob('./pages/**/*.vue'),
        );
        if (page.default.layout === undefined) {
            page.default.layout = AppLayout;
        }
        return page;
    },
    async setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });

        app.use(plugin);
        const pinia = createPinia();
        app.use(pinia);
        app.use(PrimeVue, {
            theme: {
                preset: Aura,
                options: {
                    darkModeSelector: '.dark',
                    cssLayer: {
                        name: 'primevue',
                        order: 'theme, base, primevue, utilities',
                    },
                },
            },
        });
        app.use(ToastService);
        app.use(ConfirmationService);

        // Initialize auth before mounting so guards and admin checks are ready
        const authStore = useAuthStore(pinia);
        await authStore.loadFromStorage();

        app.mount(el);

        setupAuthGuard();
    },
});
