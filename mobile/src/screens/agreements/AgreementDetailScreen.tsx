import React, { useCallback, useEffect, useState } from 'react';
import { View, ScrollView, StyleSheet, Alert } from 'react-native';
import { Text, Card, Button, Chip, IconButton, TextInput, Menu } from 'react-native-paper';
import { useNavigation, useRoute } from '@react-navigation/native';

import { fetchAgreement, deleteAgreement, addAgreementStage } from '../../api/agreements';
import { AgreementDetail, AGREEMENT_STAGES, TRANSACTION_TYPE_LABELS } from '../../types';
import { AgreementDetailRouteProp, AgreementsNavProp } from '../../navigation/types';
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

export default function AgreementDetailScreen() {
  const navigation = useNavigation<AgreementsNavProp>();
  const route = useRoute<AgreementDetailRouteProp>();
  const { user } = useAuthStore();
  const [agreement, setAgreement] = useState<AgreementDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [stageMenuVisible, setStageMenuVisible] = useState(false);
  const [newStage, setNewStage] = useState('');
  const [stageDate, setStageDate] = useState(new Date().toISOString().split('T')[0]);
  const [addingStage, setAddingStage] = useState(false);

  const load = useCallback(async () => {
    try {
      setError(null);
      const data = await fetchAgreement(route.params.id);
      setAgreement(data);
    } catch {
      setError('Nie udało się pobrać danych umowy.');
    } finally {
      setLoading(false);
    }
  }, [route.params.id]);

  useEffect(() => { load(); }, []);

  const handleDelete = () => {
    Alert.alert('Usuń umowę', 'Czy na pewno chcesz usunąć tę umowę?', [
      { text: 'Anuluj', style: 'cancel' },
      { text: 'Usuń', style: 'destructive', onPress: async () => { await deleteAgreement(route.params.id); navigation.goBack(); } },
    ]);
  };

  const handleAddStage = async () => {
    if (!newStage || !stageDate) return;
    setAddingStage(true);
    try {
      await addAgreementStage(route.params.id, newStage, stageDate);
      await load();
      setNewStage('');
    } catch {
      Alert.alert('Błąd', 'Nie udało się dodać etapu.');
    } finally {
      setAddingStage(false);
    }
  };

  if (loading) return <LoadingView message="Ładowanie umowy..." />;
  if (error || !agreement) return <ErrorView message={error ?? 'Błąd'} onRetry={load} />;

  const canDelete = user?.role === 'administrator';

  return (
    <ScrollView style={styles.root} contentContainerStyle={styles.content}>
      <View style={styles.headerRow}>
        <View style={{ flex: 1 }}>
          <Text style={styles.num}>Umowa #{agreement.agreement_number}</Text>
          <Chip compact style={styles.txChip}>{TRANSACTION_TYPE_LABELS[agreement.transaction_type] ?? agreement.transaction_type}</Chip>
        </View>
        <IconButton icon="pencil" size={24} onPress={() => navigation.navigate('AgreementForm', { id: agreement.id })} iconColor={PRIMARY} />
      </View>

      <Card style={styles.card}>
        <Card.Title title="Dane umowy" />
        <Card.Content>
          <Row label="Data zawarcia" value={agreement.date_signed} />
          <Row label="Data zakończenia" value={agreement.is_indefinite ? 'Bezterminowa' : agreement.date_end} />
          <Row label="Prowizja" value={agreement.commission_amount != null ? `${agreement.commission_amount} ${agreement.commission_unit}` : undefined} />
          {agreement.is_exclusive && <Row label="Wyłączność" value="Tak" />}
          <Row label="Opiekun" value={agreement.owner_name} />
        </Card.Content>
      </Card>

      <Card style={styles.card}>
        <Card.Title title={`Aktualny etap: ${agreement.current_stage}`} />
        <Card.Content>
          <TextInput
            label="Data etapu"
            value={stageDate}
            onChangeText={setStageDate}
            mode="outlined"
            style={styles.input}
            placeholder="RRRR-MM-DD"
          />
          <Menu
            visible={stageMenuVisible}
            onDismiss={() => setStageMenuVisible(false)}
            anchor={
              <Button mode="outlined" onPress={() => setStageMenuVisible(true)} icon="chevron-down" style={styles.stageBtn}>
                {newStage || 'Wybierz nowy etap'}
              </Button>
            }
          >
            {AGREEMENT_STAGES.map(s => (
              <Menu.Item key={s} title={s} onPress={() => { setNewStage(s); setStageMenuVisible(false); }} />
            ))}
          </Menu>
          <Button
            mode="contained"
            onPress={handleAddStage}
            loading={addingStage}
            disabled={addingStage || !newStage || !stageDate}
            buttonColor={PRIMARY}
            style={[styles.stageBtn, { marginTop: 8 }]}
          >
            Aktualizuj etap
          </Button>
        </Card.Content>
      </Card>

      {agreement.stages.length > 0 && (
        <Card style={styles.card}>
          <Card.Title title="Historia etapów" />
          <Card.Content>
            {[...agreement.stages].reverse().map(s => (
              <View key={s.id} style={styles.row}>
                <Text style={styles.label}>{s.stage_date}</Text>
                <Text style={styles.value}>{s.stage_name}</Text>
              </View>
            ))}
          </Card.Content>
        </Card>
      )}

      {agreement.clients.length > 0 && (
        <Card style={styles.card}>
          <Card.Title title={`Klienci (${agreement.clients.length})`} />
          <Card.Content>
            {agreement.clients.map(c => (
              <View key={c.id} style={styles.row}>
                <Text style={styles.label}>{c.client_type === 'company' ? c.company_name : `${c.first_name} ${c.last_name}`}</Text>
                <Text style={styles.value}>{c.phone}</Text>
              </View>
            ))}
          </Card.Content>
        </Card>
      )}

      {agreement.properties.length > 0 && (
        <Card style={styles.card}>
          <Card.Title title="Nieruchomości" />
          <Card.Content>
            {agreement.properties.map(p => (
              <Text key={p.id} style={styles.linkedItem}>#{p.offer_number} — {p.city}</Text>
            ))}
          </Card.Content>
        </Card>
      )}

      {agreement.searches.length > 0 && (
        <Card style={styles.card}>
          <Card.Title title="Poszukiwania" />
          <Card.Content>
            {agreement.searches.map(s => (
              <Text key={s.id} style={styles.linkedItem}>#{s.search_number} — {s.location_text || s.property_type}</Text>
            ))}
          </Card.Content>
        </Card>
      )}

      <View style={styles.actions}>
        <Button mode="contained" onPress={() => navigation.navigate('AgreementForm', { id: agreement.id })} buttonColor={PRIMARY} icon="pencil" style={styles.btn}>Edytuj</Button>
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
  num: { fontSize: 18, fontWeight: '700', color: PRIMARY },
  txChip: { backgroundColor: '#e3f2fd', alignSelf: 'flex-start', marginTop: 6 },
  card: { marginBottom: 12, borderRadius: 10 },
  row: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 5, borderBottomWidth: 1, borderBottomColor: '#f0f0f0' },
  label: { color: '#757575', flex: 1, fontSize: 13 },
  value: { color: '#212121', fontWeight: '500', flex: 1, textAlign: 'right', fontSize: 13 },
  input: { marginBottom: 8 },
  stageBtn: { borderRadius: 8 },
  linkedItem: { paddingVertical: 4, color: PRIMARY, fontSize: 13 },
  actions: { gap: 8, marginTop: 8, marginBottom: 24 },
  btn: { borderRadius: 8 },
});
