import { apiFetch } from './api-client';

// Types
export type PayoutStatus = 'pending' | 'approved' | 'processing' | 'completed' | 'rejected' | 'failed';

export interface Payout {
  id: string;
  payoutNo: string;
  amount: string;
  fee: string;
  actualAmount: string;
  currency: string;
  status: PayoutStatus;
  bankName: string;
  maskedAccountNumber: string;
  accountHolder: string;
  createdAt: string;
}

export interface PayoutDetail extends Payout {
  approvedAt: string | null;
  processingAt: string | null;
  completedAt: string | null;
  rejectedAt: string | null;
  failedAt: string | null;
  rejectReason: string | null;
  failReason: string | null;
  remark: string | null;
}

export interface PayoutListParams {
  page?: number;
  limit?: number;
  payoutNo?: string;
  status?: PayoutStatus;
  createdAtFrom?: string;
  createdAtTo?: string;
}

export interface CreatePayoutRequest {
  bankAccountId: string;
  amount: string;
  remark?: string;
}

export interface PayoutSummary {
  availableBalance: string;
  processingAmount: string;
  pendingAmount: string;
  bankAccounts: Array<{
    id: string;
    bankName: string;
    maskedAccountNumber: string;
    accountHolder: string;
    isDefault: boolean;
  }>;
}

// Status labels and colors
export const PAYOUT_STATUS_LABELS: Record<PayoutStatus, string> = {
  pending: 'merchantPayouts.statusPending',
  approved: 'merchantPayouts.statusApproved',
  processing: 'merchantPayouts.statusProcessing',
  completed: 'merchantPayouts.statusCompleted',
  rejected: 'merchantPayouts.statusRejected',
  failed: 'merchantPayouts.statusFailed',
};

export const PAYOUT_STATUS_COLORS: Record<PayoutStatus, string> = {
  pending: 'blue',
  approved: 'cyan',
  processing: 'orange',
  completed: 'green',
  rejected: 'red',
  failed: 'default',
};

// API functions
export const merchantPayoutApi = {
  /**
   * Get payout list
   */
  async getPayouts(params: PayoutListParams = {}): Promise<{
    data: Payout[];
    total: number;
    page: number;
    limit: number;
  }> {
    const searchParams = new URLSearchParams();
    if (params.page) searchParams.set('page', String(params.page));
    if (params.limit) searchParams.set('limit', String(params.limit));
    if (params.payoutNo) searchParams.set('payoutNo', params.payoutNo);
    if (params.status) searchParams.set('status', params.status);
    if (params.createdAtFrom) searchParams.set('createdAtFrom', params.createdAtFrom);
    if (params.createdAtTo) searchParams.set('createdAtTo', params.createdAtTo);

    const query = searchParams.toString();
    return apiFetch<{
      data: Payout[];
      total: number;
      page: number;
      limit: number;
    }>(`/api/merchant/payouts${query ? `?${query}` : ''}`);
  },

  /**
   * Get payout detail
   */
  async getPayout(id: string): Promise<PayoutDetail> {
    return apiFetch<PayoutDetail>(`/api/merchant/payouts/${id}`);
  },

  /**
   * Create payout request
   */
  async createPayout(data: CreatePayoutRequest): Promise<{ message: string; data: PayoutDetail }> {
    return apiFetch<{ message: string; data: PayoutDetail }>('/api/merchant/payouts', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data),
    });
  },

  /**
   * Get payout summary
   */
  async getPayoutSummary(): Promise<PayoutSummary> {
    return apiFetch<PayoutSummary>('/api/merchant/payouts/summary');
  },
};
