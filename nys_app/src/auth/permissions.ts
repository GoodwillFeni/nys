import type { Account, RouteName, ActionName } from '../types';

/**
 * Low-level predicates against an Account's stored permission arrays.
 * Super admins bypass all checks at the store layer (see useAuthStore).
 */
export function canRoute(account: Account | undefined, route: RouteName): boolean {
  if (!account) return false;
  return account.route_access.includes(route);
}

export function canAction(account: Account | undefined, action: ActionName): boolean {
  if (!account) return false;
  return account.action_access.includes(action);
}

/**
 * Thin wrappers so RootNavigator's existing imports keep working unchanged.
 * Each wrapper checks for the relevant root screen of its module.
 */
export function canFarm(account: Account | undefined): boolean {
  return canRoute(account, 'FarmDashboard') || canRoute(account, 'AnimalList');
}

export function canBLE(account: Account | undefined): boolean {
  return canRoute(account, 'DevicesList') || canRoute(account, 'AddDevice');
}

export function canPOS(account: Account | undefined): boolean {
  return canRoute(account, 'ShopPOS');
}

export function canShopAdmin(account: Account | undefined): boolean {
  return canRoute(account, 'AdminOrders') || canRoute(account, 'ShopSalesSummary');
}

export function canShopCustomer(account: Account | undefined): boolean {
  return canRoute(account, 'ShopProducts') || canRoute(account, 'ShopMyOrders');
}
