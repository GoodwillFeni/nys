import { api } from '../../api/client';

export interface ApiDevice {
  id: number;
  name: string;
  device_uid: string;
  account?: { id: number; name: string } | null;
}

/**
 * Verify that a device_uid (read from the BLE device) belongs to the user's
 * active account. The backend's GET /devices is already scoped to the caller's
 * accounts, so if the device comes back we're good.
 */
export async function verifyDeviceOwnership(
  deviceUid: string,
  activeAccountId: number | null
): Promise<{ ok: true; device: ApiDevice } | { ok: false; reason: string }> {
  try {
    const { data } = await api.get<{ data: ApiDevice[] }>('/devices', {
      params: { device_uid: deviceUid },
    });
    const match = data.data.find((d) => d.device_uid === deviceUid);
    if (!match) {
      return { ok: false, reason: 'This device is not registered to any of your accounts.' };
    }
    if (activeAccountId && match.account && match.account.id !== activeAccountId) {
      return { ok: false, reason: `Device belongs to "${match.account.name}" \u2014 switch account first.` };
    }
    return { ok: true, device: match };
  } catch (e: any) {
    return { ok: false, reason: e?.response?.data?.message ?? 'Could not verify device with server.' };
  }
}
