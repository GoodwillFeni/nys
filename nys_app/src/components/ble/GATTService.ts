import { Buffer } from 'buffer';
import { bleManager } from './BLEManager';

// Match the on-air UUIDs the device actually broadcasts. NimBLE's
// BLE_UUID128_INIT() takes bytes little-endian, so the discriminator (0x01,
// 0x02, ..., 0x09) lands in byte[15] and shows up at the START of the
// canonical UUID string ("01a7e800-...").
export const SERVICE_UUID      = '01a7e800-004e-5953-0000-000000000000';
export const CHR_DEVICE_UID    = '02a7e800-004e-5953-0000-000000000000';
export const CHR_SSID          = '03a7e800-004e-5953-0000-000000000000';
export const CHR_PASSWORD      = '04a7e800-004e-5953-0000-000000000000';
export const CHR_API_URL       = '05a7e800-004e-5953-0000-000000000000';
export const CHR_HB_INTERVAL   = '06a7e800-004e-5953-0000-000000000000';
export const CHR_LOC_INTERVAL  = '07a7e800-004e-5953-0000-000000000000';
export const CHR_INPUT1_DESC   = '08a7e800-004e-5953-0000-000000000000';
export const CHR_COMMIT        = '09a7e800-004e-5953-0000-000000000000';

const b64 = (s: string) => Buffer.from(s, 'utf8').toString('base64');
const fromB64 = (s: string) => Buffer.from(s, 'base64').toString('utf8');

/**
 * All GATT operations work by deviceId, NOT a stored Device handle. This
 * avoids the "Service ... for device ? not found" failure that happens when
 * a cached Device handle goes stale (link drops, services not re-discovered).
 */
export async function readStringChar(deviceId: string, charUUID: string): Promise<string> {
  const v = await bleManager.readStringByDeviceId(deviceId, SERVICE_UUID, charUUID);
  return v ? fromB64(v) : '';
}

export async function writeStringChar(deviceId: string, charUUID: string, value: string): Promise<void> {
  await bleManager.writeStringByDeviceId(deviceId, SERVICE_UUID, charUUID, b64(value));
}

export async function writeUintChar(deviceId: string, charUUID: string, value: number): Promise<void> {
  await writeStringChar(deviceId, charUUID, String(value));
}

export interface NYSConfigPayload {
  ssid?: string;
  password?: string;
  api_url?: string;
  heartbeat_interval_s?: number;
  location_interval_s?: number;
  input1_desc?: string;
}

export async function writeNYSConfig(deviceId: string, cfg: NYSConfigPayload): Promise<void> {
  if (cfg.ssid !== undefined)                 await writeStringChar(deviceId, CHR_SSID, cfg.ssid);
  if (cfg.password !== undefined)             await writeStringChar(deviceId, CHR_PASSWORD, cfg.password);
  if (cfg.api_url !== undefined)              await writeStringChar(deviceId, CHR_API_URL, cfg.api_url);
  if (cfg.heartbeat_interval_s !== undefined) await writeUintChar(deviceId, CHR_HB_INTERVAL, cfg.heartbeat_interval_s);
  if (cfg.location_interval_s !== undefined)  await writeUintChar(deviceId, CHR_LOC_INTERVAL, cfg.location_interval_s);
  if (cfg.input1_desc !== undefined)          await writeStringChar(deviceId, CHR_INPUT1_DESC, cfg.input1_desc);
}

/**
 * Read every config characteristic the device exposes and return them as
 * a payload that ConfigForm can consume directly via its `initial` prop.
 *
 * The password characteristic is intentionally NOT read — devices return an
 * empty string for it on read (write-only by convention) so re-prefilling
 * an empty password would clear the stored WiFi creds on the next save.
 * Caller leaves the password field blank to keep the existing one.
 *
 * Reads run sequentially to avoid swamping the GATT link on slow Androids;
 * total round-trip is ~5 chars × ~50ms = <300ms, well within UI tolerance.
 */
export async function readNYSConfig(deviceId: string): Promise<NYSConfigPayload> {
  const ssid       = await readStringChar(deviceId, CHR_SSID);
  const apiUrl     = await readStringChar(deviceId, CHR_API_URL);
  const hbStr      = await readStringChar(deviceId, CHR_HB_INTERVAL);
  const locStr     = await readStringChar(deviceId, CHR_LOC_INTERVAL);
  const input1Desc = await readStringChar(deviceId, CHR_INPUT1_DESC);

  // Intervals come back as ASCII decimal strings (matches writeUintChar).
  // Treat empty / non-numeric as "device hasn't set this yet".
  const toNum = (s: string): number | undefined => {
    if (!s) return undefined;
    const n = Number(s);
    return Number.isFinite(n) ? n : undefined;
  };

  return {
    ssid: ssid || undefined,
    api_url: apiUrl || undefined,
    heartbeat_interval_s: toNum(hbStr),
    location_interval_s:  toNum(locStr),
    input1_desc: input1Desc || undefined,
  };
}

export async function commitConfig(deviceId: string): Promise<void> {
  // Fire-and-forget: the device immediately calls esp_restart() and won't ACK.
  // Using write-with-response would always show as "failed" on the app side.
  const b64v = Buffer.from('1', 'utf8').toString('base64');
  await bleManager.writeStringNoResponseByDeviceId(deviceId, SERVICE_UUID, CHR_COMMIT, b64v);
}

export async function readDeviceUid(deviceId: string): Promise<string> {
  return readStringChar(deviceId, CHR_DEVICE_UID);
}
