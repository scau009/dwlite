// Account types
export type AccountType = 'admin' | 'merchant' | 'warehouse';

// User type matching backend User entity
export interface User {
  id: string;
  email: string;
  roles: string[];
  isVerified: boolean;
  accountType: AccountType;
  createdAt: string;
  warehouseId?: string;
  warehouseName?: string;
}

// API Request types
export interface LoginRequest {
  email: string;
  password: string;
}

export interface RegisterRequest {
  email: string;
  password: string;
}

export interface RefreshRequest {
  refresh_token: string;
}

export interface ForgotPasswordRequest {
  email: string;
}

export interface ResetPasswordRequest {
  token: string;
  password: string;
}

export interface ChangePasswordRequest {
  currentPassword: string;
  newPassword: string;
}

export interface VerifyEmailRequest {
  token: string;
}

// API Response types
export interface AuthTokens {
  token: string;
  refresh_token: string;
}

export interface RegisterResponse {
  message: string;
  user: {
    id: string;
    email: string;
  };
}

export interface VerifyEmailResponse {
  message: string;
  user: {
    id: string;
    email: string;
  };
}

export interface MessageResponse {
  message: string;
}

export interface ApiError {
  error: string;
  violations?: Record<string, string>;
}

// Validation error type
export interface ValidationErrors {
  [field: string]: string;
}
