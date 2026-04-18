import React, { useCallback, useEffect, useState } from 'react';
import { View, Text, StyleSheet, Alert, ActivityIndicator } from 'react-native';
import { Device } from 'react-native-ble-plx';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Button } from '../../components/common/Button';
import { ScanList } from '../../components/ble/ScanList';
import { bleManager, extractUidFromName } from '../../components/ble/BLEManager';
import { verifyDeviceOwnership } from '../../components/ble/DeviceVerify';
import { useAuthStore } from '../../store/auth';
import type { BLEStackParamList } from '../../navigation/RootNavigator';

type Nav = NativeStackNavigationProp<BLEStackParamList, 'BLEScan'>;

export function BLEScanScreen() {
  const nav = useNavigation<Nav>();
  const activeAccountId = useAuthStore((s) => s.activeAccountId);
  const [devices, setDevices] = useState<Device[]>([]);
  const [scanning, setScanning] = useState(false);
  const [verifying, setVerifying] = useState(false);

  const startScan = useCallback(async () => {
    setDevices([]);
    const granted = await bleManager.requestPermissions();
    if (!granted) { Alert.alert('Permission denied', 'Bluetooth and Location permissions are required.'); return; }
    const poweredOn = await bleManager.waitPoweredOn();
    if (!poweredOn) { Alert.alert('Bluetooth off', 'Please turn on Bluetooth and try again.'); return; }

    setScanning(true);
    bleManager.startScan(
      (d) => setDevices((prev) => [...prev, d]),
      (e) => { setScanning(false); Alert.alert('Scan error', e.message); },
      10000
    );
    setTimeout(() => setScanning(false), 10000);
  }, []);

  useEffect(() => () => bleManager.stopScan(), []);

  const onSelect = async (d: Device) => {
    bleManager.stopScan();
    setScanning(false);
    const uid = extractUidFromName(d.name ?? d.localName);
    if (!uid) { Alert.alert('Unknown device', 'Device UID could not be determined.'); return; }

    setVerifying(true);
    const res = await verifyDeviceOwnership(uid, activeAccountId);
    setVerifying(false);
    if (!res.ok) { Alert.alert('Not allowed', res.reason); return; }

    nav.navigate('BLEConfig', { deviceId: d.id, deviceUid: uid, deviceName: res.device.name });
  };

  return (
    <View style={styles.c}>
      <Text style={styles.h}>Nearby NYS Devices</Text>
      <ScanList devices={devices} onSelect={onSelect} />
      <View style={styles.actions}>
        <Button title={scanning ? 'Scanning\u2026' : 'Scan'} onPress={startScan} loading={scanning} />
      </View>
      {verifying && (
        <View style={styles.overlay}>
          <ActivityIndicator size="large" color="#2a6ef2" />
          <Text style={styles.overlayText}>Verifying device\u2026</Text>
        </View>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  c: { flex: 1, padding: 16, backgroundColor: '#ecf0f1' },
  h: { fontSize: 18, fontWeight: '600', color: '#2c3e50', marginBottom: 12 },
  actions: { marginTop: 16 },
  overlay: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: 'rgba(255,255,255,0.85)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  overlayText: { marginTop: 8, fontSize: 14, color: '#2c3e50' },
});
