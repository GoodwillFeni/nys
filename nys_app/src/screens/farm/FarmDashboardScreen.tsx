import React, { useCallback, useState } from 'react';
import { View, Text, StyleSheet, ScrollView, RefreshControl, ActivityIndicator, Alert } from 'react-native';
import { useFocusEffect, useNavigation } from '@react-navigation/native';
import { getDashboard } from '../../api/farm';
import { formatDate } from '../../utils/date';
import type { DashboardData } from '../../types/farm';
import { Button } from '../../components/common/Button';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import type { FarmStackParamList } from '../../navigation/FarmStack';

type Nav = NativeStackNavigationProp<FarmStackParamList, 'FarmDashboard'>;

export function FarmDashboardScreen() {
  const nav = useNavigation<Nav>();
  const [data, setData] = useState<DashboardData | null>(null);
  const [loading, setLoading] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      setData(await getDashboard());
    } catch (e: any) {
      Alert.alert('Failed to load dashboard', e?.response?.data?.message ?? e.message);
    } finally {
      setLoading(false);
    }
  }, []);

  useFocusEffect(useCallback(() => { load(); }, [load]));

  if (!data && loading) {
    return <View style={styles.center}><ActivityIndicator size="large" /></View>;
  }
  if (!data) {
    return (
      <View style={styles.center}>
        <Text>No data</Text>
        <Button title="Retry" onPress={load} />
      </View>
    );
  }

  return (
    <ScrollView
      contentContainerStyle={styles.c}
      refreshControl={<RefreshControl refreshing={loading} onRefresh={load} />}
    >
      <View style={styles.row}>
        <Stat label="Farms" value={data.total_farms} />
        <Stat label="Animals" value={data.total_animals} />
        <Stat label="Low stock" value={data.low_stock_count} />
      </View>

      <Section title="P&L (this period)">
        <Text style={styles.small}>{data.pnl.period}</Text>
        <Line label="Income" value={data.pnl.income} />
        <Line label="Expense" value={data.pnl.expense} />
        <Line label="Investment" value={data.pnl.investment} />
        <Line label="Profit" value={data.pnl.profit} bold />
      </Section>

      <Section title="Animals by status">
        {Object.entries(data.animals_by_status).map(([k, v]) => (
          <Line key={k} label={k} value={v} />
        ))}
      </Section>

      <Section title="Recent events">
        {data.recent_events.slice(0, 6).map((e) => (
          <View key={e.id} style={styles.eventRow}>
            <Text style={styles.eventType}>{e.event_type}</Text>
            <Text style={styles.small}>
              {e.animal?.animal_tag ?? '\u2014'} {e.animal?.animal_name ? `(${e.animal.animal_name})` : ''} \u00b7 {formatDate(e.event_date)}
            </Text>
          </View>
        ))}
      </Section>

      <View style={{ height: 8 }} />
      <Button title="View animals" onPress={() => nav.navigate('AnimalList')} />
    </ScrollView>
  );
}

function Stat({ label, value }: { label: string; value: number }) {
  return (
    <View style={styles.stat}>
      <Text style={styles.statVal}>{value}</Text>
      <Text style={styles.statLbl}>{label}</Text>
    </View>
  );
}
function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <View style={styles.section}>
      <Text style={styles.sectionTitle}>{title}</Text>
      {children}
    </View>
  );
}
function Line({ label, value, bold }: { label: string; value: number | string; bold?: boolean }) {
  return (
    <View style={styles.line}>
      <Text style={[styles.lineLbl, bold && styles.bold]}>{label}</Text>
      <Text style={[styles.lineVal, bold && styles.bold]}>{typeof value === 'number' ? value : value}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  c: { padding: 16, backgroundColor: '#ecf0f1' },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', gap: 12 },
  row: { flexDirection: 'row', gap: 8 },
  stat: { flex: 1, backgroundColor: '#fff', padding: 12, borderRadius: 8, alignItems: 'center' },
  statVal: { fontSize: 22, fontWeight: '700', color: '#2c3e50' },
  statLbl: { fontSize: 11, color: '#7f8c8d', marginTop: 2, textTransform: 'uppercase' },
  section: { backgroundColor: '#fff', padding: 14, borderRadius: 8, marginTop: 12 },
  sectionTitle: { fontSize: 14, fontWeight: '700', color: '#34495e', marginBottom: 8, textTransform: 'uppercase' },
  line: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 4 },
  lineLbl: { color: '#34495e' },
  lineVal: { color: '#2c3e50', fontWeight: '500' },
  bold: { fontWeight: '700' },
  small: { fontSize: 12, color: '#7f8c8d' },
  eventRow: { paddingVertical: 6, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: '#dfe4ea' },
  eventType: { fontWeight: '600', color: '#2c3e50' },
});
