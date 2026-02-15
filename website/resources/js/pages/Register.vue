<template>
    <div class="max-w-md mx-auto px-4 py-12 sm:py-20">
        <h1 class="text-3xl font-bold text-center mb-8 text-slate-800 dark:text-slate-200">
            {{ t('auth.register.title') }}
        </h1>

        <!-- Guest conversion notice -->
        <div v-if="isConvertingGuest" class="p-3 rounded-lg bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-900 text-sm text-emerald-700 dark:text-emerald-300 mb-6">
            {{ t('auth.register.convertNotice') }}
        </div>

        <!-- Registration form -->
        <form @submit.prevent="handleRegister" class="space-y-5">
            <div v-if="error" class="p-3 rounded-lg bg-red-50 dark:bg-red-950/50 border border-red-200 dark:border-red-900 text-sm text-red-700 dark:text-red-300">
                {{ error }}
            </div>

            <div class="flex flex-col gap-2">
                <label for="name" class="text-sm font-medium text-slate-700 dark:text-slate-300">
                    {{ t('auth.register.name') }}
                </label>
                <InputText
                    id="name"
                    v-model="form.name"
                    autocomplete="name"
                    :invalid="!!fieldErrors.name"
                    class="w-full"
                />
                <small v-if="fieldErrors.name" class="text-red-500">{{ fieldErrors.name }}</small>
            </div>

            <div class="flex flex-col gap-2">
                <label for="email" class="text-sm font-medium text-slate-700 dark:text-slate-300">
                    {{ t('auth.register.email') }}
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
                <label for="nickname" class="text-sm font-medium text-slate-700 dark:text-slate-300">
                    {{ t('auth.register.nickname') }}
                </label>
                <InputText
                    id="nickname"
                    v-model="form.nickname"
                    autocomplete="username"
                    :invalid="!!fieldErrors.nickname"
                    class="w-full"
                />
                <small v-if="fieldErrors.nickname" class="text-red-500">{{ fieldErrors.nickname }}</small>
            </div>

            <div class="flex flex-col gap-2">
                <label for="password" class="text-sm font-medium text-slate-700 dark:text-slate-300">
                    {{ t('auth.register.password') }}
                </label>
                <Password
                    id="password"
                    v-model="form.password"
                    toggleMask
                    :invalid="!!fieldErrors.password"
                    inputClass="w-full"
                    class="w-full"
                />
                <small v-if="fieldErrors.password" class="text-red-500">{{ fieldErrors.password }}</small>
            </div>

            <div class="flex flex-col gap-2">
                <label for="password_confirmation" class="text-sm font-medium text-slate-700 dark:text-slate-300">
                    {{ t('auth.register.confirmPassword') }}
                </label>
                <Password
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    :feedback="false"
                    toggleMask
                    inputClass="w-full"
                    class="w-full"
                />
            </div>

            <Button
                type="submit"
                :label="t('auth.register.submit')"
                severity="success"
                :loading="loading"
                class="w-full"
            />
        </form>

        <!-- Divider -->
        <div v-if="!isConvertingGuest" class="flex items-center gap-4 my-8">
            <div class="flex-1 h-px bg-slate-200 dark:bg-slate-800"></div>
            <span class="text-sm text-slate-400 dark:text-slate-500">{{ t('auth.or') }}</span>
            <div class="flex-1 h-px bg-slate-200 dark:bg-slate-800"></div>
        </div>

        <!-- Guest section (hide when already a guest) -->
        <div v-if="!isConvertingGuest" class="p-6 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
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
                    severity="secondary"
                    :loading="guestLoading"
                    class="w-full"
                />
            </form>
        </div>

        <p class="mt-6 text-center text-sm text-slate-500 dark:text-slate-400">
            {{ t('auth.register.hasAccount') }}
            <Link href="/login" class="text-emerald-600 dark:text-emerald-400 font-medium hover:underline">
                {{ t('auth.register.login') }}
            </Link>
        </p>
    </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Button from 'primevue/button';
import { useAuthStore } from '../stores/authStore.js';
import { useI18n } from '../composables/useI18n.js';

const authStore = useAuthStore();
const { t } = useI18n();

const isConvertingGuest = authStore.isAuthenticated && authStore.isGuest;

const form = reactive({
    name: '',
    email: '',
    nickname: authStore.nickname || '',
    password: '',
    password_confirmation: '',
});

const loading = ref(false);
const error = ref('');
const fieldErrors = reactive({});

const guestNickname = ref('');
const guestLoading = ref(false);
const guestError = ref('');

async function handleRegister() {
    loading.value = true;
    error.value = '';
    Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k]);

    try {
        // If user is a guest, convert their account instead of creating new
        if (authStore.isAuthenticated && authStore.isGuest) {
            await authStore.convertGuest({ ...form });
        } else {
            await authStore.register({ ...form });
        }
        const urlParams = new URLSearchParams(window.location.search);
        const redirect = urlParams.get('redirect') || '/games';
        router.visit(redirect);
    } catch (err) {
        const status = err.response?.status;
        const data = err.response?.data;

        if (status === 422 && data?.errors) {
            Object.assign(fieldErrors, Object.fromEntries(
                Object.entries(data.errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v])
            ));
        } else {
            error.value = data?.message || t('common.error');
        }
    } finally {
        loading.value = false;
    }
}

async function handleGuest() {
    if (!guestNickname.value.trim()) return;

    guestLoading.value = true;
    guestError.value = '';

    try {
        await authStore.createGuest(guestNickname.value.trim());
        const urlParams = new URLSearchParams(window.location.search);
        const redirect = urlParams.get('redirect') || '/games';
        router.visit(redirect);
    } catch (err) {
        guestError.value = err.response?.data?.message || t('common.error');
    } finally {
        guestLoading.value = false;
    }
}
</script>
