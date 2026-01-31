export const lightTheme = {
  // Background colors
  background: '#f8fafc',
  surface: '#ffffff',
  surfaceSecondary: '#f1f5f9',

  // Text colors
  text: '#1e293b',
  textSecondary: '#64748b',
  textTertiary: '#94a3b8',

  // Primary colors (indigo)
  primary: '#6366f1',
  primaryLight: '#818cf8',
  primaryDark: '#4f46e5',

  // Status colors
  success: '#22c55e',
  error: '#ef4444',
  warning: '#f59e0b',
  info: '#3b82f6',

  // Border colors
  border: '#e2e8f0',
  borderLight: '#f1f5f9',

  // Input colors
  inputBackground: '#ffffff',
  inputBorder: '#e2e8f0',
  inputText: '#1e293b',
  inputPlaceholder: '#94a3b8',

  // Button colors
  buttonPrimaryBackground: '#6366f1',
  buttonPrimaryText: '#ffffff',
  buttonSecondaryBackground: '#ffffff',
  buttonSecondaryText: '#6366f1',
  buttonSecondaryBorder: '#6366f1',
  buttonDisabledBackground: '#e2e8f0',
  buttonDisabledText: '#94a3b8',

  // Card colors
  cardBackground: '#ffffff',
  cardBorder: '#e2e8f0',

  // Header colors
  headerBackground: '#6366f1',
  headerText: '#ffffff',

  // Overlay
  overlay: 'rgba(0, 0, 0, 0.5)',
};

export const darkTheme = {
  // Background colors
  background: '#0f172a',
  surface: '#1e293b',
  surfaceSecondary: '#334155',

  // Text colors
  text: '#f1f5f9',
  textSecondary: '#94a3b8',
  textTertiary: '#64748b',

  // Primary colors (indigo)
  primary: '#818cf8',
  primaryLight: '#a5b4fc',
  primaryDark: '#6366f1',

  // Status colors
  success: '#4ade80',
  error: '#f87171',
  warning: '#fbbf24',
  info: '#60a5fa',

  // Border colors
  border: '#334155',
  borderLight: '#475569',

  // Input colors
  inputBackground: '#1e293b',
  inputBorder: '#475569',
  inputText: '#f1f5f9',
  inputPlaceholder: '#64748b',

  // Button colors
  buttonPrimaryBackground: '#6366f1',
  buttonPrimaryText: '#ffffff',
  buttonSecondaryBackground: '#1e293b',
  buttonSecondaryText: '#818cf8',
  buttonSecondaryBorder: '#818cf8',
  buttonDisabledBackground: '#334155',
  buttonDisabledText: '#64748b',

  // Card colors
  cardBackground: '#1e293b',
  cardBorder: '#334155',

  // Header colors
  headerBackground: '#1e293b',
  headerText: '#f1f5f9',

  // Overlay
  overlay: 'rgba(0, 0, 0, 0.7)',
};

export type Theme = typeof lightTheme;
