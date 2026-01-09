import { apiFetch } from './api-client';

// Types
export type SettlementStatus = 'pending' | 'settled' | 'cancelled';

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

export interface Settlement {
  id: string;
  settlementNo: string;
  orderNo: string;
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
  items: SettlementItem[];
  cancelledAt: string | null;
  cancelReason: string | null;
}

export interface SettlementListParams {
  page?: number;
  limit?: number;
  settlementNo?: string;
  status?: SettlementStatus;
  scheduledSettleAtFrom?: string;
  scheduledSettleAtTo?: string;
}

export interface SettlementSummary {
  pendingAmount: string;
  settledAmount: string;
}

// Status labels and colors
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

// API functions
export const merchantSettlementApi = {
  /**
   * Get settlement list
   */
  async getSettlements(params: SettlementListParams = {}): Promise<{
    data: Settlement[];
    total: number;
    page: number;
    limit: number;
  }> {
    const searchParams = new URLSearchParams();
    if (params.page) searchParams.set('page', String(params.page));
    if (params.limit) searchParams.set('limit', String(params.limit));
    if (params.settlementNo) searchParams.set('settlementNo', params.settlementNo);
    if (params.status) searchParams.set('status', params.status);
    if (params.scheduledSettleAtFrom) searchParams.set('scheduledSettleAtFrom', params.scheduledSettleAtFrom);
    if (params.scheduledSettleAtTo) searchParams.set('scheduledSettleAtTo', params.scheduledSettleAtTo);

    const query = searchParams.toString();
    return apiFetch<{
      data: Settlement[];
      total: number;
      page: number;
      limit: number;
    }>(`/api/merchant/settlements${query ? `?${query}` : ''}`);
  },

  /**
   * Get settlement detail
   */
  async getSettlement(id: string): Promise<SettlementDetail> {
    return apiFetch<SettlementDetail>(`/api/merchant/settlements/${id}`);
  },

  /**
   * Get settlement summary
   */
  async getSettlementSummary(): Promise<SettlementSummary> {
    return apiFetch<SettlementSummary>('/api/merchant/settlements/summary');
  },
};
