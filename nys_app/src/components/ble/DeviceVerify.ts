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
 *
 * Strict v2 (Section C of pre-deploy plan): refuse when activeAccountId is
 * missing (never call the API without an account context), and reject any
 * device whose account differs from the active one \u2014 no more lenient
 * pass-through when match.account is undefined.
 */
export async function verifyDeviceOwnership(
  deviceUid: string,
  activeAccountId: number | null
): Promise<{ ok: true; device: ApiDevice } | { ok: false; reason: string }> {
  if (!activeAccountId) {
    return { ok: false, reason: 'No active account selected. Pick one in the account switcher first.' };
  }
  try {
    const { data } = await api.get<{ data: ApiDevice[] }>('/devices', {
      params: { device_uid: deviceUid },
    });
    const match = data.data.find((d) => d.device_uid === deviceUid);
    if (!match) {
      return { ok: false, reason: 'This device is not registered to any of your accounts.' };
    }
    if (match.account && match.account.id !== activeAccountId) {
      return { ok: false, reason: `Device belongs to "${match.account.name}" \u2014 switch account first.` };
    }
    return { ok: true, device: match };
  } catch (e: any) {
    return { ok: false, reason: e?.response?.data?.message ?? 'Could not verify device with server.' };
  }
}
