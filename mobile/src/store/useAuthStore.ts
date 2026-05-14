import { create } from 'zustand';
import { AuthUser } from '../types';
import { login as apiLogin, logout as apiLogout, restoreSession } from '../api/auth';
import { getApiClient } from '../api/client';

interface AuthState {
  user: AuthUser | null;
  isLoading: boolean;
  isRestoringSession: boolean;
  error: string | null;

  login: (siteUrl: string, username: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  restoreSession: () => Promise<void>;
  clearError: () => void;
}

export const useAuthStore = create<AuthState>((set) => ({
  user: null,
  isLoading: false,
  isRestoringSession: true,
  error: null,

  login: async (siteUrl, username, password) => {
    set({ isLoading: true, error: null });
    try {
      const user = await apiLogin(siteUrl, username, password);
      set({ user, isLoading: false });
    } catch (err) {
      const { getLoginError } = await import('../api/auth');
      set({ isLoading: false, error: getLoginError(err) });
      throw err;
    }
  },

  logout: async () => {
    set({ isLoading: true });
    await apiLogout();
    set({ user: null, isLoading: false, error: null });
  },

  restoreSession: async () => {
    set({ isRestoringSession: true });
    try {
      const session = await restoreSession();
      if (!session) {
        set({ isRestoringSession: false });
        return;
      }

      const res = await getApiClient().get<AuthUser>('/me');
      set({ user: { ...res.data, token: session.token }, isRestoringSession: false });
    } catch {
      set({ user: null, isRestoringSession: false });
    }
  },

  clearError: () => set({ error: null }),
}));
