import React, { useState } from 'react';
import { ScrollView, StyleSheet, Text, View } from 'react-native';
import { Input } from '../common/Input';
import { Button } from '../common/Button';

export interface ConfigFormValues {
  ssid: string;
  password: string;
  api_url: string;
  heartbeat_interval_s: string;
  location_interval_s: string;
  input1_desc: string;
}

interface Props {
  initial?: Partial<ConfigFormValues>;
  onSubmit: (vals: ConfigFormValues) => Promise<void> | void;
  submitting?: boolean;
}

export function ConfigForm({ initial, onSubmit, submitting }: Props) {
  const [vals, setVals] = useState<ConfigFormValues>({
    ssid: initial?.ssid ?? '',
    password: initial?.password ?? '',
    api_url: initial?.api_url ?? 'http://192.168.101.153:8000/api/device/message',
    heartbeat_interval_s: initial?.heartbeat_interval_s ?? '3600',
    location_interval_s: initial?.location_interval_s ?? '300',
    input1_desc: initial?.input1_desc ?? '',
  });

  const set = <K extends keyof ConfigFormValues>(k: K, v: ConfigFormValues[K]) =>
    setVals((s) => ({ ...s, [k]: v }));

  return (
    <ScrollView contentContainerStyle={styles.c}>
      <Text style={styles.sect}>WiFi</Text>
      <Input label="SSID" value={vals.ssid} onChangeText={(v) => set('ssid', v)} autoCapitalize="none" />
      <Input label="Password" value={vals.password} onChangeText={(v) => set('password', v)} secureTextEntry />

      <Text style={styles.sect}>Backend</Text>
      <Input label="API URL" value={vals.api_url} onChangeText={(v) => set('api_url', v)} autoCapitalize="none" />

      <Text style={styles.sect}>Intervals (seconds)</Text>
      <Input label="Heartbeat" value={vals.heartbeat_interval_s} onChangeText={(v) => set('heartbeat_interval_s', v)} keyboardType="numeric" />
      <Input label="Location" value={vals.location_interval_s} onChangeText={(v) => set('location_interval_s', v)} keyboardType="numeric" />

      <Text style={styles.sect}>Labels</Text>
      <Input label="Input 1 description" value={vals.input1_desc} onChangeText={(v) => set('input1_desc', v)} />

      <View style={{ height: 12 }} />
      <Button title="Save & Restart Device" loading={submitting} onPress={() => onSubmit(vals)} />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  c: { padding: 16, paddingBottom: 40 },
  sect: { fontSize: 13, fontWeight: '600', color: '#7f8c8d', marginTop: 16, marginBottom: 6, textTransform: 'uppercase' },
});
