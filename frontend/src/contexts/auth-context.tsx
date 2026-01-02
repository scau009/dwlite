import {
  createContext,
  useContext,
  useEffect,
  useState,
  useCallback,
  type ReactNode,
} from 'react';
import type { User, LoginRequest, RegisterRequest } from '@/types/auth';
import { authApi } from '@/lib/auth-api';
import { tokenStorage } from '@/lib/api-client';

// Mock credentials for testing
const MOCK_CREDENTIALS = {
  email: 'test@demo.com',
  password: '123456',
};

const MOCK_USER: User = {
  id: 'mock-user-1',
  email: 'test@demo.com',
  roles: ['ROLE_USER'],
  isVerified: true,
  accountType: 'admin',
  createdAt: new Date().toISOString(),
};

// Check if using mock login
const isMockLogin = (email: string, password: string): boolean => {
  return email === MOCK_CREDENTIALS.email && password === MOCK_CREDENTIALS.password;
};

interface AuthContextType {
  user: User | null;
  isLoading: boolean;
  isAuthenticated: boolean;
  login: (data: LoginRequest) => Promise<void>;
  register: (data: RegisterRequest) => Promise<{ message: string }>;
  logout: () => Promise<void>;
  refreshUser: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  const refreshUser = useCallback(async () => {
    try {
      const userData = await authApi.getMe();
      setUser(userData);
    } catch {
      setUser(null);
      tokenStorage.clearTokens();
    }
  }, []);

  // Initialize auth state on mount
  useEffect(() => {
    const initAuth = async () => {
      const token = tokenStorage.getAccessToken();
      if (token) {
        // Check if it's a mock token
        if (token === 'mock-access-token') {
          setUser(MOCK_USER);
        } else {
          await refreshUser();
        }
      }
      setIsLoading(false);
    };
    initAuth();
  }, [refreshUser]);

  const login = async (data: LoginRequest) => {
    // Mock login for testing
    if (isMockLogin(data.email, data.password)) {
      // Set mock token to localStorage to persist session
      tokenStorage.setTokens({
        token: 'mock-access-token',
        refresh_token: 'mock-refresh-token',
      });
      setUser(MOCK_USER);
      return;
    }

    // Real login
    await authApi.login(data);
    await refreshUser();
  };

  const register = async (data: RegisterRequest) => {
    const response = await authApi.register(data);
    return { message: response.message };
  };

  const logout = async () => {
    const token = tokenStorage.getAccessToken();
    try {
      // Skip API call for mock login
      if (token !== 'mock-access-token') {
        await authApi.logout();
      }
    } finally {
      tokenStorage.clearTokens();
      setUser(null);
    }
  };

  return (
    <AuthContext.Provider
      value={{
        user,
        isLoading,
        isAuthenticated: !!user,
        login,
        register,
        logout,
        refreshUser,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}

// eslint-disable-next-line react-refresh/only-export-components
export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
}
