import axios from 'axios';
import * as SecureStore from 'expo-secure-store';
import { AuthUser } from '../types';
import { SECURE_STORE_BASE_URL_KEY, SECURE_STORE_TOKEN_KEY, extractErrorMessage, resetApiClient } from './client';

export async function login(siteUrl: string, username: string, password: string): Promise<AuthUser> {
  const baseUrl = siteUrl.replace(/\/$/, '') + '/wp-json/eocrm/v1';

  const response = await axios.post<AuthUser & { token: string }>(
    `${baseUrl}/auth`,
    { username, password },
    {
      timeout: 30_000,
      headers: { 'Content-Type': 'application/json' },
    }
  );

  const data = response.data;

  await SecureStore.setItemAsync(SECURE_STORE_TOKEN_KEY, data.token);
  await SecureStore.setItemAsync(SECURE_STORE_BASE_URL_KEY, siteUrl);

  resetApiClient();

  return data;
}

export async function logout(): Promise<void> {
  try {
    const { getApiClient } = await import('./client');
    await getApiClient().post('/auth/logout');
  } catch {
    // best-effort
  } finally {
    await SecureStore.deleteItemAsync(SECURE_STORE_TOKEN_KEY);
    await SecureStore.deleteItemAsync(SECURE_STORE_BASE_URL_KEY);
    resetApiClient();
  }
}

export async function restoreSession(): Promise<{ token: string; baseUrl: string } | null> {
  const token = await SecureStore.getItemAsync(SECURE_STORE_TOKEN_KEY);
  const baseUrl = await SecureStore.getItemAsync(SECURE_STORE_BASE_URL_KEY);

  if (!token || !baseUrl) return null;

  return { token, baseUrl };
}

export function getLoginError(error: unknown): string {
  if (axios.isAxiosError(error)) {
    const status = error.response?.status;
    if (status === 401) return 'Nieprawidłowy login lub hasło.';
    if (status === 403) return 'Brak dostępu do systemu CRM.';
    if (status === 0 || error.code === 'ERR_NETWORK') return 'Nie można połączyć się z serwerem. Sprawdź adres URL.';
  }
  return extractErrorMessage(error);
}
