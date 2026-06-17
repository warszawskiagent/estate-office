import React, { useCallback, useEffect, useState } from 'react';
import { View, FlatList, StyleSheet, RefreshControl } from 'react-native';
import { Text, Card, FAB, Searchbar, Chip } from 'react-native-paper';
import { useNavigation } from '@react-navigation/native';

import { fetchSearches } from '../../api/searches';
import { Search, TRANSACTION_TYPE_LABELS } from '../../types';
import { SearchesNavProp } from '../../navigation/types';
import LoadingView from '../../components/LoadingView';
import ErrorView from '../../components/ErrorView';
import EmptyView from '../../components/EmptyView';

const PRIMARY = '#1a237e';

function formatBudget(from: number | null, to: number | null): string {
  if (!from && !to) return '';
  if (from && to) return `${from.toLocaleString('pl-PL')} – ${to.toLocaleString('pl-PL')} PLN`;
  if (from) return `od ${from.toLocaleString('pl-PL')} PLN`;
  return `do ${to!.toLocaleString('pl-PL')} PLN`;
}

export default function SearchesScreen() {
  const navigation = useNavigation<SearchesNavProp>();
  const [items, setItems] = useState<Search[]>([]);
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
      const res = await fetchSearches({ search: searchStr, page: pageNum, per_page: 25 });
      setTotalCount(res.total);
      setHasMore(pageNum < res.total_pages);
      setItems(prev => append ? [...prev, ...res.items] : res.items);
    } catch {
      setError('Nie udało się pobrać listy poszukiwań.');
    } finally {
      setLoading(false);
      setRefreshing(false);
      setLoadingMore(false);
    }
  }, []);

  useEffect(() => { load('', 1, false); }, []);
  const onSearch = useCallback((q: string) => { setSearch(q); setPage(1); setLoading(true); load(q, 1, false); }, [load]);
  const onRefresh = () => { setRefreshing(true); setPage(1); load(search, 1, false); };
  const onLoadMore = () => { if (loadingMore || !hasMore) return; const next = page + 1; setPage(next); setLoadingMore(true); load(search, next, true); };

  if (loading) return <LoadingView message="Ładowanie poszukiwań..." />;
  if (error) return <ErrorView message={error} onRetry={() => { setLoading(true); load(search, 1, false); }} />;

  return (
    <View style={styles.root}>
      <Searchbar placeholder="Szukaj poszukiwań..." value={search} onChangeText={onSearch} style={styles.searchbar} iconColor={PRIMARY} />
      {totalCount > 0 && <Text style={styles.total}>Znaleziono: {totalCount}</Text>}
      <FlatList
        data={items}
        keyExtractor={(item) => String(item.id)}
        renderItem={({ item }) => (
          <Card style={styles.card} onPress={() => navigation.navigate('SearchDetail', { id: item.id })}>
            <Card.Content>
              <View style={styles.header}>
                <Text variant="titleSmall" style={styles.num}>#{item.search_number}</Text>
                <Text style={styles.date}>{item.created_at.split(' ')[0]}</Text>
              </View>
              <View style={styles.row}>
                <Chip compact style={styles.chip}>{TRANSACTION_TYPE_LABELS[item.transaction_type] ?? item.transaction_type}</Chip>
                {item.property_type && <Chip compact style={styles.chip}>{item.property_type}</Chip>}
              </View>
              {item.location_text ? <Text style={styles.location}>{item.location_text}</Text> : null}
              {(item.budget_from || item.budget_to) ? (
                <Text style={styles.budget}>{formatBudget(item.budget_from, item.budget_to)}</Text>
              ) : null}
              <Text variant="bodySmall" style={styles.muted}>{item.owner_name}</Text>
            </Card.Content>
          </Card>
        )}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} colors={[PRIMARY]} />}
        onEndReached={onLoadMore}
        onEndReachedThreshold={0.3}
        contentContainerStyle={[styles.list, items.length === 0 && styles.listEmpty]}
        ListEmptyComponent={<EmptyView message="Brak poszukiwań" icon="search-outline" actionLabel="Dodaj poszukiwanie" onAction={() => navigation.navigate('SearchForm', {})} />}
      />
      <FAB icon="plus" style={styles.fab} color="#fff" onPress={() => navigation.navigate('SearchForm', {})} />
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: '#f5f5f5' },
  searchbar: { margin: 12, elevation: 1 },
  total: { paddingHorizontal: 16, paddingBottom: 4, color: '#757575', fontSize: 12 },
  list: { padding: 12, paddingTop: 0 },
  listEmpty: { flex: 1 },
  card: { marginBottom: 10, borderRadius: 10 },
  header: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: 6 },
  num: { color: PRIMARY, fontWeight: '700' },
  date: { color: '#757575', fontSize: 12 },
  row: { flexDirection: 'row', gap: 6, marginBottom: 6 },
  chip: { backgroundColor: '#e8eaf6' },
  location: { color: '#424242', fontSize: 13, marginBottom: 4 },
  budget: { color: '#2e7d32', fontWeight: '600', fontSize: 14, marginBottom: 4 },
  muted: { color: '#9e9e9e' },
  fab: { position: 'absolute', right: 16, bottom: 16, backgroundColor: PRIMARY },
});
