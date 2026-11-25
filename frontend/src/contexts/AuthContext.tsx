'use client';
import { createContext, useContext, useState, useEffect, ReactNode } from 'react';
import { authAPI } from '@/lib/api';

interface User {
  id: number;
  username: string;
  full_name: string;
  role: string;
  email?: string;
}

interface AuthContextType {
  user: User | null;
  loading: boolean;
  login: (username: string, password: string) => Promise<void>;
  logout: () => void;
  isAdmin: boolean;
  isPriest: boolean;
  isEditor: boolean;
  isModerator: boolean;
}

const AuthContext = createContext<AuthContextType>({
  user: null,
  loading: true,
  login: async () => {},
  logout: () => {},
  isAdmin: false,
  isPriest: false,
  isEditor: false,
  isModerator: false,
});

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    authAPI.getMe()
      .then(res => setUser(res.data))
      .catch(() => setUser(null))
      .finally(() => setLoading(false));
  }, []);

  const login = async (username: string, password: string) => {
    await authAPI.login(username, password);
    const userRes = await authAPI.getMe();
    setUser(userRes.data);
  };

  const logout = async () => {
    try {
      await authAPI.logout();
    } catch (e) {}
    setUser(null);
    window.location.href = '/login';
  };

  return (
    <AuthContext.Provider value={{
      user,
      loading,
      login,
      logout,
      isAdmin: user?.role === 'admin',
      isPriest: user?.role === 'priest',
      isEditor: ['admin', 'priest', 'service_admin'].includes(user?.role || ''),
      isModerator: ['admin', 'priest'].includes(user?.role || '')
    }}>
      {children}
    </AuthContext.Provider>
  );
}

export const useAuth = () => useContext(AuthContext);
