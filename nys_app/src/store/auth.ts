import { create } from 'zustand';
import * as SecureStore from 'expo-secure-store';
import type { User, Account, RouteName, ActionName } from '../types';

const TOKEN_KEY = 'nys_token';
const USER_KEY = 'nys_user';
const ACCOUNTS_KEY = 'nys_accounts';
const ACTIVE_KEY = 'nys_active_account';

interface AuthState {
  token: string | null;
  user: User | null;
  accounts: Account[];
  activeAccountId: number | null;
  hydrated: boolean;
  hydrate: () => Promise<void>;
  setSession: (token: string, user: User, accounts: Account[]) => Promise<void>;
  setActiveAccount: (id: number) => Promise<void>;
  logout: () => Promise<void>;
  activeAccount: () => Account | undefined;
  canAccessRoute: (route: RouteName) => boolean;
  canAction: (action: ActionName) => boolean;
}

export const useAuthStore = create<AuthState>((set, get) => ({
  token: null,
  user: null,
  accounts: [],
  activeAccountId: null,
  hydrated: false,

  hydrate: async () => {
    const [token, userStr, accountsStr, activeStr] = await Promise.all([
      SecureStore.getItemAsync(TOKEN_KEY),
      SecureStore.getItemAsync(USER_KEY),
      SecureStore.getItemAsync(ACCOUNTS_KEY),
      SecureStore.getItemAsync(ACTIVE_KEY),
    ]);
    set({
      token: token ?? null,
      user: userStr ? JSON.parse(userStr) : null,
      accounts: accountsStr ? JSON.parse(accountsStr) : [],
      activeAccountId: activeStr ? Number(activeStr) : null,
      hydrated: true,
    });
  },

  setSession: async (token, user, accounts) => {
    await Promise.all([
      SecureStore.setItemAsync(TOKEN_KEY, token),
      SecureStore.setItemAsync(USER_KEY, JSON.stringify(user)),
      SecureStore.setItemAsync(ACCOUNTS_KEY, JSON.stringify(accounts)),
    ]);
    const activeAccountId = accounts[0]?.id ?? null;
    if (activeAccountId) {
      await SecureStore.setItemAsync(ACTIVE_KEY, String(activeAccountId));
    }
    set({ token, user, accounts, activeAccountId });
  },

  setActiveAccount: async (id) => {
    await SecureStore.setItemAsync(ACTIVE_KEY, String(id));
    set({ activeAccountId: id });
  },

  logout: async () => {
    await Promise.all([
      SecureStore.deleteItemAsync(TOKEN_KEY),
      SecureStore.deleteItemAsync(USER_KEY),
      SecureStore.deleteItemAsync(ACCOUNTS_KEY),
      SecureStore.deleteItemAsync(ACTIVE_KEY),
    ]);
    set({ token: null, user: null, accounts: [], activeAccountId: null });
  },

  activeAccount: () => {
    const { accounts, activeAccountId } = get();
    return accounts.find((a) => a.id === activeAccountId);
  },

  canAccessRoute: (route) => {
    const { user, accounts, activeAccountId } = get();
    if (user?.is_super_admin) return true;
    const acc = accounts.find((a) => a.id === activeAccountId);
    return !!acc && acc.route_access.includes(route);
  },

  canAction: (action) => {
    const { user, accounts, activeAccountId } = get();
    if (user?.is_super_admin) return true;
    const acc = accounts.find((a) => a.id === activeAccountId);
    return !!acc && acc.action_access.includes(action);
  },
}));
