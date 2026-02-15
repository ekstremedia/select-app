<template>
    <GameLayout
        :game-code="gameStore.gameCode || props.code"
        :player-count="gameStore.players.length"
        @leave="handleLeave"
    >
        <div class="flex flex-col h-full overflow-hidden">
            <!-- Loading state -->
            <div v-if="loading" class="flex-1 flex items-center justify-center">
                <ProgressBar mode="indeterminate" class="w-48" />
            </div>

            <!-- Error state -->
            <div v-else-if="error" class="flex-1 flex flex-col items-center justify-center px-4">
                <p class="text-lg text-slate-500 dark:text-slate-400 mb-4">{{ error }}</p>
                <Button :label="t('common.retry')" severity="secondary" @click="initGame" />
            </div>

            <!-- Game content -->
            <template v-else>
                <!-- Timer bar -->
                <div v-if="showTimer" class="shrink-0 px-4 py-2 bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs text-slate-500 dark:text-slate-400">
                            {{ t('game.round') }} {{ gameStore.currentRound?.round_number ?? 1 }} {{ t('game.of') }} {{ totalRounds }}
                        </span>
                        <span class="text-sm font-mono font-bold" :class="gameStore.timeRemaining <= 10 ? 'text-red-500' : 'text-slate-700 dark:text-slate-300'">
                            {{ gameStore.timeRemaining }}s
                        </span>
                    </div>
                    <ProgressBar
                        :value="timerPercent"
                        :showValue="false"
                        :class="{ 'timer-warning': gameStore.timeRemaining <= 10 }"
                        style="height: 4px"
                    />
                </div>

                <!-- Phase: Lobby -->
                <div v-if="phase === 'lobby'" class="flex-1 overflow-y-auto">
                    <!-- Lobby expiring warning -->
                    <div v-if="gameStore.lobbyExpiring" class="mx-4 mt-4 p-4 rounded-xl bg-amber-50 dark:bg-amber-950/50 border border-amber-300 dark:border-amber-700 text-center">
                        <p class="text-sm font-medium text-amber-700 dark:text-amber-300 mb-2">
                            {{ t('lobby.expiringWarning') }}
                        </p>
                        <Button
                            :label="t('lobby.keepOpen')"
                            severity="warn"
                            size="small"
                            @click="handleKeepalive"
                        />
                    </div>

                    <div class="max-w-md mx-auto px-4 py-8 text-center">
                        <h2 class="text-2xl font-bold mb-2 text-slate-800 dark:text-slate-200">
                            {{ t('lobby.title') }}
                        </h2>

                        <!-- Game code display -->
                        <div class="my-6 p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-900">
                            <p class="text-xs text-emerald-600 dark:text-emerald-400 mb-1">{{ t('lobby.gameCode') }}</p>
                            <p class="text-xl font-mono font-bold tracking-[0.4em] text-emerald-700 dark:text-emerald-300">
                                {{ gameStore.gameCode }}
                            </p>
                            <div class="flex items-center justify-center gap-2 mt-3">
                                <Button
                                    :label="copiedLink ? t('lobby.copied') : t('lobby.shareLink')"
                                    size="small"
                                    severity="success"
                                    @click="copyLink"
                                />
                                <Button
                                    :label="t('lobby.inviteEmail')"
                                    size="small"
                                    severity="secondary"
                                    @click="inviteDialogVisible = true"
                                />
                            </div>
                        </div>

                        <!-- Start / End buttons (host only) -->
                        <div v-if="gameStore.isHost" class="mb-6">
                            <Button
                                :label="t('lobby.startGame')"
                                severity="success"
                                size="large"
                                class="w-full"
                                :disabled="gameStore.players.length < 2"
                                :loading="startLoading"
                                @click="handleStart"
                            />
                            <p v-if="gameStore.players.length < 2" class="text-sm text-slate-400 mt-2">
                                {{ t('lobby.needMorePlayers') }}
                            </p>
                            <Button
                                :label="t('lobby.endGame')"
                                severity="danger"
                                variant="text"
                                size="small"
                                class="w-full mt-2"
                                @click="handleEndGame"
                            />
                        </div>
                        <p v-else class="text-sm text-slate-500 dark:text-slate-400 mb-6">
                            {{ t('lobby.waitingForHost') }}
                        </p>

                        <!-- Player list -->
                        <div class="space-y-1 mb-6">
                            <div
                                v-for="player in gameStore.players"
                                :key="player.id"
                                class="flex items-center justify-between px-3 py-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-900 transition-colors"
                            >
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="font-medium text-slate-800 dark:text-slate-200 truncate">{{ player.nickname }}</span>
                                    <span v-if="player.id === gameStore.currentGame?.host_player_id" class="text-xs text-yellow-600 dark:text-yellow-400">{{ t('lobby.host') }}</span>
                                    <span v-else-if="player.is_co_host" class="text-xs text-blue-500 dark:text-blue-400">{{ t('lobby.coHost') }}</span>
                                    <span v-else-if="player.is_bot" class="text-xs text-slate-400">{{ t('lobby.bot') }}</span>
                                </div>
                                <button
                                    v-if="canManagePlayer(player)"
                                    class="w-7 h-7 shrink-0 rounded-md flex items-center justify-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-800 transition-colors"
                                    @click="togglePlayerMenu($event, player)"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                                        <path d="M3 10a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0zM8.5 10a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0zM15.5 8.5a1.5 1.5 0 100 3 1.5 1.5 0 000-3z" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <Popover ref="playerMenuRef">
                            <div class="py-1 min-w-[10rem]">
                                <!-- Bot: just remove -->
                                <template v-if="menuPlayer?.is_bot">
                                    <button
                                        class="w-full text-left px-3 py-2 text-sm hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors flex items-center gap-2 text-amber-600 dark:text-amber-400"
                                        @click="handleMenuRemoveBot"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                                            <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                                        </svg>
                                        {{ t('lobby.removeBot') }}
                                    </button>
                                </template>
                                <!-- Human: co-host, kick, ban -->
                                <template v-else>
                                    <button
                                        v-if="gameStore.isActualHost && menuPlayer"
                                        class="w-full text-left px-3 py-2 text-sm hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors flex items-center gap-2 text-slate-700 dark:text-slate-300"
                                        @click="handleMenuCoHost"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 text-blue-500">
                                            <path d="M10 8a3 3 0 100-6 3 3 0 000 6zM3.465 14.493a1.23 1.23 0 00.41 1.412A9.957 9.957 0 0010 18c2.31 0 4.438-.784 6.131-2.1.43-.333.604-.903.408-1.41a7.002 7.002 0 00-13.074.003z" />
                                        </svg>
                                        {{ menuPlayer?.is_co_host ? t('lobby.removeCoHost') : t('lobby.makeCoHost') }}
                                    </button>
                                    <button
                                        class="w-full text-left px-3 py-2 text-sm hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors flex items-center gap-2 text-amber-600 dark:text-amber-400"
                                        @click="handleMenuKick"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                                            <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                                        </svg>
                                        {{ t('lobby.kick') }}
                                    </button>
                                    <button
                                        class="w-full text-left px-3 py-2 text-sm hover:bg-red-50 dark:hover:bg-red-950/30 transition-colors flex items-center gap-2 text-red-600 dark:text-red-400"
                                        @click="handleMenuBan"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                                        </svg>
                                        {{ t('lobby.ban') }}
                                    </button>
                                </template>
                            </div>
                        </Popover>

                        <!-- Add bot button (host only, in lobby) -->
                        <div v-if="gameStore.isHost" class="mb-6">
                            <Button
                                :label="t('lobby.addBot')"
                                severity="secondary"
                                variant="outlined"
                                size="small"
                                :loading="addBotLoading"
                                @click="handleAddBot"
                            />
                        </div>

                        <!-- Banned players section (host/co-host only) -->
                        <div v-if="gameStore.isHost && gameStore.currentGame?.banned_players?.length" class="space-y-2 mb-6">
                            <h3 class="text-sm font-semibold text-slate-500 dark:text-slate-400">{{ t('lobby.bannedPlayers') }}</h3>
                            <div
                                v-for="bp in gameStore.currentGame.banned_players"
                                :key="bp.id"
                                class="flex items-center justify-between p-3 rounded-xl bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-900"
                            >
                                <div>
                                    <span class="font-medium text-slate-700 dark:text-slate-300">{{ bp.nickname }}</span>
                                    <span class="text-xs text-slate-400 ml-2">{{ bp.ban_reason || t('lobby.noBanReason') }}</span>
                                </div>
                                <Button
                                    :label="t('lobby.unban')"
                                    size="small"
                                    severity="secondary"
                                    variant="text"
                                    @click="handleUnbanPlayer(bp.id)"
                                />
                            </div>
                        </div>

                        <!-- Settings summary -->
                        <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-left mb-6">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300">{{ t('lobby.settings') }}</h3>
                                <button
                                    v-if="gameStore.isHost"
                                    class="w-7 h-7 rounded-md flex items-center justify-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-800 transition-colors"
                                    @click="openSettingsDialog"
                                    :title="t('lobby.editSettings')"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                                        <path fill-rule="evenodd" d="M7.84 1.804A1 1 0 018.82 1h2.36a1 1 0 01.98.804l.331 1.652a6.993 6.993 0 011.929 1.115l1.598-.54a1 1 0 011.186.447l1.18 2.044a1 1 0 01-.205 1.251l-1.267 1.113a7.047 7.047 0 010 2.228l1.267 1.113a1 1 0 01.206 1.25l-1.18 2.045a1 1 0 01-1.187.447l-1.598-.54a6.993 6.993 0 01-1.929 1.115l-.33 1.652a1 1 0 01-.98.804H8.82a1 1 0 01-.98-.804l-.331-1.652a6.993 6.993 0 01-1.929-1.115l-1.598.54a1 1 0 01-1.186-.447l-1.18-2.044a1 1 0 01.205-1.251l1.267-1.114a7.05 7.05 0 010-2.227L1.821 7.773a1 1 0 01-.206-1.25l1.18-2.045a1 1 0 011.187-.447l1.598.54A6.993 6.993 0 017.51 3.456l.33-1.652zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                            <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm text-slate-500 dark:text-slate-400">
                                <span>{{ t('create.rounds') }}:</span>
                                <span class="font-medium text-slate-700 dark:text-slate-300">{{ gameStore.currentGame?.settings?.rounds ?? 5 }}</span>
                                <span>{{ t('create.answerTime') }}:</span>
                                <span class="font-medium text-slate-700 dark:text-slate-300">{{ gameStore.currentGame?.settings?.answer_time ?? 60 }}s</span>
                                <span>{{ t('create.voteTime') }}:</span>
                                <span class="font-medium text-slate-700 dark:text-slate-300">{{ gameStore.currentGame?.settings?.vote_time ?? 30 }}s</span>
                                <span>{{ t('create.timeBetweenRounds') }}:</span>
                                <span class="font-medium text-slate-700 dark:text-slate-300">{{ gameStore.currentGame?.settings?.time_between_rounds ?? 30 }}s</span>
                                <span>{{ t('create.acronymLength') }}:</span>
                                <span class="font-medium text-slate-700 dark:text-slate-300">{{ gameStore.currentGame?.settings?.acronym_length_min ?? 5 }}</span>
                                <span>{{ t('create.maxPlayers') }}:</span>
                                <span class="font-medium text-slate-700 dark:text-slate-300">{{ gameStore.currentGame?.settings?.max_players ?? 8 }}</span>
                                <span>{{ t('create.maxEditsShort') }}:</span>
                                <span class="font-medium text-slate-700 dark:text-slate-300">{{ (gameStore.currentGame?.settings?.max_edits ?? 0) === 0 ? t('create.unlimited') : gameStore.currentGame?.settings?.max_edits }}</span>
                                <span>{{ t('create.maxVoteChangesShort') }}:</span>
                                <span class="font-medium text-slate-700 dark:text-slate-300">{{ (gameStore.currentGame?.settings?.max_vote_changes ?? 0) === 0 ? t('create.unlimited') : gameStore.currentGame?.settings?.max_vote_changes }}</span>
                                <span>{{ t('create.readyCheck') }}:</span>
                                <span class="font-medium text-slate-700 dark:text-slate-300">{{ (gameStore.currentGame?.settings?.allow_ready_check ?? true) ? t('common.yes') : t('common.no') }}</span>
                                <span>{{ t('game.chat') }}:</span>
                                <span class="font-medium text-slate-700 dark:text-slate-300">{{ (gameStore.currentGame?.settings?.chat_enabled ?? true) ? t('common.yes') : t('common.no') }}</span>
                                <span>{{ t('create.visibility') }}:</span>
                                <span class="font-medium text-slate-700 dark:text-slate-300">{{ gameStore.currentGame?.is_public ? t('create.public') : t('create.private') }}</span>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Phase: Playing (answer input) -->
                <div v-else-if="phase === 'playing'" class="flex-1 overflow-y-auto">
                    <div class="max-w-lg mx-auto px-4 py-6 text-center">
                        <!-- Acronym display (reactive — letters change color as you type) -->
                        <div class="flex justify-center gap-1.5 sm:gap-3 mb-6">
                            <span
                                v-for="(match, i) in letterMatches"
                                :key="i"
                                class="select-none inline-flex items-center justify-center w-12 h-12 sm:w-16 sm:h-16 rounded-xl text-xl sm:text-3xl font-bold border-2 transition-colors duration-150"
                                :class="match.status === 'correct'
                                    ? 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300 border-emerald-400 dark:border-emerald-600'
                                    : match.status === 'wrong'
                                        ? 'bg-red-100 dark:bg-red-900/50 text-red-600 dark:text-red-400 border-red-400 dark:border-red-700'
                                        : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-300 dark:border-slate-600'"
                            >
                                {{ match.expected }}
                            </span>
                        </div>

                        <!-- Submitted + can edit -->
                        <div v-if="gameStore.hasSubmittedAnswer && !isEditing" class="space-y-4">
                            <div class="p-6 rounded-2xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-900">
                                <p class="text-emerald-700 dark:text-emerald-300 font-medium mb-2 break-words">{{ gameStore.myAnswer?.text?.toLowerCase() }}</p>
                                <p v-if="gameStore.currentRound" class="text-xs text-slate-400 mt-2">
                                    {{ gameStore.currentRound.answers_count ?? 0 }}/{{ gameStore.currentRound.total_players ?? gameStore.players.length }} {{ t('game.submitted') }}
                                </p>
                            </div>
                            <!-- Ready check -->
                            <div v-if="allowReadyCheck" class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                                <div class="flex items-center gap-3">
                                    <Checkbox v-model="isReady" :binary="true" inputId="readyCheck" @change="handleReadyToggle" />
                                    <label for="readyCheck" class="text-sm text-slate-700 dark:text-slate-300 cursor-pointer select-none">
                                        {{ t('game.readyLabel') }}
                                    </label>
                                </div>
                                <span class="text-xs text-slate-400">
                                    {{ gameStore.readyCount }}/{{ gameStore.totalPlayersForReady || gameStore.players.length }} {{ t('game.readyCount') }}
                                </span>
                            </div>
                            <Button
                                v-if="editsRemaining > 0"
                                :label="editsRemaining === Infinity ? t('game.edit') : `${t('game.edit')} (${editsRemaining} ${t('game.remaining')})`"
                                severity="secondary"
                                variant="outlined"
                                size="small"
                                class="w-full"
                                @click="startEditing"
                            />
                        </div>

                        <!-- Answer input (initial or editing) -->
                        <form v-else @submit.prevent="handleSubmitAnswer" class="space-y-4">
                            <div class="relative">
                                <textarea
                                    ref="answerInput"
                                    v-model="answerText"
                                    :placeholder="t('game.yourAnswer')"
                                    rows="3"
                                    class="w-full p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-200 text-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 resize-none"
                                    @input="sanitizeAndValidate"
                                    @keydown.enter.prevent="isAnswerValid && handleSubmitAnswer()"
                                ></textarea>
                            </div>

                            <p class="text-xs text-slate-400">
                                {{ validWordCount }}/{{ acronymLetters.length }} {{ t('game.wordsMatch') }}
                            </p>

                            <div class="flex gap-2">
                                <Button
                                    v-if="isEditing"
                                    :label="t('common.cancel')"
                                    severity="secondary"
                                    variant="outlined"
                                    class="flex-1"
                                    @click="isEditing = false"
                                />
                                <Button
                                    type="submit"
                                    :label="isEditing ? t('game.updateAnswer') : t('game.submitAnswer')"
                                    severity="success"
                                    :class="isEditing ? 'flex-1' : 'w-full'"
                                    :disabled="!isAnswerValid"
                                    :loading="submitLoading"
                                />
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Phase: Voting -->
                <div v-else-if="phase === 'voting'" class="flex-1 overflow-y-auto">
                    <div class="max-w-lg mx-auto px-4 py-6">
                        <h2 class="text-xl font-bold text-center mb-6 text-slate-800 dark:text-slate-200">
                            {{ t('game.voting') }}
                        </h2>

                        <!-- Vote status -->
                        <p v-if="gameStore.currentRound" class="text-xs text-slate-400 text-center mb-4">
                            {{ gameStore.currentRound.votes_count ?? 0 }}/{{ gameStore.currentRound.total_voters ?? 0 }} {{ t('game.votes') }}
                        </p>

                        <!-- Vote cards — always visible, click to vote or change vote -->
                        <div class="space-y-3">
                            <div
                                v-for="answer in gameStore.answers"
                                :key="answer.id"
                                class="p-4 rounded-xl border-2 transition-all"
                                :class="
                                    answer.is_own
                                        ? 'border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 opacity-50 cursor-not-allowed'
                                        : gameStore.myVote?.answer_id === answer.id
                                            ? 'border-emerald-500 dark:border-emerald-400 bg-emerald-50 dark:bg-emerald-950/50 cursor-pointer'
                                            : 'border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 hover:border-emerald-300 dark:hover:border-emerald-700 cursor-pointer'
                                "
                                @click="onAnswerClick(answer)"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <p class="select-none text-slate-800 dark:text-slate-200 break-words">{{ answer.text?.toLowerCase() }}</p>
                                    <span v-if="gameStore.myVote?.answer_id === answer.id" class="shrink-0 text-emerald-500 dark:text-emerald-400 text-xs font-medium">
                                        {{ t('game.yourVote') }}
                                    </span>
                                </div>
                                <p v-if="answer.is_own" class="text-xs text-slate-400 mt-1">{{ t('game.yourSubmission') }}</p>
                            </div>
                        </div>

                        <p v-if="gameStore.hasVoted" class="text-center text-xs text-slate-400 mt-4">
                            {{ voteChangesLeft > 0 ? (voteChangesLeft === Infinity ? t('game.tapToChangeVote') : `${t('game.tapToChangeVote')} (${voteChangesLeft} ${t('game.voteChangesRemaining')})`) : '' }}
                        </p>
                    </div>
                </div>

                <!-- Phase: Results -->
                <div v-else-if="phase === 'results'" class="flex-1 overflow-y-auto">
                    <div class="max-w-lg mx-auto px-4 py-6">
                        <h2 class="text-xl font-bold text-center mb-6 text-slate-800 dark:text-slate-200">
                            {{ t('game.results') }}
                        </h2>

                        <!-- Round results -->
                        <div class="space-y-3 mb-8">
                            <div
                                v-for="(result, i) in gameStore.roundResults"
                                :key="result.player_id || i"
                                class="p-4 rounded-xl border border-slate-200 dark:border-slate-800"
                                :class="isRoundWinner(result) ? 'bg-emerald-50 dark:bg-emerald-950/50 border-emerald-200 dark:border-emerald-900' : 'bg-slate-50 dark:bg-slate-900'"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="select-none font-medium text-slate-800 dark:text-slate-200 break-words">{{ (result.answer || result.text)?.toLowerCase() }}</p>
                                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ result.player_name || result.player_nickname }}</p>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <p class="text-xs text-slate-400">{{ result.votes ?? result.votes_count ?? 0 }} {{ t('game.votes') }}</p>
                                    </div>
                                </div>
                                <Badge v-if="isRoundWinner(result) && !roundHasTie" :value="t('game.winner')" severity="success" class="mt-2" />
                                <Badge v-else-if="isRoundWinner(result) && roundHasTie" :value="t('game.tie')" severity="warn" class="mt-2" />
                            </div>
                        </div>

                        <!-- Scoreboard -->
                        <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                            <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">{{ t('game.scoreboard') }}</h3>
                            <div class="space-y-2">
                                <div
                                    v-for="score in gameStore.scores"
                                    :key="score.player_id"
                                    class="flex items-center justify-between select-none"
                                >
                                    <span class="text-sm text-slate-700 dark:text-slate-300">{{ score.player_name || score.nickname }}</span>
                                    <span class="text-sm font-bold text-emerald-600 dark:text-emerald-400">{{ score.score }} {{ t('game.points') }}</span>
                                </div>
                            </div>
                        </div>

                        <p class="text-center text-sm text-slate-400 mt-4">
                            {{ t('game.nextRound') }}
                            <span v-if="gameStore.timeRemaining > 0" class="font-mono font-bold">{{ gameStore.timeRemaining }}s</span>
                            <span v-else>...</span>
                        </p>
                    </div>
                </div>

                <!-- Phase: Finished -->
                <div v-else-if="phase === 'finished'" class="flex-1 overflow-y-auto">
                    <div class="max-w-lg mx-auto px-4 py-8 text-center">
                        <h2 class="text-3xl font-bold mb-2 text-emerald-600 dark:text-emerald-400 animate-bounce-in">
                            {{ gameStore.currentGame?.settings?.finished_reason === 'inactivity' ? t('game.finishedInactivity') : t('game.finished') }}
                        </h2>

                        <!-- Winner -->
                        <div v-if="gameStore.currentGame?.winner" class="my-6 p-6 rounded-2xl bg-gradient-to-br from-emerald-50 to-yellow-50 dark:from-emerald-950/50 dark:to-yellow-950/30 border-2 border-emerald-300 dark:border-emerald-700 animate-winner-reveal shadow-lg shadow-emerald-200/50 dark:shadow-emerald-900/50">
                            <div class="text-4xl mb-2">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-12 h-12 mx-auto text-yellow-500 animate-trophy-bounce">
                                    <path d="M5 3h14c.55 0 1 .45 1 1v2c0 2.76-2.24 5-5 5h-.42c-.77 1.25-1.94 2.18-3.33 2.65L12 17h2a1 1 0 110 2H10a1 1 0 110-2h2l-.75-3.35C9.84 13.18 8.67 12.25 7.9 11H7.5C5.24 11 3 8.76 3 6V4c0-.55.45-1 1-1h1zm1 2v1c0 1.65 1.35 3 3 3h.09c-.05-.33-.09-.66-.09-1V5H6zm12 0h-3v3c0 .34-.04.67-.09 1H15c1.65 0 3-1.35 3-3V5z"/>
                                </svg>
                            </div>
                            <p class="text-sm text-emerald-600 dark:text-emerald-400 mb-1 font-medium">{{ t('game.winner') }}</p>
                            <p class="text-3xl font-bold text-emerald-700 dark:text-emerald-300">
                                {{ gameStore.currentGame.winner.player_name || gameStore.currentGame.winner.nickname }}
                            </p>
                            <p class="text-lg font-bold text-yellow-600 dark:text-yellow-400 mt-1">
                                {{ gameStore.currentGame.winner.score }} {{ t('game.points') }}
                            </p>
                        </div>

                        <!-- Tie -->
                        <div v-else class="my-6 p-6 rounded-2xl bg-gradient-to-br from-amber-50 to-slate-50 dark:from-amber-950/30 dark:to-slate-950/50 border-2 border-amber-300 dark:border-amber-700 animate-winner-reveal">
                            <p class="text-4xl mb-2">&#129309;</p>
                            <p class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ t('game.tie') }}!</p>
                        </div>

                        <!-- Final scores -->
                        <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 mb-6">
                            <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">{{ t('game.finalScores') }}</h3>
                            <div class="space-y-2">
                                <div
                                    v-for="(score, i) in gameStore.scores"
                                    :key="score.player_id"
                                    class="flex items-center justify-between p-2 rounded transition-all"
                                    :class="gameStore.currentGame?.winner && i === 0 ? 'bg-emerald-50 dark:bg-emerald-950/50 ring-1 ring-emerald-200 dark:ring-emerald-800' : ''"
                                    :style="{ animationDelay: `${i * 100}ms` }"
                                >
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-bold w-5" :class="getFinalRankClass(i)">
                                            {{ getFinalRankLabel(i) }}
                                        </span>
                                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ score.player_name || score.nickname }}</span>
                                    </div>
                                    <span class="text-sm font-bold text-emerald-600 dark:text-emerald-400">{{ score.score }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-3">
                            <Button
                                v-if="gameStore.isHost"
                                :label="t('game.playAgainSamePlayers')"
                                severity="success"
                                class="flex-1"
                                :loading="rematchLoading"
                                @click="handleRematch"
                            />
                            <Button
                                v-else
                                :label="t('game.playAgain')"
                                severity="success"
                                class="flex-1"
                                @click="router.visit('/spill')"
                            />
                            <Button
                                :label="t('game.viewArchive')"
                                severity="secondary"
                                variant="outlined"
                                class="flex-1"
                                @click="router.visit(`/arkiv/${props.code}`)"
                            />
                        </div>
                    </div>
                </div>
            </template>

            <!-- Chat panel toggle -->
            <div v-if="chatEnabled" class="shrink-0 border-t border-slate-200 dark:border-slate-800">
                <button
                    @click="chatOpen = !chatOpen"
                    class="w-full px-4 py-2 flex items-center justify-between text-sm text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-900 transition-colors"
                >
                    <span>{{ t('game.chat') }}</span>
                    <div class="flex items-center gap-2">
                        <Badge v-if="unreadCount > 0" :value="unreadCount" severity="danger" />
                        <svg :class="{ 'rotate-180': chatOpen }" class="w-4 h-4 transition-transform" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="18 15 12 9 6 15"/></svg>
                    </div>
                </button>

                <!-- IRC-style chat -->
                <div v-if="chatOpen" class="border-t border-slate-200 dark:border-slate-800 max-h-48 flex flex-col" @click="focusChatInput">
                    <div ref="chatContainer" class="flex-1 overflow-y-auto px-3 py-1 space-y-0.5 min-h-0 font-mono text-xs">
                        <div
                            v-for="(msg, i) in gameStore.chatMessages"
                            :key="i"
                            :class="msg.system ? 'text-slate-400 dark:text-slate-500' : msg.action ? 'text-purple-500 dark:text-purple-400' : ''"
                        >
                            <template v-if="msg.system">
                                <span class="text-slate-500">*** </span>
                                <span>{{ msg.message }}</span>
                            </template>
                            <template v-else-if="msg.action">
                                <span class="text-slate-500">* </span><span class="font-medium">{{ msg.nickname }}</span>{{ ' ' }}<span>{{ msg.message }}</span>
                            </template>
                            <template v-else>
                                <span class="text-slate-500">&lt;</span><span class="font-medium text-emerald-600 dark:text-emerald-400">{{ msg.nickname }}</span><span class="text-slate-500">&gt;</span>{{ ' ' }}<span class="text-slate-700 dark:text-slate-300">{{ msg.message }}</span>
                            </template>
                        </div>
                        <p v-if="gameStore.chatMessages.length === 0" class="text-slate-400 text-center py-2">
                            --- {{ t('game.chat') }} ---
                        </p>
                    </div>
                    <form @submit.prevent="handleSendChat" class="flex gap-1 p-1.5 border-t border-slate-100 dark:border-slate-800" @click.stop>
                        <InputText
                            ref="chatInputRef"
                            v-model="chatMessage"
                            :placeholder="t('game.sendMessage')"
                            class="flex-1 font-mono !text-xs"
                            size="small"
                        />
                        <Button type="submit" label="Send" severity="success" size="small" :disabled="!chatMessage.trim()" />
                    </form>
                </div>
            </div>
        </div>
    </GameLayout>
    <Dialog v-model:visible="inviteDialogVisible" :header="t('lobby.inviteTitle')" modal :style="{ width: '24rem' }">
        <form @submit.prevent="handleInvite" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ t('lobby.inviteEmailLabel') }}</label>
                <InputText
                    v-model="inviteEmail"
                    type="email"
                    :placeholder="t('lobby.inviteEmailLabel')"
                    class="w-full"
                    :disabled="invitesRemaining <= 0"
                />
            </div>
            <p v-if="inviteSent" class="text-sm text-emerald-600 dark:text-emerald-400 font-medium">{{ t('lobby.inviteSent') }}</p>
            <p v-if="inviteError" class="text-sm text-red-500">{{ inviteError }}</p>
            <p class="text-xs text-slate-400">{{ t('lobby.inviteLimit').replace('{n}', invitesRemaining) }}</p>
            <Button
                type="submit"
                :label="t('lobby.inviteSend')"
                severity="success"
                class="w-full"
                :loading="inviteLoading"
                :disabled="!inviteEmail.trim() || invitesRemaining <= 0"
            />
        </form>
    </Dialog>
    <Dialog v-model:visible="banDialogVisible" :header="t('lobby.ban')" modal :style="{ width: '24rem' }">
        <p class="mb-4 text-sm text-slate-700 dark:text-slate-300">{{ t('lobby.banConfirm').replace('{name}', banDialogNickname) }}</p>
        <div class="mb-4">
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ t('lobby.banReason') }}</label>
            <InputText
                v-model="banReason"
                :placeholder="t('lobby.banReason')"
                class="w-full"
                maxlength="200"
            />
        </div>
        <div class="flex gap-2 justify-end">
            <Button :label="t('common.cancel')" severity="secondary" variant="outlined" @click="banDialogVisible = false" />
            <Button :label="t('lobby.ban')" severity="danger" @click="confirmBan" />
        </div>
    </Dialog>
    <Dialog v-model:visible="passwordDialogVisible" :header="t('lobby.enterPassword')" modal :style="{ width: '24rem' }" :closable="false">
        <form @submit.prevent="handlePasswordSubmit" class="space-y-4">
            <p class="text-sm text-slate-600 dark:text-slate-400">{{ t('lobby.passwordRequired') }}</p>
            <div v-if="passwordError" class="p-3 rounded-lg bg-red-50 dark:bg-red-950/50 border border-red-200 dark:border-red-900 text-sm text-red-700 dark:text-red-300">
                {{ passwordError }}
            </div>
            <InputText
                v-model="gamePassword"
                type="password"
                :placeholder="t('create.password')"
                class="w-full"
                autofocus
            />
            <div class="flex gap-2 justify-end">
                <Button :label="t('common.back')" severity="secondary" variant="outlined" @click="router.visit('/spill')" />
                <Button type="submit" :label="t('games.join')" severity="success" :loading="passwordLoading" :disabled="!gamePassword.trim()" />
            </div>
        </form>
    </Dialog>
    <Dialog v-model:visible="settingsDialogVisible" :header="t('lobby.editSettings')" modal :style="{ width: '28rem' }" class="max-h-[90vh]">
        <div class="space-y-5 overflow-y-auto max-h-[70vh] px-1 py-2">
            <div v-if="settingsError" class="p-3 rounded-lg bg-red-50 dark:bg-red-950/50 border border-red-200 dark:border-red-900 text-sm text-red-700 dark:text-red-300">
                {{ settingsError }}
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700 dark:text-slate-300 mb-2 block">
                    {{ t('create.rounds') }}: {{ settingsForm.rounds }}
                </label>
                <Slider v-model="settingsForm.rounds" :min="1" :max="20" :step="1" class="w-full px-3" />
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700 dark:text-slate-300 mb-2 block">
                    {{ t('create.answerTime') }}: {{ settingsForm.answer_time }} {{ t('create.seconds') }}
                </label>
                <Slider v-model="settingsForm.answer_time" :min="15" :max="180" :step="5" class="w-full px-3" />
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700 dark:text-slate-300 mb-2 block">
                    {{ t('create.voteTime') }}: {{ settingsForm.vote_time }} {{ t('create.seconds') }}
                </label>
                <Slider v-model="settingsForm.vote_time" :min="10" :max="120" :step="5" class="w-full px-3" />
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700 dark:text-slate-300 mb-2 block">
                    {{ t('create.timeBetweenRounds') }}: {{ settingsForm.time_between_rounds }} {{ t('create.seconds') }}
                </label>
                <Slider v-model="settingsForm.time_between_rounds" :min="3" :max="120" :step="1" class="w-full px-3" />
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700 dark:text-slate-300 mb-2 block">
                    {{ t('create.acronymLength') }}: {{ settingsForm.acronym_length }}
                </label>
                <Slider v-model="settingsForm.acronym_length" :min="1" :max="6" :step="1" class="w-full px-3" />
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700 dark:text-slate-300 mb-2 block">
                    {{ t('create.maxPlayers') }}: {{ settingsForm.max_players }}
                </label>
                <Slider v-model="settingsForm.max_players" :min="2" :max="16" :step="1" class="w-full px-3" />
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700 dark:text-slate-300 mb-2 block">
                    {{ t('create.excludeLetters') }}
                </label>
                <InputText
                    v-model="settingsForm.excluded_letters"
                    class="w-full uppercase tracking-[0.2em] font-mono"
                    :placeholder="'XZQ'"
                    @input="settingsForm.excluded_letters = settingsForm.excluded_letters.toUpperCase().replace(/[^A-ZÆØÅ]/g, '')"
                />
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700 dark:text-slate-300 mb-1 block">
                    {{ t('create.maxEditsShort') }}: {{ settingsForm.max_edits === 0 ? t('create.unlimited') : settingsForm.max_edits }}
                </label>
                <p class="text-xs text-slate-400 mb-2">{{ t('create.maxEditsDesc') }}</p>
                <Slider v-model="settingsForm.max_edits" :min="0" :max="10" :step="1" class="w-full px-3" />
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700 dark:text-slate-300 mb-1 block">
                    {{ t('create.maxVoteChangesShort') }}: {{ settingsForm.max_vote_changes === 0 ? t('create.unlimited') : settingsForm.max_vote_changes }}
                </label>
                <p class="text-xs text-slate-400 mb-2">{{ t('create.maxVoteChangesDesc') }}</p>
                <Slider v-model="settingsForm.max_vote_changes" :min="0" :max="10" :step="1" class="w-full px-3" />
            </div>

            <div class="flex items-center justify-between">
                <div>
                    <label for="settingsReadyCheck" class="text-sm font-medium text-slate-700 dark:text-slate-300 cursor-pointer">
                        {{ t('create.readyCheck') }}
                    </label>
                    <p class="text-xs text-slate-400">{{ t('create.readyCheckDesc') }}</p>
                </div>
                <ToggleSwitch v-model="settingsForm.allow_ready_check" inputId="settingsReadyCheck" />
            </div>

            <div class="flex items-center justify-between">
                <label for="settingsChat" class="text-sm font-medium text-slate-700 dark:text-slate-300 cursor-pointer">
                    {{ t('create.chat') }}
                </label>
                <ToggleSwitch v-model="settingsForm.chat_enabled" inputId="settingsChat" />
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700 dark:text-slate-300 mb-2 block">
                    {{ t('create.visibility') }}
                </label>
                <div class="flex gap-3">
                    <Button
                        :label="t('create.public')"
                        :severity="!settingsForm.is_private ? 'success' : 'secondary'"
                        :variant="!settingsForm.is_private ? undefined : 'outlined'"
                        size="small"
                        @click="settingsForm.is_private = false"
                    />
                    <Button
                        :label="t('create.private')"
                        :severity="settingsForm.is_private ? 'success' : 'secondary'"
                        :variant="settingsForm.is_private ? undefined : 'outlined'"
                        size="small"
                        @click="settingsForm.is_private = true"
                    />
                </div>
            </div>

            <div v-if="settingsForm.is_private">
                <label class="text-sm font-medium text-slate-700 dark:text-slate-300 mb-1 block">
                    {{ t('create.password') }}
                </label>
                <p class="text-xs text-slate-400 mb-2">{{ t('lobby.passwordHint') }}</p>
                <InputText
                    v-model="settingsForm.password"
                    type="text"
                    class="w-full"
                />
            </div>
        </div>

        <div class="flex gap-2 justify-end mt-4">
            <Button :label="t('common.cancel')" severity="secondary" variant="outlined" @click="settingsDialogVisible = false" />
            <Button :label="t('common.save')" severity="success" :loading="settingsLoading" @click="handleSaveSettings" />
        </div>
    </Dialog>
    <ConfirmDialog />
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { router } from '@inertiajs/vue3';
import { storeToRefs } from 'pinia';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import Badge from 'primevue/badge';
import ProgressBar from 'primevue/progressbar';
import Dialog from 'primevue/dialog';
import Popover from 'primevue/popover';
import Slider from 'primevue/slider';
import ToggleSwitch from 'primevue/toggleswitch';
import Checkbox from 'primevue/checkbox';
import ConfirmDialog from 'primevue/confirmdialog';
import { useConfirm } from 'primevue/useconfirm';
import confetti from 'canvas-confetti';
import GameLayout from '../layouts/GameLayout.vue';
import { useGameStore } from '../stores/gameStore.js';
import { useAuthStore } from '../stores/authStore.js';
import { useI18n } from '../composables/useI18n.js';
import { api, getApiError } from '../services/api.js';

defineOptions({ layout: false });

const props = defineProps({ code: String });

const gameStore = useGameStore();
const authStore = useAuthStore();
const { t } = useI18n();
const confirm = useConfirm();

const { phase } = storeToRefs(gameStore);

const loading = ref(true);
const error = ref('');
const startLoading = ref(false);
const submitLoading = ref(false);
const voteLoading = ref(false);
const answerText = ref('');
const chatOpen = ref(false);
const chatMessage = ref('');
const chatContainer = ref(null);
const answerInput = ref(null);
const chatInputRef = ref(null);
const copied = ref(false);
const copiedLink = ref(false);
const unreadCount = ref(0);
const isEditing = ref(false);
const submitCount = ref(0);
const rematchLoading = ref(false);
const voteCount = ref(0);
const banDialogVisible = ref(false);
const banDialogPlayerId = ref(null);
const banDialogNickname = ref('');
const banReason = ref('');
const playerMenuRef = ref(null);
const menuPlayer = ref(null);
const inviteDialogVisible = ref(false);
const inviteEmail = ref('');
const inviteLoading = ref(false);
const inviteSent = ref(false);
const inviteError = ref('');
const invitesRemaining = ref(5);
const addBotLoading = ref(false);
const settingsDialogVisible = ref(false);
const settingsForm = reactive({
    rounds: 8,
    answer_time: 60,
    vote_time: 30,
    time_between_rounds: 30,
    acronym_length: 5,
    max_players: 8,
    excluded_letters: '',
    chat_enabled: true,
    allow_ready_check: true,
    max_edits: 0,
    max_vote_changes: 0,
    is_private: false,
    password: '',
});
const settingsLoading = ref(false);
const settingsError = ref('');
const passwordDialogVisible = ref(false);
const gamePassword = ref('');
const passwordError = ref('');
const passwordLoading = ref(false);
const isReady = ref(false);
const allowReadyCheck = computed(() => gameStore.currentGame?.settings?.allow_ready_check ?? true);
const maxEdits = computed(() => gameStore.currentGame?.settings?.max_edits ?? 0);
const maxVoteChanges = computed(() => gameStore.currentGame?.settings?.max_vote_changes ?? 0);
const chatEnabled = computed(() => gameStore.currentGame?.settings?.chat_enabled ?? true);
const editsRemaining = computed(() => maxEdits.value === 0 ? Infinity : Math.max(0, maxEdits.value - Math.max(0, submitCount.value - 1)));
const voteChangesLeft = computed(() => maxVoteChanges.value === 0 ? Infinity : Math.max(0, maxVoteChanges.value - Math.max(0, voteCount.value - 1)));

const totalRounds = computed(() => gameStore.currentGame?.settings?.rounds ?? 5);

const roundHasTie = computed(() => {
    const results = gameStore.roundResults;
    if (!results || results.length < 2) return false;
    const topVotes = results[0]?.votes ?? results[0]?.votes_count ?? 0;
    const secondVotes = results[1]?.votes ?? results[1]?.votes_count ?? 0;
    return topVotes === secondVotes && topVotes > 0;
});

function isRoundWinner(result) {
    const results = gameStore.roundResults;
    if (!results?.length) return false;
    const topVotes = results[0]?.votes ?? results[0]?.votes_count ?? 0;
    const myVotes = result.votes ?? result.votes_count ?? 0;
    return myVotes > 0 && myVotes === topVotes;
}

function canVote(answerId) {
    // Allow clicking the already-voted answer (to see feedback), or if changes remain
    return gameStore.myVote?.answer_id === answerId || voteChangesLeft.value > 0;
}

function getFinalRank(index) {
    const allScores = gameStore.scores;
    if (!allScores?.length) return index + 1;
    const myScore = allScores[index]?.score ?? 0;
    // Find the first player with this score to determine the rank
    const firstWithScore = allScores.findIndex(s => (s.score ?? 0) === myScore);
    return firstWithScore;
}

function getFinalRankLabel(index) {
    const rank = getFinalRank(index);
    const medals = ['\uD83E\uDD47', '\uD83E\uDD48', '\uD83E\uDD49'];
    // Only show gold medal if there's a clear winner (no tie at top)
    if (rank === 0 && !gameStore.currentGame?.winner) {
        return `${index + 1}.`;
    }
    return rank < 3 ? medals[rank] : `${rank + 1}.`;
}

function getFinalRankClass(index) {
    const rank = getFinalRank(index);
    if (rank === 0 && !gameStore.currentGame?.winner) return 'text-slate-400';
    if (rank === 0) return 'text-yellow-500';
    if (rank === 1) return 'text-slate-400';
    if (rank === 2) return 'text-amber-700';
    return 'text-slate-400';
}

const acronymLetters = computed(() => {
    return gameStore.acronym ? gameStore.acronym.split('') : [];
});

const showTimer = computed(() => {
    return phase.value === 'playing' || phase.value === 'voting' || phase.value === 'results';
});

const timerPercent = computed(() => {
    let total;
    if (phase.value === 'results') {
        total = gameStore.currentGame?.settings?.time_between_rounds ?? 15;
    } else if (phase.value === 'voting') {
        total = gameStore.currentGame?.settings?.vote_time ?? 30;
    } else {
        total = gameStore.currentGame?.settings?.answer_time ?? 60;
    }
    return Math.max(0, (gameStore.timeRemaining / total) * 100);
});

const letterMatches = computed(() => {
    // Use submitted answer text when not editing, otherwise use live input
    const text = (gameStore.hasSubmittedAnswer && !isEditing.value)
        ? (gameStore.myAnswer?.text || '')
        : answerText.value;
    const words = text.trim().split(/\s+/).filter(Boolean);
    return acronymLetters.value.map((letter, i) => {
        if (i >= words.length) {
            return { expected: letter, status: 'empty' };
        }
        const firstChar = words[i].charAt(0).toUpperCase();
        return {
            expected: letter,
            status: firstChar === letter.toUpperCase() ? 'correct' : 'wrong',
        };
    });
});

const validWordCount = computed(() => {
    return letterMatches.value.filter((m) => m.status === 'correct').length;
});

const wordCount = computed(() => {
    return answerText.value.trim().split(/\s+/).filter(Boolean).length;
});

const isAnswerValid = computed(() => {
    return letterMatches.value.length > 0
        && letterMatches.value.every((m) => m.status === 'correct')
        && wordCount.value === acronymLetters.value.length;
});

function sanitizeAndValidate() {
    // Strip characters that aren't letters, spaces, or allowed punctuation (,.!?:;-)
    answerText.value = answerText.value.replace(/[^\p{L}\s,.!?:;\-]/gu, '');
}

async function initGame() {
    // If not authenticated, redirect to login with this game URL as redirect target
    if (!authStore.isAuthenticated) {
        router.visit(`/logg-inn?redirect=${encodeURIComponent(`/spill/${props.code}`)}`);
        return;
    }

    loading.value = true;
    error.value = '';

    try {
        await gameStore.fetchGame(props.code);

        // Auto-join if the player isn't in the game yet (lobby or mid-game)
        const myId = authStore.player?.id;
        const isInGame = gameStore.players.some((p) => p.id === myId);
        if (!isInGame && phase.value !== 'finished') {
            try {
                await gameStore.joinGame(props.code);
            } catch (joinErr) {
                const msg = joinErr.response?.data?.error || '';
                if (msg.includes('password') || msg.includes('Incorrect')) {
                    // Keep loading true so game content doesn't render behind dialog
                    gameStore.resetState();
                    passwordDialogVisible.value = true;
                    return;
                }
                // "Player already in game" is fine
                if (!msg.toLowerCase().includes('already in game')) {
                    throw joinErr;
                }
            }
        }

        gameStore.connectWebSocket(props.code);

        // If the game is active, fetch full state (round, my_answer, answers, my_vote)
        if (phase.value !== 'lobby') {
            await gameStore.fetchGameState(props.code);
        }

    } catch (err) {
        error.value = getApiError(err, t);
    } finally {
        if (!passwordDialogVisible.value) {
            loading.value = false;
        }
    }
}

async function handleLeave() {
    try {
        await gameStore.leaveGame(props.code);
        router.visit('/spill');
    } catch {
        router.visit('/spill');
    }
}

async function handleStart() {
    startLoading.value = true;
    try {
        await gameStore.startGame(props.code);
    } catch (err) {
        error.value = err.response?.data?.message || t('common.error');
    } finally {
        startLoading.value = false;
    }
}

async function handleKeepalive() {
    try {
        await gameStore.keepalive(props.code);
    } catch {
        // Ignore
    }
}

function handleEndGame() {
    confirm.require({
        message: t('lobby.endGameConfirm'),
        header: t('lobby.endGame'),
        acceptLabel: t('common.confirm'),
        rejectLabel: t('common.cancel'),
        accept: async () => {
            try {
                await gameStore.endGame(props.code);
                router.visit('/spill');
            } catch (err) {
                error.value = err.response?.data?.error || t('common.error');
            }
        },
    });
}

async function handleReadyToggle() {
    if (!gameStore.currentRound) return;
    try {
        await gameStore.markReady(gameStore.currentRound.id, isReady.value);
    } catch {
        // Revert on failure
        isReady.value = !isReady.value;
    }
}

function startEditing() {
    answerText.value = gameStore.myAnswer?.text || '';
    isEditing.value = true;
    isReady.value = false;
}

async function handleSubmitAnswer() {
    if (!isAnswerValid.value || !gameStore.currentRound) return;

    const trimmed = answerText.value.trim();

    // Don't count as an edit if text is unchanged
    if (isEditing.value && trimmed === gameStore.myAnswer?.text) {
        isEditing.value = false;
        return;
    }

    submitLoading.value = true;
    try {
        await gameStore.submitAnswer(gameStore.currentRound.id, trimmed);
        submitCount.value++;
        isEditing.value = false;
    } catch (err) {
        const status = err.response?.status;
        if (status === 422) {
            error.value = t('game.answerInvalid');
        } else {
            error.value = err.response?.data?.error || err.response?.data?.message || t('common.error');
        }
    } finally {
        submitLoading.value = false;
    }
}

function onAnswerClick(answer) {
    if (answer.is_own || voteLoading.value || !canVote(answer.id)) return;
    handleDirectVote(answer.id);
}

async function handleDirectVote(answerId) {
    if (!answerId || !gameStore.currentRound || voteLoading.value) return;

    // Clicking the same answer you already voted for is a no-op
    if (gameStore.myVote?.answer_id === answerId) return;

    voteLoading.value = true;
    try {
        await gameStore.submitVote(gameStore.currentRound.id, answerId);
        voteCount.value++;
    } catch (err) {
        error.value = err.response?.data?.message || t('common.error');
    } finally {
        voteLoading.value = false;
    }
}

function focusChatInput() {
    nextTick(() => {
        chatInputRef.value?.$el?.focus();
    });
}

async function handleSendChat() {
    const text = chatMessage.value.trim();
    if (!text) return;

    const myNickname = authStore.player?.nickname || 'Anonymous';

    // IRC command handling
    if (text.startsWith('/')) {
        const parts = text.split(' ');
        const cmd = parts[0].toLowerCase();
        const rest = parts.slice(1).join(' ');

        switch (cmd) {
            case '/me':
                if (rest) {
                    // Send as action message
                    try {
                        await gameStore.sendChatMessage(props.code, rest, true);
                        chatMessage.value = '';
                    } catch { /* ignore */ }
                }
                return;
            case '/away':
                try {
                    await gameStore.sendChatMessage(props.code, rest || 'is away', true);
                } catch { /* ignore */ }
                chatMessage.value = '';
                return;
            case '/whois': {
                const target = rest.trim();
                const player = gameStore.players.find(p =>
                    p.nickname.toLowerCase() === target.toLowerCase()
                );
                if (player) {
                    const role = player.id === gameStore.currentGame?.host_player_id ? 'Host' : player.is_co_host ? 'Co-host' : 'Player';
                    gameStore.chatMessages.push({
                        system: true,
                        message: `${player.nickname} [${role}] — Score: ${player.score ?? 0}`,
                    });
                } else {
                    gameStore.chatMessages.push({
                        system: true,
                        message: target ? `${target}: No such nick` : 'Usage: /whois <nick>',
                    });
                }
                chatMessage.value = '';
                return;
            }
            case '/quit':
            case '/part':
                chatMessage.value = '';
                handleLeave();
                return;
            default:
                gameStore.chatMessages.push({
                    system: true,
                    message: `Unknown command: ${cmd}`,
                });
                chatMessage.value = '';
                return;
        }
    }

    try {
        await gameStore.sendChatMessage(props.code, text);
        chatMessage.value = '';
    } catch {
        // Ignore chat errors
    }
}

async function handleInvite() {
    if (!inviteEmail.value.trim()) return;

    inviteLoading.value = true;
    inviteError.value = '';
    inviteSent.value = false;

    try {
        const { data } = await api.games.invite(props.code, inviteEmail.value.trim());
        inviteSent.value = true;
        invitesRemaining.value = data.invites_remaining ?? 0;
        inviteEmail.value = '';
        setTimeout(() => { inviteSent.value = false; }, 3000);
    } catch (err) {
        if (err.response?.status === 429) {
            invitesRemaining.value = 0;
        }
        inviteError.value = err.response?.data?.error || t('lobby.inviteError');
    } finally {
        inviteLoading.value = false;
    }
}

function celebrateWinner() {
    // Initial big burst
    confetti({ particleCount: 150, spread: 100, origin: { y: 0.6 } });
    // Side cannons with delay
    setTimeout(() => {
        confetti({ particleCount: 80, angle: 60, spread: 55, origin: { x: 0, y: 0.7 } });
        confetti({ particleCount: 80, angle: 120, spread: 55, origin: { x: 1, y: 0.7 } });
    }, 300);
    // Gold stars burst
    setTimeout(() => {
        confetti({
            particleCount: 50, spread: 360, startVelocity: 30,
            ticks: 60, origin: { x: 0.5, y: 0.3 },
            colors: ['#FFD700', '#FFA500', '#FF6347'],
            shapes: ['star'],
        });
    }, 700);
    // Final shower
    setTimeout(() => {
        confetti({ particleCount: 100, spread: 160, origin: { y: 0.35 }, gravity: 1.2 });
    }, 1200);
}

async function handleRematch() {
    rematchLoading.value = true;
    try {
        await gameStore.rematch(props.code);
        const newCode = gameStore.gameCode;
        if (newCode) {
            router.visit(`/spill/${newCode}`);
        }
    } catch (err) {
        error.value = err.response?.data?.error || t('common.error');
    } finally {
        rematchLoading.value = false;
    }
}

async function handleToggleCoHost(playerId) {
    try {
        await gameStore.toggleCoHost(props.code, playerId);
    } catch (err) {
        error.value = err.response?.data?.error || t('common.error');
    }
}

function canManagePlayer(player) {
    if (player.id === gameStore.currentGame?.host_player_id) return false;
    if (player.id === authStore.player?.id) return false;
    return gameStore.isHost;
}

function togglePlayerMenu(event, player) {
    menuPlayer.value = player;
    playerMenuRef.value.toggle(event);
}

function handleMenuCoHost() {
    playerMenuRef.value.hide();
    if (menuPlayer.value) handleToggleCoHost(menuPlayer.value.id);
}

function handleMenuKick() {
    playerMenuRef.value.hide();
    if (menuPlayer.value) handleKickPlayer(menuPlayer.value.id, menuPlayer.value.nickname);
}

function handleMenuBan() {
    playerMenuRef.value.hide();
    if (menuPlayer.value) handleBanPlayer(menuPlayer.value.id, menuPlayer.value.nickname);
}

function handleMenuRemoveBot() {
    playerMenuRef.value.hide();
    if (menuPlayer.value) handleRemoveBot(menuPlayer.value.id);
}

async function handleAddBot() {
    addBotLoading.value = true;
    try {
        await gameStore.addBot(props.code);
    } catch (err) {
        error.value = err.response?.data?.error || t('common.error');
    } finally {
        addBotLoading.value = false;
    }
}

async function handleRemoveBot(playerId) {
    try {
        await gameStore.removeBot(props.code, playerId);
    } catch (err) {
        error.value = err.response?.data?.error || t('common.error');
    }
}

function handleKickPlayer(playerId, nickname) {
    confirm.require({
        message: t('lobby.kickConfirm').replace('{name}', nickname),
        header: t('lobby.kick'),
        acceptLabel: t('common.confirm'),
        rejectLabel: t('common.cancel'),
        accept: async () => {
            try {
                await gameStore.kickPlayer(props.code, playerId);
            } catch (err) {
                error.value = err.response?.data?.error || t('common.error');
            }
        },
    });
}

function handleBanPlayer(playerId, nickname) {
    banReason.value = '';
    banDialogPlayerId.value = playerId;
    banDialogNickname.value = nickname;
    banDialogVisible.value = true;
}

async function confirmBan() {
    try {
        await gameStore.banPlayer(props.code, banDialogPlayerId.value, banReason.value || null);
        banDialogVisible.value = false;
    } catch (err) {
        error.value = err.response?.data?.error || t('common.error');
    }
}

async function handleUnbanPlayer(playerId) {
    try {
        await gameStore.unbanPlayer(props.code, playerId);
    } catch (err) {
        error.value = err.response?.data?.error || t('common.error');
    }
}

async function handleToggleVisibility() {
    try {
        await gameStore.updateVisibility(props.code, !gameStore.currentGame?.is_public);
    } catch (err) {
        error.value = err.response?.data?.error || t('common.error');
    }
}

async function handlePasswordSubmit() {
    passwordLoading.value = true;
    passwordError.value = '';
    try {
        await gameStore.joinGame(props.code, gamePassword.value);
        passwordDialogVisible.value = false;
        gamePassword.value = '';
        // Re-fetch full game data and continue normal init
        await gameStore.fetchGame(props.code);
        gameStore.connectWebSocket(props.code);
        if (phase.value !== 'lobby') {
            await gameStore.fetchGameState(props.code);
        }
        loading.value = false;
    } catch (err) {
        passwordError.value = err.response?.data?.error || t('common.error');
    } finally {
        passwordLoading.value = false;
    }
}

function openSettingsDialog() {
    const s = gameStore.currentGame?.settings ?? {};
    settingsForm.rounds = s.rounds ?? 8;
    settingsForm.answer_time = s.answer_time ?? 60;
    settingsForm.vote_time = s.vote_time ?? 30;
    settingsForm.time_between_rounds = s.time_between_rounds ?? 30;
    settingsForm.acronym_length = s.acronym_length_min ?? 5;
    settingsForm.max_players = s.max_players ?? 8;
    settingsForm.excluded_letters = s.excluded_letters ?? '';
    settingsForm.chat_enabled = s.chat_enabled ?? true;
    settingsForm.allow_ready_check = s.allow_ready_check ?? true;
    settingsForm.max_edits = s.max_edits ?? 0;
    settingsForm.max_vote_changes = s.max_vote_changes ?? 0;
    settingsForm.is_private = !gameStore.currentGame?.is_public;
    settingsForm.password = '';
    settingsError.value = '';
    settingsDialogVisible.value = true;
}

async function handleSaveSettings() {
    settingsLoading.value = true;
    settingsError.value = '';
    try {
        await gameStore.updateSettings(props.code, {
            settings: {
                rounds: settingsForm.rounds,
                answer_time: settingsForm.answer_time,
                vote_time: settingsForm.vote_time,
                time_between_rounds: settingsForm.time_between_rounds,
                acronym_length_min: settingsForm.acronym_length,
                acronym_length_max: settingsForm.acronym_length,
                max_players: settingsForm.max_players,
                excluded_letters: settingsForm.excluded_letters || undefined,
                chat_enabled: settingsForm.chat_enabled,
                allow_ready_check: settingsForm.allow_ready_check,
                max_edits: settingsForm.max_edits,
                max_vote_changes: settingsForm.max_vote_changes,
            },
            is_public: !settingsForm.is_private,
            password: settingsForm.is_private && settingsForm.password ? settingsForm.password : undefined,
        });
        settingsDialogVisible.value = false;
    } catch (err) {
        settingsError.value = err.response?.data?.error || err.response?.data?.message || t('common.error');
    } finally {
        settingsLoading.value = false;
    }
}

function copyCode() {
    navigator.clipboard.writeText(gameStore.gameCode || props.code);
    copied.value = true;
    setTimeout(() => { copied.value = false; }, 2000);
}

function copyLink() {
    const code = gameStore.gameCode || props.code;
    const url = `${window.location.origin}/spill/${code}`;
    navigator.clipboard.writeText(url);
    copiedLink.value = true;
    setTimeout(() => { copiedLink.value = false; }, 2000);
}

// Track unread chat messages when chat is closed
watch(() => gameStore.chatMessages.length, (newLen, oldLen) => {
    if (!chatOpen.value) {
        unreadCount.value += Math.max(1, newLen - (oldLen ?? 0));
    } else {
        nextTick(() => {
            if (chatContainer.value) {
                chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
            }
        });
    }
});

watch(chatOpen, (open) => {
    if (open) {
        unreadCount.value = 0;
        nextTick(() => {
            if (chatContainer.value) {
                chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
            }
        });
    }
});

// Restore isReady from server state (e.g. after fetchGameState)
watch(() => gameStore.myAnswer?.is_ready, (val) => {
    if (val !== undefined) {
        isReady.value = val;
    }
});

// Restore edit count from server state
watch(() => gameStore.myAnswer?.edit_count, (val) => {
    if (val !== undefined) {
        submitCount.value = val + 1; // +1 because submitCount includes initial submit
    }
});

// Restore vote change count from server state
watch(() => gameStore.myVote?.change_count, (val) => {
    if (val !== undefined) {
        voteCount.value = val + 1; // +1 because voteCount includes initial vote
    }
});

// Reset state when phase changes (guard: skip initial hydration from null)
watch(phase, (newPhase, oldPhase) => {
    if (newPhase === 'playing' && oldPhase) {
        answerText.value = '';
        isEditing.value = false;
        isReady.value = false;
        submitCount.value = 0;
        voteCount.value = 0;
    }
    if (newPhase === 'finished' && oldPhase !== 'finished') {
        // Only celebrate if there's a clear winner (not a tie)
        nextTick(() => {
            if (gameStore.currentGame?.winner) {
                celebrateWinner();
            }
        });
    }
});

onMounted(async () => {
    if (!authStore.isInitialized) {
        await authStore.loadFromStorage();
    }
    initGame();
});

onUnmounted(() => {
    gameStore.disconnectWebSocket();
});
</script>

<style scoped>
.animate-bounce-in {
    animation: bounceIn 0.6s cubic-bezier(0.22, 1, 0.36, 1);
}
.animate-winner-reveal {
    animation: winnerReveal 0.8s cubic-bezier(0.22, 1, 0.36, 1) 0.2s both;
}
.animate-trophy-bounce {
    animation: trophyBounce 1s ease-in-out 0.5s both;
}
@keyframes bounceIn {
    0% { transform: scale(0.3); opacity: 0; }
    50% { transform: scale(1.05); }
    70% { transform: scale(0.95); }
    100% { transform: scale(1); opacity: 1; }
}
@keyframes winnerReveal {
    0% { transform: scale(0.8) translateY(20px); opacity: 0; }
    100% { transform: scale(1) translateY(0); opacity: 1; }
}
@keyframes trophyBounce {
    0% { transform: scale(0) rotate(-20deg); }
    50% { transform: scale(1.3) rotate(10deg); }
    70% { transform: scale(0.9) rotate(-5deg); }
    100% { transform: scale(1) rotate(0deg); }
}
</style>
