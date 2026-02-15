<template>
    <div class="max-w-md mx-auto px-4 py-12 sm:py-20">
        <!-- Game invite banner -->
        <div v-if="gamePreview" class="mb-6 p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-900 text-center">
            <p class="text-sm font-medium text-emerald-700 dark:text-emerald-300 mb-1">
                {{ t('auth.joiningGame') }}
            </p>
            <p class="text-xl font-mono font-bold tracking-[0.3em] text-emerald-600 dark:text-emerald-400">
                #{{ gamePreview.code }}
            </p>
            <p class="text-sm text-emerald-600 dark:text-emerald-400 mt-1">
                {{ t('auth.gameWithPlayers').replace('{count}', gamePreview.player_count) }}
            </p>
            <div class="flex flex-wrap justify-center gap-1.5 mt-2">
                <span
                    v-for="(name, index) in gamePreview.players"
                    :key="index"
                    class="text-xs px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300"
                >{{ name }}</span>
            </div>
        </div>

        <!-- Guest play section (always on top) -->
        <div class="p-6 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 mb-6">
            <h2 class="text-lg font-semibold mb-4 text-slate-800 dark:text-slate-200">
                {{ t('auth.guest.title') }}
            </h2>
            <form @submit.prevent="handleGuest" class="space-y-4">
                <div v-if="guestError" class="p-3 rounded-lg bg-red-50 dark:bg-red-950/50 border border-red-200 dark:border-red-900 text-sm text-red-700 dark:text-red-300">
                    {{ guestError }}
                </div>

                <div class="flex flex-col gap-2">
                    <label for="guestNickname" class="text-sm font-medium text-slate-700 dark:text-slate-300">
                        {{ t('auth.guest.nickname') }}
                    </label>
                    <InputText
                        id="guestNickname"
                        v-model="guestNickname"
                        class="w-full"
                    />
                </div>

                <Button
                    type="submit"
                    :label="t('auth.guest.submit')"
                    severity="success"
                    :loading="guestLoading"
                    class="w-full"
                />
            </form>
        </div>

        <div class="flex items-center gap-4 mb-6">
            <div class="flex-1 h-px bg-slate-200 dark:bg-slate-800"></div>
            <span class="text-sm text-slate-400 dark:text-slate-500">{{ t('auth.or') }}</span>
            <div class="flex-1 h-px bg-slate-200 dark:bg-slate-800"></div>
        </div>

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
            <Link
                href="/glemt-passord"
                class="text-sm text-emerald-600 dark:text-emerald-400 hover:underline"
            >
                {{ t('auth.login.forgotPassword') }}
            </Link>

            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ t('auth.login.noAccount') }}
                <Link :href="registerUrl" class="text-emerald-600 dark:text-emerald-400 font-medium hover:underline">
                    {{ t('auth.login.register') }}
                </Link>
            </p>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Button from 'primevue/button';
import { useAuthStore } from '../stores/authStore.js';
import { useI18n } from '../composables/useI18n.js';

const props = defineProps({
    gamePreview: { type: Object, default: null },
});

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

const guestNickname = ref('');
const guestLoading = ref(false);
const guestError = ref('');

function getRedirectParam() {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get('redirect') || '';
}

function getSafeRedirect() {
    const redirect = getRedirectParam() || '/spill';
    // Prevent open redirect: must start with / and not //
    if (redirect.startsWith('/') && !redirect.startsWith('//') && !redirect.includes('://')) {
        return redirect;
    }
    return '/spill';
}

const registerUrl = computed(() => {
    const redirect = getRedirectParam();
    if (redirect) {
        return `/registrer?redirect=${encodeURIComponent(redirect)}`;
    }
    return '/registrer';
});

async function handleGuest() {
    if (!guestNickname.value.trim()) return;

    guestLoading.value = true;
    guestError.value = '';

    try {
        await authStore.createGuest(guestNickname.value.trim());
        router.visit(getSafeRedirect());
    } catch (err) {
        const data = err.response?.data;
        guestError.value = data?.errors?.nickname?.[0] || data?.message || t('common.error');
    } finally {
        guestLoading.value = false;
    }
}

async function handleLogin() {
    loading.value = true;
    error.value = '';
    Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k]);

    try {
        await authStore.login(form.email, form.password, form.twoFactorCode || undefined);
        router.visit(getSafeRedirect());
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
