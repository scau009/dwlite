import { apiFetch } from './api-client';

// Types
export interface DashboardSummary {
  todayOrders: number;
  todayOrdersGrowth: number;
  todayRevenue: string;
  todayRevenueGrowth: number;
  pendingExceptions: number;
  allocationFailed: number;
}

export interface OrderStats {
  byStatus: Record<string, number>;
}

export interface FulfillmentStats {
  byStatus: Record<string, number>;
  byType: Record<string, number>;
}

export interface ExceptionStats {
  orderExceptions: number;
  inboundExceptions: number;
}

export interface ChannelStats {
  total: number;
  syncFailed: number;
  outOfStock: number;
}

export interface MerchantStats {
  total: number;
  active: number;
  pending: number;
}

export interface DashboardData {
  summary: DashboardSummary;
  orderStats: OrderStats;
  fulfillmentStats: FulfillmentStats;
  exceptionStats: ExceptionStats;
  channelStats: ChannelStats;
  merchantStats: MerchantStats;
}

export interface TrendItem {
  date: string;
  orderCount: number;
  fulfillmentCount: number;
}

export interface RecentOrder {
  id: string;
  orderNo: string;
  channelName: string | null;
  totalAmount: string;
  status: string;
  placedAt: string;
}

export interface RecentException {
  id: string;
  exceptionNo: string;
  type: string;
  orderNo: string;
  status: string;
  createdAt: string;
}

export interface PendingFulfillment {
  id: string;
  fulfillmentNo: string;
  orderNo: string;
  type: string;
  status: string;
  createdAt: string;
}

export interface RecentData {
  recentOrders: RecentOrder[];
  recentExceptions: RecentException[];
  pendingFulfillments: PendingFulfillment[];
}

// API functions
export const adminDashboardApi = {
  /**
   * 获取工作台统计数据
   */
  getDashboard: async (): Promise<{ data: DashboardData }> => {
    return apiFetch<{ data: DashboardData }>('/api/admin/dashboard');
  },

  /**
   * 获取近7天趋势数据
   */
  getTrend: async (): Promise<{ data: TrendItem[] }> => {
    return apiFetch<{ data: TrendItem[] }>('/api/admin/dashboard/trend');
  },

  /**
   * 获取最近的数据列表
   */
  getRecent: async (): Promise<{ data: RecentData }> => {
    return apiFetch<{ data: RecentData }>('/api/admin/dashboard/recent');
  },
};
