import React, { useCallback, useEffect, useState } from 'react';
import { View, FlatList, StyleSheet, RefreshControl } from 'react-native';
import { Text, Card, FAB, Searchbar, Chip } from 'react-native-paper';
import { useNavigation } from '@react-navigation/native';

import { fetchAgreements } from '../../api/agreements';
import { Agreement, TRANSACTION_TYPE_LABELS } from '../../types';
import { AgreementsNavProp } from '../../navigation/types';
import LoadingView from '../../components/LoadingView';
import ErrorView from '../../components/ErrorView';
import EmptyView from '../../components/EmptyView';

const PRIMARY = '#1a237e';

function stageColor(stage: string): string {
  if (stage.includes('zakończ')) return '#e8f5e9';
  if (stage.includes('przyrzec') || stage.includes('przekaz')) return '#e3f2fd';
  if (stage.includes('Umowa')) return '#fff3e0';
  return '#fafafa';
}

export default function AgreementsScreen() {
  const navigation = useNavigation<AgreementsNavProp>();
  const [items, setItems] = useState<Agreement[]>([]);
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
      const res = await fetchAgreements({ search: searchStr, page: pageNum, per_page: 25 });
      setTotalCount(res.total);
      setHasMore(pageNum < res.total_pages);
      setItems(prev => append ? [...prev, ...res.items] : res.items);
    } catch {
      setError('Nie udało się pobrać listy umów.');
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

  if (loading) return <LoadingView message="Ładowanie umów..." />;
  if (error) return <ErrorView message={error} onRetry={() => { setLoading(true); load(search, 1, false); }} />;

  return (
    <View style={styles.root}>
      <Searchbar placeholder="Szukaj umów..." value={search} onChangeText={onSearch} style={styles.searchbar} iconColor={PRIMARY} />
      {totalCount > 0 && <Text style={styles.total}>Znaleziono: {totalCount}</Text>}
      <FlatList
        data={items}
        keyExtractor={(item) => String(item.id)}
        renderItem={({ item }) => (
          <Card style={styles.card} onPress={() => navigation.navigate('AgreementDetail', { id: item.id })}>
            <Card.Content>
              <View style={styles.header}>
                <Text variant="titleSmall" style={styles.num}>#{item.agreement_number}</Text>
                <Text style={styles.date}>{item.date_signed}</Text>
              </View>
              <View style={styles.row}>
                <Chip compact style={styles.chip}>{TRANSACTION_TYPE_LABELS[item.transaction_type] ?? item.transaction_type}</Chip>
              </View>
              <View style={[styles.stageBar, { backgroundColor: stageColor(item.current_stage) }]}>
                <Text style={styles.stage}>{item.current_stage}</Text>
              </View>
              <Text variant="bodySmall" style={styles.muted}>{item.owner_name}</Text>
            </Card.Content>
          </Card>
        )}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} colors={[PRIMARY]} />}
        onEndReached={onLoadMore}
        onEndReachedThreshold={0.3}
        contentContainerStyle={[styles.list, items.length === 0 && styles.listEmpty]}
        ListEmptyComponent={<EmptyView message="Brak umów" icon="document-text-outline" actionLabel="Dodaj umowę" onAction={() => navigation.navigate('AgreementForm', {})} />}
      />
      <FAB icon="plus" style={styles.fab} color="#fff" onPress={() => navigation.navigate('AgreementForm', {})} />
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
  row: { flexDirection: 'row', gap: 6, marginBottom: 8 },
  chip: { backgroundColor: '#e3f2fd' },
  stageBar: { borderRadius: 6, paddingHorizontal: 10, paddingVertical: 4, alignSelf: 'flex-start', marginBottom: 6 },
  stage: { fontSize: 12, fontWeight: '500' },
  muted: { color: '#9e9e9e' },
  fab: { position: 'absolute', right: 16, bottom: 16, backgroundColor: PRIMARY },
});
