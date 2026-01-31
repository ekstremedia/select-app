# Changelog

All notable changes to the Select mobile app will be documented in this file.

## [Unreleased]

### Added - 2025-01-31

#### Internationalization (i18n)
- Added i18next for translations with react-i18next integration
- Norwegian Bokmål (nb) as default language
- Norwegian Nynorsk (nn) support
- English (en) support
- Translation files in `src/i18n/locales/`
- Settings persistence for language preference

#### Theming
- Dark and light theme support with dark as default
- Theme colors defined in `src/theme/colors.ts`
- ThemeProvider context in `src/theme/ThemeProvider.tsx`
- All screens updated to use dynamic theming
- Settings persistence for theme preference

#### Debug Widget
- ConnectionStatus component showing real-time connection status
- API connection status (green/red indicator)
- WebSocket (Reverb) connection status
- Delectus game orchestrator status
- Expandable panel with refresh button

#### Settings Screen
- Language selection (Bokmål, Nynorsk, English)
- Theme selection (Dark, Light)
- Persisted via AsyncStorage through Zustand store

### Changed
- Renamed app from "Gullkorn" to "Select"
- Updated app.json with new name, slug, and scheme
- All hardcoded Norwegian text replaced with translation keys

### Fixed
- Fixed Expo Go boolean casting bug by using development builds
- Fixed dependency version mismatches with `npx expo-doctor` and `npx expo install --fix`
- Removed expo-localization native module dependency (was causing crashes)

---

## [Initial] - 2025-01-30

### Added
- Initial Expo React Native project with TypeScript
- Expo Router for file-based navigation
- Zustand stores for auth and game state management
- Persistent auth state with AsyncStorage
- Guest player creation flow
- User login and registration screens
- Guest-to-user account conversion
- Profile screen with player statistics
- Game creation screen with settings
- Game code entry for joining games
- Real-time lobby with player list
- WebSocket integration with Laravel Echo
- Answer submission screen with live validation
- Visual letter-by-letter validation feedback
- Countdown timer for deadlines
- Voting screen with answer selection
- Round results with vote breakdown
- Final leaderboard for completed games
- Game sharing functionality

### Screens
- / - Home (guest creation or main menu)
- /create - Create new game with settings
- /join - Enter game code to join
- /profile - Player profile and auth
- /game/[code] - Active game (lobby, play, vote, results)

### Features
- Real-time player join/leave updates
- Automatic screen transitions based on game state
- Answer validation against acronym letters
- Timer warnings when deadline approaches
- Vote change support during voting phase
- Score tracking throughout game
