# Daily Development Log

## 2026-01-30

### WebSocket / Reverb Fixes

Fixed multiple issues preventing WebSocket connections and presence channel authorization in the debug console.

#### Issue 1: Reverb App Key Not Displayed
**Problem:** Debug console showed empty App Key because config path was wrong.

**Fix:** Updated `resources/views/debug.blade.php`:
```php
// Before (wrong path)
config('reverb.apps.0.key')

// After (correct path - nested apps array)
config('reverb.apps.apps.0.key')
```

#### Issue 2: Presence Channel Auth Failing (403)
**Problem:** Subscribing to presence channels (`presence-game.{code}`) failed with 403 Forbidden. Laravel's default `/broadcasting/auth` endpoint doesn't handle guest token authentication.

**Fix:** Created custom broadcast auth controller:
- **File:** `app/Application/Http/Controllers/Api/V1/BroadcastAuthController.php`
- Handles `X-Guest-Token` header for guest authentication
- Generates proper Pusher auth signatures for presence channels
- Route: `POST /api/broadcasting/auth` in `routes/web.php`

#### Issue 3: Debug Console Auth Request
**Problem:** Debug console needed to send CSRF token and guest token with auth requests.

**Fix:** Updated `resources/views/debug.blade.php`:
- Added CSRF meta tag: `<meta name="csrf-token" content="{{ csrf_token() }}">`
- Custom fetch handler in Pusher `channelAuthorization` config
- Includes both CSRF token and guest token in headers

### Files Changed
- `resources/views/debug.blade.php` - Fixed config paths, added CSRF, custom auth handler
- `routes/web.php` - Added custom broadcast auth route
- `routes/channels.php` - Added guest token handling (backup, main logic in controller)
- `app/Application/Http/Controllers/Api/V1/BroadcastAuthController.php` - New file

### Laravel Boost Installation
Installed Laravel Boost v2.0.4 for AI-assisted development:
```bash
docker compose exec app composer require laravel/boost --dev
```

To complete setup (requires interactive terminal):
```bash
docker compose exec -it app php artisan boost:install
```

Note: Host machine has PHP 8.3, project requires PHP 8.4+. Must run artisan commands inside Docker container.

### Testing
Verified in debug console:
1. WebSocket connects to Reverb
2. Guest creation works
3. Game creation works
4. Presence channel subscription succeeds with proper auth
