import React, { useMemo, useState } from 'react';
import { View, Text, StyleSheet, ScrollView, Alert } from 'react-native';
import { useNavigation, useRoute, RouteProp } from '@react-navigation/native';
import { Input } from '../../components/common/Input';
import { Button } from '../../components/common/Button';
import { logEvent, OffspringInput } from '../../api/farm';
import { useAuthStore } from '../../store/auth';
import type { FarmStackParamList } from '../../navigation/FarmStack';
import type { CostType } from '../../types/farm';

type Rte = RouteProp<FarmStackParamList, 'LogEvent'>;

const EVENT_TYPES = ['Feeding', 'Vaccination', 'Treatment', 'Supplement', 'Birth', 'Death', 'Sold', 'Other'];
const COST_TYPES: CostType[] = ['expense', 'income', 'investment', 'loss', 'running', 'birth'];
const SEX_OPTIONS: OffspringInput['sex'][] = ['Female', 'Male', 'Unknown'];

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

  // A birth = cost_type set to 'birth'. event_type is just description.
  const isBirth = useMemo(() => costType === 'birth', [costType]);
  const motherSexOk = !isBirth || (animal.sex ?? '').toLowerCase() === 'female';

  const [offspring, setOffspring] = useState<OffspringInput[]>([
    { animal_tag: '', sex: 'Female', animal_name: '' },
  ]);

  const setOffspringCount = (raw: string) => {
    const target = Math.max(1, Math.min(20, Number(raw) || 1));
    setOffspring((cur) => {
      const next = [...cur];
      while (next.length < target) next.push({ animal_tag: '', sex: 'Female', animal_name: '' });
      while (next.length > target) next.pop();
      return next;
    });
  };
  const updateOffspring = (i: number, patch: Partial<OffspringInput>) => {
    setOffspring((cur) => cur.map((o, idx) => (idx === i ? { ...o, ...patch } : o)));
  };

  // (no-op — isBirth is already derived from costType, no need to force-set it)

  const submit = async () => {
    if (!accountId) { Alert.alert('No account', 'Please select an account first.'); return; }
    if (isBirth && !motherSexOk) {
      Alert.alert('Invalid mother', `Birth events require a Female animal. ${animal.animal_tag} is sex='${animal.sex ?? '?'}'.`);
      return;
    }
    if (isBirth && offspring.some((o) => !o.sex)) {
      Alert.alert('Missing info', 'Each calf needs a sex (tag is optional — system marks untagged calves "NB-…").');
      return;
    }
    setSubmitting(true);
    try {
      // For Birth, omit cost when 0 so backend auto-fills from animal type's default_birth_value.
      const numericCost = Number(cost) || 0;
      await logEvent({
        account_id: accountId,
        farm_id: animal.farm_id,
        animal_id: animal.id,
        event_type: eventType,
        event_date: eventDate,
        cost: isBirth && numericCost === 0 ? undefined : numericCost,
        cost_type: costType,
        meta: notes ? { notes } : undefined,
        offspring: isBirth ? offspring : undefined,
      });
      Alert.alert(
        'Saved',
        isBirth ? `Birth recorded — ${offspring.length} offspring created.` : 'Event logged.',
        [{ text: 'OK', onPress: () => nav.goBack() }]
      );
    } catch (e: any) {
      // Surface Laravel validation errors clearly so the user sees what failed.
      const data = e?.response?.data;
      let msg = data?.message ?? e?.message ?? 'Save failed';
      if (data?.errors) {
        const lines = Object.entries(data.errors).map(
          ([k, v]) => `${k}: ${(Array.isArray(v) ? (v as string[])[0] : v)}`
        );
        msg = `${msg}\n${lines.join('\n')}`;
      }
      Alert.alert('Save failed', msg);
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <ScrollView contentContainerStyle={styles.c} keyboardShouldPersistTaps="handled">
      <Text style={styles.h}>{animal.animal_tag} {animal.animal_name ? `· ${animal.animal_name}` : ''}</Text>

      <Text style={styles.lbl}>Event type</Text>
      <ChipRow items={EVENT_TYPES} value={eventType} onChange={setEventType} />

      <Input label="Event date (YYYY-MM-DD)" value={eventDate} onChangeText={setEventDate} />

      <Text style={styles.lbl}>Cost type</Text>
      <ChipRow items={COST_TYPES} value={costType} onChange={(v) => setCostType(v as CostType)} />

      <Input label="Cost" value={cost} onChangeText={setCost} keyboardType="numeric" />

      {isBirth && (
        <View style={styles.birthBox}>
          <Text style={styles.birthHeader}>
            Offspring{' '}
            {!motherSexOk && (
              <Text style={styles.warn}>(animal sex='{animal.sex ?? '?'}' — must be Female)</Text>
            )}
          </Text>
          <Input
            label="Number born"
            value={String(offspring.length)}
            onChangeText={setOffspringCount}
            keyboardType="numeric"
          />
          {offspring.map((o, i) => (
            <View key={i} style={styles.offspringRow}>
              <Text style={styles.offspringLabel}>Calf #{i + 1}</Text>
              <Input
                label="Animal tag"
                value={String(o.animal_tag)}
                onChangeText={(v) => updateOffspring(i, { animal_tag: v })}
                keyboardType="numeric"
              />
              <Text style={styles.lbl}>Sex</Text>
              <ChipRow
                items={SEX_OPTIONS as unknown as string[]}
                value={o.sex}
                onChange={(v) => updateOffspring(i, { sex: v as OffspringInput['sex'] })}
              />
              <Input
                label="Name (optional)"
                value={o.animal_name ?? ''}
                onChangeText={(v) => updateOffspring(i, { animal_name: v })}
              />
            </View>
          ))}
        </View>
      )}

      <Input label="Notes (optional)" value={notes} onChangeText={setNotes} multiline />

      <Button title={isBirth ? 'Record birth' : 'Log event'} loading={submitting} onPress={submit} />
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
  c: { padding: 16, backgroundColor: '#ecf0f1', paddingBottom: 40 },
  h: { fontSize: 16, fontWeight: '700', color: '#2c3e50', marginBottom: 12 },
  lbl: { fontSize: 13, color: '#34495e', marginBottom: 6, fontWeight: '500' },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: 6, marginBottom: 12 },
  chip: { paddingHorizontal: 12, paddingVertical: 8, backgroundColor: '#fff', borderRadius: 16, color: '#34495e', overflow: 'hidden' },
  chipActive: { backgroundColor: '#2a6ef2', color: '#fff' },
  birthBox: {
    backgroundColor: '#f6f4ff',
    borderColor: '#d9d3ff',
    borderWidth: 1,
    borderRadius: 12,
    padding: 12,
    marginBottom: 12,
  },
  birthHeader: { color: '#6a5cff', fontWeight: '700', marginBottom: 8 },
  warn: { color: '#c0392b', fontWeight: '500', fontSize: 12 },
  offspringRow: { borderTopWidth: 1, borderTopColor: '#d9d3ff', paddingTop: 8, marginTop: 8 },
  offspringLabel: { color: '#6a5cff', fontWeight: '600', marginBottom: 6 },
});
