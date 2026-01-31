# Mobile App Development Setup

This guide covers setting up the React Native (Expo) mobile app for development.

## Prerequisites

### Required

| Tool | Version | Notes |
|------|---------|-------|
| Node.js | 20.x | Required for Expo |
| Yarn | 1.x | Package manager |
| Java | 17 | Required for Android builds |
| Xcode | 15+ | For iOS development (macOS only) |
| Android Studio | Latest | For Android development |

### Optional but Recommended

| Tool | Purpose |
|------|---------|
| Watchman | Faster file watching for React Native |

### Install Java 17 (Required for Android)

```bash
brew install openjdk@17
```

Add to `~/.zshrc`:

```bash
# Java for Android development
export JAVA_HOME=/opt/homebrew/opt/openjdk@17
export PATH="/opt/homebrew/opt/openjdk@17/bin:$PATH"
```

## Installation

### 1. Install Watchman (macOS)

```bash
brew install watchman
```

### 2. Configure Android SDK Environment

Add to your `~/.zshrc` (or `~/.bashrc`):

```bash
# Android SDK
export ANDROID_HOME=$HOME/Library/Android/sdk
export PATH=$PATH:$ANDROID_HOME/emulator
export PATH=$PATH:$ANDROID_HOME/platform-tools
export PATH=$PATH:$ANDROID_HOME/cmdline-tools/latest/bin
```

Reload your shell:

```bash
source ~/.zshrc
```

Verify the setup:

```bash
echo $ANDROID_HOME  # Should show path to Android SDK
adb --version       # Should show adb version
```

### 3. Configure Environment Variables

Copy the example environment file:

```bash
cp .env.example .env
```

Edit `.env` with your configuration:

```bash
# For Android Emulator (recommended for local development)
EXPO_PUBLIC_API_URL=http://10.0.2.2:8000/api/v1
EXPO_PUBLIC_REVERB_HOST=10.0.2.2
EXPO_PUBLIC_REVERB_PORT=8080
EXPO_PUBLIC_REVERB_SCHEME=http
EXPO_PUBLIC_REVERB_APP_KEY=select-key
```

**Important:** Android Emulator uses `10.0.2.2` to reach the host machine's `localhost`.

### 4. Install Dependencies

```bash
yarn install
```

## Running the App

### Start Android Emulator

Before running on Android, you need to start an emulator:

```bash
# List available emulators
~/Library/Android/sdk/emulator/emulator -list-avds

# Start an emulator (e.g., Pixel_9_Pro)
~/Library/Android/sdk/emulator/emulator -avd Pixel_9_Pro &
```

Or after reloading your shell (`source ~/.zshrc`):

```bash
emulator -avd Pixel_9_Pro &
```

Alternatively, start from Android Studio: **Tools → Device Manager → Click play button**.

### Start the Development Server

```bash
yarn start
```

This starts the Expo development server. You'll see options to:
- Press `i` to open iOS Simulator
- Press `a` to open Android Emulator
- Scan QR code with Expo Go app on physical device

### Platform-Specific Commands

```bash
# Run on iOS Simulator
yarn ios

# Run on Android Emulator
yarn android

# Run on web browser
yarn web
```

## Platform-Specific Configuration

### iOS Simulator

The iOS Simulator can reach the host machine using:
- `localhost` - Direct localhost access
- `select.test` - If configured in `/etc/hosts`

Update `.env` for iOS:

```bash
EXPO_PUBLIC_API_URL=http://localhost:8000/api/v1
EXPO_PUBLIC_REVERB_HOST=localhost
```

Or with custom hostname:

```bash
EXPO_PUBLIC_API_URL=http://select.test:8000/api/v1
EXPO_PUBLIC_REVERB_HOST=select.test
```

### Android Emulator

Android Emulator requires `10.0.2.2` to reach the host machine's localhost:

```bash
EXPO_PUBLIC_API_URL=http://10.0.2.2:8000/api/v1
EXPO_PUBLIC_REVERB_HOST=10.0.2.2
```

### Physical Device

Use your computer's local IP address:

```bash
# Find your IP address (macOS)
ipconfig getifaddr en0

# Example configuration
EXPO_PUBLIC_API_URL=http://192.168.1.100:8000/api/v1
EXPO_PUBLIC_REVERB_HOST=192.168.1.100
```

## Troubleshooting

### "adb: command not found"

Run `source ~/.zshrc` to reload environment variables, or restart your terminal.

### "Connection refused" on Android Emulator

Ensure you're using `10.0.2.2` instead of `localhost` in `.env`.

### iOS Simulator can't connect

1. Verify `select.test` is in `/etc/hosts`:
   ```bash
   grep select.test /etc/hosts
   # Should show: 127.0.0.1   select.test
   ```

2. Or use `localhost` in `.env` instead.

### Backend not responding

Check that the Docker containers are running:

```bash
cd ../website
docker compose ps
```

Ensure `select-nginx` and `select-reverb` are running.

### Metro bundler issues

Clear the Metro cache:

```bash
yarn start --clear
```

### "java.lang.String cannot be cast to java.lang.Boolean" (Expo Go)

This is a known bug with Expo Go on some configurations. Use a development build instead:

```bash
# Build and run directly on Android (bypasses Expo Go)
npx expo run:android

# For iOS
npx expo run:ios
```

### Dependency version mismatches

Run the Expo doctor to check and fix dependencies:

```bash
npx expo-doctor
npx expo install --fix
```

### "Unable to locate a Java Runtime"

Install Java 17 and configure environment:

```bash
brew install openjdk@17
```

Add to `~/.zshrc`:
```bash
export JAVA_HOME=/opt/homebrew/opt/openjdk@17
export PATH="/opt/homebrew/opt/openjdk@17/bin:$PATH"
```

Then reload: `source ~/.zshrc`

### Watchman issues

Reset Watchman:

```bash
watchman watch-del-all
```

## Development Workflow

1. **Start the backend** (in `website/` directory):
   ```bash
   docker compose up -d
   ```

2. **Start the mobile app** (in `mobileapp/` directory):
   ```bash
   yarn start
   ```

3. **Open simulator/emulator**:
   - Press `i` for iOS Simulator
   - Press `a` for Android Emulator

4. **Debug API/WebSocket**:
   - Open http://select.test:8000/debug in browser
   - Test WebSocket connections and API endpoints

## Architecture

### Theming

The app supports dark and light themes with dark as default.

**Files:**
- `src/theme/colors.ts` - Theme color definitions (lightTheme, darkTheme)
- `src/theme/ThemeProvider.tsx` - React context provider
- `src/theme/index.ts` - Exports

**Usage in components:**
```tsx
import { useTheme } from '../src/theme';

function MyComponent() {
  const { theme, isDark } = useTheme();

  return (
    <View style={{ backgroundColor: theme.background }}>
      <Text style={{ color: theme.text }}>Hello</Text>
    </View>
  );
}
```

### Internationalization (i18n)

Supports Norwegian Bokmål (default), Nynorsk, and English.

**Files:**
- `src/i18n/index.ts` - i18next configuration
- `src/i18n/locales/nb.json` - Norwegian Bokmål
- `src/i18n/locales/nn.json` - Norwegian Nynorsk
- `src/i18n/locales/en.json` - English

**Usage in components:**
```tsx
import { useTranslation } from 'react-i18next';

function MyComponent() {
  const { t } = useTranslation();

  return <Text>{t('home.welcome', { name: 'Player' })}</Text>;
}
```

### Settings Persistence

User preferences are stored via Zustand with AsyncStorage:

- `src/stores/settingsStore.ts` - Language and theme preferences

Settings are automatically persisted and restored on app launch.

### Debug Widget

The `ConnectionStatus` component (`src/components/ConnectionStatus.tsx`) shows:
- API connection status
- WebSocket (Reverb) connection status
- Delectus game orchestrator status

Tap to expand for details, tap refresh to recheck connections.

## Available Scripts

| Command | Description |
|---------|-------------|
| `yarn start` | Start Expo development server |
| `yarn start --clear` | Start with cleared Metro cache |
| `yarn ios` | Run on iOS Simulator |
| `yarn android` | Run on Android Emulator |
| `yarn web` | Run in web browser |
| `yarn lint` | Run ESLint |
| `yarn test` | Run tests |
| `npx expo run:android` | Build and run Android dev build |
| `npx expo run:ios` | Build and run iOS dev build |
| `npx expo-doctor` | Check for dependency issues |
| `npx expo install --fix` | Fix dependency versions |
