import React, { useEffect, useState } from 'react';
import { ScrollView, StyleSheet, View } from 'react-native';
import { Text, TextInput, Button, SegmentedButtons, HelperText, Card, Checkbox } from 'react-native-paper';
import { useNavigation, useRoute } from '@react-navigation/native';

import { createClient, fetchClient, updateClient } from '../../api/clients';
import { ClientFormRouteProp, ClientsNavProp } from '../../navigation/types';
import { extractErrorMessage } from '../../api/client';
import LoadingView from '../../components/LoadingView';

const PRIMARY = '#1a237e';

export default function ClientFormScreen() {
  const navigation = useNavigation<ClientsNavProp>();
  const route = useRoute<ClientFormRouteProp>();
  const isEdit = !!route.params?.id;

  const [loading, setLoading] = useState(isEdit);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');

  const [clientType, setClientType] = useState<'person' | 'company'>('person');
  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [companyName, setCompanyName] = useState('');
  const [representativeName, setRepresentativeName] = useState('');
  const [phone, setPhone] = useState('');
  const [email, setEmail] = useState('');
  const [website, setWebsite] = useState('');
  const [pesel, setPesel] = useState('');
  const [documentType, setDocumentType] = useState('');
  const [documentNumber, setDocumentNumber] = useState('');
  const [nip, setNip] = useState('');
  const [krs, setKrs] = useState('');
  const [regon, setRegon] = useState('');
  const [addrStreet, setAddrStreet] = useState('');
  const [addrBuildingNo, setAddrBuildingNo] = useState('');
  const [addrApartmentNo, setAddrApartmentNo] = useState('');
  const [addrPostalCode, setAddrPostalCode] = useState('');
  const [addrCity, setAddrCity] = useState('');
  const [addrCountry, setAddrCountry] = useState('Polska');
  const [corrSame, setCorrSame] = useState(true);

  useEffect(() => {
    if (!isEdit) return;
    fetchClient(route.params!.id!).then((c) => {
      setClientType(c.client_type);
      setFirstName(c.first_name ?? '');
      setLastName(c.last_name ?? '');
      setCompanyName(c.company_name ?? '');
      setRepresentativeName(c.representative_name ?? '');
      setPhone(c.phone ?? '');
      setEmail(c.email ?? '');
      setWebsite(c.website ?? '');
      setPesel(c.pesel ?? '');
      setDocumentType(c.document_type ?? '');
      setDocumentNumber(c.document_number ?? '');
      setNip(c.nip ?? '');
      setKrs(c.krs ?? '');
      setRegon(c.regon ?? '');
      const main = c.addresses.find(a => a.address_type === 'main');
      if (main) {
        setAddrStreet(main.street ?? '');
        setAddrBuildingNo(main.building_no ?? '');
        setAddrApartmentNo(main.apartment_no ?? '');
        setAddrPostalCode(main.postal_code ?? '');
        setAddrCity(main.city ?? '');
        setAddrCountry(main.country ?? 'Polska');
      }
    }).finally(() => setLoading(false));
  }, [isEdit]);

  const handleSave = async () => {
    setError('');
    if (!phone.trim()) { setError('Numer telefonu jest wymagany.'); return; }
    if (clientType === 'person' && (!firstName.trim() || !lastName.trim())) { setError('Imię i nazwisko są wymagane.'); return; }
    if (clientType === 'company' && !companyName.trim()) { setError('Nazwa firmy jest wymagana.'); return; }

    const payload: Record<string, unknown> = {
      client_type: clientType,
      first_name: firstName.trim(),
      last_name: lastName.trim(),
      company_name: companyName.trim(),
      representative_name: representativeName.trim(),
      phone: phone.trim(),
      email: email.trim(),
      website: website.trim(),
      pesel: pesel.trim(),
      document_type: documentType,
      document_number: documentNumber.trim(),
      nip: nip.trim(),
      krs: krs.trim(),
      regon: regon.trim(),
      address_street: addrStreet.trim(),
      address_building_no: addrBuildingNo.trim(),
      address_apartment_no: addrApartmentNo.trim(),
      address_postal_code: addrPostalCode.trim(),
      address_city: addrCity.trim(),
      address_country: addrCountry.trim() || 'Polska',
      correspondence_same: corrSame,
    };

    setSaving(true);
    try {
      if (isEdit) {
        await updateClient(route.params!.id!, payload);
        navigation.goBack();
      } else {
        const created = await createClient(payload);
        navigation.replace('ClientDetail', { id: created.id });
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
        <Card.Title title="Typ klienta" />
        <Card.Content>
          <SegmentedButtons
            value={clientType}
            onValueChange={(v) => setClientType(v as 'person' | 'company')}
            buttons={[{ value: 'person', label: 'Osoba fizyczna' }, { value: 'company', label: 'Firma' }]}
          />
        </Card.Content>
      </Card>

      <Card style={styles.card}>
        <Card.Title title="Dane podstawowe" />
        <Card.Content>
          {clientType === 'person' ? (
            <>
              <TextInput label="Imię *" value={firstName} onChangeText={setFirstName} mode="outlined" style={styles.input} />
              <TextInput label="Nazwisko *" value={lastName} onChangeText={setLastName} mode="outlined" style={styles.input} />
            </>
          ) : (
            <>
              <TextInput label="Nazwa firmy *" value={companyName} onChangeText={setCompanyName} mode="outlined" style={styles.input} />
              <TextInput label="Imię i nazwisko reprezentanta" value={representativeName} onChangeText={setRepresentativeName} mode="outlined" style={styles.input} />
            </>
          )}
          <TextInput label="Telefon *" value={phone} onChangeText={setPhone} mode="outlined" style={styles.input} keyboardType="phone-pad" />
          <TextInput label="E-mail" value={email} onChangeText={setEmail} mode="outlined" style={styles.input} keyboardType="email-address" autoCapitalize="none" />
          {clientType === 'company' && (
            <TextInput label="Strona WWW" value={website} onChangeText={setWebsite} mode="outlined" style={styles.input} keyboardType="url" autoCapitalize="none" />
          )}
        </Card.Content>
      </Card>

      <Card style={styles.card}>
        <Card.Title title={clientType === 'company' ? 'Dane rejestrowe' : 'Dane identyfikacyjne'} />
        <Card.Content>
          {clientType === 'person' ? (
            <>
              <TextInput label="PESEL" value={pesel} onChangeText={setPesel} mode="outlined" style={styles.input} keyboardType="numeric" />
              <TextInput label="Nr dokumentu" value={documentNumber} onChangeText={setDocumentNumber} mode="outlined" style={styles.input} />
            </>
          ) : (
            <>
              <TextInput label="NIP" value={nip} onChangeText={setNip} mode="outlined" style={styles.input} keyboardType="numeric" />
              <TextInput label="KRS" value={krs} onChangeText={setKrs} mode="outlined" style={styles.input} />
              <TextInput label="REGON" value={regon} onChangeText={setRegon} mode="outlined" style={styles.input} />
            </>
          )}
        </Card.Content>
      </Card>

      <Card style={styles.card}>
        <Card.Title title="Adres zamieszkania/rejestrowy" />
        <Card.Content>
          <TextInput label="Ulica" value={addrStreet} onChangeText={setAddrStreet} mode="outlined" style={styles.input} />
          <View style={styles.row}>
            <TextInput label="Numer" value={addrBuildingNo} onChangeText={setAddrBuildingNo} mode="outlined" style={[styles.input, { flex: 1 }]} />
            <View style={{ width: 8 }} />
            <TextInput label="Lokal" value={addrApartmentNo} onChangeText={setAddrApartmentNo} mode="outlined" style={[styles.input, { flex: 1 }]} />
          </View>
          <View style={styles.row}>
            <TextInput label="Kod pocztowy" value={addrPostalCode} onChangeText={setAddrPostalCode} mode="outlined" style={[styles.input, { flex: 1 }]} keyboardType="numeric" />
            <View style={{ width: 8 }} />
            <TextInput label="Miasto" value={addrCity} onChangeText={setAddrCity} mode="outlined" style={[styles.input, { flex: 2 }]} />
          </View>
          <TextInput label="Kraj" value={addrCountry} onChangeText={setAddrCountry} mode="outlined" style={styles.input} />
          <View style={styles.checkRow}>
            <Checkbox.Android status={corrSame ? 'checked' : 'unchecked'} onPress={() => setCorrSame(!corrSame)} color={PRIMARY} />
            <Text onPress={() => setCorrSame(!corrSame)}>Adres korespondencyjny taki sam</Text>
          </View>
        </Card.Content>
      </Card>

      {error !== '' && <HelperText type="error" visible style={styles.err}>{error}</HelperText>}

      <View style={styles.actions}>
        <Button mode="contained" onPress={handleSave} loading={saving} disabled={saving} buttonColor={PRIMARY} icon="content-save" style={styles.btn}>
          {isEdit ? 'Zapisz zmiany' : 'Dodaj klienta'}
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
  checkRow: { flexDirection: 'row', alignItems: 'center', marginTop: 4 },
  actions: { gap: 8, marginTop: 8 },
  btn: { borderRadius: 8 },
  err: { marginBottom: 8 },
});
