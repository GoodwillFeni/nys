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

  /**
   * Connect, negotiate a larger MTU on Android (default 23 truncates writes),
   * then discover services. Returns the device handle that has services
   * attached — must be the one passed to read/write calls.
   */
  async connect(id: string): Promise<Device> {
    let d = await this.manager.connectToDevice(id, { timeout: 15000 });
    if (Platform.OS === 'android') {
      try { d = await d.requestMTU(185); } catch { /* MTU is best-effort */ }
    }
    // discoverAllServicesAndCharacteristics returns a NEW Device with
    // services attached. The one we had before doesn't.
    d = await d.discoverAllServicesAndCharacteristics();
    return d;
  }

  /** True if the device is currently connected. */
  async isConnected(id: string): Promise<boolean> {
    try { return await this.manager.isDeviceConnected(id); } catch { return false; }
  }

  /**
   * Returns a Device handle ready for IO. Reuses the live connection if present
   * (re-running service discovery to refresh the GATT cache). Only does a full
   * disconnect+reconnect if the link is actually dead.
   *
   * Why not always reconnect: the ESP32 firmware defers deep sleep while a
   * peer is connected. A spurious disconnect from the app side can drop the
   * peer flag, the device's wake-window expires, and it sleeps mid-write.
   */
  async ensureConnected(id: string): Promise<Device> {
    try {
      if (await this.isConnected(id)) {
        // Live link — just refresh services. requestMTU is idempotent on Android;
        // discoverAllServicesAndCharacteristics returns a Device with services attached.
        const cached = await this.manager.devices([id]);
        const found = cached[0];
        if (found) {
          if (Platform.OS === 'android') {
            try { await found.requestMTU(185); } catch { /* already negotiated */ }
          }
          return await found.discoverAllServicesAndCharacteristics();
        }
      }
    } catch { /* fall through to fresh connect */ }
    return await this.connect(id);
  }

  /**
   * Manager-level write that resolves the device by ID at the moment of the
   * write. Avoids stale Device handles entirely.
   */
  async writeStringByDeviceId(
    deviceId: string,
    serviceUUID: string,
    charUUID: string,
    base64Value: string,
  ): Promise<void> {
    await this.manager.writeCharacteristicWithResponseForDevice(
      deviceId, serviceUUID, charUUID, base64Value
    );
  }

  /**
   * Fire-and-forget write — used for the COMMIT char where the device reboots
   * immediately and won't be around to send an ACK back.
   */
  async writeStringNoResponseByDeviceId(
    deviceId: string,
    serviceUUID: string,
    charUUID: string,
    base64Value: string,
  ): Promise<void> {
    await this.manager.writeCharacteristicWithoutResponseForDevice(
      deviceId, serviceUUID, charUUID, base64Value
    );
  }

  async readStringByDeviceId(
    deviceId: string,
    serviceUUID: string,
    charUUID: string,
  ): Promise<string | null> {
    const chr = await this.manager.readCharacteristicForDevice(deviceId, serviceUUID, charUUID);
    return chr.value ?? null;
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
