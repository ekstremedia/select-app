import { useState, useEffect } from 'react';
import { View, Text, StyleSheet, TouchableOpacity } from 'react-native';
import { useTranslation } from 'react-i18next';
import { useTheme } from '../theme';

const API_BASE_URL = process.env.EXPO_PUBLIC_API_URL || 'http://localhost:8000/api/v1';
const REVERB_HOST = process.env.EXPO_PUBLIC_REVERB_HOST || 'localhost';
const REVERB_PORT = process.env.EXPO_PUBLIC_REVERB_PORT || '8080';
const REVERB_SCHEME = process.env.EXPO_PUBLIC_REVERB_SCHEME || 'http';

interface ConnectionState {
  api: 'checking' | 'connected' | 'disconnected';
  reverb: 'checking' | 'connected' | 'disconnected';
  delectus: 'checking' | 'running' | 'stopped' | 'unknown';
}

export default function ConnectionStatus() {
  const { t } = useTranslation();
  const { theme } = useTheme();
  const [status, setStatus] = useState<ConnectionState>({
    api: 'checking',
    reverb: 'checking',
    delectus: 'checking',
  });
  const [expanded, setExpanded] = useState(false);

  const checkConnections = async () => {
    setStatus({ api: 'checking', reverb: 'checking', delectus: 'checking' });

    // Check API connection
    try {
      const response = await fetch(`${API_BASE_URL}/auth/me`, {
        method: 'GET',
        headers: { 'Accept': 'application/json' },
      });
      // Even a 401 means the API is reachable
      setStatus(prev => ({ ...prev, api: 'connected' }));
    } catch (error) {
      setStatus(prev => ({ ...prev, api: 'disconnected' }));
    }

    // Check Delectus status (successful response means it's running)
    try {
      const response = await fetch(`${API_BASE_URL}/debug/delectus`, {
        method: 'GET',
        headers: { 'Accept': 'application/json' },
      });
      if (response.ok) {
        setStatus(prev => ({ ...prev, delectus: 'running' }));
      } else {
        setStatus(prev => ({ ...prev, delectus: 'stopped' }));
      }
    } catch (error) {
      setStatus(prev => ({ ...prev, delectus: 'unknown' }));
    }

    // Check Reverb/WebSocket connection
    try {
      const wsUrl = `${REVERB_SCHEME === 'https' ? 'wss' : 'ws'}://${REVERB_HOST}:${REVERB_PORT}/app/select-key`;
      const ws = new WebSocket(wsUrl);

      const timeout = setTimeout(() => {
        ws.close();
        setStatus(prev => ({ ...prev, reverb: 'disconnected' }));
      }, 5000);

      ws.onopen = () => {
        clearTimeout(timeout);
        setStatus(prev => ({ ...prev, reverb: 'connected' }));
        ws.close();
      };

      ws.onerror = () => {
        clearTimeout(timeout);
        setStatus(prev => ({ ...prev, reverb: 'disconnected' }));
      };
    } catch (error) {
      setStatus(prev => ({ ...prev, reverb: 'disconnected' }));
    }
  };

  useEffect(() => {
    checkConnections();
  }, []);

  const getStatusColor = (state: string) => {
    switch (state) {
      case 'connected':
      case 'running':
        return theme.success;
      case 'disconnected':
      case 'stopped':
        return theme.error;
      case 'checking':
        return theme.warning;
      case 'unknown':
        return theme.textTertiary;
      default:
        return theme.textTertiary;
    }
  };

  const getStatusText = (state: string) => {
    switch (state) {
      case 'connected': return t('connection.connected');
      case 'disconnected': return t('connection.disconnected');
      case 'checking': return t('connection.checking');
      case 'running': return t('connection.running');
      case 'stopped': return t('connection.stopped');
      case 'unknown': return t('connection.unknown');
      default: return state;
    }
  };

  const getOverallColor = () => {
    const allGood = status.api === 'connected' && status.reverb === 'connected' && status.delectus === 'running';
    const allBad = status.api === 'disconnected' && status.reverb === 'disconnected';
    const checking = status.api === 'checking' || status.reverb === 'checking' || status.delectus === 'checking';

    if (allGood) return theme.success;
    if (allBad) return theme.error;
    if (checking) return theme.warning;
    return theme.warning;
  };

  const styles = createStyles(theme);

  return (
    <View style={styles.container}>
      <TouchableOpacity onPress={() => setExpanded(!expanded)} style={styles.indicator}>
        <View style={[styles.dot, { backgroundColor: getOverallColor() }]} />
        {expanded && <Text style={styles.label}>{t('common.connection')}</Text>}
      </TouchableOpacity>

      {expanded && (
        <View style={styles.details}>
          <View style={styles.row}>
            <View style={[styles.smallDot, { backgroundColor: getStatusColor(status.api) }]} />
            <Text style={styles.text}>{t('connection.api')}: {getStatusText(status.api)}</Text>
          </View>
          <View style={styles.row}>
            <View style={[styles.smallDot, { backgroundColor: getStatusColor(status.reverb) }]} />
            <Text style={styles.text}>{t('connection.websocket')}: {getStatusText(status.reverb)}</Text>
          </View>
          <View style={styles.row}>
            <View style={[styles.smallDot, { backgroundColor: getStatusColor(status.delectus) }]} />
            <Text style={styles.text}>{t('connection.delectus')}: {getStatusText(status.delectus)}</Text>
          </View>
          <TouchableOpacity onPress={checkConnections} style={styles.refreshButton}>
            <Text style={styles.refreshText}>{t('common.refresh')}</Text>
          </TouchableOpacity>
          <Text style={styles.urlText}>{API_BASE_URL}</Text>
          <Text style={styles.urlText}>{REVERB_HOST}:{REVERB_PORT}</Text>
        </View>
      )}
    </View>
  );
}

const createStyles = (theme: any) =>
  StyleSheet.create({
    container: {
      position: 'absolute',
      top: 50,
      right: 16,
      alignItems: 'flex-end',
      zIndex: 100,
    },
    indicator: {
      flexDirection: 'row',
      alignItems: 'center',
      backgroundColor: theme.surface,
      borderRadius: 20,
      padding: 8,
      gap: 6,
    },
    dot: {
      width: 16,
      height: 16,
      borderRadius: 8,
    },
    smallDot: {
      width: 10,
      height: 10,
      borderRadius: 5,
    },
    label: {
      fontSize: 12,
      color: theme.textSecondary,
      fontWeight: '500',
    },
    details: {
      marginTop: 8,
      backgroundColor: theme.surface,
      borderRadius: 12,
      padding: 12,
      shadowColor: '#000',
      shadowOffset: { width: 0, height: 2 },
      shadowOpacity: 0.1,
      shadowRadius: 4,
      elevation: 3,
      minWidth: 200,
    },
    row: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: 8,
      marginBottom: 6,
    },
    text: {
      fontSize: 13,
      color: theme.text,
    },
    urlText: {
      fontSize: 10,
      color: theme.textTertiary,
      marginTop: 4,
    },
    refreshButton: {
      marginTop: 8,
      backgroundColor: theme.primary,
      borderRadius: 6,
      padding: 6,
      alignItems: 'center',
    },
    refreshText: {
      color: '#fff',
      fontSize: 12,
      fontWeight: '500',
    },
  });
