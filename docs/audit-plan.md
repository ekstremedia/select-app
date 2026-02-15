# Game Quality Audit — Bug Fixes & Polish

## Context
Comprehensive audit of the entire Select game (frontend + backend) to catch bugs, missing translations, mobile issues, and edge cases. Three parallel audits revealed ~20 actionable issues. This plan covers all fixes grouped by priority.

---

## Phase 1: Actual Bugs

### 1.1 Anonymous voting leak — RoundController exposes player_id during voting
**File:** `app/Application/Http/Controllers/Api/V1/RoundController.php:54-60`
**Bug:** `current()` endpoint returns `player_id` and `player_name` for answers during voting phase. GameController's `state()` correctly hides this, but RoundController doesn't.
**Fix:** During voting (not completed), omit `player_id` and `player_name`, and shuffle answers:
```php
if ($round->isVoting()) {
    // Hide player identity during voting
    $response['answers'] = $round->answers()->get()->shuffle(crc32($round->id))->map(fn ($a) => [
        'id' => $a->id,
        'text' => $a->text,
        'votes_count' => null,
    ]);
} elseif ($round->isCompleted()) {
    // Reveal everything after round is done
    $response['answers'] = $round->answers()->with('player')->get()->map(fn ($a) => [
        'id' => $a->id,
        'player_id' => $a->player_id,
        'player_name' => $a->author_nickname ?? $a->player->nickname,
        'text' => $a->text,
        'votes_count' => $a->votes_count,
    ]);
}
```

### 1.2 Duplicate i18n key — `common.serverError` defined twice
**File:** `resources/js/composables/useI18n.js:232+236, 505+509`
**Bug:** Key defined twice in both NO and EN — second silently overwrites first.
**Fix:** Remove duplicate lines 236 and 509. Keep the short versions (lines 232, 505).

### 1.3 Text overflow — answers can break layout on mobile
**File:** `resources/js/pages/Game.vue` — voting phase answer cards + submitted answer display
**Bug:** No `break-words` class on answer text. A very long word (no spaces) will overflow the card on mobile.
**Fix:** Add `break-words` to all answer text displays:
- Voting phase answer text
- Submitted answer display in playing phase
- Results phase answer text

---

## Phase 2: Missing i18n (hardcoded English strings)

### 2.1 GameLayout.vue:15
```
{{ playerCount }} {{ playerCount === 1 ? 'player' : 'players' }}
```
→ `{{ playerCount }} {{ t(playerCount === 1 ? 'common.player' : 'common.players') }}`

### 2.2 ProfileSettings.vue:116
```
Dark mode
```
→ `{{ t('profile.settings.darkMode') }}`

### 2.3 ProfileSettings.vue:143
```
This action is irreversible. All your data will be permanently deleted.
```
→ `{{ t('profile.settings.deleteWarning') }}`

### 2.4 Welcome.vue:15
```
:title="isDark ? 'Light mode' : 'Dark mode'"
```
→ `:title="isDark ? t('nav.lightMode') : t('nav.darkMode')"`

### 2.5 HallOfFame.vue:94
```
label="Shuffle"
```
→ `:label="t('hallOfFame.shuffle')"`

### 2.6 Admin.vue:143
```
Ban IP address
```
→ `{{ t('admin.banIp') }}`

### 2.7 Admin.vue:199-203 (5 stats labels)
```
'Active today', 'Games today', 'Finished', 'Answers', 'Banned'
```
→ `t('admin.activeToday')`, `t('admin.gamesToday')`, `t('admin.finished')`, `t('admin.answers')`, `t('admin.banned')`

### New i18n keys to add (both NO and EN):
| Key | NO | EN |
|-----|----|----|
| `common.player` | `spiller` | `player` |
| `common.players` | `spillere` | `players` |
| `profile.settings.darkMode` | `Mørk modus` | `Dark mode` |
| `profile.settings.deleteWarning` | `Denne handlingen kan ikke angres. Alle dine data vil bli permanent slettet.` | `This action is irreversible. All your data will be permanently deleted.` |
| `nav.lightMode` | `Lys modus` | `Light mode` |
| `nav.darkMode` | `Mørk modus` | `Dark mode` |
| `hallOfFame.shuffle` | `Tilfeldig` | `Shuffle` |
| `admin.banIp` | `Utesteng IP-adresse` | `Ban IP address` |
| `admin.activeToday` | `Aktive i dag` | `Active today` |
| `admin.gamesToday` | `Spill i dag` | `Games today` |
| `admin.finished` | `Fullført` | `Finished` |
| `admin.answers` | `Svar` | `Answers` |
| `admin.banned` | `Utestengt` | `Banned` |

---

## Phase 3: Backend Validation

### 3.1 CreateGameRequest — add cross-field validation
**File:** `app/Application/Http/Requests/Api/V1/CreateGameRequest.php`
**Issue:** No validation that `min_players <= max_players` or `acronym_length_min <= acronym_length_max`.
**Fix:** Add `after()` method:
```php
public function after(): array
{
    return [
        function ($validator) {
            $s = $this->input('settings', []);
            if (isset($s['min_players'], $s['max_players']) && $s['min_players'] > $s['max_players']) {
                $validator->errors()->add('settings.min_players', 'Min players must be ≤ max players.');
            }
            if (isset($s['acronym_length_min'], $s['acronym_length_max']) && $s['acronym_length_min'] > $s['acronym_length_max']) {
                $validator->errors()->add('settings.acronym_length_min', 'Min length must be ≤ max length.');
            }
        },
    ];
}
```

### 3.2 Chat rate limit — scope per game, not globally
**File:** `app/Application/Http/Controllers/Api/V1/GameController.php` (chat method)
**Issue:** Rate limit key is `'chat:'.$player->id` — blocks chatting across all games.
**Fix:** Change to `'chat:'.$player->id.':'.$code`

---

## Phase 4: Mobile Polish

### 4.1 Acronym letter spacing on small phones
**File:** `resources/js/pages/Game.vue` — playing phase acronym display
**Current:** `gap-2 sm:gap-3`
**Fix:** Change to `gap-1.5 sm:gap-3` for tighter spacing on very small screens.

### 4.2 Welcome.vue sentence truncation
**File:** `resources/js/pages/Welcome.vue`
**Issue:** Gullkorn sentences with `truncate` cut off mid-word on one line.
**Fix:** Change `truncate` to `line-clamp-2` to allow wrapping to 2 lines.

---

## Files to Modify

| File | Changes |
|------|---------|
| `app/Application/Http/Controllers/Api/V1/RoundController.php` | Hide player_id during voting |
| `app/Application/Http/Requests/Api/V1/CreateGameRequest.php` | Cross-field validation |
| `app/Application/Http/Controllers/Api/V1/GameController.php` | Chat rate limit scope |
| `resources/js/composables/useI18n.js` | Remove dupes, add ~13 new keys |
| `resources/js/pages/Game.vue` | break-words, acronym gap |
| `resources/js/pages/Welcome.vue` | line-clamp-2 |
| `resources/js/pages/ProfileSettings.vue` | i18n for dark mode + delete warning |
| `resources/js/pages/HallOfFame.vue` | i18n for Shuffle |
| `resources/js/pages/Admin.vue` | i18n for 6 hardcoded strings |
| `resources/js/layouts/GameLayout.vue` | i18n for player/players |

---

## Verification

1. `dc exec select php artisan test --compact` — all tests pass
2. `dc exec select vendor/bin/pint --test` — style passes
3. `yarn build` — frontend compiles
4. Manual: switch language to English → verify all pages show English (no Norwegian leaking)
5. Manual: voting phase → verify answers don't show who wrote them
6. Manual: create game with very long answer → verify it wraps properly on mobile
