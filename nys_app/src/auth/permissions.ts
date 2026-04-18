import type { Account, Role } from '../types';

export type ModuleKey =
  | 'farm'
  | 'farmWrite'
  | 'pos'
  | 'shopAdmin'
  | 'shopCustomer';

const MATRIX: Record<Role, ModuleKey[]> = {
  Owner: ['farm', 'farmWrite', 'pos', 'shopAdmin', 'shopCustomer'],
  Admin: ['farm', 'farmWrite', 'pos', 'shopAdmin'],
  Super_Admin: ['farm', 'farmWrite', 'pos', 'shopAdmin', 'shopCustomer'],
  ShopKeeper: ['pos', 'shopAdmin'],
  FarmWorker: ['farm', 'farmWrite'],
  Customer: ['shopCustomer'],
  Viewer: ['farm'],
};

export function can(role: Role | undefined, module: ModuleKey): boolean {
  if (!role) return false;
  return MATRIX[role]?.includes(module) ?? false;
}

export function canFarm(role?: Role) { return can(role, 'farm'); }
export function canFarmWrite(role?: Role) { return can(role, 'farmWrite'); }
export function canPOS(role?: Role) { return can(role, 'pos'); }
export function canShopAdmin(role?: Role) { return can(role, 'shopAdmin'); }
export function canShopCustomer(role?: Role) { return can(role, 'shopCustomer'); }

/**
 * Device (BLE) configuration is controlled by an explicit per-account
 * permission toggled by the admin in the web user management form.
 * Owners and Super_Admins always have it implicitly.
 */
export function canBLE(account: Account | undefined): boolean {
  if (!account) return false;
  if (account.role === 'Owner' || account.role === 'Super_Admin') return true;
  return !!account.can_manage_devices;
}
