import React, { useEffect, useState } from 'react';
import { View, Text, StyleSheet, Alert, ActivityIndicator } from 'react-native';
import { useNavigation, useRoute, RouteProp } from '@react-navigation/native';
import { bleManager } from '../../components/ble/BLEManager';
import { ConfigForm, ConfigFormValues } from '../../components/ble/ConfigForm';
import { commitConfig, writeNYSConfig } from '../../components/ble/GATTService';
import type { BLEStackParamList } from '../../navigation/RootNavigator';

type Rte = RouteProp<BLEStackParamList, 'BLEConfig'>;

export function BLEConfigScreen() {
  const route = useRoute<Rte>();
  const nav = useNavigation();
  const { deviceId, deviceName, deviceUid } = route.params;
  const [connecting, setConnecting] = useState(true);
  const [submitting, setSubmitting] = useState(false);

  // Initial connect just to verify the device responds + cache services.
  // The actual write flow does its own ensureConnected() so a stale link
  // doesn't matter here.
  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        await bleManager.connect(deviceId);
      } catch (e: any) {
        if (!cancelled) {
          Alert.alert('Connection failed', e.message ?? String(e), [
            { text: 'OK', onPress: () => nav.goBack() },
          ]);
        }
      } finally {
        if (!cancelled) setConnecting(false);
      }
    })();
    return () => {
      cancelled = true;
      bleManager.disconnect(deviceId);
    };
  }, [deviceId, nav]);

  const onSubmit = async (vals: ConfigFormValues) => {
    setSubmitting(true);
    try {
      // Always reconnect fresh — guarantees a non-stale GATT handle, so
      // writes never fail with "Service ... for device ? not found".
      await bleManager.ensureConnected(deviceId);

      await writeNYSConfig(deviceId, {
        ssid: vals.ssid || undefined,
        password: vals.password || undefined,
        api_url: vals.api_url || undefined,
        heartbeat_interval_s: vals.heartbeat_interval_s ? Number(vals.heartbeat_interval_s) : undefined,
        location_interval_s: vals.location_interval_s ? Number(vals.location_interval_s) : undefined,
        input1_desc: vals.input1_desc || undefined,
      });
      await commitConfig(deviceId);
      Alert.alert('Success', 'Device is restarting with the new configuration.', [
        { text: 'OK', onPress: () => nav.goBack() },
      ]);
    } catch (e: any) {
      Alert.alert('Write failed', e.message ?? String(e));
    } finally {
      setSubmitting(false);
    }
  };

  if (connecting) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color="#2a6ef2" />
        <Text style={styles.hint}>Connecting to {deviceName}{'…'}</Text>
      </View>
    );
  }

  return (
    <View style={styles.c}>
      <View style={styles.header}>
        <Text style={styles.name}>{deviceName}</Text>
        <Text style={styles.uid}>UID: {deviceUid}</Text>
      </View>
      <ConfigForm onSubmit={onSubmit} submitting={submitting} />
    </View>
  );
}

const styles = StyleSheet.create({
  c: { flex: 1, backgroundColor: '#ecf0f1' },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: '#ecf0f1' },
  hint: { marginTop: 12, color: '#34495e' },
  header: { padding: 16, backgroundColor: '#fff', borderBottomWidth: 1, borderBottomColor: '#dfe4ea' },
  name: { fontSize: 18, fontWeight: '600', color: '#2c3e50' },
  uid: { fontSize: 12, color: '#7f8c8d', marginTop: 2 },
});
