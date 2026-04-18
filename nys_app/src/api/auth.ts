import { api } from './client';
import type { Account, LoginResponse, Role, User } from '../types';

interface ApiAccount {
  id: number;
  name: string;
  type?: string;
  role?: Role;
  can_manage_devices?: boolean | number;
  pivot?: { role?: Role; can_manage_devices?: boolean | number };
}

interface RawLoginResponse {
  status: string;
  token: string;
  expires_at: string;
  user: User;
  accounts: ApiAccount[];
}

function normalizeAccount(a: ApiAccount): Account {
  const role = (a.pivot?.role ?? a.role ?? 'Viewer') as Role;
  const flag = a.pivot?.can_manage_devices ?? a.can_manage_devices;
  return {
    id: a.id,
    name: a.name,
    type: a.type,
    role,
    can_manage_devices: !!Number(flag ?? 0),
  };
}

export async function login(loginId: string, password: string): Promise<LoginResponse> {
  const { data } = await api.post<RawLoginResponse>('/login', { login: loginId, password });
  return {
    status: data.status,
    token: data.token,
    expires_at: data.expires_at,
    user: data.user,
    accounts: data.accounts.map(normalizeAccount),
  };
}

export async function logoutApi(): Promise<void> {
  try {
    await api.post('/logout');
  } catch {
    /* ignore — token cleared locally regardless */
  }
}
