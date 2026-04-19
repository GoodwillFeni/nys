import type { RouteName, ActionName } from '../permissions-registry';

export type { RouteName, ActionName };

export interface User {
  id: number;
  name: string;
  surname: string;
  email: string;
  phone?: string;
  is_super_admin: boolean;
}

export interface Account {
  id: number;
  name: string;
  type?: string;
  route_access: RouteName[];
  action_access: ActionName[];
}

export interface LoginResponse {
  status: string;
  token: string;
  expires_at: string;
  user: User;
  accounts: Account[];
}
