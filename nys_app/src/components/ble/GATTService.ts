import { Device } from 'react-native-ble-plx';
import { Buffer } from 'buffer';

// Must match nys_esp/components/ble_cfg/ble_cfg.c
export const SERVICE_UUID      = 'a7e80000-4e4e-5953-0000-000000000001';
export const CHR_DEVICE_UID    = 'a7e80000-4e4e-5953-0000-000000000002';
export const CHR_SSID          = 'a7e80000-4e4e-5953-0000-000000000003';
export const CHR_PASSWORD      = 'a7e80000-4e4e-5953-0000-000000000004';
export const CHR_API_URL       = 'a7e80000-4e4e-5953-0000-000000000005';
export const CHR_HB_INTERVAL   = 'a7e80000-4e4e-5953-0000-000000000006';
export const CHR_LOC_INTERVAL  = 'a7e80000-4e4e-5953-0000-000000000007';
export const CHR_INPUT1_DESC   = 'a7e80000-4e4e-5953-0000-000000000008';
export const CHR_COMMIT        = 'a7e80000-4e4e-5953-0000-000000000009';

const b64 = (s: string) => Buffer.from(s, 'utf8').toString('base64');
const fromB64 = (s: string) => Buffer.from(s, 'base64').toString('utf8');

export async function readStringChar(dev: Device, charUUID: string): Promise<string> {
  const chr = await dev.readCharacteristicForService(SERVICE_UUID, charUUID);
  return chr.value ? fromB64(chr.value) : '';
}

export async function writeStringChar(dev: Device, charUUID: string, value: string): Promise<void> {
  await dev.writeCharacteristicWithResponseForService(SERVICE_UUID, charUUID, b64(value));
}

export async function writeUintChar(dev: Device, charUUID: string, value: number): Promise<void> {
  await writeStringChar(dev, charUUID, String(value));
}

export interface NYSConfigPayload {
  ssid?: string;
  password?: string;
  api_url?: string;
  heartbeat_interval_s?: number;
  location_interval_s?: number;
  input1_desc?: string;
}

export async function writeNYSConfig(dev: Device, cfg: NYSConfigPayload): Promise<void> {
  if (cfg.ssid !== undefined)                 await writeStringChar(dev, CHR_SSID, cfg.ssid);
  if (cfg.password !== undefined)             await writeStringChar(dev, CHR_PASSWORD, cfg.password);
  if (cfg.api_url !== undefined)              await writeStringChar(dev, CHR_API_URL, cfg.api_url);
  if (cfg.heartbeat_interval_s !== undefined) await writeUintChar(dev, CHR_HB_INTERVAL, cfg.heartbeat_interval_s);
  if (cfg.location_interval_s !== undefined)  await writeUintChar(dev, CHR_LOC_INTERVAL, cfg.location_interval_s);
  if (cfg.input1_desc !== undefined)          await writeStringChar(dev, CHR_INPUT1_DESC, cfg.input1_desc);
}

export async function commitConfig(dev: Device): Promise<void> {
  await writeStringChar(dev, CHR_COMMIT, '1');
}

export async function readDeviceUid(dev: Device): Promise<string> {
  return readStringChar(dev, CHR_DEVICE_UID);
}
