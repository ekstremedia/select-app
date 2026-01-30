# Changelog

All notable changes to the Gullkorn mobile app will be documented in this file.

## [Unreleased]

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
