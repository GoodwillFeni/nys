import React, { useState } from 'react';
import { View, Text, StyleSheet, ScrollView, Alert } from 'react-native';
import { useNavigation, useRoute, RouteProp } from '@react-navigation/native';
import { Input } from '../../components/common/Input';
import { Button } from '../../components/common/Button';
import { logEvent } from '../../api/farm';
import { useAuthStore } from '../../store/auth';
import type { FarmStackParamList } from '../../navigation/FarmStack';
import type { CostType } from '../../types/farm';

type Rte = RouteProp<FarmStackParamList, 'LogEvent'>;

const EVENT_TYPES = ['Feeding', 'Vaccination', 'Treatment', 'Supplement', 'Birth', 'Death', 'Sold', 'Other'];
const COST_TYPES: CostType[] = ['expense', 'income', 'investment', 'loss', 'running', 'birth'];

export function LogEventScreen() {
  const { animal } = useRoute<Rte>().params;
  const nav = useNavigation();
  const accountId = useAuthStore((s) => s.activeAccountId);

  const today = new Date().toISOString().slice(0, 10);
  const [eventType, setEventType] = useState(EVENT_TYPES[0]);
  const [eventDate, setEventDate] = useState(today);
  const [cost, setCost] = useState('0');
  const [costType, setCostType] = useState<CostType>('expense');
  const [notes, setNotes] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const submit = async () => {
    if (!accountId) { Alert.alert('No account', 'Please select an account first.'); return; }
    setSubmitting(true);
    try {
      await logEvent({
        account_id: accountId,
        farm_id: animal.farm_id,
        animal_id: animal.id,
        event_type: eventType,
        event_date: eventDate,
        cost: Number(cost) || 0,
        cost_type: costType,
        meta: notes ? { notes } : undefined,
      });
      Alert.alert('Saved', 'Event logged.', [{ text: 'OK', onPress: () => nav.goBack() }]);
    } catch (e: any) {
      Alert.alert('Save failed', e?.response?.data?.message ?? e.message);
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <ScrollView contentContainerStyle={styles.c}>
      <Text style={styles.h}>{animal.animal_tag} {animal.animal_name ? `\u00b7 ${animal.animal_name}` : ''}</Text>

      <Text style={styles.lbl}>Event type</Text>
      <ChipRow items={EVENT_TYPES} value={eventType} onChange={setEventType} />

      <Input label="Event date (YYYY-MM-DD)" value={eventDate} onChangeText={setEventDate} />

      <Text style={styles.lbl}>Cost type</Text>
      <ChipRow items={COST_TYPES} value={costType} onChange={(v) => setCostType(v as CostType)} />

      <Input label="Cost" value={cost} onChangeText={setCost} keyboardType="numeric" />
      <Input label="Notes (optional)" value={notes} onChangeText={setNotes} multiline />

      <Button title="Log event" loading={submitting} onPress={submit} />
    </ScrollView>
  );
}

function ChipRow({ items, value, onChange }: { items: string[]; value: string; onChange: (v: string) => void }) {
  return (
    <View style={styles.chips}>
      {items.map((it) => {
        const active = it === value;
        return (
          <Text
            key={it}
            onPress={() => onChange(it)}
            style={[styles.chip, active && styles.chipActive]}
          >
            {it}
          </Text>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  c: { padding: 16, backgroundColor: '#ecf0f1' },
  h: { fontSize: 16, fontWeight: '700', color: '#2c3e50', marginBottom: 12 },
  lbl: { fontSize: 13, color: '#34495e', marginBottom: 6, fontWeight: '500' },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: 6, marginBottom: 12 },
  chip: { paddingHorizontal: 12, paddingVertical: 8, backgroundColor: '#fff', borderRadius: 16, color: '#34495e', overflow: 'hidden' },
  chipActive: { backgroundColor: '#2a6ef2', color: '#fff' },
});
