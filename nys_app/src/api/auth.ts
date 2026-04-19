import { api } from './client';
import type { Account, LoginResponse, User, RouteName, ActionName } from '../types';

interface ApiAccount {
  id: number;
  name: string;
  type?: string;
  route_access?: RouteName[];
  action_access?: ActionName[];
  pivot?: {
    route_access?: RouteName[] | string;
    action_access?: ActionName[] | string;
  };
}

interface RawLoginResponse {
  status: string;
  token: string;
  expires_at: string;
  user: User;
  accounts: ApiAccount[];
}

function parseList<T extends string>(value: T[] | string | undefined): T[] {
  if (Array.isArray(value)) return value;
  if (typeof value === 'string') {
    try { const parsed = JSON.parse(value); return Array.isArray(parsed) ? parsed : []; } catch { return []; }
  }
  return [];
}

function normalizeAccount(a: ApiAccount): Account {
  const routes  = parseList<RouteName>(a.pivot?.route_access  ?? a.route_access);
  const actions = parseList<ActionName>(a.pivot?.action_access ?? a.action_access);
  return {
    id: a.id,
    name: a.name,
    type: a.type,
    route_access: routes,
    action_access: actions,
  };
}

export async function login(loginId: string, password: string): Promise<LoginResponse> {
  const { data } = await api.post<RawLoginResponse>('/login', { login: loginId, password });
  return {
    status: data.status,
    token: data.token,
    expires_at: data.expires_at,
    user: { ...data.user, is_super_admin: !!data.user.is_super_admin },
    accounts: data.accounts.map(normalizeAccount),
  };
}

export async function logoutApi(): Promise<void> {
  try { await api.post('/logout'); } catch { /* ignore */ }
}
