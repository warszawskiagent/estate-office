import React, { useCallback, useEffect, useState } from 'react';
import { View, ScrollView, StyleSheet, Alert, Image, Dimensions } from 'react-native';
import { Text, Card, Chip, Button, Divider, List, IconButton } from 'react-native-paper';
import { useNavigation, useRoute } from '@react-navigation/native';

import { fetchProperty, deleteProperty } from '../../api/properties';
import { PropertyDetail, TRANSACTION_TYPE_LABELS, PROPERTY_TYPE_LABELS } from '../../types';
import { PropertyDetailRouteProp, PropertiesNavProp } from '../../navigation/types';
import { useAuthStore } from '../../store/useAuthStore';
import LoadingView from '../../components/LoadingView';
import ErrorView from '../../components/ErrorView';

const PRIMARY = '#1a237e';
const { width } = Dimensions.get('window');

function Row({ label, value }: { label: string; value: string | number | null | undefined }) {
  if (value == null || value === '') return null;
  return (
    <View style={styles.row}>
      <Text style={styles.rowLabel}>{label}</Text>
      <Text style={styles.rowValue}>{String(value)}</Text>
    </View>
  );
}

export default function PropertyDetailScreen() {
  const navigation = useNavigation<PropertiesNavProp>();
  const route = useRoute<PropertyDetailRouteProp>();
  const { user } = useAuthStore();
  const [property, setProperty] = useState<PropertyDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    try {
      setError(null);
      const data = await fetchProperty(route.params.id);
      setProperty(data);
    } catch {
      setError('Nie udało się pobrać danych nieruchomości.');
    } finally {
      setLoading(false);
    }
  }, [route.params.id]);

  useEffect(() => { load(); }, []);

  const handleDelete = () => {
    Alert.alert('Usuń nieruchomość', 'Czy na pewno chcesz usunąć tę nieruchomość?', [
      { text: 'Anuluj', style: 'cancel' },
      {
        text: 'Usuń',
        style: 'destructive',
        onPress: async () => {
          await deleteProperty(route.params.id);
          navigation.goBack();
        },
      },
    ]);
  };

  if (loading) return <LoadingView message="Ładowanie nieruchomości..." />;
  if (error || !property) return <ErrorView message={error ?? 'Błąd'} onRetry={load} />;

  const canDelete = user?.role === 'administrator';
  const address = [property.street, property.building_no, property.apartment_no ? `/${property.apartment_no}` : '', property.postal_code, property.city].filter(Boolean).join(' ');

  const photos = (property.media ?? []).filter(m => m.media_type === 'photo');

  return (
    <ScrollView style={styles.root} contentContainerStyle={styles.content}>
      {photos.length > 0 && (
        <ScrollView horizontal pagingEnabled style={styles.photoScroll}>
          {photos.map((m) => (
            <Image key={m.id} source={{ uri: m.media_url }} style={styles.photo} resizeMode="cover" />
          ))}
        </ScrollView>
      )}

      <View style={styles.headerRow}>
        <View style={{ flex: 1 }}>
          <Text style={styles.offerNo}>Oferta #{property.offer_number}</Text>
          <Text style={styles.address}>{address}</Text>
        </View>
        <IconButton
          icon="pencil"
          size={24}
          onPress={() => navigation.navigate('PropertyForm', { id: property.id })}
          iconColor={PRIMARY}
        />
      </View>

      <View style={styles.chips}>
        <Chip style={styles.chip}>{PROPERTY_TYPE_LABELS[property.property_type] ?? property.property_type}</Chip>
        <Chip style={styles.chip}>{TRANSACTION_TYPE_LABELS[property.transaction_type] ?? property.transaction_type}</Chip>
        {property.is_new_offer && <Chip style={[styles.chip, { backgroundColor: '#e8f5e9' }]}>Nowa oferta</Chip>}
        {property.is_exclusive && <Chip style={[styles.chip, { backgroundColor: '#fff3e0' }]}>Wyłączność</Chip>}
        {property.is_premium && <Chip style={[styles.chip, { backgroundColor: '#ede7f6' }]}>Premium</Chip>}
        {property.is_sold && <Chip style={[styles.chip, { backgroundColor: '#ffebee' }]}>Sprzedana</Chip>}
      </View>

      <Card style={styles.card}>
        <Card.Title title="Cena i powierzchnia" />
        <Card.Content>
          {property.price != null && <Row label="Cena" value={`${property.price.toLocaleString('pl-PL')} ${property.price_currency}`} />}
          {property.price_per_m2 != null && <Row label="Cena za m²" value={`${property.price_per_m2.toLocaleString('pl-PL')} ${property.price_currency}/m²`} />}
          {property.admin_rent != null && <Row label="Czynsz adm." value={`${property.admin_rent.toLocaleString('pl-PL')} PLN`} />}
          <Row label="Powierzchnia" value={property.area != null ? `${property.area} m²` : null} />
          <Row label="Pokoje" value={property.rooms} />
          <Row label="Sypialnie" value={property.bedrooms} />
          <Row label="Łazienki" value={property.bathrooms} />
          <Row label="Piętro" value={property.floor_no} />
          <Row label="Liczba pięter" value={property.floors_total} />
          <Row label="Rok budowy" value={property.year_built} />
        </Card.Content>
      </Card>

      <Card style={styles.card}>
        <Card.Title title="Adres i lokalizacja" />
        <Card.Content>
          <Row label="Ulica" value={[property.street, property.building_no].filter(Boolean).join(' ')} />
          <Row label="Lokal" value={property.apartment_no} />
          <Row label="Kod pocztowy" value={property.postal_code} />
          <Row label="Miasto" value={property.city} />
          <Row label="Dzielnica" value={property.district} />
          <Row label="Stan prawny" value={property.legal_status} />
          <Row label="Nr KW" value={property.land_registry_no} />
        </Card.Content>
      </Card>

      {property.description ? (
        <Card style={styles.card}>
          <Card.Title title="Opis" />
          <Card.Content>
            <Text style={styles.description}>{property.description.replace(/<[^>]+>/g, '')}</Text>
          </Card.Content>
        </Card>
      ) : null}

      <Card style={styles.card}>
        <Card.Title title="Opiekun" />
        <Card.Content>
          <Row label="Agent" value={property.owner_name} />
        </Card.Content>
      </Card>

      <View style={styles.actions}>
        <Button
          mode="contained"
          onPress={() => navigation.navigate('PropertyForm', { id: property.id })}
          buttonColor={PRIMARY}
          icon="pencil"
          style={styles.actionBtn}
        >
          Edytuj
        </Button>
        {canDelete && (
          <Button
            mode="outlined"
            onPress={handleDelete}
            textColor="#c62828"
            icon="delete"
            style={styles.actionBtn}
          >
            Usuń
          </Button>
        )}
        <Button
          mode="text"
          onPress={() => navigation.goBack()}
          icon="arrow-left"
          style={styles.actionBtn}
        >
          Powrót
        </Button>
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: '#f5f5f5' },
  content: { padding: 16 },
  photoScroll: { height: 220, marginHorizontal: -16, marginBottom: 16 },
  photo: { width, height: 220 },
  headerRow: { flexDirection: 'row', alignItems: 'flex-start', marginBottom: 12 },
  offerNo: { fontSize: 18, fontWeight: '700', color: PRIMARY },
  address: { color: '#616161', marginTop: 2, fontSize: 14 },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: 6, marginBottom: 16 },
  chip: { backgroundColor: '#e3f2fd' },
  card: { marginBottom: 12, borderRadius: 10 },
  row: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 5, borderBottomWidth: 1, borderBottomColor: '#f0f0f0' },
  rowLabel: { color: '#757575', flex: 1, fontSize: 13 },
  rowValue: { color: '#212121', fontWeight: '500', flex: 1, textAlign: 'right', fontSize: 13 },
  description: { color: '#424242', lineHeight: 22, fontSize: 14 },
  actions: { flexDirection: 'column', gap: 8, marginTop: 8, marginBottom: 24 },
  actionBtn: { borderRadius: 8 },
});
