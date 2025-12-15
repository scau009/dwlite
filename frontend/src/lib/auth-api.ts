import { apiFetch, tokenStorage } from './api-client';
import type {
  User,
  LoginRequest,
  RegisterRequest,
  AuthTokens,
  RegisterResponse,
  VerifyEmailResponse,
  MessageResponse,
  ForgotPasswordRequest,
  ResetPasswordRequest,
  ChangePasswordRequest,
} from '@/types/auth';

export const authApi = {
  register: async (data: RegisterRequest): Promise<RegisterResponse> => {
    return apiFetch<RegisterResponse>('/api/auth/register', {
      method: 'POST',
      body: JSON.stringify(data),
      skipAuth: true,
    });
  },

  verifyEmail: async (token: string): Promise<VerifyEmailResponse> => {
    return apiFetch<VerifyEmailResponse>(`/api/auth/verify-email?token=${token}`, {
      method: 'GET',
      skipAuth: true,
    });
  },

  login: async (data: LoginRequest): Promise<AuthTokens> => {
    const tokens = await apiFetch<AuthTokens>('/api/auth/login', {
      method: 'POST',
      body: JSON.stringify(data),
      skipAuth: true,
    });
    tokenStorage.setTokens(tokens);
    return tokens;
  },

  logout: async (): Promise<MessageResponse> => {
    try {
      const result = await apiFetch<MessageResponse>('/api/auth/logout', {
        method: 'POST',
      });
      return result;
    } finally {
      tokenStorage.clearTokens();
    }
  },

  getMe: async (): Promise<User> => {
    return apiFetch<User>('/api/auth/me');
  },

  forgotPassword: async (data: ForgotPasswordRequest): Promise<MessageResponse> => {
    return apiFetch<MessageResponse>('/api/auth/forgot-password', {
      method: 'POST',
      body: JSON.stringify(data),
      skipAuth: true,
    });
  },

  resetPassword: async (data: ResetPasswordRequest): Promise<MessageResponse> => {
    return apiFetch<MessageResponse>('/api/auth/reset-password', {
      method: 'POST',
      body: JSON.stringify(data),
      skipAuth: true,
    });
  },

  changePassword: async (data: ChangePasswordRequest): Promise<MessageResponse> => {
    return apiFetch<MessageResponse>('/api/auth/change-password', {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  },

  refreshToken: async (refreshToken: string): Promise<AuthTokens> => {
    const tokens = await apiFetch<AuthTokens>('/api/auth/refresh', {
      method: 'POST',
      body: JSON.stringify({ refresh_token: refreshToken }),
      skipAuth: true,
    });
    tokenStorage.setTokens(tokens);
    return tokens;
  },
};
