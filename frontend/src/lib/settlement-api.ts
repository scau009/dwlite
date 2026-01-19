import { apiFetch } from './api-client';

// Settlement types
export interface Settlement {
  id: string;
  settlementNo: string;
  merchantId: string;
  merchantName: string;
  orderId: string;
  orderNo: string;
  fulfillmentId: string;
  fulfillmentNo: string;
  grossAmount: string;
  commissionRate: string;
  commissionAmount: string;
  netAmount: string;
  currency: string;
  status: SettlementStatus;
  settlementDays: number;
  scheduledSettleAt: string;
  settledAt: string | null;
  createdAt: string;
}

export interface SettlementDetail extends Settlement {
  cancelledAt: string | null;
  cancelReason: string | null;
  walletTransactionId: string | null;
  items: SettlementItem[];
}

export interface SettlementItem {
  id: string;
  skuCode: string | null;
  productName: string | null;
  quantity: number;
  unitPrice: string;
  grossAmount: string;
  commissionRate: string;
  commissionAmount: string;
  netAmount: string;
}

export type SettlementStatus = 'pending' | 'settled' | 'cancelled';

export const SETTLEMENT_STATUS_LABELS: Record<SettlementStatus, string> = {
  pending: 'settlements.statusPending',
  settled: 'settlements.statusSettled',
  cancelled: 'settlements.statusCancelled',
};

export const SETTLEMENT_STATUS_COLORS: Record<SettlementStatus, string> = {
  pending: 'blue',
  settled: 'green',
  cancelled: 'red',
};

// Payout types
export interface Payout {
  id: string;
  payoutNo: string;
  merchantId: string;
  merchantName: string;
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
  bankAccountId: string;
  accountNumber: string;
  bankCode: string | null;
  approvedAt: string | null;
  processingAt: string | null;
  completedAt: string | null;
  rejectedAt: string | null;
  failedAt: string | null;
  reviewedBy: string | null;
  rejectReason: string | null;
  failReason: string | null;
  remark: string | null;
  externalTransactionId: string | null;
  walletTransactionId: string | null;
}

export type PayoutStatus = 'pending' | 'approved' | 'processing' | 'completed' | 'rejected' | 'failed';

export const PAYOUT_STATUS_LABELS: Record<PayoutStatus, string> = {
  pending: 'payouts.statusPending',
  approved: 'payouts.statusApproved',
  processing: 'payouts.statusProcessing',
  completed: 'payouts.statusCompleted',
  rejected: 'payouts.statusRejected',
  failed: 'payouts.statusFailed',
};

export const PAYOUT_STATUS_COLORS: Record<PayoutStatus, string> = {
  pending: 'blue',
  approved: 'cyan',
  processing: 'orange',
  completed: 'green',
  rejected: 'red',
  failed: 'default',
};

// Request/Response types
export interface SettlementListParams {
  page?: number;
  limit?: number;
  settlementNo?: string;
  merchantId?: string;
  status?: SettlementStatus;
  scheduledSettleAtFrom?: string;
  scheduledSettleAtTo?: string;
}

export interface PayoutListParams {
  page?: number;
  limit?: number;
  payoutNo?: string;
  merchantId?: string;
  status?: PayoutStatus;
  createdAtFrom?: string;
  createdAtTo?: string;
}

export interface PaginatedResponse<T> {
  data: T[];
  total: number;
  page: number;
  limit: number;
}

// API functions
export const settlementApi = {
  // Settlement APIs
  getSettlements: async (params: SettlementListParams = {}): Promise<PaginatedResponse<Settlement>> => {
    const searchParams = new URLSearchParams();
    if (params.page) searchParams.set('page', String(params.page));
    if (params.limit) searchParams.set('limit', String(params.limit));
    if (params.settlementNo) searchParams.set('settlementNo', params.settlementNo);
    if (params.merchantId) searchParams.set('merchantId', params.merchantId);
    if (params.status) searchParams.set('status', params.status);
    if (params.scheduledSettleAtFrom) searchParams.set('scheduledSettleAtFrom', params.scheduledSettleAtFrom);
    if (params.scheduledSettleAtTo) searchParams.set('scheduledSettleAtTo', params.scheduledSettleAtTo);

    const query = searchParams.toString();
    return apiFetch<PaginatedResponse<Settlement>>(`/api/admin/settlements${query ? `?${query}` : ''}`);
  },

  getSettlement: async (id: string): Promise<SettlementDetail> => {
    return apiFetch<SettlementDetail>(`/api/admin/settlements/${id}`);
  },

  // Payout APIs
  getPayouts: async (params: PayoutListParams = {}): Promise<PaginatedResponse<Payout>> => {
    const searchParams = new URLSearchParams();
    if (params.page) searchParams.set('page', String(params.page));
    if (params.limit) searchParams.set('limit', String(params.limit));
    if (params.payoutNo) searchParams.set('payoutNo', params.payoutNo);
    if (params.merchantId) searchParams.set('merchantId', params.merchantId);
    if (params.status) searchParams.set('status', params.status);
    if (params.createdAtFrom) searchParams.set('createdAtFrom', params.createdAtFrom);
    if (params.createdAtTo) searchParams.set('createdAtTo', params.createdAtTo);

    const query = searchParams.toString();
    return apiFetch<PaginatedResponse<Payout>>(`/api/admin/payouts${query ? `?${query}` : ''}`);
  },

  getPayout: async (id: string): Promise<PayoutDetail> => {
    return apiFetch<PayoutDetail>(`/api/admin/payouts/${id}`);
  },

  settle: async (id: string): Promise<{ success: boolean; message: string }> => {
    return apiFetch<{ success: boolean; message: string }>(`/api/admin/settlements/${id}/settle`, {
      method: 'POST',
    });
  },

  approvePayout: async (id: string): Promise<{ message: string; payout: Payout }> => {
    return apiFetch<{ message: string; payout: Payout }>(`/api/admin/payouts/${id}/approve`, {
      method: 'POST',
    });
  },

  rejectPayout: async (id: string, reason: string): Promise<{ message: string; payout: Payout }> => {
    return apiFetch<{ message: string; payout: Payout }>(`/api/admin/payouts/${id}/reject`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ reason }),
    });
  },
};
