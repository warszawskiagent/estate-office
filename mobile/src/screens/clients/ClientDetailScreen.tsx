import React, { useCallback, useEffect, useState } from 'react';
import { View, ScrollView, StyleSheet, Alert } from 'react-native';
import { Text, Card, Button, Chip, IconButton } from 'react-native-paper';
import { useNavigation, useRoute } from '@react-navigation/native';

import { fetchClient, deleteClient } from '../../api/clients';
import { ClientDetail, TRANSACTION_TYPE_LABELS } from '../../types';
import { ClientDetailRouteProp, ClientsNavProp } from '../../navigation/types';
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

export default function ClientDetailScreen() {
  const navigation = useNavigation<ClientsNavProp>();
  const route = useRoute<ClientDetailRouteProp>();
  const { user } = useAuthStore();
  const [client, setClient] = useState<ClientDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    try {
      setError(null);
      const data = await fetchClient(route.params.id);
      setClient(data);
    } catch {
      setError('Nie udało się pobrać danych klienta.');
    } finally {
      setLoading(false);
    }
  }, [route.params.id]);

  useEffect(() => { load(); }, []);

  const handleDelete = () => {
    Alert.alert('Usuń klienta', 'Czy na pewno chcesz usunąć tego klienta?', [
      { text: 'Anuluj', style: 'cancel' },
      { text: 'Usuń', style: 'destructive', onPress: async () => { await deleteClient(route.params.id); navigation.goBack(); } },
    ]);
  };

  if (loading) return <LoadingView message="Ładowanie klienta..." />;
  if (error || !client) return <ErrorView message={error ?? 'Błąd'} onRetry={load} />;

  const displayName = client.client_type === 'company' ? client.company_name : `${client.first_name} ${client.last_name}`.trim();
  const mainAddress = client.addresses.find(a => a.address_type === 'main');
  const corrAddress = client.addresses.find(a => a.address_type === 'correspondence');
  const canDelete = user?.role === 'administrator';

  return (
    <ScrollView style={styles.root} contentContainerStyle={styles.content}>
      <View style={styles.headerRow}>
        <View style={{ flex: 1 }}>
          <Text style={styles.name}>{displayName}</Text>
          <Chip compact style={styles.typeChip}>{client.client_type === 'company' ? 'Firma' : 'Osoba fizyczna'}</Chip>
        </View>
        <IconButton icon="pencil" size={24} onPress={() => navigation.navigate('ClientForm', { id: client.id })} iconColor={PRIMARY} />
      </View>

      <Card style={styles.card}>
        <Card.Title title="Dane kontaktowe" />
        <Card.Content>
          <Row label="Telefon" value={client.phone} />
          <Row label="E-mail" value={client.email} />
          {client.client_type === 'company' && <Row label="WWW" value={client.website} />}
          <Row label="Opiekun" value={client.owner_name} />
        </Card.Content>
      </Card>

      <Card style={styles.card}>
        <Card.Title title={client.client_type === 'company' ? 'Dane firmy' : 'Dane identyfikacyjne'} />
        <Card.Content>
          {client.client_type === 'company' ? (
            <>
              <Row label="Reprezentant" value={client.representative_name} />
              <Row label="NIP" value={client.nip} />
              <Row label="KRS" value={client.krs} />
              <Row label="REGON" value={client.regon} />
            </>
          ) : (
            <>
              <Row label="PESEL" value={client.pesel} />
              <Row label="Rodzaj dok." value={client.document_type} />
              <Row label="Nr dokumentu" value={client.document_number} />
            </>
          )}
        </Card.Content>
      </Card>

      {mainAddress && (
        <Card style={styles.card}>
          <Card.Title title="Adres zamieszkania/rejestrowy" />
          <Card.Content>
            <Row label="Ulica" value={[mainAddress.street, mainAddress.building_no, mainAddress.apartment_no ? `/${mainAddress.apartment_no}` : ''].filter(Boolean).join(' ')} />
            <Row label="Kod / Miasto" value={`${mainAddress.postal_code} ${mainAddress.city}`} />
            <Row label="Kraj" value={mainAddress.country} />
          </Card.Content>
        </Card>
      )}

      {corrAddress && corrAddress.city && corrAddress.city !== mainAddress?.city && (
        <Card style={styles.card}>
          <Card.Title title="Adres korespondencyjny" />
          <Card.Content>
            <Row label="Ulica" value={[corrAddress.street, corrAddress.building_no].filter(Boolean).join(' ')} />
            <Row label="Kod / Miasto" value={`${corrAddress.postal_code} ${corrAddress.city}`} />
          </Card.Content>
        </Card>
      )}

      {client.agreements.length > 0 && (
        <Card style={styles.card}>
          <Card.Title title={`Umowy (${client.agreements.length})`} />
          <Card.Content>
            {client.agreements.map(a => (
              <View key={a.id} style={styles.linkedRow}>
                <View style={{ flex: 1 }}>
                  <Text style={styles.linkedTitle}>#{a.agreement_number}</Text>
                  <Text style={styles.linkedSub}>{TRANSACTION_TYPE_LABELS[a.transaction_type]} · {a.current_stage}</Text>
                </View>
              </View>
            ))}
          </Card.Content>
        </Card>
      )}

      <View style={styles.actions}>
        <Button mode="contained" onPress={() => navigation.navigate('ClientForm', { id: client.id })} buttonColor={PRIMARY} icon="pencil" style={styles.btn}>Edytuj</Button>
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
  name: { fontSize: 20, fontWeight: '700', color: PRIMARY },
  typeChip: { backgroundColor: '#e3f2fd', alignSelf: 'flex-start', marginTop: 6 },
  card: { marginBottom: 12, borderRadius: 10 },
  row: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 5, borderBottomWidth: 1, borderBottomColor: '#f0f0f0' },
  label: { color: '#757575', flex: 1, fontSize: 13 },
  value: { color: '#212121', fontWeight: '500', flex: 1, textAlign: 'right', fontSize: 13 },
  linkedRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', paddingVertical: 6, borderBottomWidth: 1, borderBottomColor: '#f0f0f0' },
  linkedTitle: { fontWeight: '600', color: PRIMARY },
  linkedSub: { color: '#757575', fontSize: 12 },
  actions: { gap: 8, marginTop: 8, marginBottom: 24 },
  btn: { borderRadius: 8 },
});
