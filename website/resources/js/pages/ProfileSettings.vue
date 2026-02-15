<template>
    <div class="max-w-2xl mx-auto px-4 py-8 sm:py-12">
        <!-- User header -->
        <div class="flex items-center gap-4 mb-10">
            <div class="w-16 h-16 rounded-full bg-emerald-100 dark:bg-emerald-900/50 flex items-center justify-center text-2xl font-bold text-emerald-600 dark:text-emerald-400 shrink-0">
                {{ authStore.nickname?.charAt(0)?.toUpperCase() }}
            </div>
            <div class="min-w-0">
                <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-200 truncate">
                    {{ authStore.nickname }}
                </h1>
                <p v-if="authStore.user?.email" class="text-sm text-slate-500 dark:text-slate-400 truncate">
                    {{ authStore.user.email }}
                </p>
            </div>
        </div>

        <div class="space-y-6">
            <!-- Account section -->
            <section class="rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 overflow-hidden">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 px-6 pt-5 pb-3">
                    {{ t('profile.settings.account') }}
                </h2>

                <!-- Name field -->
                <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-800">
                    <div class="flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <label class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ t('profile.settings.name') }}</label>
                            <p v-if="!editing.name" class="text-slate-800 dark:text-slate-200 truncate">
                                {{ authStore.user?.name || '—' }}
                            </p>
                        </div>
                        <Button
                            v-if="!editing.name"
                            :label="t('game.edit')"
                            severity="secondary"
                            variant="text"
                            size="small"
                            @click="startEdit('name', authStore.user?.name || '')"
                        />
                    </div>
                    <form v-if="editing.name" @submit.prevent="saveName" class="mt-2 flex gap-2">
                        <InputText v-model="fields.name" class="flex-1" autofocus />
                        <Button type="submit" :label="t('common.save')" severity="success" size="small" :loading="saving.name" />
                        <Button :label="t('common.cancel')" severity="secondary" variant="text" size="small" @click="editing.name = false" />
                    </form>
                    <small v-if="errors.name" class="text-red-500 mt-1 block">{{ errors.name }}</small>
                    <small v-if="saved.name" class="text-emerald-600 dark:text-emerald-400 mt-1 block">{{ t('profile.settings.saved') }}</small>
                </div>

                <!-- Nickname field -->
                <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-800">
                    <div class="flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <label class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ t('profile.settings.nickname') }}</label>
                            <p v-if="!editing.nickname" class="text-slate-800 dark:text-slate-200 truncate">
                                {{ authStore.nickname }}
                            </p>
                        </div>
                        <Button
                            v-if="!editing.nickname"
                            :label="t('game.edit')"
                            severity="secondary"
                            variant="text"
                            size="small"
                            @click="startEdit('nickname', authStore.nickname || '')"
                        />
                    </div>
                    <form v-if="editing.nickname" @submit.prevent="saveNickname" class="mt-2 flex gap-2">
                        <InputText v-model="fields.nickname" class="flex-1" autofocus />
                        <Button type="submit" :label="t('common.save')" severity="success" size="small" :loading="saving.nickname" />
                        <Button :label="t('common.cancel')" severity="secondary" variant="text" size="small" @click="editing.nickname = false" />
                    </form>
                    <small v-if="errors.nickname" class="text-red-500 mt-1 block">{{ errors.nickname }}</small>
                    <small v-if="saved.nickname" class="text-emerald-600 dark:text-emerald-400 mt-1 block">{{ t('profile.settings.saved') }}</small>
                </div>

                <!-- Email field -->
                <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-800">
                    <div class="flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <label class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ t('profile.settings.email') }}</label>
                            <p v-if="!editing.email" class="text-slate-800 dark:text-slate-200 truncate">
                                {{ authStore.user?.email || '—' }}
                            </p>
                        </div>
                        <Button
                            v-if="!editing.email"
                            :label="t('game.edit')"
                            severity="secondary"
                            variant="text"
                            size="small"
                            @click="startEdit('email', authStore.user?.email || '')"
                        />
                    </div>
                    <form v-if="editing.email" @submit.prevent="saveEmail" class="mt-2 flex gap-2">
                        <InputText v-model="fields.email" type="email" class="flex-1" autofocus />
                        <Button type="submit" :label="t('common.save')" severity="success" size="small" :loading="saving.email" />
                        <Button :label="t('common.cancel')" severity="secondary" variant="text" size="small" @click="editing.email = false" />
                    </form>
                    <small v-if="errors.email" class="text-red-500 mt-1 block">{{ errors.email }}</small>
                    <small v-if="saved.email" class="text-emerald-600 dark:text-emerald-400 mt-1 block">{{ t('profile.settings.saved') }}</small>
                </div>
            </section>

            <!-- Password section -->
            <section class="rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 overflow-hidden">
                <div class="px-6 pt-5 pb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        {{ t('profile.settings.changePassword') }}
                    </h2>
                    <Button
                        v-if="!editing.password"
                        :label="t('game.edit')"
                        severity="secondary"
                        variant="text"
                        size="small"
                        @click="editing.password = true"
                    />
                </div>

                <div v-if="!editing.password" class="px-6 pb-5">
                    <p class="text-sm text-slate-500 dark:text-slate-400">••••••••</p>
                </div>

                <form v-else @submit.prevent="savePassword" class="px-6 pb-5 space-y-4">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ t('profile.settings.currentPassword') }}</label>
                        <Password
                            v-model="passwordForm.current_password"
                            :feedback="false"
                            toggleMask
                            inputClass="w-full"
                            class="w-full"
                        />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ t('profile.settings.newPassword') }}</label>
                        <Password
                            v-model="passwordForm.password"
                            toggleMask
                            inputClass="w-full"
                            class="w-full"
                        />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ t('profile.settings.confirmPassword') }}</label>
                        <Password
                            v-model="passwordForm.password_confirmation"
                            :feedback="false"
                            toggleMask
                            inputClass="w-full"
                            class="w-full"
                        />
                    </div>
                    <div class="flex gap-2">
                        <Button type="submit" :label="t('common.save')" severity="success" size="small" :loading="saving.password" />
                        <Button :label="t('common.cancel')" severity="secondary" variant="text" size="small" @click="cancelPassword" />
                    </div>
                    <small v-if="errors.password" class="text-red-500 block">{{ errors.password }}</small>
                    <small v-if="saved.password" class="text-emerald-600 dark:text-emerald-400 block">{{ t('profile.settings.saved') }}</small>
                </form>
            </section>

            <!-- Two-factor authentication -->
            <section class="rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 px-6 py-5">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            {{ t('profile.settings.twoFactor') }}
                        </h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                            {{ t('auth.login.twoFactorCode') }}
                        </p>
                    </div>
                    <ToggleSwitch v-model="twoFactorEnabled" @update:modelValue="toggleTwoFactor" />
                </div>
            </section>

            <!-- Danger zone -->
            <section class="rounded-2xl border-2 border-red-200 dark:border-red-900 px-6 py-5">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-red-500 dark:text-red-400 mb-2">
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
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Button from 'primevue/button';
import ToggleSwitch from 'primevue/toggleswitch';
import Dialog from 'primevue/dialog';
import { useAuthStore } from '../stores/authStore.js';
import { useI18n } from '../composables/useI18n.js';
import { api } from '../services/api.js';

const authStore = useAuthStore();
const { t } = useI18n();

const showDeleteDialog = ref(false);
const deleteLoading = ref(false);
const twoFactorEnabled = ref(false);

// Inline editing state
const editing = reactive({ name: false, nickname: false, email: false, password: false });
const fields = reactive({ name: '', nickname: '', email: '' });
const saving = reactive({ name: false, nickname: false, email: false, password: false });
const errors = reactive({ name: '', nickname: '', email: '', password: '' });
const saved = reactive({ name: false, nickname: false, email: false, password: false });

const passwordForm = reactive({
    current_password: '',
    password: '',
    password_confirmation: '',
});

let savedTimers = {};

function startEdit(field, value) {
    fields[field] = value;
    errors[field] = '';
    saved[field] = false;
    editing[field] = true;
}

function showSaved(field) {
    saved[field] = true;
    editing[field] = false;
    if (savedTimers[field]) clearTimeout(savedTimers[field]);
    savedTimers[field] = setTimeout(() => { saved[field] = false; }, 2000);
}

function extractError(err, field) {
    return err.response?.data?.errors?.[field]?.[0]
        || err.response?.data?.message
        || t('common.error');
}

async function saveName() {
    saving.name = true;
    errors.name = '';
    try {
        const { data } = await api.profile.update({ name: fields.name });
        authStore.user = { ...authStore.user, name: data.user.name };
        showSaved('name');
    } catch (err) {
        errors.name = extractError(err, 'name');
    } finally {
        saving.name = false;
    }
}

async function saveNickname() {
    saving.nickname = true;
    errors.nickname = '';
    try {
        const { data } = await api.profile.updateNickname(fields.nickname);
        authStore.player = { ...authStore.player, nickname: data.player.nickname };
        showSaved('nickname');
    } catch (err) {
        errors.nickname = extractError(err, 'nickname');
    } finally {
        saving.nickname = false;
    }
}

async function saveEmail() {
    saving.email = true;
    errors.email = '';
    try {
        const { data } = await api.profile.update({ email: fields.email });
        authStore.user = { ...authStore.user, email: data.user.email };
        showSaved('email');
    } catch (err) {
        errors.email = extractError(err, 'email');
    } finally {
        saving.email = false;
    }
}

async function savePassword() {
    saving.password = true;
    errors.password = '';
    try {
        await api.profile.updatePassword({
            current_password: passwordForm.current_password,
            password: passwordForm.password,
            password_confirmation: passwordForm.password_confirmation,
        });
        cancelPassword();
        showSaved('password');
    } catch (err) {
        errors.password = extractError(err, 'password');
    } finally {
        saving.password = false;
    }
}

function cancelPassword() {
    editing.password = false;
    passwordForm.current_password = '';
    passwordForm.password = '';
    passwordForm.password_confirmation = '';
    errors.password = '';
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

onMounted(() => {
    // Pre-fill with current values
    fields.name = authStore.user?.name || '';
    fields.nickname = authStore.nickname || '';
    fields.email = authStore.user?.email || '';
});
</script>
