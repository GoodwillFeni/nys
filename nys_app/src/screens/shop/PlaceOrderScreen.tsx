import React, { useState } from 'react';
import { View, Text, StyleSheet, ScrollView, Alert } from 'react-native';
import { useNavigation, useRoute, RouteProp } from '@react-navigation/native';
import { Input } from '../../components/common/Input';
import { Button } from '../../components/common/Button';
import { placeOrder } from '../../api/shop';
import type { OrderPaymentMethod } from '../../types/shop';
import type { ShopStackParamList } from '../../navigation/ShopStack';

type Rte = RouteProp<ShopStackParamList, 'PlaceOrder'>;
const METHODS: { key: OrderPaymentMethod; label: string }[] = [
  { key: 'pay_in_store', label: 'Pay in store' },
  { key: 'deposit', label: 'Deposit' },
  { key: 'credit', label: 'Credit' },
];

export function PlaceOrderScreen() {
  const { items } = useRoute<Rte>().params;
  const nav = useNavigation();
  const [method, setMethod] = useState<OrderPaymentMethod>('pay_in_store');
  const [notes, setNotes] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const submit = async () => {
    setSubmitting(true);
    try {
      await placeOrder({ items, payment_method: method, notes: notes || undefined });
      Alert.alert('Order placed', 'Your order is awaiting approval.', [
        { text: 'OK', onPress: () => nav.goBack() },
      ]);
    } catch (e: any) {
      Alert.alert('Order failed', e?.response?.data?.message ?? e.message);
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <ScrollView contentContainerStyle={styles.c}>
      <Text style={styles.h}>Order summary</Text>
      <Text style={styles.sub}>{items.length} line item(s)</Text>

      <Text style={styles.lbl}>Payment method</Text>
      <View style={styles.chips}>
        {METHODS.map((m) => (
          <Text
            key={m.key}
            onPress={() => setMethod(m.key)}
            style={[styles.chip, m.key === method && styles.chipActive]}
          >
            {m.label}
          </Text>
        ))}
      </View>

      <Input label="Notes (optional)" value={notes} onChangeText={setNotes} multiline />
      <Button title="Place order" loading={submitting} onPress={submit} />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  c: { padding: 16, backgroundColor: '#ecf0f1' },
  h: { fontSize: 18, fontWeight: '700', color: '#2c3e50' },
  sub: { fontSize: 13, color: '#7f8c8d', marginBottom: 16 },
  lbl: { fontSize: 13, color: '#34495e', marginBottom: 6, fontWeight: '500' },
  chips: { flexDirection: 'row', gap: 6, marginBottom: 12 },
  chip: { paddingHorizontal: 14, paddingVertical: 8, backgroundColor: '#fff', borderRadius: 16, color: '#34495e', overflow: 'hidden' },
  chipActive: { backgroundColor: '#2a6ef2', color: '#fff' },
});
