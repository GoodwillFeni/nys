export type Role =
  | 'Owner'
  | 'Admin'
  | 'Viewer'
  | 'FarmWorker'
  | 'ShopKeeper'
  | 'Customer'
  | 'Super_Admin';

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
  role: Role;
  can_manage_devices?: boolean;
}

export interface LoginResponse {
  status: string;
  token: string;
  expires_at: string;
  user: User;
  accounts: Account[];
}
