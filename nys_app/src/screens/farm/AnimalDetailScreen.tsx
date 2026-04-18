import React from 'react';
import { View, Text, StyleSheet, ScrollView } from 'react-native';
import { useRoute, RouteProp, useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Button } from '../../components/common/Button';
import { formatDate } from '../../utils/date';
import type { FarmStackParamList } from '../../navigation/FarmStack';

type Rte = RouteProp<FarmStackParamList, 'AnimalDetail'>;
type Nav = NativeStackNavigationProp<FarmStackParamList, 'AnimalDetail'>;

export function AnimalDetailScreen() {
  const { animal } = useRoute<Rte>().params;
  const nav = useNavigation<Nav>();

  return (
    <ScrollView contentContainerStyle={styles.c}>
      <View style={styles.card}>
        <Text style={styles.tag}>{animal.animal_tag}</Text>
        <Text style={styles.name}>{animal.animal_name ?? '\u2014'}</Text>
        <Row label="Status"  value={animal.status ?? '\u2014'} />
        <Row label="Sex"     value={animal.sex ?? '\u2014'} />
        <Row label="DOB"     value={formatDate(animal.date_of_birth)} />
        <Row label="Type"    value={animal.animalType?.name ?? '\u2014'} />
        <Row label="Breed"   value={animal.breed?.breed_name ?? '\u2014'} />
        <Row label="Farm"    value={animal.farm?.name ?? '\u2014'} />
        <Row label="Notes"   value={animal.notes ?? '\u2014'} />
      </View>

      <Button title="Log event" onPress={() => nav.navigate('LogEvent', { animal })} />
      <View style={{ height: 8 }} />
      <Button title="Edit animal" variant="secondary" onPress={() => nav.navigate('AnimalEdit', { animal })} />
      <View style={{ height: 8 }} />
      <Button title="Record inventory movement" variant="secondary" onPress={() => nav.navigate('InventoryMovement', { animal })} />
    </ScrollView>
  );
}

function Row({ label, value }: { label: string; value: string }) {
  return (
    <View style={styles.row}>
      <Text style={styles.lbl}>{label}</Text>
      <Text style={styles.val}>{value}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  c: { padding: 16, backgroundColor: '#ecf0f1' },
  card: { backgroundColor: '#fff', padding: 16, borderRadius: 8, marginBottom: 16 },
  tag: { fontSize: 22, fontWeight: '700', color: '#2c3e50' },
  name: { fontSize: 16, color: '#34495e', marginBottom: 12 },
  row: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 6, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: '#dfe4ea' },
  lbl: { color: '#7f8c8d', fontSize: 13 },
  val: { color: '#2c3e50', fontSize: 14, fontWeight: '500', flexShrink: 1, textAlign: 'right' },
});
