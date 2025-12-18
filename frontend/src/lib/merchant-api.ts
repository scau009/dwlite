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
   * 初始化商户钱包
   */
  initMerchantWallets: async (
    id: string
  ): Promise<{ message: string; wallets: WalletInfo[] }> => {
    return apiFetch(`/api/admin/merchants/${id}/wallets/init`, {
      method: 'POST',
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
};
