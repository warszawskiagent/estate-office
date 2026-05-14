import React, { useCallback, useEffect, useState } from 'react';
import { View, FlatList, StyleSheet, RefreshControl } from 'react-native';
import { Text, Card, FAB, Searchbar, Avatar } from 'react-native-paper';
import { useNavigation } from '@react-navigation/native';

import { fetchClients } from '../../api/clients';
import { Client } from '../../types';
import { ClientsNavProp } from '../../navigation/types';
import LoadingView from '../../components/LoadingView';
import ErrorView from '../../components/ErrorView';
import EmptyView from '../../components/EmptyView';

const PRIMARY = '#1a237e';

function getInitials(client: Client): string {
  if (client.client_type === 'company') {
    return client.company_name?.substring(0, 2).toUpperCase() ?? '??';
  }
  return ((client.first_name?.[0] ?? '') + (client.last_name?.[0] ?? '')).toUpperCase();
}

function getDisplayName(client: Client): string {
  if (client.client_type === 'company') return client.company_name;
  return `${client.first_name} ${client.last_name}`.trim();
}

export default function ClientsScreen() {
  const navigation = useNavigation<ClientsNavProp>();
  const [items, setItems] = useState<Client[]>([]);
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
      const res = await fetchClients({ search: searchStr, page: pageNum, per_page: 25 });
      setTotalCount(res.total);
      setHasMore(pageNum < res.total_pages);
      setItems(prev => append ? [...prev, ...res.items] : res.items);
    } catch {
      setError('Nie udało się pobrać listy klientów.');
    } finally {
      setLoading(false);
      setRefreshing(false);
      setLoadingMore(false);
    }
  }, []);

  useEffect(() => { load('', 1, false); }, []);

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

  if (loading) return <LoadingView message="Ładowanie klientów..." />;
  if (error) return <ErrorView message={error} onRetry={() => { setLoading(true); load(search, 1, false); }} />;

  return (
    <View style={styles.root}>
      <Searchbar placeholder="Szukaj klientów..." value={search} onChangeText={onSearch} style={styles.searchbar} iconColor={PRIMARY} />
      {totalCount > 0 && <Text style={styles.total}>Znaleziono: {totalCount}</Text>}
      <FlatList
        data={items}
        keyExtractor={(item) => String(item.id)}
        renderItem={({ item }) => (
          <Card style={styles.card} onPress={() => navigation.navigate('ClientDetail', { id: item.id })}>
            <Card.Content style={styles.cardContent}>
              <Avatar.Text size={44} label={getInitials(item)} style={{ backgroundColor: PRIMARY }} />
              <View style={styles.info}>
                <Text variant="titleSmall" numberOfLines={1}>{getDisplayName(item)}</Text>
                <Text variant="bodySmall" style={styles.muted}>{item.phone}</Text>
                {item.email ? <Text variant="bodySmall" style={styles.muted}>{item.email}</Text> : null}
                {item.address_city ? <Text variant="bodySmall" style={styles.muted}>{item.address_city}</Text> : null}
              </View>
            </Card.Content>
          </Card>
        )}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} colors={[PRIMARY]} />}
        onEndReached={onLoadMore}
        onEndReachedThreshold={0.3}
        contentContainerStyle={[styles.list, items.length === 0 && styles.listEmpty]}
        ListEmptyComponent={<EmptyView message="Brak klientów" icon="people-outline" actionLabel="Dodaj klienta" onAction={() => navigation.navigate('ClientForm', {})} />}
      />
      <FAB icon="plus" style={styles.fab} color="#fff" onPress={() => navigation.navigate('ClientForm', {})} />
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
  cardContent: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  info: { flex: 1 },
  muted: { color: '#757575' },
  fab: { position: 'absolute', right: 16, bottom: 16, backgroundColor: PRIMARY },
});
