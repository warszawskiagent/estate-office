import React, { useEffect, useState } from 'react';
import { ScrollView, StyleSheet, View } from 'react-native';
import { Text, TextInput, Button, SegmentedButtons, HelperText, Switch, Card } from 'react-native-paper';
import { useNavigation, useRoute } from '@react-navigation/native';

import { createProperty, fetchProperty, updateProperty } from '../../api/properties';
import { PropertyDetail, PropertyType, TransactionType } from '../../types';
import { PropertyFormRouteProp, PropertiesNavProp } from '../../navigation/types';
import { extractErrorMessage } from '../../api/client';
import LoadingView from '../../components/LoadingView';

const PRIMARY = '#1a237e';

export default function PropertyFormScreen() {
  const navigation = useNavigation<PropertiesNavProp>();
  const route = useRoute<PropertyFormRouteProp>();
  const isEdit = !!route.params?.id;

  const [loading, setLoading] = useState(isEdit);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');

  const [transactionType, setTransactionType] = useState<TransactionType>('SPRZEDAZ');
  const [propertyType, setPropertyType] = useState<PropertyType>('MIESZKANIE');
  const [offerNumber, setOfferNumber] = useState('');
  const [street, setStreet] = useState('');
  const [buildingNo, setBuildingNo] = useState('');
  const [apartmentNo, setApartmentNo] = useState('');
  const [postalCode, setPostalCode] = useState('');
  const [city, setCity] = useState('');
  const [district, setDistrict] = useState('');
  const [price, setPrice] = useState('');
  const [area, setArea] = useState('');
  const [rooms, setRooms] = useState('');
  const [floorNo, setFloorNo] = useState('');
  const [floorsTotal, setFloorsTotal] = useState('');
  const [yearBuilt, setYearBuilt] = useState('');
  const [description, setDescription] = useState('');
  const [isExclusive, setIsExclusive] = useState(false);
  const [isNewOffer, setIsNewOffer] = useState(false);
  const [exportWww, setExportWww] = useState(false);
  const [isPremium, setIsPremium] = useState(false);

  useEffect(() => {
    if (!isEdit) return;
    fetchProperty(route.params!.id!).then((p) => {
      setTransactionType(p.transaction_type as TransactionType);
      setPropertyType(p.property_type as PropertyType);
      setOfferNumber(p.offer_number);
      setStreet(p.street ?? '');
      setBuildingNo(p.building_no ?? '');
      setApartmentNo(p.apartment_no ?? '');
      setPostalCode(p.postal_code ?? '');
      setCity(p.city ?? '');
      setDistrict(p.district ?? '');
      setPrice(p.price != null ? String(p.price) : '');
      setArea(p.area != null ? String(p.area) : '');
      setRooms(p.rooms != null ? String(p.rooms) : '');
      setFloorNo(p.floor_no != null ? String(p.floor_no) : '');
      setFloorsTotal(p.floors_total != null ? String(p.floors_total) : '');
      setYearBuilt(p.year_built != null ? String(p.year_built) : '');
      setDescription(p.description ?? '');
      setIsExclusive(p.is_exclusive);
      setIsNewOffer(p.is_new_offer);
      setExportWww(p.export_www);
      setIsPremium(p.is_premium);
    }).finally(() => setLoading(false));
  }, [isEdit]);

  const handleSave = async () => {
    setError('');
    if (!city.trim()) { setError('Miasto jest wymagane.'); return; }

    const payload: Record<string, unknown> = {
      transaction_type: transactionType,
      property_type: propertyType,
      offer_number: offerNumber || undefined,
      street: street.trim(),
      building_no: buildingNo.trim(),
      apartment_no: apartmentNo.trim(),
      postal_code: postalCode.trim(),
      city: city.trim(),
      district: district.trim(),
      price: price ? parseFloat(price.replace(',', '.')) : undefined,
      area: area ? parseFloat(area.replace(',', '.')) : undefined,
      rooms: rooms ? parseInt(rooms, 10) : undefined,
      floor_no: floorNo ? parseInt(floorNo, 10) : undefined,
      floors_total: floorsTotal ? parseInt(floorsTotal, 10) : undefined,
      year_built: yearBuilt ? parseInt(yearBuilt, 10) : undefined,
      description,
      is_exclusive: isExclusive,
      is_new_offer: isNewOffer,
      export_www: exportWww,
      is_premium: isPremium,
    };

    setSaving(true);
    try {
      if (isEdit) {
        await updateProperty(route.params!.id!, payload);
      } else {
        const created = await createProperty(payload);
        navigation.replace('PropertyDetail', { id: created.id });
        return;
      }
      navigation.goBack();
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setSaving(false);
    }
  };

  if (loading) return <LoadingView message="Ładowanie danych..." />;

  return (
    <ScrollView style={styles.root} contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
      <Card style={styles.card}>
        <Card.Title title="Typ transakcji" />
        <Card.Content>
          <SegmentedButtons
            value={transactionType}
            onValueChange={(v) => setTransactionType(v as TransactionType)}
            buttons={[
              { value: 'SPRZEDAZ', label: 'Sprzedaż' },
              { value: 'KUPNO', label: 'Kupno' },
              { value: 'WYNAJEM', label: 'Wynajem' },
              { value: 'NAJEM', label: 'Najem' },
            ]}
          />
        </Card.Content>
      </Card>

      <Card style={styles.card}>
        <Card.Title title="Rodzaj nieruchomości" />
        <Card.Content>
          <SegmentedButtons
            value={propertyType}
            onValueChange={(v) => setPropertyType(v as PropertyType)}
            buttons={[
              { value: 'MIESZKANIE', label: 'Mieszkanie' },
              { value: 'DOM', label: 'Dom' },
              { value: 'DZIALKA', label: 'Działka' },
              { value: 'LOKAL', label: 'Lokal' },
            ]}
          />
        </Card.Content>
      </Card>

      <Card style={styles.card}>
        <Card.Title title="Adres" />
        <Card.Content>
          <TextInput label="Ulica" value={street} onChangeText={setStreet} mode="outlined" style={styles.input} />
          <View style={styles.row}>
            <TextInput label="Numer" value={buildingNo} onChangeText={setBuildingNo} mode="outlined" style={[styles.input, { flex: 1 }]} />
            <View style={{ width: 8 }} />
            <TextInput label="Lokal" value={apartmentNo} onChangeText={setApartmentNo} mode="outlined" style={[styles.input, { flex: 1 }]} />
          </View>
          <View style={styles.row}>
            <TextInput label="Kod pocztowy" value={postalCode} onChangeText={setPostalCode} mode="outlined" style={[styles.input, { flex: 1 }]} keyboardType="numeric" />
            <View style={{ width: 8 }} />
            <TextInput label="Miasto *" value={city} onChangeText={setCity} mode="outlined" style={[styles.input, { flex: 2 }]} />
          </View>
          <TextInput label="Dzielnica" value={district} onChangeText={setDistrict} mode="outlined" style={styles.input} />
        </Card.Content>
      </Card>

      <Card style={styles.card}>
        <Card.Title title="Dane nieruchomości" />
        <Card.Content>
          <TextInput label="Cena" value={price} onChangeText={setPrice} mode="outlined" style={styles.input} keyboardType="decimal-pad" right={<TextInput.Affix text="PLN" />} />
          <TextInput label="Powierzchnia" value={area} onChangeText={setArea} mode="outlined" style={styles.input} keyboardType="decimal-pad" right={<TextInput.Affix text="m²" />} />
          {propertyType !== 'DZIALKA' && (
            <>
              <TextInput label="Liczba pokoi" value={rooms} onChangeText={setRooms} mode="outlined" style={styles.input} keyboardType="numeric" />
              {propertyType !== 'DOM' && (
                <TextInput label="Piętro" value={floorNo} onChangeText={setFloorNo} mode="outlined" style={styles.input} keyboardType="numeric" />
              )}
              <TextInput label="Liczba pięter" value={floorsTotal} onChangeText={setFloorsTotal} mode="outlined" style={styles.input} keyboardType="numeric" />
              <TextInput label="Rok budowy" value={yearBuilt} onChangeText={setYearBuilt} mode="outlined" style={styles.input} keyboardType="numeric" />
            </>
          )}
        </Card.Content>
      </Card>

      <Card style={styles.card}>
        <Card.Title title="Opis" />
        <Card.Content>
          <TextInput
            label="Opis nieruchomości"
            value={description}
            onChangeText={setDescription}
            mode="outlined"
            multiline
            numberOfLines={5}
            style={styles.input}
          />
        </Card.Content>
      </Card>

      <Card style={styles.card}>
        <Card.Title title="Znaczniki" />
        <Card.Content>
          <View style={styles.switchRow}><Text>Nowa oferta</Text><Switch value={isNewOffer} onValueChange={setIsNewOffer} color={PRIMARY} /></View>
          <View style={styles.switchRow}><Text>Wyłączność</Text><Switch value={isExclusive} onValueChange={setIsExclusive} color={PRIMARY} /></View>
          <View style={styles.switchRow}><Text>Eksport na WWW</Text><Switch value={exportWww} onValueChange={setExportWww} color={PRIMARY} /></View>
          <View style={styles.switchRow}><Text>Premium</Text><Switch value={isPremium} onValueChange={setIsPremium} color={PRIMARY} /></View>
        </Card.Content>
      </Card>

      {error !== '' && <HelperText type="error" visible style={styles.error}>{error}</HelperText>}

      <View style={styles.actions}>
        <Button
          mode="contained"
          onPress={handleSave}
          loading={saving}
          disabled={saving}
          buttonColor={PRIMARY}
          icon="content-save"
          style={styles.btn}
        >
          {isEdit ? 'Zapisz zmiany' : 'Dodaj nieruchomość'}
        </Button>
        <Button mode="outlined" onPress={() => navigation.goBack()} style={styles.btn}>Anuluj</Button>
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: '#f5f5f5' },
  content: { padding: 16, paddingBottom: 40 },
  card: { marginBottom: 12, borderRadius: 10 },
  input: { marginBottom: 8 },
  row: { flexDirection: 'row' },
  switchRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', paddingVertical: 6 },
  actions: { gap: 8, marginTop: 8 },
  btn: { borderRadius: 8 },
  error: { marginBottom: 8 },
});
