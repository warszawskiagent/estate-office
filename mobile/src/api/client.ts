import axios, { AxiosInstance, AxiosRequestConfig, InternalAxiosRequestConfig } from 'axios';
import * as SecureStore from 'expo-secure-store';

export const SECURE_STORE_TOKEN_KEY = 'eocrm_token';
export const SECURE_STORE_BASE_URL_KEY = 'eocrm_base_url';

let apiClient: AxiosInstance | null = null;
let currentBaseUrl = '';

export function getApiClient(): AxiosInstance {
  if (apiClient) return apiClient;

  apiClient = axios.create({
    timeout: 30_000,
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
  });

  apiClient.interceptors.request.use(async (config: InternalAxiosRequestConfig) => {
    if (!config.baseURL) {
      const stored = await SecureStore.getItemAsync(SECURE_STORE_BASE_URL_KEY);
      if (stored) {
        config.baseURL = stored.replace(/\/$/, '') + '/wp-json/eocrm/v1';
        currentBaseUrl = config.baseURL;
      }
    }

    const token = await SecureStore.getItemAsync(SECURE_STORE_TOKEN_KEY);
    if (token && config.headers) {
      config.headers.Authorization = `Bearer ${token}`;
    }

    return config;
  });

  apiClient.interceptors.response.use(
    (response) => response,
    async (error) => {
      if (error.response?.status === 401) {
        await SecureStore.deleteItemAsync(SECURE_STORE_TOKEN_KEY);
      }
      return Promise.reject(error);
    }
  );

  return apiClient;
}

export function resetApiClient(): void {
  apiClient = null;
  currentBaseUrl = '';
}

export function buildBaseUrl(siteUrl: string): string {
  return siteUrl.replace(/\/$/, '') + '/wp-json/eocrm/v1';
}

export function extractErrorMessage(error: unknown): string {
  if (axios.isAxiosError(error)) {
    const data = error.response?.data as Record<string, unknown> | undefined;
    if (data?.message && typeof data.message === 'string') return data.message;
    if (data?.code && typeof data.code === 'string') return data.code;
    if (error.code === 'ECONNABORTED') return 'Przekroczono czas połączenia z serwerem.';
    if (error.code === 'ERR_NETWORK') return 'Brak połączenia z internetem.';
  }
  return 'Wystąpił nieoczekiwany błąd.';
}
