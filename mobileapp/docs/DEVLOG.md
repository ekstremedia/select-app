# Development Log

## 2025-01-31

### Session Goals
- Set up mobile app development environment
- Add internationalization (i18n) with Norwegian and English
- Add dark/light theming with dark as default
- Add connection status debug widget

### Completed Tasks

#### Environment Setup
1. Configured Android SDK environment variables in `~/.zshrc`
2. Installed Java 17 for Android builds (`brew install openjdk@17`)
3. Created `.env` file with Android emulator configuration (10.0.2.2)
4. Created `android/local.properties` with SDK path

#### App Renaming
- Renamed app from "Gullkorn" to "Select"
- Updated `app.json` (name, slug, scheme, package)
- Updated all hardcoded Norwegian text to use translation keys

#### Internationalization (i18n)
- Installed `i18next` and `react-i18next`
- Created translation files:
  - `src/i18n/locales/nb.json` (Norwegian Bokmål - default)
  - `src/i18n/locales/nn.json` (Norwegian Nynorsk)
  - `src/i18n/locales/en.json` (English)
- Created i18n configuration in `src/i18n/index.ts`
- Note: Removed `expo-localization` due to native module crash

#### Theming
- Created theme system:
  - `src/theme/colors.ts` - Light and dark color palettes
  - `src/theme/ThemeProvider.tsx` - React context provider
  - `src/theme/index.ts` - Exports
- Updated all screens to use dynamic theme colors
- Dark theme is default

#### Settings Persistence
- Created `src/stores/settingsStore.ts` with Zustand + AsyncStorage
- Persists language and theme preferences
- Settings screen allows changing language and theme

#### Connection Status Widget
- Created `src/components/ConnectionStatus.tsx`
- Shows API, WebSocket (Reverb), and Delectus status
- Expandable panel with connection details
- Refresh button to recheck connections

#### Screens Updated
- `app/_layout.tsx` - Added ThemeProvider wrapper, themed navigation
- `app/index.tsx` - Full theming and translations
- `app/create.tsx` - Full theming and translations
- `app/join.tsx` - Full theming and translations
- `app/settings.tsx` - Already had theming, verified working

### Issues Encountered & Solutions

| Issue | Solution |
|-------|----------|
| Expo Go "java.lang.String cannot be cast to Boolean" | Use `npx expo run:android` instead of Expo Go |
| "Unable to locate Java Runtime" | Install Java 17: `brew install openjdk@17` |
| "Cannot find native module 'ExpoLocalization'" | Removed expo-localization, use simple i18n config |
| Dependency version mismatches | Run `npx expo-doctor` then `npx expo install --fix` |
| Delectus showing "stopped" when running | Fixed status check logic in ConnectionStatus |

### Files Created
- `src/theme/colors.ts`
- `src/theme/ThemeProvider.tsx`
- `src/theme/index.ts`
- `src/i18n/index.ts`
- `src/i18n/locales/nb.json`
- `src/i18n/locales/nn.json`
- `src/i18n/locales/en.json`
- `src/stores/settingsStore.ts`
- `src/components/ConnectionStatus.tsx`
- `android/local.properties`
- `.env`
- `docs/DEVLOG.md` (this file)

### Files Modified
- `app.json` - App name and identifiers
- `app/_layout.tsx` - ThemeProvider, themed navigation
- `app/index.tsx` - Theming and translations
- `app/create.tsx` - Theming and translations
- `app/join.tsx` - Theming and translations
- `app/settings.tsx` - Verified theming
- `CHANGELOG.md` - Updated with changes
- `SETUP.md` - Added Java, dev builds, architecture docs
- `~/.zshrc` - Android SDK and Java environment variables

### Next Steps
- [ ] Add theming to `app/profile.tsx`
- [ ] Add theming to `app/game/[code].tsx`
- [ ] Test iOS simulator
- [ ] Add system theme detection (auto dark/light)
- [ ] Add more translations as needed
