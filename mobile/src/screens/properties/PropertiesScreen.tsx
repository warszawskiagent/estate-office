import React, { useCallback, useEffect, useState } from 'react';
import { View, FlatList, StyleSheet, RefreshControl } from 'react-native';
import { Text, Card, Chip, FAB, Searchbar, Badge } from 'react-native-paper';
import { useNavigation } from '@react-navigation/native';
import { Ionicons } from '@expo/vector-icons';

import { fetchProperties } from '../../api/properties';
import { Property, TRANSACTION_TYPE_LABELS, PROPERTY_TYPE_LABELS } from '../../types';
import { PropertiesNavProp } from '../../navigation/types';
import LoadingView from '../../components/LoadingView';
import ErrorView from '../../components/ErrorView';
import EmptyView from '../../components/EmptyView';

const PRIMARY = '#1a237e';

function PropertyCard({ item, onPress }: { item: Property; onPress: () => void }) {
  const address = [item.street, item.building_no, item.city].filter(Boolean).join(', ');
  return (
    <Card style={styles.card} onPress={onPress}>
      {item.primary_photo ? (
        <Card.Cover source={{ uri: item.primary_photo }} style={styles.cover} />
      ) : null}
      <Card.Content style={styles.cardContent}>
        <View style={styles.cardHeader}>
          <Text variant="titleSmall" style={styles.offerNo}>#{item.offer_number}</Text>
          <View style={styles.badges}>
            {item.is_new_offer && <Badge style={[styles.badge, { backgroundColor: '#43a047' }]}>Nowa</Badge>}
            {item.is_sold && <Badge style={[styles.badge, { backgroundColor: '#e53935' }]}>Sprzedana</Badge>}
            {item.is_rented && <Badge style={[styles.badge, { backgroundColor: '#fb8c00' }]}>Wynajęta</Badge>}
            {item.is_premium && <Badge style={[styles.badge, { backgroundColor: PRIMARY }]}>Premium</Badge>}
          </View>
        </View>
        <Text variant="bodyMedium" numberOfLines={1} style={styles.address}>{address || '—'}</Text>
        <View style={styles.row}>
          <Chip compact style={styles.typeChip}>{PROPERTY_TYPE_LABELS[item.property_type] ?? item.property_type}</Chip>
          <Chip compact style={styles.txChip}>{TRANSACTION_TYPE_LABELS[item.transaction_type] ?? item.transaction_type}</Chip>
        </View>
        <View style={styles.detailsRow}>
          {item.price != null && (
            <Text style={styles.price}>{item.price.toLocaleString('pl-PL')} {item.price_currency}</Text>
          )}
          {item.area != null && <Text style={styles.detail}>{item.area} m²</Text>}
          {item.rooms != null && <Text style={styles.detail}>{item.rooms} pok.</Text>}
        </View>
        <Text variant="bodySmall" style={styles.owner}>{item.owner_name}</Text>
      </Card.Content>
    </Card>
  );
}

export default function PropertiesScreen() {
  const navigation = useNavigation<PropertiesNavProp>();
  const [items, setItems] = useState<Property[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [loadingMore, setLoadingMore] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [hasMore, setHasMore] = useState(true);
  const [totalCount, setTotalCount] = useState(0);

  const load = useCallback(async (searchStr: string, pageNum: number, append: boolean) => {
    try {
      setError(null);
      const res = await fetchProperties({ search: searchStr, page: pageNum, per_page: 20 });
      setTotalCount(res.total);
      setHasMore(pageNum < res.total_pages);
      setItems(prev => append ? [...prev, ...res.items] : res.items);
    } catch {
      setError('Nie udało się pobrać listy nieruchomości.');
    } finally {
      setLoading(false);
      setRefreshing(false);
      setLoadingMore(false);
    }
  }, []);

  useEffect(() => { load(search, 1, false); }, []);

  const onSearch = useCallback((q: string) => {
    setSearch(q);
    setPage(1);
    setLoading(true);
    load(q, 1, false);
  }, [load]);

  const onRefresh = () => { setRefreshing(true); setPage(1); load(search, 1, false); };

  const onLoadMore = () => {
    if (loadingMore || !hasMore) return;
    const next = page + 1;
    setPage(next);
    setLoadingMore(true);
    load(search, next, true);
  };

  if (loading) return <LoadingView message="Ładowanie nieruchomości..." />;
  if (error) return <ErrorView message={error} onRetry={() => { setLoading(true); load(search, 1, false); }} />;

  return (
    <View style={styles.root}>
      <Searchbar
        placeholder="Szukaj nieruchomości..."
        value={search}
        onChangeText={onSearch}
        style={styles.searchbar}
        iconColor={PRIMARY}
      />
      {totalCount > 0 && (
        <Text style={styles.totalLabel}>Znaleziono: {totalCount}</Text>
      )}
      <FlatList
        data={items}
        keyExtractor={(item) => String(item.id)}
        renderItem={({ item }) => (
          <PropertyCard
            item={item}
            onPress={() => navigation.navigate('PropertyDetail', { id: item.id })}
          />
        )}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} colors={[PRIMARY]} />}
        onEndReached={onLoadMore}
        onEndReachedThreshold={0.3}
        contentContainerStyle={[styles.list, items.length === 0 && styles.listEmpty]}
        ListEmptyComponent={
          <EmptyView
            message="Brak nieruchomości"
            icon="home-outline"
            actionLabel="Dodaj nieruchomość"
            onAction={() => navigation.navigate('PropertyForm', {})}
          />
        }
      />
      <FAB
        icon="plus"
        style={styles.fab}
        color="#fff"
        onPress={() => navigation.navigate('PropertyForm', {})}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: '#f5f5f5' },
  searchbar: { margin: 12, elevation: 1 },
  totalLabel: { paddingHorizontal: 16, paddingBottom: 4, color: '#757575', fontSize: 12 },
  list: { padding: 12, paddingTop: 0 },
  listEmpty: { flex: 1 },
  card: { marginBottom: 12, borderRadius: 10, overflow: 'hidden' },
  cover: { height: 160 },
  cardContent: { paddingTop: 10 },
  cardHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 4 },
  offerNo: { color: PRIMARY, fontWeight: '600' },
  badges: { flexDirection: 'row', gap: 4 },
  badge: { fontSize: 10 },
  address: { color: '#424242', marginBottom: 6 },
  row: { flexDirection: 'row', gap: 6, marginBottom: 8 },
  typeChip: { backgroundColor: '#e3f2fd' },
  txChip: { backgroundColor: '#e8f5e9' },
  detailsRow: { flexDirection: 'row', gap: 12, alignItems: 'center', marginBottom: 4 },
  price: { fontSize: 16, fontWeight: '700', color: PRIMARY },
  detail: { fontSize: 13, color: '#616161' },
  owner: { color: '#9e9e9e', marginTop: 2 },
  fab: { position: 'absolute', right: 16, bottom: 16, backgroundColor: PRIMARY },
});
