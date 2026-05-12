import React, { useEffect, useState } from 'react';
import { View, Text, StyleSheet, Alert, ActivityIndicator } from 'react-native';
import { useNavigation, useRoute, RouteProp } from '@react-navigation/native';
import { bleManager } from '../../components/ble/BLEManager';
import { ConfigForm, ConfigFormValues } from '../../components/ble/ConfigForm';
import { commitConfig, readNYSConfig, writeNYSConfig, NYSConfigPayload } from '../../components/ble/GATTService';
import { verifyDeviceOwnership } from '../../components/ble/DeviceVerify';
import { useAuthStore } from '../../store/auth';
import type { BLEStackParamList } from '../../navigation/RootNavigator';

type Rte = RouteProp<BLEStackParamList, 'BLEConfig'>;

export function BLEConfigScreen() {
  const route = useRoute<Rte>();
  const nav = useNavigation();
  const { deviceId, deviceName, deviceUid } = route.params;
  const activeAccountId = useAuthStore((s) => s.activeAccountId);
  const [connecting, setConnecting] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [initial, setInitial] = useState<Partial<ConfigFormValues> | null>(null);

  // Connect, then read the device's current config so the form prefills with
  // what's actually on the device — not stale defaults. Without this the
  // user sees "3600s" every time they open the screen, even if they just
  // changed it to 600s, and a save with a blank field overwrites the
  // existing value.
  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        await bleManager.connect(deviceId);

        // Re-verify ownership AFTER connecting, in case the user switched
        // active account between scan and connect (or the device was
        // re-assigned to a different account on the server while we were
        // on this screen). Catches the race the scan-time check can't.
        const recheck = await verifyDeviceOwnership(deviceUid, activeAccountId);
        if (cancelled) return;
        if (!recheck.ok) {
          await bleManager.disconnect(deviceId).catch(() => {});
          Alert.alert('Not allowed', recheck.reason, [
            { text: 'OK', onPress: () => nav.goBack() },
          ]);
          return;
        }

        const cfg: NYSConfigPayload = await readNYSConfig(deviceId);
        if (cancelled) return;
        // Convert numeric intervals back to strings for the text inputs.
        setInitial({
          ssid: cfg.ssid ?? '',
          // Password is write-only by convention — leave blank, save only
          // if the user explicitly types a new one.
          password: '',
          api_url: cfg.api_url ?? '',
          heartbeat_interval_s: cfg.heartbeat_interval_s !== undefined
            ? String(cfg.heartbeat_interval_s) : '',
          location_interval_s: cfg.location_interval_s !== undefined
            ? String(cfg.location_interval_s) : '',
          input1_desc: cfg.input1_desc ?? '',
        });
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
  }, [deviceId, deviceUid, activeAccountId, nav]);

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

  if (connecting || initial === null) {
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
      <ConfigForm initial={initial} onSubmit={onSubmit} submitting={submitting} />
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
