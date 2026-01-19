import { apiFetch } from './api-client';

// Types
export interface MerchantDashboardSummary {
  totalAvailable: number;
  totalInTransit: number;
  totalReserved: number;
  totalDamaged: number;
  totalSkuCount: number;
  warehouseCount: number;
  pendingInbounds: number;
  pendingOutbounds: number;
  pendingExceptions: number;
}

export interface WalletInfo {
  balance: string;
  frozenAmount: string;
}

export interface MerchantDashboardData {
  summary: MerchantDashboardSummary;
  inbound: Record<string, number>;
  outbound: Record<string, number>;
  wallet: {
    deposit: WalletInfo;
    balance: WalletInfo;
  };
  settlement: {
    pendingAmount: string;
    settledAmount: string;
  };
  payout: {
    availableBalance: string;
    processingAmount: string;
  };
}

export interface MerchantTrendItem {
  date: string;
  inboundCount: number;
  outboundCount: number;
}

export interface RecentInbound {
  id: string;
  orderNo: string;
  status: string;
  totalQuantity: number;
  createdAt: string;
}

export interface RecentOutbound {
  id: string;
  outboundNo: string;
  status: string;
  totalQuantity: number;
  createdAt: string;
}

export interface RecentException {
  id: string;
  exceptionNo: string;
  type: string;
  status: string;
  createdAt: string;
}

export interface MerchantRecentData {
  recentInbounds: RecentInbound[];
  recentOutbounds: RecentOutbound[];
  recentExceptions: RecentException[];
}

// API functions
export const merchantDashboardApi = {
  /**
   * Get merchant dashboard statistics
   */
  getDashboard: async (): Promise<MerchantDashboardData> => {
    return apiFetch<MerchantDashboardData>('/api/merchant/dashboard');
  },

  /**
   * Get 7-day trend data
   */
  getTrend: async (): Promise<{ data: MerchantTrendItem[] }> => {
    return apiFetch<{ data: MerchantTrendItem[] }>('/api/merchant/dashboard/trend');
  },

  /**
   * Get recent orders and exceptions
   */
  getRecent: async (): Promise<{ data: MerchantRecentData }> => {
    return apiFetch<{ data: MerchantRecentData }>('/api/merchant/dashboard/recent');
  },
};
