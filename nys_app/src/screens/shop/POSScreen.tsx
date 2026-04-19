import React, { useCallback, useMemo, useState } from 'react';
import {
  View, Text, StyleSheet, FlatList, RefreshControl, Alert, ScrollView, Pressable, Image, Modal,
} from 'react-native';
import * as ImagePicker from 'expo-image-picker';
import { useFocusEffect } from '@react-navigation/native';
import { Ionicons } from '@expo/vector-icons';
import {
  addCartItem, getCart, listProducts, posCheckout, removeCartItem, listCustomers,
  type ShopCustomer,
} from '../../api/shop';
import type { POSCart, POSPaymentMethod, ShopProduct } from '../../types/shop';
import { Input } from '../../components/common/Input';
import { Button } from '../../components/common/Button';

const METHODS: POSPaymentMethod[] = ['Cash', 'Cash Deposit', 'Credit'];

interface ProofPhoto { uri: string; name: string; type: string }

export function POSScreen() {
  const [products, setProducts] = useState<ShopProduct[]>([]);
  const [productSearch, setProductSearch] = useState('');

  const [cart, setCart] = useState<POSCart | null>(null);
  const [loading, setLoading] = useState(false);

  const [method, setMethod] = useState<POSPaymentMethod>('Cash');
  const [amountReceived, setAmountReceived] = useState('');

  const [customers, setCustomers] = useState<ShopCustomer[]>([]);
  const [customerSearch, setCustomerSearch] = useState('');
  const [customerPickerOpen, setCustomerPickerOpen] = useState(false);
  const [selectedCustomer, setSelectedCustomer] = useState<ShopCustomer | null>(null);
  const [manualCustomerName, setManualCustomerName] = useState('');
  const [manualCustomerPhone, setManualCustomerPhone] = useState('');

  const [proof, setProof] = useState<ProofPhoto | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const [p, c, cust] = await Promise.all([listProducts(), getCart(), listCustomers().catch(() => [])]);
      setProducts(p);
      setCart(c);
      setCustomers(cust);
    } catch (e: any) {
      Alert.alert('Load failed', e?.response?.data?.message ?? e.message);
    } finally { setLoading(false); }
  }, []);

  useFocusEffect(useCallback(() => { load(); }, [load]));

  const filteredProducts = useMemo(() => {
    const q = productSearch.trim().toLowerCase();
    if (!q) return products;
    return products.filter((p) =>
      p.product_name.toLowerCase().includes(q) ||
      (p.product_type ?? '').toLowerCase().includes(q)
    );
  }, [products, productSearch]);

  const filteredCustomers = useMemo(() => {
    const q = customerSearch.trim().toLowerCase();
    if (!q) return customers;
    return customers.filter((c) =>
      c.name.toLowerCase().includes(q) ||
      (c.phone ?? '').toLowerCase().includes(q)
    );
  }, [customers, customerSearch]);

  const add = async (p: ShopProduct) => {
    try { setCart(await addCartItem(p.id, 1)); }
    catch (e: any) { Alert.alert('Failed', e?.response?.data?.message ?? e.message); }
  };
  const remove = async (itemId: number) => {
    try { setCart(await removeCartItem(itemId)); }
    catch (e: any) { Alert.alert('Failed', e?.response?.data?.message ?? e.message); }
  };

  const attachProof = async (source: 'camera' | 'library') => {
    const perm = source === 'camera'
      ? await ImagePicker.requestCameraPermissionsAsync()
      : await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!perm.granted) {
      Alert.alert('Permission denied', `Cannot access ${source}.`);
      return;
    }
    const result = source === 'camera'
      ? await ImagePicker.launchCameraAsync({ quality: 0.7, mediaTypes: ImagePicker.MediaTypeOptions.Images })
      : await ImagePicker.launchImageLibraryAsync({ quality: 0.7, mediaTypes: ImagePicker.MediaTypeOptions.Images });
    if (result.canceled || !result.assets?.[0]) return;
    const a = result.assets[0];
    const ext = (a.uri.split('.').pop() ?? 'jpg').toLowerCase();
    setProof({
      uri: a.uri,
      name: `deposit-slip-${Date.now()}.${ext}`,
      type: a.mimeType ?? (ext === 'png' ? 'image/png' : 'image/jpeg'),
    });
  };

  const pickProof = () => {
    Alert.alert('Attach deposit slip', undefined, [
      { text: 'Take photo',    onPress: () => attachProof('camera') },
      { text: 'Choose from library', onPress: () => attachProof('library') },
      { text: 'Cancel', style: 'cancel' },
    ]);
  };

  const checkout = async () => {
    if (!cart || cart.items.length === 0) { Alert.alert('Cart empty', 'Add items first.'); return; }
    const needsAmount = method === 'Cash' || method === 'Cash Deposit';
    if (needsAmount && !amountReceived) { Alert.alert('Amount required', 'Enter amount received.'); return; }
    if (method === 'Credit' && !selectedCustomer) {
      Alert.alert('Customer required', 'Select a customer for Credit sales.');
      return;
    }
    if (method === 'Cash Deposit' && !proof) {
      Alert.alert('Proof required', 'Attach the deposit slip photo.');
      return;
    }

    setSubmitting(true);
    try {
      const sale = await posCheckout({
        payment_method: method,
        customer_id: selectedCustomer?.id,
        customer_name: selectedCustomer?.name ?? (manualCustomerName || undefined),
        customer_phone: selectedCustomer?.phone ?? (manualCustomerPhone || undefined),
        amount_received: needsAmount ? Number(amountReceived) : undefined,
        payment_proof: method === 'Cash Deposit' ? proof : null,
      });
      Alert.alert(
        'Sale complete',
        `Total R ${Number(sale.total_amount).toFixed(2)}${sale.change_amount ? ` \u00b7 change R ${Number(sale.change_amount).toFixed(2)}` : ''}`
      );
      setAmountReceived('');
      setSelectedCustomer(null);
      setManualCustomerName('');
      setManualCustomerPhone('');
      setProof(null);
      load();
    } catch (e: any) {
      Alert.alert('Checkout failed', e?.response?.data?.message ?? e.message);
    } finally { setSubmitting(false); }
  };

  return (
    <ScrollView
      style={styles.c}
      contentContainerStyle={{ paddingBottom: 60 }}
      keyboardShouldPersistTaps="handled"
      refreshControl={<RefreshControl refreshing={loading} onRefresh={load} />}
    >
      <Text style={styles.h}>Products</Text>
      <Input
        value={productSearch}
        onChangeText={setProductSearch}
        placeholder="Search products\u2026"
        autoCorrect={false}
      />
      <FlatList
        data={filteredProducts}
        keyExtractor={(p) => String(p.id)}
        horizontal
        contentContainerStyle={{ gap: 8, paddingBottom: 8 }}
        scrollEnabled
        renderItem={({ item }) => (
          <View style={styles.prod}>
            <Text style={styles.prodName} numberOfLines={2}>{item.product_name}</Text>
            <Text style={styles.prodPrice}>R {Number(item.actual_price).toFixed(2)}</Text>
            <Text style={styles.prodStock}>Stock: {item.int_stock}</Text>
            <Button title="Add" onPress={() => add(item)} />
          </View>
        )}
        ListEmptyComponent={<Text style={styles.empty}>No products match.</Text>}
      />

      <Text style={styles.h}>Cart</Text>
      {cart && cart.items.length > 0 ? (
        cart.items.map((ci) => (
          <View key={ci.id} style={styles.cartRow}>
            <View style={{ flex: 1 }}>
              <Text style={styles.cartName}>{ci.product?.product_name ?? `Item #${ci.product_id}`}</Text>
              <Text style={styles.cartMeta}>{ci.qty} \u00d7 R {Number(ci.unit_price).toFixed(2)} = R {Number(ci.total_price).toFixed(2)}</Text>
            </View>
            <Pressable onPress={() => remove(ci.id)} hitSlop={10}>
              <Text style={styles.rm}>remove</Text>
            </Pressable>
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
              <Pressable key={m} onPress={() => setMethod(m)}>
                <Text style={[styles.chip, m === method && styles.chipActive]}>{m}</Text>
              </Pressable>
            ))}
          </View>

          {/* Credit: mandatory customer picker */}
          {method === 'Credit' && (
            <View style={styles.section}>
              <Text style={styles.lbl}>Customer (required)</Text>
              {selectedCustomer ? (
                <View style={styles.selectedCustomer}>
                  <View style={{ flex: 1 }}>
                    <Text style={styles.custName}>{selectedCustomer.name}</Text>
                    {!!selectedCustomer.phone && <Text style={styles.custPhone}>{selectedCustomer.phone}</Text>}
                  </View>
                  <Pressable onPress={() => setSelectedCustomer(null)} hitSlop={10}>
                    <Text style={styles.rm}>change</Text>
                  </Pressable>
                </View>
              ) : (
                <Button title="Select customer\u2026" variant="secondary" onPress={() => setCustomerPickerOpen(true)} />
              )}
            </View>
          )}

          {/* Cash / Cash Deposit: optional walk-in name */}
          {method !== 'Credit' && (
            <View style={styles.section}>
              <Input label="Customer name (optional)" value={manualCustomerName} onChangeText={setManualCustomerName} />
              <Input label="Customer phone (optional)" value={manualCustomerPhone} onChangeText={setManualCustomerPhone} keyboardType="phone-pad" />
            </View>
          )}

          {(method === 'Cash' || method === 'Cash Deposit') && (
            <Input label="Amount received" value={amountReceived} onChangeText={setAmountReceived} keyboardType="numeric" />
          )}

          {/* Cash Deposit: proof photo */}
          {method === 'Cash Deposit' && (
            <View style={styles.section}>
              <Text style={styles.lbl}>Deposit slip (required)</Text>
              {proof ? (
                <View>
                  <Image source={{ uri: proof.uri }} style={styles.proofPreview} resizeMode="cover" />
                  <View style={styles.proofRow}>
                    <Button title="Replace" variant="secondary" onPress={pickProof} />
                    <View style={{ width: 8 }} />
                    <Button title="Remove" variant="danger" onPress={() => setProof(null)} />
                  </View>
                </View>
              ) : (
                <Pressable onPress={pickProof} style={styles.proofPlaceholder}>
                  <Ionicons name="camera-outline" size={28} color="#6a5cff" />
                  <Text style={styles.proofText}>Take photo or attach deposit slip</Text>
                </Pressable>
              )}
            </View>
          )}

          <Button title="Complete sale" loading={submitting} onPress={checkout} />
        </>
      )}

      {/* Customer picker modal */}
      <Modal visible={customerPickerOpen} animationType="slide" onRequestClose={() => setCustomerPickerOpen(false)}>
        <View style={styles.modalRoot}>
          <View style={styles.modalHeader}>
            <Text style={styles.modalTitle}>Select customer</Text>
            <Pressable onPress={() => setCustomerPickerOpen(false)} hitSlop={10}>
              <Ionicons name="close" size={24} color="#2c3e50" />
            </Pressable>
          </View>
          <View style={{ padding: 16 }}>
            <Input
              value={customerSearch}
              onChangeText={setCustomerSearch}
              placeholder="Search by name or phone\u2026"
              autoCorrect={false}
              autoFocus
            />
          </View>
          <FlatList
            data={filteredCustomers}
            keyExtractor={(c) => String(c.id)}
            keyboardShouldPersistTaps="handled"
            ItemSeparatorComponent={() => <View style={styles.sep} />}
            renderItem={({ item }) => (
              <Pressable
                style={styles.custRow}
                onPress={() => {
                  setSelectedCustomer(item);
                  setCustomerPickerOpen(false);
                  setCustomerSearch('');
                }}
              >
                <Text style={styles.custName}>{item.name}</Text>
                {!!item.phone && <Text style={styles.custPhone}>{item.phone}</Text>}
              </Pressable>
            )}
            ListEmptyComponent={
              <Text style={[styles.empty, { padding: 24 }]}>
                {customerSearch ? 'No customers match your search.' : 'No customers in this account.'}
              </Text>
            }
          />
        </View>
      </Modal>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  c: { flex: 1, padding: 16, backgroundColor: '#ecf0f1' },
  h: { fontSize: 14, fontWeight: '700', color: '#34495e', textTransform: 'uppercase', marginTop: 12, marginBottom: 8 },
  section: { marginTop: 8 },
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
  chips: { flexDirection: 'row', gap: 6, marginBottom: 12, flexWrap: 'wrap' },
  chip: { paddingHorizontal: 14, paddingVertical: 8, backgroundColor: '#fff', borderRadius: 16, color: '#34495e', overflow: 'hidden' },
  chipActive: { backgroundColor: '#6a5cff', color: '#fff' },
  lbl: { fontSize: 13, color: '#34495e', marginBottom: 6, fontWeight: '500' },
  selectedCustomer: {
    flexDirection: 'row', alignItems: 'center',
    padding: 12, backgroundColor: '#fff', borderRadius: 8,
  },
  custRow: { padding: 14, backgroundColor: '#fff' },
  custName: { fontSize: 15, fontWeight: '600', color: '#2c3e50' },
  custPhone: { fontSize: 12, color: '#7f8c8d', marginTop: 2 },
  sep: { height: 1, backgroundColor: '#ecf0f1' },
  proofPlaceholder: {
    alignItems: 'center', justifyContent: 'center',
    borderWidth: 2, borderColor: '#6a5cff', borderStyle: 'dashed',
    borderRadius: 8, padding: 24, backgroundColor: '#fff',
  },
  proofText: { color: '#6a5cff', marginTop: 8, fontWeight: '500' },
  proofPreview: { width: '100%', height: 240, borderRadius: 8, backgroundColor: '#000' },
  proofRow: { flexDirection: 'row', marginTop: 8 },
  modalRoot: { flex: 1, backgroundColor: '#fff' },
  modalHeader: {
    flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between',
    padding: 16, borderBottomWidth: 1, borderBottomColor: '#ecf0f1',
  },
  modalTitle: { fontSize: 18, fontWeight: '600', color: '#2c3e50' },
});
