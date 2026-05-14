import React, { useCallback, useEffect, useState } from 'react';
import { View, ScrollView, StyleSheet, RefreshControl } from 'react-native';
import { Text, Card, Chip, Avatar, Button, Divider } from 'react-native-paper';
import { useNavigation } from '@react-navigation/native';
import { Ionicons } from '@expo/vector-icons';

import { fetchDashboard } from '../api/dashboard';
import { useAuthStore } from '../store/useAuthStore';
import { DashboardData, TRANSACTION_TYPE_LABELS } from '../types';
import LoadingView from '../components/LoadingView';
import ErrorView from '../components/ErrorView';

const PRIMARY = '#1a237e';

function StatCard({ icon, label, value, color }: { icon: keyof typeof Ionicons.glyphMap; label: string; value: number; color: string }) {
  return (
    <Card style={[styles.statCard, { borderLeftColor: color }]}>
      <Card.Content style={styles.statContent}>
        <Ionicons name={icon} size={28} color={color} />
        <View style={styles.statText}>
          <Text variant="headlineMedium" style={{ color, fontWeight: '700' }}>{value}</Text>
          <Text variant="bodySmall" style={styles.statLabel}>{label}</Text>
        </View>
      </Card.Content>
    </Card>
  );
}

export default function DashboardScreen() {
  const { user, logout } = useAuthStore();
  const navigation = useNavigation<any>();
  const [data, setData] = useState<DashboardData | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    try {
      setError(null);
      const result = await fetchDashboard();
      setData(result);
    } catch {
      setError('Nie udało się pobrać danych pulpitu.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => { load(); }, []);

  const onRefresh = () => { setRefreshing(true); load(); };

  if (loading) return <LoadingView message="Ładowanie pulpitu..." />;
  if (error) return <ErrorView message={error} onRetry={load} />;

  return (
    <ScrollView
      style={styles.root}
      contentContainerStyle={styles.content}
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} colors={[PRIMARY]} />}
    >
      <View style={styles.header}>
        <View>
          <Text style={styles.welcome}>Witaj,</Text>
          <Text style={styles.userName}>{user?.display_name}</Text>
          <Chip icon="shield-account" style={styles.roleChip} compact>
            {user?.role === 'administrator' ? 'Administrator' : user?.role === 'manager' ? 'Menedżer' : 'Agent'}
          </Chip>
        </View>
        <Button
          icon="logout"
          mode="text"
          onPress={logout}
          textColor="#fff"
          compact
        >
          Wyloguj
        </Button>
      </View>

      <Text style={styles.sectionTitle}>Statystyki</Text>
      <View style={styles.statsGrid}>
        <StatCard icon="home-outline" label="Nieruchomości" value={data?.stats.properties ?? 0} color="#1565c0" />
        <StatCard icon="document-text-outline" label="Umowy" value={data?.stats.agreements ?? 0} color="#2e7d32" />
        <StatCard icon="people-outline" label="Klienci" value={data?.stats.clients ?? 0} color="#e65100" />
        <StatCard icon="search-outline" label="Poszukiwania" value={data?.stats.searches ?? 0} color="#6a1b9a" />
      </View>

      {data && data.recent_properties.length > 0 && (
        <>
          <Text style={styles.sectionTitle}>Ostatnie nieruchomości</Text>
          {data.recent_properties.slice(0, 5).map((p) => (
            <Card
              key={p.id}
              style={styles.listCard}
              onPress={() => navigation.navigate('PropertiesStack', { screen: 'PropertyDetail', params: { id: p.id } })}
            >
              <Card.Content>
                <Text variant="titleSmall" numberOfLines={1}>#{p.offer_number} — {p.city}</Text>
                <Text variant="bodySmall" style={styles.muted}>
                  {p.property_type} · {TRANSACTION_TYPE_LABELS[p.transaction_type]}
                  {p.price ? ` · ${p.price.toLocaleString('pl-PL')} ${p.price_currency}` : ''}
                </Text>
              </Card.Content>
            </Card>
          ))}
          <Button
            onPress={() => navigation.navigate('PropertiesStack')}
            textColor={PRIMARY}
            style={styles.moreButton}
          >
            Wszystkie nieruchomości →
          </Button>
        </>
      )}

      {data && data.recent_agreements.length > 0 && (
        <>
          <Text style={styles.sectionTitle}>Ostatnie umowy</Text>
          {data.recent_agreements.slice(0, 5).map((a) => (
            <Card
              key={a.id}
              style={styles.listCard}
              onPress={() => navigation.navigate('AgreementsStack', { screen: 'AgreementDetail', params: { id: a.id } })}
            >
              <Card.Content>
                <Text variant="titleSmall">#{a.agreement_number}</Text>
                <Text variant="bodySmall" style={styles.muted}>
                  {TRANSACTION_TYPE_LABELS[a.transaction_type]} · {a.current_stage}
                </Text>
              </Card.Content>
            </Card>
          ))}
          <Button
            onPress={() => navigation.navigate('AgreementsStack')}
            textColor={PRIMARY}
            style={styles.moreButton}
          >
            Wszystkie umowy →
          </Button>
        </>
      )}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: '#f5f5f5' },
  content: { padding: 16 },
  header: { backgroundColor: PRIMARY, borderRadius: 12, padding: 20, flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 20 },
  welcome: { color: '#90caf9', fontSize: 13 },
  userName: { color: '#fff', fontSize: 20, fontWeight: '700' },
  roleChip: { backgroundColor: 'rgba(255,255,255,0.2)', marginTop: 6, alignSelf: 'flex-start' },
  sectionTitle: { fontSize: 16, fontWeight: '600', color: '#424242', marginBottom: 10, marginTop: 8 },
  statsGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 10, marginBottom: 8 },
  statCard: { width: '47%', borderLeftWidth: 4, borderRadius: 8 },
  statContent: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  statText: {},
  statLabel: { color: '#757575' },
  listCard: { marginBottom: 8, borderRadius: 8 },
  muted: { color: '#757575', marginTop: 2 },
  moreButton: { marginBottom: 4 },
});
