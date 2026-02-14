<template>
    <div class="max-w-md mx-auto px-4 py-12 sm:py-20">
        <h1 class="text-3xl font-bold text-center mb-8 text-slate-800 dark:text-slate-200">
            {{ t('auth.login.title') }}
        </h1>

        <form @submit.prevent="handleLogin" class="space-y-5">
            <!-- Error message -->
            <div v-if="error" class="p-3 rounded-lg bg-red-50 dark:bg-red-950/50 border border-red-200 dark:border-red-900 text-sm text-red-700 dark:text-red-300">
                {{ error }}
            </div>

            <div class="flex flex-col gap-2">
                <label for="email" class="text-sm font-medium text-slate-700 dark:text-slate-300">
                    {{ t('auth.login.email') }}
                </label>
                <InputText
                    id="email"
                    v-model="form.email"
                    type="email"
                    autocomplete="email"
                    :invalid="!!fieldErrors.email"
                    class="w-full"
                />
                <small v-if="fieldErrors.email" class="text-red-500">{{ fieldErrors.email }}</small>
            </div>

            <div class="flex flex-col gap-2">
                <label for="password" class="text-sm font-medium text-slate-700 dark:text-slate-300">
                    {{ t('auth.login.password') }}
                </label>
                <Password
                    id="password"
                    v-model="form.password"
                    :feedback="false"
                    toggleMask
                    :invalid="!!fieldErrors.password"
                    inputClass="w-full"
                    class="w-full"
                />
                <small v-if="fieldErrors.password" class="text-red-500">{{ fieldErrors.password }}</small>
            </div>

            <!-- Two-factor code (shown conditionally) -->
            <div v-if="showTwoFactor" class="flex flex-col gap-2">
                <label for="twoFactorCode" class="text-sm font-medium text-slate-700 dark:text-slate-300">
                    {{ t('auth.login.twoFactor') }}
                </label>
                <InputText
                    id="twoFactorCode"
                    v-model="form.twoFactorCode"
                    type="text"
                    inputmode="numeric"
                    maxlength="6"
                    :placeholder="t('auth.login.twoFactorCode')"
                    class="w-full text-center tracking-[0.3em] text-lg"
                />
            </div>

            <Button
                type="submit"
                :label="t('auth.login.submit')"
                severity="success"
                :loading="loading"
                class="w-full"
            />
        </form>

        <div class="mt-6 text-center space-y-3">
            <router-link
                to="/forgot-password"
                class="text-sm text-emerald-600 dark:text-emerald-400 hover:underline"
            >
                {{ t('auth.login.forgotPassword') }}
            </router-link>

            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ t('auth.login.noAccount') }}
                <router-link to="/register" class="text-emerald-600 dark:text-emerald-400 font-medium hover:underline">
                    {{ t('auth.login.register') }}
                </router-link>
            </p>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Button from 'primevue/button';
import { useAuthStore } from '../stores/authStore.js';
import { useI18n } from '../composables/useI18n.js';

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
const { t } = useI18n();

const form = reactive({
    email: '',
    password: '',
    twoFactorCode: '',
});

const loading = ref(false);
const error = ref('');
const fieldErrors = reactive({});
const showTwoFactor = ref(false);

async function handleLogin() {
    loading.value = true;
    error.value = '';
    Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k]);

    try {
        await authStore.login(form.email, form.password, form.twoFactorCode || undefined);
        const redirect = route.query.redirect || '/games';
        router.push(redirect);
    } catch (err) {
        const status = err.response?.status;
        const data = err.response?.data;

        if (status === 422 && data?.errors) {
            Object.assign(fieldErrors, Object.fromEntries(
                Object.entries(data.errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v])
            ));
        } else if (status === 423 || data?.two_factor) {
            showTwoFactor.value = true;
            error.value = '';
        } else {
            error.value = data?.message || t('common.error');
        }
    } finally {
        loading.value = false;
    }
}
</script>
