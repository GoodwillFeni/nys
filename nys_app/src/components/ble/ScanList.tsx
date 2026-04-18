import React from 'react';
import { FlatList, Pressable, StyleSheet, Text, View } from 'react-native';
import { Device } from 'react-native-ble-plx';
import { extractUidFromName } from './BLEManager';

interface Props {
  devices: Device[];
  onSelect: (d: Device) => void;
}

export function ScanList({ devices, onSelect }: Props) {
  if (devices.length === 0) {
    return <Text style={styles.empty}>No NYS devices found yet. Make sure the device is powered on.</Text>;
  }
  return (
    <FlatList
      data={devices}
      keyExtractor={(d) => d.id}
      ItemSeparatorComponent={() => <View style={styles.sep} />}
      renderItem={({ item }) => (
        <Pressable style={styles.row} onPress={() => onSelect(item)}>
          <Text style={styles.name}>{item.name ?? 'NYS device'}</Text>
          <Text style={styles.uid}>UID: {extractUidFromName(item.name) ?? '\u2014'}</Text>
          <Text style={styles.rssi}>RSSI: {item.rssi ?? '\u2014'} dBm</Text>
        </Pressable>
      )}
    />
  );
}

const styles = StyleSheet.create({
  empty: { color: '#7f8c8d', textAlign: 'center', marginTop: 24 },
  row: { backgroundColor: '#fff', padding: 14, borderRadius: 8 },
  name: { fontSize: 16, fontWeight: '600', color: '#2c3e50' },
  uid: { fontSize: 12, color: '#34495e', marginTop: 2 },
  rssi: { fontSize: 11, color: '#7f8c8d', marginTop: 2 },
  sep: { height: 8 },
});
