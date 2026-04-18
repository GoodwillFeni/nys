import { BleManager, Device, State } from 'react-native-ble-plx';
import { PermissionsAndroid, Platform } from 'react-native';

const ADV_PREFIX = 'NYS-';

class BLE {
  private manager = new BleManager();
  private scanning = false;

  async requestPermissions(): Promise<boolean> {
    if (Platform.OS !== 'android') return true;
    const api = Platform.Version as number;
    const perms: string[] = [];
    if (api >= 31) {
      perms.push(
        PermissionsAndroid.PERMISSIONS.BLUETOOTH_SCAN,
        PermissionsAndroid.PERMISSIONS.BLUETOOTH_CONNECT,
      );
    }
    perms.push(PermissionsAndroid.PERMISSIONS.ACCESS_FINE_LOCATION);
    const res = await PermissionsAndroid.requestMultiple(perms as any);
    return perms.every((p) => res[p as keyof typeof res] === PermissionsAndroid.RESULTS.GRANTED);
  }

  async waitPoweredOn(timeoutMs = 5000): Promise<boolean> {
    const start = Date.now();
    while (Date.now() - start < timeoutMs) {
      const s = await this.manager.state();
      if (s === State.PoweredOn) return true;
      await new Promise((r) => setTimeout(r, 300));
    }
    return false;
  }

  /**
   * Scan for NYS devices. Calls `onDevice` for each unique result.
   * Auto-stops after `timeoutMs`.
   */
  startScan(onDevice: (d: Device) => void, onError: (e: Error) => void, timeoutMs = 10000): () => void {
    if (this.scanning) this.manager.stopDeviceScan();
    this.scanning = true;
    const seen = new Set<string>();

    // Scan all devices and filter by name prefix — the service UUID lives in
    // the scan response, not the main advertisement, so Android's UUID filter
    // would miss our devices.
    this.manager.startDeviceScan(null, { allowDuplicates: false }, (err, device) => {
      if (err) { this.scanning = false; onError(err); return; }
      if (!device) return;
      if (seen.has(device.id)) return;
      const name = device.name ?? device.localName ?? '';
      if (!name.startsWith(ADV_PREFIX)) return;
      seen.add(device.id);
      onDevice(device);
    });

    const t = setTimeout(() => this.stopScan(), timeoutMs);
    return () => { clearTimeout(t); this.stopScan(); };
  }

  stopScan() {
    if (this.scanning) {
      this.manager.stopDeviceScan();
      this.scanning = false;
    }
  }

  async connect(id: string): Promise<Device> {
    const d = await this.manager.connectToDevice(id, { timeout: 10000 });
    await d.discoverAllServicesAndCharacteristics();
    return d;
  }

  async disconnect(id: string): Promise<void> {
    try { await this.manager.cancelDeviceConnection(id); } catch { /* ignore */ }
  }
}

export const bleManager = new BLE();

export function extractUidFromName(name: string | null | undefined): string | null {
  if (!name) return null;
  if (!name.startsWith(ADV_PREFIX)) return null;
  return name.substring(ADV_PREFIX.length);
}
