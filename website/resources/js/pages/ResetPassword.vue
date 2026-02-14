<template>
    <div class="max-w-md mx-auto px-4 py-12 sm:py-20">
        <h1 class="text-3xl font-bold text-center mb-8 text-slate-800 dark:text-slate-200">
            {{ t('auth.resetPassword.title') }}
        </h1>

        <!-- Success message -->
        <div v-if="success" class="text-center">
            <div class="p-4 rounded-lg bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-900 text-sm text-emerald-700 dark:text-emerald-300 mb-6">
                {{ t('auth.forgotPassword.sent') }}
            </div>
            <router-link to="/login" class="text-emerald-600 dark:text-emerald-400 font-medium hover:underline">
                {{ t('auth.login.title') }}
            </router-link>
        </div>

        <form v-else @submit.prevent="handleReset" class="space-y-5">
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
                    class="w-full"
                />
                <small v-if="fieldErrors.email" class="text-red-500">{{ fieldErrors.email }}</small>
            </div>

            <div class="flex flex-col gap-2">
                <label for="password" class="text-sm font-medium text-slate-700 dark:text-slate-300">
                    {{ t('auth.resetPassword.newPassword') }}
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
                    {{ t('auth.resetPassword.confirmPassword') }}
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
                :label="t('auth.resetPassword.submit')"
                severity="success"
                :loading="loading"
                class="w-full"
            />
        </form>
    </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { useRoute } from 'vue-router';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Button from 'primevue/button';
import { api } from '../services/api.js';
import { useI18n } from '../composables/useI18n.js';

const route = useRoute();
const { t } = useI18n();

const form = reactive({
    email: '',
    password: '',
    password_confirmation: '',
});

const loading = ref(false);
const error = ref('');
const success = ref(false);
const fieldErrors = reactive({});

async function handleReset() {
    loading.value = true;
    error.value = '';
    Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k]);

    try {
        await api.auth.resetPassword({
            token: route.params.token,
            email: form.email,
            password: form.password,
            password_confirmation: form.password_confirmation,
        });
        success.value = true;
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
</script>
