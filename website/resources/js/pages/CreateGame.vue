<template>
    <div class="max-w-2xl mx-auto px-4 py-8 sm:py-12">
        <h1 class="text-3xl font-bold mb-8 text-slate-800 dark:text-slate-200">
            {{ t('create.title') }}
        </h1>

        <form @submit.prevent="handleCreate" class="space-y-6">
            <div v-if="error" class="p-3 rounded-lg bg-red-50 dark:bg-red-950/50 border border-red-200 dark:border-red-900 text-sm text-red-700 dark:text-red-300">
                {{ error }}
            </div>

            <!-- Rounds -->
            <div class="p-6 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-5">
                <div>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300 mb-2 block">
                        {{ t('create.rounds') }}: {{ settings.rounds }}
                    </label>
                    <Slider v-model="settings.rounds" :min="1" :max="20" :step="1" class="w-full px-3" />
                </div>

                <!-- Answer time -->
                <div>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300 mb-2 block">
                        {{ t('create.answerTime') }}: {{ settings.answer_time }} {{ t('create.seconds') }}
                    </label>
                    <Slider v-model="settings.answer_time" :min="15" :max="180" :step="5" class="w-full px-3" />
                </div>

                <!-- Vote time -->
                <div>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300 mb-2 block">
                        {{ t('create.voteTime') }}: {{ settings.vote_time }} {{ t('create.seconds') }}
                    </label>
                    <Slider v-model="settings.vote_time" :min="10" :max="120" :step="5" class="w-full px-3" />
                </div>

                <!-- Time between rounds -->
                <div>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300 mb-2 block">
                        {{ t('create.timeBetweenRounds') }}: {{ settings.time_between_rounds }} {{ t('create.seconds') }}
                    </label>
                    <Slider v-model="settings.time_between_rounds" :min="3" :max="120" :step="1" class="w-full px-3" />
                </div>

                <!-- Max edits -->
                <div>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300 mb-1 block">
                        {{ t('create.maxEdits') }}: {{ settings.max_edits === 0 ? t('create.unlimited') : settings.max_edits }}
                    </label>
                    <p class="text-xs text-slate-400 mb-2">{{ t('create.maxEditsDesc') }}</p>
                    <Slider v-model="settings.max_edits" :min="0" :max="10" :step="1" class="w-full px-3" />
                </div>

                <!-- Max vote changes -->
                <div>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300 mb-1 block">
                        {{ t('create.maxVoteChanges') }}: {{ settings.max_vote_changes === 0 ? t('create.unlimited') : settings.max_vote_changes }}
                    </label>
                    <p class="text-xs text-slate-400 mb-2">{{ t('create.maxVoteChangesDesc') }}</p>
                    <Slider v-model="settings.max_vote_changes" :min="0" :max="10" :step="1" class="w-full px-3" />
                </div>

                <!-- Ready check -->
                <div class="flex items-center justify-between">
                    <div>
                        <label for="readyCheck" class="text-sm font-medium text-slate-700 dark:text-slate-300 cursor-pointer">
                            {{ t('create.readyCheck') }}
                        </label>
                        <p class="text-xs text-slate-400">{{ t('create.readyCheckDesc') }}</p>
                    </div>
                    <ToggleSwitch v-model="settings.allow_ready_check" inputId="readyCheck" />
                </div>

                <!-- Chat -->
                <div class="flex items-center justify-between">
                    <label for="chatEnabled" class="text-sm font-medium text-slate-700 dark:text-slate-300 cursor-pointer">
                        {{ t('create.chat') }}
                    </label>
                    <ToggleSwitch v-model="settings.chat_enabled" inputId="chatEnabled" />
                </div>
            </div>

            <!-- Acronym settings -->
            <div class="p-6 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-5">
                <div>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300 mb-2 block">
                        {{ t('create.acronymLength') }}: {{ settings.acronym_length }}
                    </label>
                    <Slider v-model="settings.acronym_length" :min="1" :max="6" :step="1" class="w-full px-3" />
                </div>

                <!-- Max players -->
                <div>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300 mb-2 block">
                        {{ t('create.maxPlayers') }}: {{ settings.max_players }}
                    </label>
                    <Slider v-model="settings.max_players" :min="2" :max="16" :step="1" class="w-full px-3" />
                </div>

                <!-- Excluded letters -->
                <div>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300 mb-2 block">
                        {{ t('create.excludeLetters') }}
                    </label>
                    <InputText
                        v-model="settings.excluded_letters"
                        class="w-full uppercase tracking-[0.2em] font-mono"
                        :placeholder="'XZQ'"
                        @input="settings.excluded_letters = settings.excluded_letters.toUpperCase().replace(/[^A-Z]/g, '')"
                    />
                </div>
            </div>

            <!-- Visibility -->
            <div class="p-6 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-4">
                <div>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300 mb-2 block">
                        {{ t('create.visibility') }}
                    </label>
                    <div class="flex gap-3">
                        <Button
                            :label="t('create.public')"
                            :severity="!settings.is_private ? 'success' : 'secondary'"
                            :variant="!settings.is_private ? undefined : 'outlined'"
                            size="small"
                            @click="settings.is_private = false"
                        />
                        <Button
                            :label="t('create.private')"
                            :severity="settings.is_private ? 'success' : 'secondary'"
                            :variant="settings.is_private ? undefined : 'outlined'"
                            size="small"
                            @click="settings.is_private = true"
                        />
                    </div>
                </div>

                <!-- Password (if private) -->
                <div v-if="settings.is_private" class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">
                        {{ t('create.password') }}
                    </label>
                    <InputText
                        v-model="settings.password"
                        type="text"
                        class="w-full"
                    />
                </div>
            </div>

            <!-- Bot players -->
            <div class="p-6 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                <div class="flex items-center gap-3">
                    <Checkbox v-model="settings.add_bots" :binary="true" inputId="addBots" />
                    <div>
                        <label for="addBots" class="text-sm font-medium text-slate-700 dark:text-slate-300 cursor-pointer">
                            {{ t('create.addBots') }}
                        </label>
                        <p class="text-xs text-slate-400">{{ t('create.addBotsDesc') }}</p>
                    </div>
                </div>
            </div>

            <div class="flex gap-3">
                <Button
                    :label="t('common.back')"
                    severity="secondary"
                    variant="outlined"
                    class="flex-1"
                    @click="router.visit('/spill')"
                />
                <Button
                    type="submit"
                    :label="t('create.submit')"
                    severity="success"
                    :loading="loading"
                    class="flex-1"
                />
            </div>
        </form>
    </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import Slider from 'primevue/slider';
import Checkbox from 'primevue/checkbox';
import ToggleSwitch from 'primevue/toggleswitch';
import { useGameStore } from '../stores/gameStore.js';
import { useAuthStore } from '../stores/authStore.js';
import { useI18n } from '../composables/useI18n.js';

const gameStore = useGameStore();
const authStore = useAuthStore();
const { t } = useI18n();

const settings = reactive({
    rounds: 8,
    answer_time: 60,
    vote_time: 30,
    time_between_rounds: 30,
    acronym_length: 5,
    max_players: 8,
    excluded_letters: '',
    is_private: false,
    password: '',
    chat_enabled: true,
    allow_ready_check: true,
    max_edits: 0,
    max_vote_changes: 0,
    add_bots: false,
});

const loading = ref(false);
const error = ref('');

async function handleCreate() {
    loading.value = true;
    error.value = '';

    try {
        const gameSettings = {
            rounds: settings.rounds,
            answer_time: settings.answer_time,
            vote_time: settings.vote_time,
            time_between_rounds: settings.time_between_rounds,
            acronym_length_min: settings.acronym_length,
            acronym_length_max: settings.acronym_length,
            max_players: settings.max_players,
            chat_enabled: settings.chat_enabled,
            allow_ready_check: settings.allow_ready_check,
            max_edits: settings.max_edits,
            max_vote_changes: settings.max_vote_changes,
        };
        if (settings.excluded_letters) {
            gameSettings.excluded_letters = settings.excluded_letters;
        }

        const payload = {
            settings: gameSettings,
            is_public: !settings.is_private,
        };
        if (settings.is_private && settings.password) {
            payload.password = settings.password;
        }
        if (settings.add_bots) {
            payload.add_bots = true;
        }

        const data = await gameStore.createGame(payload);
        const code = data.game?.code || gameStore.gameCode;
        router.visit(`/spill/${code}`);
    } catch (err) {
        error.value = err.response?.data?.message || t('common.error');
    } finally {
        loading.value = false;
    }
}
</script>
