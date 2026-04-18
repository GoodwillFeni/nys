import React, { useState } from 'react';
import { View, Text, StyleSheet, ScrollView, Alert } from 'react-native';
import { useNavigation, useRoute, RouteProp } from '@react-navigation/native';
import { Input } from '../../components/common/Input';
import { Button } from '../../components/common/Button';
import { recordInventoryMovement } from '../../api/farm';
import type { FarmStackParamList } from '../../navigation/FarmStack';
import type { MovementType } from '../../types/farm';

type Rte = RouteProp<FarmStackParamList, 'InventoryMovement'>;
const MOVEMENT_TYPES: MovementType[] = ['purchase', 'issue', 'adjustment'];

export function InventoryMovementScreen() {
  const { animal } = useRoute<Rte>().params ?? { animal: undefined };
  const nav = useNavigation();

  const [farmId, setFarmId] = useState(animal?.farm_id ? String(animal.farm_id) : '');
  const [itemId, setItemId] = useState('');
  const [movementType, setMovementType] = useState<MovementType>('purchase');
  const [qty, setQty] = useState('');
  const [unitCost, setUnitCost] = useState('');
  const [notes, setNotes] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const submit = async () => {
    if (!farmId || !itemId || !qty) { Alert.alert('Missing info', 'Farm, item and quantity are required.'); return; }
    setSubmitting(true);
    try {
      await recordInventoryMovement({
        farm_id: Number(farmId),
        inventory_item_id: Number(itemId),
        movement_type: movementType,
        qty: Number(qty),
        unit_cost: unitCost ? Number(unitCost) : undefined,
        notes: notes || undefined,
        animal_id: animal?.id,
      });
      Alert.alert('Saved', 'Movement recorded.', [{ text: 'OK', onPress: () => nav.goBack() }]);
    } catch (e: any) {
      Alert.alert('Save failed', e?.response?.data?.message ?? e.message);
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <ScrollView contentContainerStyle={styles.c}>
      <Text style={styles.h}>Inventory movement</Text>
      {animal && <Text style={styles.sub}>For {animal.animal_tag} {animal.animal_name ?? ''}</Text>}

      <Text style={styles.lbl}>Type</Text>
      <View style={styles.chips}>
        {MOVEMENT_TYPES.map((t) => (
          <Text
            key={t}
            onPress={() => setMovementType(t)}
            style={[styles.chip, t === movementType && styles.chipActive]}
          >
            {t}
          </Text>
        ))}
      </View>

      <Input label="Farm ID" value={farmId} onChangeText={setFarmId} keyboardType="numeric" />
      <Input label="Inventory item ID" value={itemId} onChangeText={setItemId} keyboardType="numeric" />
      <Input label="Quantity" value={qty} onChangeText={setQty} keyboardType="numeric" />
      <Input label="Unit cost (optional for issue)" value={unitCost} onChangeText={setUnitCost} keyboardType="numeric" />
      <Input label="Notes" value={notes} onChangeText={setNotes} multiline />
      <Button title="Record movement" loading={submitting} onPress={submit} />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  c: { padding: 16, backgroundColor: '#ecf0f1' },
  h: { fontSize: 18, fontWeight: '700', color: '#2c3e50' },
  sub: { fontSize: 13, color: '#7f8c8d', marginBottom: 12 },
  lbl: { fontSize: 13, color: '#34495e', marginBottom: 6, fontWeight: '500' },
  chips: { flexDirection: 'row', gap: 6, marginBottom: 12 },
  chip: { paddingHorizontal: 14, paddingVertical: 8, backgroundColor: '#fff', borderRadius: 16, color: '#34495e', overflow: 'hidden' },
  chipActive: { backgroundColor: '#2a6ef2', color: '#fff' },
});
