import React, { useCallback, useEffect, useState } from 'react';
import { View, ScrollView, StyleSheet, Alert } from 'react-native';
import { Text, Card, Button, Chip, IconButton } from 'react-native-paper';
import { useNavigation, useRoute } from '@react-navigation/native';

import { fetchSearch, deleteSearch } from '../../api/searches';
import { SearchDetail, TRANSACTION_TYPE_LABELS } from '../../types';
import { SearchDetailRouteProp, SearchesNavProp } from '../../navigation/types';
import { useAuthStore } from '../../store/useAuthStore';
import LoadingView from '../../components/LoadingView';
import ErrorView from '../../components/ErrorView';

const PRIMARY = '#1a237e';

function Row({ label, value }: { label: string; value: string | number | null | undefined }) {
  if (value == null || value === '') return null;
  return (
    <View style={styles.row}>
      <Text style={styles.label}>{label}</Text>
      <Text style={styles.value}>{String(value)}</Text>
    </View>
  );
}

export default function SearchDetailScreen() {
  const navigation = useNavigation<SearchesNavProp>();
  const route = useRoute<SearchDetailRouteProp>();
  const { user } = useAuthStore();
  const [search, setSearch] = useState<SearchDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    try {
      setError(null);
      const data = await fetchSearch(route.params.id);
      setSearch(data);
    } catch {
      setError('Nie udało się pobrać danych poszukiwania.');
    } finally {
      setLoading(false);
    }
  }, [route.params.id]);

  useEffect(() => { load(); }, []);

  const handleDelete = () => {
    Alert.alert('Usuń poszukiwanie', 'Czy na pewno chcesz usunąć to poszukiwanie?', [
      { text: 'Anuluj', style: 'cancel' },
      { text: 'Usuń', style: 'destructive', onPress: async () => { await deleteSearch(route.params.id); navigation.goBack(); } },
    ]);
  };

  if (loading) return <LoadingView message="Ładowanie poszukiwania..." />;
  if (error || !search) return <ErrorView message={error ?? 'Błąd'} onRetry={load} />;

  const canDelete = user?.role === 'administrator';

  return (
    <ScrollView style={styles.root} contentContainerStyle={styles.content}>
      <View style={styles.headerRow}>
        <View style={{ flex: 1 }}>
          <Text style={styles.num}>Poszukiwanie #{search.search_number}</Text>
          <View style={styles.chips}>
            <Chip compact style={styles.chip}>{TRANSACTION_TYPE_LABELS[search.transaction_type] ?? search.transaction_type}</Chip>
            {search.property_type && <Chip compact style={styles.chip}>{search.property_type}</Chip>}
          </View>
        </View>
        <IconButton icon="pencil" size={24} onPress={() => navigation.navigate('SearchForm', { id: search.id })} iconColor={PRIMARY} />
      </View>

      <Card style={styles.card}>
        <Card.Title title="Kryteria" />
        <Card.Content>
          <Row label="Lokalizacja" value={search.location_text} />
          <Row label="Budżet od" value={search.budget_from != null ? `${search.budget_from.toLocaleString('pl-PL')} PLN` : undefined} />
          <Row label="Budżet do" value={search.budget_to != null ? `${search.budget_to.toLocaleString('pl-PL')} PLN` : undefined} />
          <Row label="Powierzchnia od" value={search.area_from != null ? `${search.area_from} m²` : undefined} />
          <Row label="Powierzchnia do" value={search.area_to != null ? `${search.area_to} m²` : undefined} />
          <Row label="Pokoje od" value={search.rooms_from} />
          <Row label="Pokoje do" value={search.rooms_to} />
          <Row label="Opiekun" value={search.owner_name} />
        </Card.Content>
      </Card>

      {search.description ? (
        <Card style={styles.card}>
          <Card.Title title="Opis" />
          <Card.Content>
            <Text style={styles.description}>{search.description.replace(/<[^>]+>/g, '')}</Text>
          </Card.Content>
        </Card>
      ) : null}

      {search.clients.length > 0 && (
        <Card style={styles.card}>
          <Card.Title title={`Klienci (${search.clients.length})`} />
          <Card.Content>
            {search.clients.map(c => (
              <View key={c.id} style={styles.row}>
                <Text style={styles.label}>{c.client_type === 'company' ? c.company_name : `${c.first_name} ${c.last_name}`}</Text>
                <Text style={styles.value}>{c.phone}</Text>
              </View>
            ))}
          </Card.Content>
        </Card>
      )}

      <View style={styles.actions}>
        <Button mode="contained" onPress={() => navigation.navigate('SearchForm', { id: search.id })} buttonColor={PRIMARY} icon="pencil" style={styles.btn}>Edytuj</Button>
        {canDelete && <Button mode="outlined" onPress={handleDelete} textColor="#c62828" icon="delete" style={styles.btn}>Usuń</Button>}
        <Button mode="text" onPress={() => navigation.goBack()} icon="arrow-left" style={styles.btn}>Powrót</Button>
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: '#f5f5f5' },
  content: { padding: 16 },
  headerRow: { flexDirection: 'row', alignItems: 'flex-start', marginBottom: 16 },
  num: { fontSize: 18, fontWeight: '700', color: PRIMARY },
  chips: { flexDirection: 'row', gap: 6, marginTop: 6 },
  chip: { backgroundColor: '#e8eaf6' },
  card: { marginBottom: 12, borderRadius: 10 },
  row: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 5, borderBottomWidth: 1, borderBottomColor: '#f0f0f0' },
  label: { color: '#757575', flex: 1, fontSize: 13 },
  value: { color: '#212121', fontWeight: '500', flex: 1, textAlign: 'right', fontSize: 13 },
  description: { color: '#424242', lineHeight: 22, fontSize: 14 },
  actions: { gap: 8, marginTop: 8, marginBottom: 24 },
  btn: { borderRadius: 8 },
});
