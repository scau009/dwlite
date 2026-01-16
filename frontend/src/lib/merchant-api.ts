import { apiFetch } from './api-client';

// Types
export interface Merchant {
  id: string;
  name: string;
  email: string;
  status: 'pending' | 'approved' | 'rejected' | 'disabled';
  contactName: string;
  contactPhone: string;
  hasWallets: boolean;
  depositBalance: string;
  balanceAmount: string;
  createdAt: string;
  updatedAt: string;
}

export interface MerchantDetail extends Merchant {
  logo: string | null;
  description: string | null;
  province: string | null;
  city: string | null;
  district: string | null;
  address: string | null;
  fullAddress: string;
  businessLicense: string | null;
  approvedAt: string | null;
  rejectedReason: string | null;
  user: {
    id: string;
    email: string;
    isVerified: boolean;
  };
}

export interface WalletTransaction {
  id: string;
  type: 'credit' | 'debit' | 'freeze' | 'unfreeze';
  amount: string;
  balanceBefore: string;
  balanceAfter: string;
  bizType: string;
  remark: string | null;
  operatorId: string | null;
  createdAt: string;
}

export interface WalletInfo {
  id: string;
  balance: string;
  frozenAmount?: string;
  status?: string;
}

// Merchant self-service types
export interface MerchantProfile {
  id: string;
  name: string;
  email: string;
  status: 'pending' | 'approved' | 'rejected' | 'disabled';
  description: string | null;
  contactName: string;
  contactPhone: string;
  province: string | null;
  city: string | null;
  district: string | null;
  address: string | null;
  fullAddress: string;
  businessLicense: string | null;
  approvedAt: string | null;
  rejectedReason: string | null;
  createdAt: string;
  updatedAt: string;
  depositBalance: string;
  depositFrozen: string;
  balanceAmount: string;
  balanceFrozen: string;
}

export interface UpdateMerchantProfileRequest {
  name: string;
  description?: string | null;
  contactName: string;
  contactPhone: string;
  province?: string | null;
  city?: string | null;
  district?: string | null;
  address?: string | null;
}

export interface Wallet {
  id: string;
  type: 'deposit' | 'balance';
  balance: string;
  frozenAmount: string;
  availableBalance: string;
  status: 'active' | 'frozen';
}

export interface WalletsResponse {
  deposit: Wallet | null;
  balance: Wallet | null;
}

export interface TransactionsResponse {
  data: WalletTransaction[];
  total: number;
  page: number;
  limit: number;
  wallet: Wallet;
}

export interface PaginatedResponse<T> {
  data: T[];
  total: number;
  page: number;
  limit: number;
}

export interface MerchantListParams {
  page?: number;
  limit?: number;
  status?: string;
  name?: string;
  email?: string;
}

export const merchantApi = {
  /**
   * 获取商户列表
   */
  getMerchants: async (params: MerchantListParams = {}): Promise<PaginatedResponse<Merchant>> => {
    const searchParams = new URLSearchParams();
    if (params.page) searchParams.set('page', String(params.page));
    if (params.limit) searchParams.set('limit', String(params.limit));
    if (params.status) searchParams.set('status', params.status);
    if (params.name) searchParams.set('name', params.name);
    if (params.email) searchParams.set('email', params.email);

    const query = searchParams.toString();
    return apiFetch<PaginatedResponse<Merchant>>(
      `/api/admin/merchants${query ? `?${query}` : ''}`
    );
  },

  /**
   * 获取商户详情
   */
  getMerchant: async (id: string): Promise<MerchantDetail> => {
    return apiFetch<MerchantDetail>(`/api/admin/merchants/${id}`);
  },

  /**
   * 切换商户状态
   */
  updateMerchantStatus: async (
    id: string,
    enabled: boolean
  ): Promise<{ message: string; merchant: Merchant }> => {
    return apiFetch(`/api/admin/merchants/${id}/status`, {
      method: 'PUT',
      body: JSON.stringify({ enabled }),
    });
  },

  /**
   * 保证金充值
   */
  chargeDeposit: async (
    id: string,
    amount: number | string,
    remark?: string
  ): Promise<{
    message: string;
    transaction: WalletTransaction;
    wallet: WalletInfo;
  }> => {
    return apiFetch(`/api/admin/merchants/${id}/wallets/deposit/charge`, {
      method: 'POST',
      body: JSON.stringify({ amount, remark }),
    });
  },

  /**
   * 获取保证金交易明细
   */
  getDepositTransactions: async (
    id: string,
    params: { page?: number; limit?: number } = {}
  ): Promise<PaginatedResponse<WalletTransaction> & { wallet: WalletInfo }> => {
    const searchParams = new URLSearchParams();
    if (params.page) searchParams.set('page', String(params.page));
    if (params.limit) searchParams.set('limit', String(params.limit));

    const query = searchParams.toString();
    return apiFetch(
      `/api/admin/merchants/${id}/wallets/deposit/transactions${query ? `?${query}` : ''}`
    );
  },

  /**
   * 审核通过商户
   */
  approveMerchant: async (id: string): Promise<{ message: string; merchant: Merchant }> => {
    return apiFetch(`/api/admin/merchants/${id}/approve`, {
      method: 'POST',
    });
  },

  /**
   * 审核拒绝商户
   */
  rejectMerchant: async (
    id: string,
    reason: string
  ): Promise<{ message: string; merchant: Merchant }> => {
    return apiFetch(`/api/admin/merchants/${id}/reject`, {
      method: 'POST',
      body: JSON.stringify({ reason }),
    });
  },

  // ============ Merchant Self-Service APIs ============

  /**
   * 获取当前商户信息
   */
  getMyProfile: async (): Promise<MerchantProfile> => {
    return apiFetch<MerchantProfile>('/api/merchant/profile');
  },

  /**
   * 更新当前商户信息
   */
  updateMyProfile: async (
    data: UpdateMerchantProfileRequest
  ): Promise<{ message: string; merchant: MerchantProfile; resubmitted?: boolean }> => {
    return apiFetch('/api/merchant/profile', {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  },

  /**
   * 获取当前商户钱包列表
   */
  getMyWallets: async (): Promise<WalletsResponse> => {
    return apiFetch<WalletsResponse>('/api/merchant/wallets');
  },

  /**
   * 获取当前商户保证金钱包交易明细
   */
  getMyDepositTransactions: async (
    params: { page?: number; limit?: number } = {}
  ): Promise<TransactionsResponse> => {
    const searchParams = new URLSearchParams();
    if (params.page) searchParams.set('page', String(params.page));
    if (params.limit) searchParams.set('limit', String(params.limit));

    const query = searchParams.toString();
    return apiFetch(
      `/api/merchant/wallets/deposit/transactions${query ? `?${query}` : ''}`
    );
  },

  /**
   * 获取当前商户余额钱包交易明细
   */
  getMyBalanceTransactions: async (
    params: { page?: number; limit?: number } = {}
  ): Promise<TransactionsResponse> => {
    const searchParams = new URLSearchParams();
    if (params.page) searchParams.set('page', String(params.page));
    if (params.limit) searchParams.set('limit', String(params.limit));

    const query = searchParams.toString();
    return apiFetch(
      `/api/merchant/wallets/balance/transactions${query ? `?${query}` : ''}`
    );
  },

  // ============ API Key Management ============

  /**
   * 获取商户的 API 密钥列表
   */
  getApiKeys: async (): Promise<{ data: MerchantApiKey[] }> => {
    return apiFetch<{ data: MerchantApiKey[] }>('/api/merchant/api-keys');
  },

  /**
   * 获取 API 密钥详情
   */
  getApiKey: async (id: string): Promise<MerchantApiKey> => {
    return apiFetch<MerchantApiKey>(`/api/merchant/api-keys/${id}`);
  },

  /**
   * 创建 API 密钥
   */
  createApiKey: async (data: CreateApiKeyRequest): Promise<CreateApiKeyResponse> => {
    return apiFetch<CreateApiKeyResponse>('/api/merchant/api-keys', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },

  /**
   * 更新 API 密钥权限
   */
  updateApiKeyPermissions: async (
    id: string,
    permissions: string[]
  ): Promise<{ message: string; apiKey: MerchantApiKey }> => {
    return apiFetch(`/api/merchant/api-keys/${id}/permissions`, {
      method: 'PUT',
      body: JSON.stringify({ permissions }),
    });
  },

  /**
   * 更新 API 密钥 IP 白名单
   */
  updateApiKeyIpWhitelist: async (
    id: string,
    ipWhitelist: string[] | null
  ): Promise<{ message: string; apiKey: MerchantApiKey }> => {
    return apiFetch(`/api/merchant/api-keys/${id}/ip-whitelist`, {
      method: 'PUT',
      body: JSON.stringify({ ipWhitelist }),
    });
  },

  /**
   * 重新生成 API 密钥 Secret
   */
  regenerateApiKeySecret: async (id: string): Promise<{ message: string; secret: string }> => {
    return apiFetch(`/api/merchant/api-keys/${id}/regenerate-secret`, {
      method: 'POST',
    });
  },

  /**
   * 停用 API 密钥
   */
  suspendApiKey: async (id: string): Promise<{ message: string; apiKey: MerchantApiKey }> => {
    return apiFetch(`/api/merchant/api-keys/${id}/suspend`, {
      method: 'POST',
    });
  },

  /**
   * 激活 API 密钥
   */
  activateApiKey: async (id: string): Promise<{ message: string; apiKey: MerchantApiKey }> => {
    return apiFetch(`/api/merchant/api-keys/${id}/activate`, {
      method: 'POST',
    });
  },

  /**
   * 删除 API 密钥
   */
  deleteApiKey: async (id: string): Promise<{ message: string }> => {
    return apiFetch(`/api/merchant/api-keys/${id}`, {
      method: 'DELETE',
    });
  },

  /**
   * 获取可用的权限列表
   */
  getAvailablePermissions: async (): Promise<{ permissions: string[] }> => {
    return apiFetch<{ permissions: string[] }>('/api/merchant/api-keys/permissions');
  },
};

// API Key Types
export interface MerchantApiKey {
  id: string;
  keyId: string;
  name: string;
  type: string;
  status: 'active' | 'suspended' | 'revoked';
  permissions: string[];
  ipWhitelist?: string[] | null;
  lastUsedAt: string | null;
  expiresAt: string | null;
  createdAt: string;
  updatedAt: string;
}

export interface CreateApiKeyRequest {
  name: string;
  permissions: string[];
  ipWhitelist?: string[] | null;
  expiresAt?: string | null;
}

export interface CreateApiKeyResponse {
  message: string;
  apiKey: MerchantApiKey;
  secret: string;
}
