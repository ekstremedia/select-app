<template>
    <div class="max-w-2xl mx-auto px-4 py-8 sm:py-12">
        <h1 class="text-3xl font-bold mb-8 text-slate-800 dark:text-slate-200">
            {{ t('profile.settings.title') }}
        </h1>

        <!-- Success message -->
        <div v-if="successMessage" class="p-3 rounded-lg bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-900 text-sm text-emerald-700 dark:text-emerald-300 mb-6">
            {{ successMessage }}
        </div>

        <div class="space-y-8">
            <!-- Nickname section -->
            <section class="p-6 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                <h2 class="text-lg font-semibold mb-4 text-slate-800 dark:text-slate-200">
                    {{ t('auth.register.nickname') }}
                </h2>
                <form @submit.prevent="updateNickname" class="flex flex-col sm:flex-row gap-3">
                    <InputText
                        v-model="nicknameForm.nickname"
                        class="flex-1"
                    />
                    <Button
                        type="submit"
                        :label="t('common.save')"
                        severity="success"
                        :loading="nicknameForm.loading"
                    />
                </form>
                <small v-if="nicknameForm.error" class="text-red-500 mt-2 block">{{ nicknameForm.error }}</small>
            </section>

            <!-- Password section -->
            <section class="p-6 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                <h2 class="text-lg font-semibold mb-4 text-slate-800 dark:text-slate-200">
                    {{ t('profile.settings.changePassword') }}
                </h2>
                <form @submit.prevent="updatePassword" class="space-y-4">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-slate-700 dark:text-slate-300">
                            {{ t('auth.login.password') }}
                        </label>
                        <Password
                            v-model="passwordForm.current_password"
                            :feedback="false"
                            toggleMask
                            inputClass="w-full"
                            class="w-full"
                        />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-slate-700 dark:text-slate-300">
                            {{ t('auth.resetPassword.newPassword') }}
                        </label>
                        <Password
                            v-model="passwordForm.password"
                            toggleMask
                            inputClass="w-full"
                            class="w-full"
                        />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-slate-700 dark:text-slate-300">
                            {{ t('auth.resetPassword.confirmPassword') }}
                        </label>
                        <Password
                            v-model="passwordForm.password_confirmation"
                            :feedback="false"
                            toggleMask
                            inputClass="w-full"
                            class="w-full"
                        />
                    </div>
                    <Button
                        type="submit"
                        :label="t('common.save')"
                        severity="success"
                        :loading="passwordForm.loading"
                    />
                    <small v-if="passwordForm.error" class="text-red-500 block">{{ passwordForm.error }}</small>
                </form>
            </section>

            <!-- Two-factor authentication -->
            <section class="p-6 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-800 dark:text-slate-200">
                            {{ t('profile.settings.twoFactor') }}
                        </h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                            {{ t('auth.login.twoFactorCode') }}
                        </p>
                    </div>
                    <ToggleSwitch v-model="twoFactorEnabled" @update:modelValue="toggleTwoFactor" />
                </div>
            </section>

            <!-- Preferences -->
            <section class="p-6 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                <h2 class="text-lg font-semibold mb-4 text-slate-800 dark:text-slate-200">
                    {{ t('profile.settings.title') }}
                </h2>
                <div class="space-y-4">
                    <!-- Sound -->
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">
                            {{ t('profile.settings.sound') }}
                        </span>
                        <ToggleSwitch v-model="soundEnabled" @update:modelValue="toggleSound" />
                    </div>

                    <!-- Dark mode -->
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">
                            {{ t('nav.darkMode') }}
                        </span>
                        <ToggleSwitch v-model="isDark" @update:modelValue="toggleDark" />
                    </div>

                    <!-- Language -->
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">
                            {{ t('nav.language') === 'EN' ? 'Norsk' : 'English' }}
                        </span>
                        <Button
                            :label="t('nav.language')"
                            severity="secondary"
                            size="small"
                            variant="outlined"
                            @click="toggleLocale"
                        />
                    </div>
                </div>
            </section>

            <!-- Danger zone -->
            <section class="p-6 rounded-2xl border-2 border-red-200 dark:border-red-900">
                <h2 class="text-lg font-semibold mb-2 text-red-600 dark:text-red-400">
                    {{ t('profile.settings.deleteAccount') }}
                </h2>
                <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">
                    {{ t('profile.settings.deleteWarning') }}
                </p>
                <Button
                    :label="t('profile.settings.deleteAccount')"
                    severity="danger"
                    variant="outlined"
                    size="small"
                    @click="showDeleteDialog = true"
                />
                <Dialog
                    v-model:visible="showDeleteDialog"
                    :header="t('profile.settings.deleteAccount')"
                    modal
                    :style="{ width: '25rem' }"
                >
                    <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">
                        {{ t('common.confirm') }}?
                    </p>
                    <div class="flex justify-end gap-2">
                        <Button :label="t('common.cancel')" severity="secondary" variant="text" @click="showDeleteDialog = false" />
                        <Button :label="t('common.confirm')" severity="danger" :loading="deleteLoading" @click="handleDeleteAccount" />
                    </div>
                </Dialog>
            </section>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { storeToRefs } from 'pinia';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Button from 'primevue/button';
import ToggleSwitch from 'primevue/toggleswitch';
import Dialog from 'primevue/dialog';
import { useAuthStore } from '../stores/authStore.js';
import { useSoundStore } from '../stores/soundStore.js';
import { useI18n } from '../composables/useI18n.js';
import { useDarkMode } from '../composables/useDarkMode.js';
import { api } from '../services/api.js';

const authStore = useAuthStore();
const soundStore = useSoundStore();
const { t, toggleLocale } = useI18n();
const { isDark, toggleDark } = useDarkMode();

const { enabled: soundEnabled } = storeToRefs(soundStore);

const successMessage = ref('');
const showDeleteDialog = ref(false);
const deleteLoading = ref(false);
const twoFactorEnabled = ref(false);

const nicknameForm = reactive({
    nickname: authStore.nickname || '',
    loading: false,
    error: '',
});

const passwordForm = reactive({
    current_password: '',
    password: '',
    password_confirmation: '',
    loading: false,
    error: '',
});

function showSuccess(msg) {
    successMessage.value = msg;
    setTimeout(() => { successMessage.value = ''; }, 3000);
}

async function updateNickname() {
    nicknameForm.loading = true;
    nicknameForm.error = '';

    try {
        const { data } = await api.profile.updateNickname(nicknameForm.nickname);
        authStore.player = { ...authStore.player, nickname: data.player.nickname };
        showSuccess(t('common.save'));
    } catch (err) {
        nicknameForm.error = err.response?.data?.message || err.response?.data?.errors?.nickname?.[0] || t('common.error');
    } finally {
        nicknameForm.loading = false;
    }
}

async function updatePassword() {
    passwordForm.loading = true;
    passwordForm.error = '';

    try {
        await api.profile.updatePassword({
            current_password: passwordForm.current_password,
            password: passwordForm.password,
            password_confirmation: passwordForm.password_confirmation,
        });
        passwordForm.current_password = '';
        passwordForm.password = '';
        passwordForm.password_confirmation = '';
        showSuccess(t('common.save'));
    } catch (err) {
        passwordForm.error = err.response?.data?.message || t('common.error');
    } finally {
        passwordForm.loading = false;
    }
}

async function toggleTwoFactor(value) {
    try {
        if (value) {
            await api.auth.twoFactor.enable();
        } else {
            await api.auth.twoFactor.disable('');
        }
    } catch {
        twoFactorEnabled.value = !value;
    }
}

async function handleDeleteAccount() {
    deleteLoading.value = true;
    try {
        await api.profile.deleteAccount();
        authStore.clearAuth();
        showDeleteDialog.value = false;
        router.visit('/');
    } catch {
        deleteLoading.value = false;
    }
}

function toggleSound() {
    soundStore.toggle();
}

onMounted(() => {
    nicknameForm.nickname = authStore.nickname || '';
});
</script>
