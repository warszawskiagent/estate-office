import React, { useEffect, useState } from 'react';
import { ScrollView, StyleSheet, View } from 'react-native';
import { TextInput, Button, SegmentedButtons, HelperText, Card } from 'react-native-paper';
import { useNavigation, useRoute } from '@react-navigation/native';

import { createSearch, fetchSearch, updateSearch } from '../../api/searches';
import { SearchFormRouteProp, SearchesNavProp } from '../../navigation/types';
import { TransactionType } from '../../types';
import { extractErrorMessage } from '../../api/client';
import LoadingView from '../../components/LoadingView';

const PRIMARY = '#1a237e';

export default function SearchFormScreen() {
  const navigation = useNavigation<SearchesNavProp>();
  const route = useRoute<SearchFormRouteProp>();
  const isEdit = !!route.params?.id;

  const [loading, setLoading] = useState(isEdit);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');

  const [transactionType, setTransactionType] = useState<TransactionType>('KUPNO');
  const [propertyType, setPropertyType] = useState('MIESZKANIE');
  const [locationText, setLocationText] = useState('');
  const [budgetFrom, setBudgetFrom] = useState('');
  const [budgetTo, setBudgetTo] = useState('');
  const [areaFrom, setAreaFrom] = useState('');
  const [areaTo, setAreaTo] = useState('');
  const [roomsFrom, setRoomsFrom] = useState('');
  const [roomsTo, setRoomsTo] = useState('');
  const [description, setDescription] = useState('');

  useEffect(() => {
    if (!isEdit) return;
    fetchSearch(route.params!.id!).then((s) => {
      setTransactionType(s.transaction_type as TransactionType);
      setPropertyType(s.property_type ?? 'MIESZKANIE');
      setLocationText(s.location_text ?? '');
      setBudgetFrom(s.budget_from != null ? String(s.budget_from) : '');
      setBudgetTo(s.budget_to != null ? String(s.budget_to) : '');
      setAreaFrom(s.area_from != null ? String(s.area_from) : '');
      setAreaTo(s.area_to != null ? String(s.area_to) : '');
      setRoomsFrom(s.rooms_from != null ? String(s.rooms_from) : '');
      setRoomsTo(s.rooms_to != null ? String(s.rooms_to) : '');
      setDescription(s.description ?? '');
    }).finally(() => setLoading(false));
  }, [isEdit]);

  const handleSave = async () => {
    setError('');

    const payload: Record<string, unknown> = {
      transaction_type: transactionType,
      property_type: propertyType || undefined,
      location_text: locationText.trim(),
      budget_from: budgetFrom ? parseFloat(budgetFrom.replace(',', '.')) : undefined,
      budget_to: budgetTo ? parseFloat(budgetTo.replace(',', '.')) : undefined,
      area_from: areaFrom ? parseFloat(areaFrom.replace(',', '.')) : undefined,
      area_to: areaTo ? parseFloat(areaTo.replace(',', '.')) : undefined,
      rooms_from: roomsFrom ? parseInt(roomsFrom, 10) : undefined,
      rooms_to: roomsTo ? parseInt(roomsTo, 10) : undefined,
      description,
    };

    setSaving(true);
    try {
      if (isEdit) {
        await updateSearch(route.params!.id!, payload);
        navigation.goBack();
      } else {
        const created = await createSearch(payload);
        navigation.replace('SearchDetail', { id: created.id });
      }
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
              { value: 'KUPNO', label: 'Kupno' },
              { value: 'NAJEM', label: 'Najem' },
              { value: 'SPRZEDAZ', label: 'Sprzedaż' },
              { value: 'WYNAJEM', label: 'Wynajem' },
            ]}
          />
        </Card.Content>
      </Card>

      <Card style={styles.card}>
        <Card.Title title="Kryteria" />
        <Card.Content>
          <TextInput label="Lokalizacja" value={locationText} onChangeText={setLocationText} mode="outlined" style={styles.input} />
          <TextInput label="Rodzaj nieruchomości" value={propertyType} onChangeText={setPropertyType} mode="outlined" style={styles.input} />
          <View style={styles.row}>
            <TextInput label="Budżet od" value={budgetFrom} onChangeText={setBudgetFrom} mode="outlined" style={[styles.input, { flex: 1 }]} keyboardType="decimal-pad" right={<TextInput.Affix text="PLN" />} />
            <View style={{ width: 8 }} />
            <TextInput label="Budżet do" value={budgetTo} onChangeText={setBudgetTo} mode="outlined" style={[styles.input, { flex: 1 }]} keyboardType="decimal-pad" right={<TextInput.Affix text="PLN" />} />
          </View>
          <View style={styles.row}>
            <TextInput label="Powierzchnia od" value={areaFrom} onChangeText={setAreaFrom} mode="outlined" style={[styles.input, { flex: 1 }]} keyboardType="decimal-pad" right={<TextInput.Affix text="m²" />} />
            <View style={{ width: 8 }} />
            <TextInput label="Powierzchnia do" value={areaTo} onChangeText={setAreaTo} mode="outlined" style={[styles.input, { flex: 1 }]} keyboardType="decimal-pad" right={<TextInput.Affix text="m²" />} />
          </View>
          <View style={styles.row}>
            <TextInput label="Pokoje od" value={roomsFrom} onChangeText={setRoomsFrom} mode="outlined" style={[styles.input, { flex: 1 }]} keyboardType="numeric" />
            <View style={{ width: 8 }} />
            <TextInput label="Pokoje do" value={roomsTo} onChangeText={setRoomsTo} mode="outlined" style={[styles.input, { flex: 1 }]} keyboardType="numeric" />
          </View>
          <TextInput label="Opis" value={description} onChangeText={setDescription} mode="outlined" multiline numberOfLines={4} style={styles.input} />
        </Card.Content>
      </Card>

      {error !== '' && <HelperText type="error" visible style={styles.err}>{error}</HelperText>}

      <View style={styles.actions}>
        <Button mode="contained" onPress={handleSave} loading={saving} disabled={saving} buttonColor={PRIMARY} icon="content-save" style={styles.btn}>
          {isEdit ? 'Zapisz zmiany' : 'Dodaj poszukiwanie'}
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
  actions: { gap: 8, marginTop: 8 },
  btn: { borderRadius: 8 },
  err: { marginBottom: 8 },
});
