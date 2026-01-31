import { View, Text, TouchableOpacity, StyleSheet, ScrollView } from 'react-native';
import { useTranslation } from 'react-i18next';
import { useTheme } from '../src/theme';
import { useSettingsStore, Language, ThemeMode } from '../src/stores/settingsStore';

const languages: { code: Language; label: string }[] = [
  { code: 'nb', label: 'Norsk bokmål' },
  { code: 'nn', label: 'Norsk nynorsk' },
  { code: 'en', label: 'English' },
];

const themes: { code: ThemeMode; labelKey: string }[] = [
  { code: 'dark', labelKey: 'settings.darkMode' },
  { code: 'light', labelKey: 'settings.lightMode' },
];

export default function SettingsScreen() {
  const { t } = useTranslation();
  const { theme } = useTheme();
  const { language, theme: themeMode, setLanguage, setTheme } = useSettingsStore();

  const styles = createStyles(theme);

  return (
    <ScrollView style={styles.container}>
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>{t('settings.language')}</Text>
        {languages.map((lang) => (
          <TouchableOpacity
            key={lang.code}
            style={[
              styles.option,
              language === lang.code && styles.optionSelected,
            ]}
            onPress={() => setLanguage(lang.code)}
          >
            <Text
              style={[
                styles.optionText,
                language === lang.code && styles.optionTextSelected,
              ]}
            >
              {lang.label}
            </Text>
            {language === lang.code && (
              <View style={styles.checkmark}>
                <Text style={styles.checkmarkText}>✓</Text>
              </View>
            )}
          </TouchableOpacity>
        ))}
      </View>

      <View style={styles.section}>
        <Text style={styles.sectionTitle}>{t('settings.theme')}</Text>
        {themes.map((themeOption) => (
          <TouchableOpacity
            key={themeOption.code}
            style={[
              styles.option,
              themeMode === themeOption.code && styles.optionSelected,
            ]}
            onPress={() => setTheme(themeOption.code)}
          >
            <Text
              style={[
                styles.optionText,
                themeMode === themeOption.code && styles.optionTextSelected,
              ]}
            >
              {t(themeOption.labelKey)}
            </Text>
            {themeMode === themeOption.code && (
              <View style={styles.checkmark}>
                <Text style={styles.checkmarkText}>✓</Text>
              </View>
            )}
          </TouchableOpacity>
        ))}
      </View>
    </ScrollView>
  );
}

const createStyles = (theme: any) =>
  StyleSheet.create({
    container: {
      flex: 1,
      backgroundColor: theme.background,
    },
    section: {
      padding: 16,
      marginBottom: 8,
    },
    sectionTitle: {
      fontSize: 14,
      fontWeight: '600',
      color: theme.textSecondary,
      textTransform: 'uppercase',
      marginBottom: 12,
      letterSpacing: 0.5,
    },
    option: {
      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'space-between',
      backgroundColor: theme.surface,
      padding: 16,
      borderRadius: 12,
      marginBottom: 8,
      borderWidth: 2,
      borderColor: 'transparent',
    },
    optionSelected: {
      borderColor: theme.primary,
    },
    optionText: {
      fontSize: 16,
      color: theme.text,
    },
    optionTextSelected: {
      color: theme.primary,
      fontWeight: '600',
    },
    checkmark: {
      width: 24,
      height: 24,
      borderRadius: 12,
      backgroundColor: theme.primary,
      alignItems: 'center',
      justifyContent: 'center',
    },
    checkmarkText: {
      color: '#fff',
      fontSize: 14,
      fontWeight: 'bold',
    },
  });
