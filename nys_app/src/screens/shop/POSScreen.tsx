import React, { useCallback, useState } from 'react';
import { View, Text, StyleSheet, FlatList, RefreshControl, Alert, ScrollView } from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import { addCartItem, getCart, listProducts, posCheckout, removeCartItem } from '../../api/shop';
import type { POSCart, POSPaymentMethod, ShopProduct } from '../../types/shop';
import { Input } from '../../components/common/Input';
import { Button } from '../../components/common/Button';

const METHODS: POSPaymentMethod[] = ['Cash', 'Cash Deposit', 'Credit'];

export function POSScreen() {
  const [products, setProducts] = useState<ShopProduct[]>([]);
  const [cart, setCart] = useState<POSCart | null>(null);
  const [loading, setLoading] = useState(false);
  const [customerName, setCustomerName] = useState('');
  const [customerPhone, setCustomerPhone] = useState('');
  const [amountReceived, setAmountReceived] = useState('');
  const [method, setMethod] = useState<POSPaymentMethod>('Cash');
  const [submitting, setSubmitting] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const [p, c] = await Promise.all([listProducts(), getCart()]);
      setProducts(p);
      setCart(c);
    } catch (e: any) {
      Alert.alert('Load failed', e?.response?.data?.message ?? e.message);
    } finally { setLoading(false); }
  }, []);

  useFocusEffect(useCallback(() => { load(); }, [load]));

  const add = async (p: ShopProduct) => {
    try { setCart(await addCartItem(p.id, 1)); }
    catch (e: any) { Alert.alert('Failed', e?.response?.data?.message ?? e.message); }
  };
  const remove = async (itemId: number) => {
    try { setCart(await removeCartItem(itemId)); }
    catch (e: any) { Alert.alert('Failed', e?.response?.data?.message ?? e.message); }
  };

  const checkout = async () => {
    if (!cart || cart.items.length === 0) { Alert.alert('Cart empty', 'Add items first.'); return; }
    const needsAmount = method === 'Cash' || method === 'Cash Deposit';
    if (needsAmount && !amountReceived) { Alert.alert('Amount required', 'Enter amount received.'); return; }
    setSubmitting(true);
    try {
      const sale = await posCheckout({
        payment_method: method,
        customer_name: customerName || undefined,
        customer_phone: customerPhone || undefined,
        amount_received: needsAmount ? Number(amountReceived) : undefined,
      });
      Alert.alert('Sale complete', `Total R ${Number(sale.total_amount).toFixed(2)}${sale.change_amount ? ` \u00b7 change R ${Number(sale.change_amount).toFixed(2)}` : ''}`);
      setCustomerName(''); setCustomerPhone(''); setAmountReceived('');
      load();
    } catch (e: any) {
      Alert.alert('Checkout failed', e?.response?.data?.message ?? e.message);
    } finally { setSubmitting(false); }
  };

  return (
    <ScrollView style={styles.c} contentContainerStyle={{ paddingBottom: 40 }} keyboardShouldPersistTaps="handled">
      <Text style={styles.h}>Products</Text>
      <FlatList
        data={products}
        keyExtractor={(p) => String(p.id)}
        horizontal
        contentContainerStyle={{ gap: 8, paddingBottom: 8 }}
        refreshControl={<RefreshControl refreshing={loading} onRefresh={load} />}
        renderItem={({ item }) => (
          <View style={styles.prod}>
            <Text style={styles.prodName} numberOfLines={2}>{item.product_name}</Text>
            <Text style={styles.prodPrice}>R {Number(item.actual_price).toFixed(2)}</Text>
            <Text style={styles.prodStock}>Stock: {item.int_stock}</Text>
            <Button title="Add" onPress={() => add(item)} />
          </View>
        )}
      />

      <Text style={styles.h}>Cart</Text>
      {cart && cart.items.length > 0 ? (
        cart.items.map((ci) => (
          <View key={ci.id} style={styles.cartRow}>
            <View style={{ flex: 1 }}>
              <Text style={styles.cartName}>{ci.product?.product_name ?? `Item #${ci.product_id}`}</Text>
              <Text style={styles.cartMeta}>{ci.qty} \u00d7 R {Number(ci.unit_price).toFixed(2)} = R {Number(ci.total_price).toFixed(2)}</Text>
            </View>
            <Text onPress={() => remove(ci.id)} style={styles.rm}>remove</Text>
          </View>
        ))
      ) : (
        <Text style={styles.empty}>Cart is empty.</Text>
      )}

      {cart && cart.items.length > 0 && (
        <>
          <Text style={styles.total}>Total: R {Number(cart.total_amount).toFixed(2)}</Text>

          <View style={styles.chips}>
            {METHODS.map((m) => (
              <Text key={m} onPress={() => setMethod(m)} style={[styles.chip, m === method && styles.chipActive]}>{m}</Text>
            ))}
          </View>

          <Input label="Customer name (optional)" value={customerName} onChangeText={setCustomerName} />
          <Input label="Customer phone (optional)" value={customerPhone} onChangeText={setCustomerPhone} keyboardType="phone-pad" />
          {(method === 'Cash' || method === 'Cash Deposit') && (
            <Input label="Amount received" value={amountReceived} onChangeText={setAmountReceived} keyboardType="numeric" />
          )}
          <Button title="Complete sale" loading={submitting} onPress={checkout} />
        </>
      )}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  c: { flex: 1, padding: 16, backgroundColor: '#ecf0f1' },
  h: { fontSize: 14, fontWeight: '700', color: '#34495e', textTransform: 'uppercase', marginTop: 12, marginBottom: 8 },
  prod: { width: 150, padding: 10, backgroundColor: '#fff', borderRadius: 8 },
  prodName: { fontSize: 13, fontWeight: '600', color: '#2c3e50', minHeight: 34 },
  prodPrice: { fontSize: 15, color: '#2a6ef2', fontWeight: '600', marginTop: 4 },
  prodStock: { fontSize: 11, color: '#7f8c8d', marginBottom: 8 },
  cartRow: { flexDirection: 'row', padding: 10, backgroundColor: '#fff', borderRadius: 8, marginBottom: 6, alignItems: 'center' },
  cartName: { fontSize: 14, fontWeight: '600', color: '#2c3e50' },
  cartMeta: { fontSize: 12, color: '#7f8c8d', marginTop: 2 },
  rm: { color: '#c0392b', fontSize: 12 },
  empty: { color: '#7f8c8d', textAlign: 'center', padding: 16 },
  total: { fontSize: 18, fontWeight: '700', color: '#2c3e50', textAlign: 'right', marginTop: 8, marginBottom: 8 },
  chips: { flexDirection: 'row', gap: 6, marginBottom: 12 },
  chip: { paddingHorizontal: 14, paddingVertical: 8, backgroundColor: '#fff', borderRadius: 16, color: '#34495e', overflow: 'hidden' },
  chipActive: { backgroundColor: '#2a6ef2', color: '#fff' },
});
