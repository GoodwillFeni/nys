import React, { useCallback, useState } from 'react';
import { View, Text, StyleSheet, FlatList, RefreshControl, Alert } from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import { getMyOrders } from '../../api/shop';
import type { ShopOrder } from '../../types/shop';
import { formatDateTime } from '../../utils/date';

export function MyOrdersScreen() {
  const [orders, setOrders] = useState<ShopOrder[]>([]);
  const [loading, setLoading] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try { setOrders(await getMyOrders()); }
    catch (e: any) { Alert.alert('Load failed', e?.response?.data?.message ?? e.message); }
    finally { setLoading(false); }
  }, []);

  useFocusEffect(useCallback(() => { load(); }, [load]));

  return (
    <View style={styles.c}>
      <FlatList
        data={orders}
        keyExtractor={(o) => String(o.id)}
        refreshControl={<RefreshControl refreshing={loading} onRefresh={load} />}
        ItemSeparatorComponent={() => <View style={{ height: 8 }} />}
        renderItem={({ item }) => (
          <View style={styles.row}>
            <View style={styles.rowTop}>
              <Text style={styles.id}>Order #{item.id}</Text>
              <StatusTag status={item.status} />
            </View>
            <Text style={styles.meta}>{item.items.length} items \u00b7 R {Number(item.total_amount).toFixed(2)}</Text>
            <Text style={styles.meta}>Payment: {item.payment_method} \u00b7 {formatDateTime(item.created_at)}</Text>
            {item.status === 'rejected' && item.rejection_reason && (
              <Text style={styles.reject}>Reason: {item.rejection_reason}</Text>
            )}
          </View>
        )}
        contentContainerStyle={orders.length === 0 ? styles.emptyWrap : undefined}
        ListEmptyComponent={!loading ? <Text style={styles.empty}>No orders yet.</Text> : null}
      />
    </View>
  );
}

function StatusTag({ status }: { status: string }) {
  const color =
    status === 'approved' ? '#27ae60' :
    status === 'rejected' ? '#c0392b' :
    status === 'completed' ? '#16a085' : '#f39c12';
  return <Text style={[styles.tag, { backgroundColor: color }]}>{status.replace('_', ' ')}</Text>;
}

const styles = StyleSheet.create({
  c: { flex: 1, padding: 16, backgroundColor: '#ecf0f1' },
  row: { padding: 14, backgroundColor: '#fff', borderRadius: 8 },
  rowTop: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 6 },
  id: { fontSize: 15, fontWeight: '700', color: '#2c3e50' },
  tag: { color: '#fff', fontSize: 11, fontWeight: '600', paddingHorizontal: 8, paddingVertical: 3, borderRadius: 10, overflow: 'hidden' },
  meta: { fontSize: 12, color: '#7f8c8d', marginTop: 2 },
  reject: { fontSize: 12, color: '#c0392b', marginTop: 4, fontStyle: 'italic' },
  emptyWrap: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  empty: { color: '#7f8c8d' },
});
