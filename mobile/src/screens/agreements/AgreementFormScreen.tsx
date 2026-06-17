import React, { useEffect, useState } from 'react';
import { ScrollView, StyleSheet, View } from 'react-native';
import { Text, TextInput, Button, SegmentedButtons, HelperText, Card, Switch } from 'react-native-paper';
import { useNavigation, useRoute } from '@react-navigation/native';

import { createAgreement, fetchAgreement, updateAgreement } from '../../api/agreements';
import { AgreementFormRouteProp, AgreementsNavProp } from '../../navigation/types';
import { TransactionType } from '../../types';
import { extractErrorMessage } from '../../api/client';
import LoadingView from '../../components/LoadingView';

const PRIMARY = '#1a237e';

export default function AgreementFormScreen() {
  const navigation = useNavigation<AgreementsNavProp>();
  const route = useRoute<AgreementFormRouteProp>();
  const isEdit = !!route.params?.id;

  const [loading, setLoading] = useState(isEdit);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');

  const [transactionType, setTransactionType] = useState<TransactionType>('SPRZEDAZ');
  const [agreementNumber, setAgreementNumber] = useState('');
  const [dateSigned, setDateSigned] = useState(new Date().toISOString().split('T')[0]);
  const [dateEnd, setDateEnd] = useState('');
  const [isIndefinite, setIsIndefinite] = useState(false);
  const [isExclusive, setIsExclusive] = useState(false);
  const [commissionAmount, setCommissionAmount] = useState('');
  const [commissionUnit, setCommissionUnit] = useState('%');

  useEffect(() => {
    if (!isEdit) return;
    fetchAgreement(route.params!.id!).then((a) => {
      setTransactionType(a.transaction_type as TransactionType);
      setAgreementNumber(a.agreement_number);
      setDateSigned(a.date_signed);
      setDateEnd(a.date_end ?? '');
      setIsIndefinite(a.is_indefinite);
      setIsExclusive(a.is_exclusive);
      setCommissionAmount(a.commission_amount != null ? String(a.commission_amount) : '');
      setCommissionUnit(a.commission_unit ?? '%');
    }).finally(() => setLoading(false));
  }, [isEdit]);

  const handleSave = async () => {
    setError('');
    if (!dateSigned.match(/^\d{4}-\d{2}-\d{2}$/)) { setError('Podaj datę zawarcia w formacie RRRR-MM-DD.'); return; }

    const payload: Record<string, unknown> = {
      transaction_type: transactionType,
      agreement_number: agreementNumber.trim() || undefined,
      date_signed: dateSigned,
      date_end: isIndefinite ? undefined : (dateEnd || undefined),
      is_indefinite: isIndefinite,
      is_exclusive: isExclusive,
      commission_amount: commissionAmount ? parseFloat(commissionAmount.replace(',', '.')) : undefined,
      commission_unit: commissionUnit,
    };

    setSaving(true);
    try {
      if (isEdit) {
        await updateAgreement(route.params!.id!, payload);
        navigation.goBack();
      } else {
        const created = await createAgreement(payload);
        navigation.replace('AgreementDetail', { id: created.id });
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
              { value: 'SPRZEDAZ', label: 'Sprzedaż' },
              { value: 'KUPNO', label: 'Kupno' },
              { value: 'WYNAJEM', label: 'Wynajem' },
              { value: 'NAJEM', label: 'Najem' },
            ]}
          />
        </Card.Content>
      </Card>

      <Card style={styles.card}>
        <Card.Title title="Dane umowy" />
        <Card.Content>
          <TextInput label="Numer umowy (pozostaw puste = auto)" value={agreementNumber} onChangeText={setAgreementNumber} mode="outlined" style={styles.input} />
          <TextInput label="Data zawarcia *" value={dateSigned} onChangeText={setDateSigned} mode="outlined" style={styles.input} placeholder="RRRR-MM-DD" keyboardType="numeric" />
          <View style={styles.switchRow}>
            <Text>Umowa bezterminowa</Text>
            <Switch value={isIndefinite} onValueChange={setIsIndefinite} color={PRIMARY} />
          </View>
          {!isIndefinite && (
            <TextInput label="Data zakończenia" value={dateEnd} onChangeText={setDateEnd} mode="outlined" style={styles.input} placeholder="RRRR-MM-DD" keyboardType="numeric" />
          )}
          <View style={styles.switchRow}>
            <Text>Wyłączność</Text>
            <Switch value={isExclusive} onValueChange={setIsExclusive} color={PRIMARY} />
          </View>
        </Card.Content>
      </Card>

      <Card style={styles.card}>
        <Card.Title title="Prowizja" />
        <Card.Content>
          <TextInput label="Kwota prowizji" value={commissionAmount} onChangeText={setCommissionAmount} mode="outlined" style={styles.input} keyboardType="decimal-pad" />
          <SegmentedButtons
            value={commissionUnit}
            onValueChange={setCommissionUnit}
            buttons={[{ value: '%', label: '%' }, { value: 'PLN', label: 'PLN' }, { value: 'EUR', label: 'EUR' }, { value: 'USD', label: 'USD' }]}
          />
        </Card.Content>
      </Card>

      {error !== '' && <HelperText type="error" visible style={styles.err}>{error}</HelperText>}

      <View style={styles.actions}>
        <Button mode="contained" onPress={handleSave} loading={saving} disabled={saving} buttonColor={PRIMARY} icon="content-save" style={styles.btn}>
          {isEdit ? 'Zapisz zmiany' : 'Dodaj umowę'}
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
  switchRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', paddingVertical: 8 },
  actions: { gap: 8, marginTop: 8 },
  btn: { borderRadius: 8 },
  err: { marginBottom: 8 },
});
