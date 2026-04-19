import React, { useCallback, useMemo, useState } from 'react';
import { View, Text, StyleSheet, FlatList, RefreshControl, Alert } from 'react-native';
import { useFocusEffect, useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Button } from '../../components/common/Button';
import { Input } from '../../components/common/Input';
import { listProducts } from '../../api/shop';
import type { ShopProduct } from '../../types/shop';
import { useAuthStore } from '../../store/auth';
import { canShopAdmin } from '../../auth/permissions';
import type { ShopStackParamList } from '../../navigation/ShopStack';

type Nav = NativeStackNavigationProp<ShopStackParamList, 'ProductList'>;

export function ShopScreen() {
  const nav = useNavigation<Nav>();
  const account = useAuthStore((s) => s.activeAccount());
  const [products, setProducts] = useState<ShopProduct[]>([]);
  const [cart, setCart] = useState<Record<number, number>>({});
  const [loading, setLoading] = useState(false);
  const [search, setSearch] = useState('');

  const filteredProducts = useMemo(() => {
    const q = search.trim().toLowerCase();
    if (!q) return products;
    return products.filter((p) =>
      p.product_name.toLowerCase().includes(q) ||
      (p.product_type ?? '').toLowerCase().includes(q)
    );
  }, [products, search]);

  const load = useCallback(async () => {
    setLoading(true);
    try { setProducts(await listProducts()); }
    catch (e: any) { Alert.alert('Load failed', e?.response?.data?.message ?? e.message); }
    finally { setLoading(false); }
  }, []);

  useFocusEffect(useCallback(() => { load(); }, [load]));

  const add = (p: ShopProduct) => setCart((c) => ({ ...c, [p.id]: (c[p.id] ?? 0) + 1 }));
  const sub = (p: ShopProduct) => setCart((c) => {
    const q = (c[p.id] ?? 0) - 1;
    const next = { ...c };
    if (q <= 0) delete next[p.id]; else next[p.id] = q;
    return next;
  });

  const cartTotal = products.reduce((sum, p) => sum + (cart[p.id] ?? 0) * Number(p.actual_price ?? 0), 0);
  const cartCount = Object.values(cart).reduce((s, q) => s + q, 0);

  const goCheckout = () => {
    const items = Object.entries(cart).map(([pid, qty]) => ({ product_id: Number(pid), qty }));
    if (items.length === 0) return;
    nav.navigate('PlaceOrder', { items });
  };

  return (
    <View style={styles.c}>
      <View style={styles.topRow}>
        {canShopAdmin(account) && (
          <Button title="Manage orders" variant="secondary" onPress={() => nav.navigate('AllOrders')} />
        )}
        <Button title="My orders" variant="secondary" onPress={() => nav.navigate('MyOrders')} />
      </View>

      <Input
        value={search}
        onChangeText={setSearch}
        placeholder="Search products by name or type\u2026"
        autoCorrect={false}
      />

      <FlatList
        data={filteredProducts}
        keyExtractor={(p) => String(p.id)}
        refreshControl={<RefreshControl refreshing={loading} onRefresh={load} />}
        ItemSeparatorComponent={() => <View style={{ height: 8 }} />}
        renderItem={({ item }) => (
          <View style={styles.row}>
            <View style={{ flex: 1 }}>
              <Text style={styles.name}>{item.product_name}</Text>
              <Text style={styles.meta}>
                {item.product_type ? `${item.product_type} \u00b7 ` : ''}Stock: {item.int_stock}
              </Text>
              <Text style={styles.price}>R {Number(item.actual_price).toFixed(2)}</Text>
            </View>
            <View style={styles.qty}>
              <Text style={styles.qtyBtn} onPress={() => sub(item)}>{'\u2212'}</Text>
              <Text style={styles.qtyVal}>{cart[item.id] ?? 0}</Text>
              <Text style={styles.qtyBtn} onPress={() => add(item)}>+</Text>
            </View>
          </View>
        )}
      />

      {cartCount > 0 && (
        <View style={styles.bar}>
          <Text style={styles.barText}>{cartCount} items \u00b7 R {cartTotal.toFixed(2)}</Text>
          <Button title="Review & order" onPress={goCheckout} />
        </View>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  c: { flex: 1, padding: 16, backgroundColor: '#ecf0f1' },
  topRow: { flexDirection: 'row', gap: 8, marginBottom: 12 },
  row: { flexDirection: 'row', padding: 14, backgroundColor: '#fff', borderRadius: 8, alignItems: 'center' },
  name: { fontSize: 15, fontWeight: '600', color: '#2c3e50' },
  meta: { fontSize: 12, color: '#7f8c8d', marginTop: 2 },
  price: { fontSize: 16, color: '#2a6ef2', marginTop: 4, fontWeight: '600' },
  qty: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  qtyBtn: { fontSize: 22, fontWeight: '700', color: '#2a6ef2', paddingHorizontal: 8 },
  qtyVal: { fontSize: 16, fontWeight: '600', color: '#2c3e50', minWidth: 20, textAlign: 'center' },
  bar: { flexDirection: 'row', alignItems: 'center', backgroundColor: '#fff', padding: 12, borderRadius: 8, marginTop: 8, gap: 12 },
  barText: { flex: 1, fontSize: 14, color: '#2c3e50', fontWeight: '500' },
});
