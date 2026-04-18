import React, { useCallback, useState } from 'react';
import { View, Text, StyleSheet, FlatList, RefreshControl, Alert, Pressable } from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import { listOrders, updateOrder } from '../../api/shop';
import type { ShopOrder } from '../../types/shop';
import { Button } from '../../components/common/Button';

const FILTERS: { key?: string; label: string }[] = [
  { key: 'pending_approval', label: 'Pending' },
  { key: 'approved', label: 'Approved' },
  { key: 'rejected', label: 'Rejected' },
  { key: 'completed', label: 'Completed' },
  { key: undefined, label: 'All' },
];

export function AllOrdersScreen() {
  const [orders, setOrders] = useState<ShopOrder[]>([]);
  const [filter, setFilter] = useState<string | undefined>('pending_approval');
  const [loading, setLoading] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await listOrders({ status: filter });
      setOrders(res.data);
    } catch (e: any) {
      Alert.alert('Load failed', e?.response?.data?.message ?? e.message);
    } finally { setLoading(false); }
  }, [filter]);

  useFocusEffect(useCallback(() => { load(); }, [load]));

  const act = async (order: ShopOrder, action: 'approved' | 'rejected' | 'completed') => {
    try {
      await updateOrder(order.id, { status: action });
      load();
    } catch (e: any) {
      Alert.alert('Action failed', e?.response?.data?.message ?? e.message);
    }
  };

  return (
    <View style={styles.c}>
      <View style={styles.filters}>
        {FILTERS.map((f) => (
          <Pressable key={f.label} onPress={() => setFilter(f.key)}>
            <Text style={[styles.chip, f.key === filter && styles.chipActive]}>{f.label}</Text>
          </Pressable>
        ))}
      </View>

      <FlatList
        data={orders}
        keyExtractor={(o) => String(o.id)}
        refreshControl={<RefreshControl refreshing={loading} onRefresh={load} />}
        ItemSeparatorComponent={() => <View style={{ height: 8 }} />}
        renderItem={({ item }) => (
          <View style={styles.row}>
            <Text style={styles.id}>Order #{item.id} \u00b7 {item.status}</Text>
            <Text style={styles.meta}>{item.items.length} items \u00b7 R {Number(item.total_amount).toFixed(2)}</Text>
            <Text style={styles.meta}>Payment: {item.payment_method}</Text>
            <View style={styles.actions}>
              {item.status === 'pending_approval' && (
                <>
                  <Button title="Approve" onPress={() => act(item, 'approved')} />
                  <View style={{ width: 8 }} />
                  <Button title="Reject" variant="danger" onPress={() => act(item, 'rejected')} />
                </>
              )}
              {item.status === 'approved' && (
                <Button title="Mark completed" onPress={() => act(item, 'completed')} />
              )}
            </View>
          </View>
        )}
        contentContainerStyle={orders.length === 0 ? styles.emptyWrap : undefined}
        ListEmptyComponent={!loading ? <Text style={styles.empty}>No orders in this view.</Text> : null}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  c: { flex: 1, padding: 16, backgroundColor: '#ecf0f1' },
  filters: { flexDirection: 'row', flexWrap: 'wrap', gap: 6, marginBottom: 12 },
  chip: { paddingHorizontal: 12, paddingVertical: 6, backgroundColor: '#fff', borderRadius: 14, color: '#34495e', overflow: 'hidden', fontSize: 12 },
  chipActive: { backgroundColor: '#2a6ef2', color: '#fff' },
  row: { padding: 14, backgroundColor: '#fff', borderRadius: 8 },
  id: { fontSize: 15, fontWeight: '700', color: '#2c3e50' },
  meta: { fontSize: 12, color: '#7f8c8d', marginTop: 2 },
  actions: { flexDirection: 'row', marginTop: 10 },
  emptyWrap: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  empty: { color: '#7f8c8d' },
});
