import axios from 'axios';
import Constants from 'expo-constants';
import { useAuthStore } from '../store/auth';

const baseURL = (Constants.expoConfig?.extra as { apiBaseUrl?: string })?.apiBaseUrl
  ?? 'http://192.168.101.177:8000/api';

export const api = axios.create({
  baseURL,
  timeout: 15000,
});

api.interceptors.request.use((config) => {
  const { token, activeAccountId } = useAuthStore.getState();
  if (token) config.headers.Authorization = `Bearer ${token}`;
  if (activeAccountId) config.headers['X-Account-ID'] = String(activeAccountId);
  return config;
});

api.interceptors.response.use(
  (r) => r,
  (err) => {
    if (err.response?.status === 401) {
      useAuthStore.getState().logout();
    }
    return Promise.reject(err);
  }
);
