import { apiFetch } from './api-client';

// Types
export type ChannelProductStatus = 'draft' | 'pending' | 'active' | 'paused' | 'rejected' | 'delisted';
export type ChannelProductSyncStatus = 'pending' | 'syncing' | 'synced' | 'failed';
export type StockMode = 'aggregate' | 'lowest' | 'fixed';

// Sync Log Types
export type SyncOperation = 'aggregate' | 'push_product' | 'update_stock_price' | 'delist';
export type SyncLogStatus = 'pending' | 'processing' | 'success' | 'failed' | 'skipped';
export type SyncTriggerSource =
  | 'listing_create'
  | 'listing_update'
  | 'listing_activate'
  | 'listing_pause'
  | 'listing_delete'
  | 'inventory_inbound'
  | 'inventory_outbound'
  | 'inventory_adjust'
  | 'manual'
  | 'scheduled'
  | 'compensation';

// Source types
export type AllocationMode = 'shared' | 'dedicated';
export type FulfillmentType = 'consignment' | 'self_fulfillment';
export type PricingModel = 'self_pricing' | 'platform_managed';
export type ListingStatus = 'draft' | 'active' | 'paused' | 'sold_out';

export interface ChannelProductSyncLog {
  id: string;
  operation: SyncOperation;
  triggerSource: SyncTriggerSource;
  status: SyncLogStatus;
  errorCode: string | null;
  errorMessage: string | null;
  beforeData: Record<string, unknown> | null;
  afterData: Record<string, unknown> | null;
  durationMs: number | null;
  startedAt: string;
  completedAt: string | null;
  createdAt: string;
}

export interface ChannelProductSource {
  id: string;
  priority: number;
  isActive: boolean;
  soldQuantity: number;
  remark: string | null;
  displayScore: number;
  allocationRank: number;
  listing: {
    id: string;
    price: string;
    compareAtPrice: string | null;
    allocationMode: AllocationMode;
    allocatedQuantity: number | null;
    availableQuantity: number;
    fulfillmentType: FulfillmentType;
    pricingModel: PricingModel;
    status: ListingStatus;
    currency: string;
  };
  merchant: {
    id: string;
    name: string;
  };
  warehouse: {
    id: string;
    name: string;
    shortName: string;
  };
  createdAt: string;
}

export interface ChannelProduct {
  id: string;
  salesChannel: {
    id: string;
    code: string;
    name: string;
    currency: string;
  };
  productSku: {
    id: string;
    skuCode: string;
    productName: string;
    productId: string;
    styleNumber?: string;
    imageUrl?: string | null;
    sizeUnit?: string | null;
    sizeValue?: string | null;
    colorName?: string | null;
  };
  platformPrice: string;
  platformCompareAtPrice: string | null;
  stockQuantity: number;
  stockMode: StockMode;
  status: ChannelProductStatus;
  syncStatus: ChannelProductSyncStatus;
  externalId: string | null;
  externalUrl: string | null;
  lastSyncedAt: string | null;
  syncError: string | null;
  createdAt: string;
  updatedAt: string;
}

export interface ChannelProductDetail extends ChannelProduct {
  safetyBuffer: number;
  fixedStock: number | null;
  totalSoldQuantity: number;
  sourcesCount: number;
  activeSourcesCount: number;
}

export interface PaginatedResponse<T> {
  data: T[];
  total: number;
  page: number;
  limit: number;
}

export interface ChannelProductListParams {
  page?: number;
  limit?: number;
  salesChannelId?: string;
  status?: ChannelProductStatus;
  syncStatus?: ChannelProductSyncStatus;
  search?: string;
}

export const channelProductApi = {
  /**
   * 获取渠道商品列表
   */
  getChannelProducts: async (
    params: ChannelProductListParams = {}
  ): Promise<PaginatedResponse<ChannelProduct>> => {
    const searchParams = new URLSearchParams();
    if (params.page) searchParams.set('page', String(params.page));
    if (params.limit) searchParams.set('limit', String(params.limit));
    if (params.salesChannelId) searchParams.set('salesChannelId', params.salesChannelId);
    if (params.status) searchParams.set('status', params.status);
    if (params.syncStatus) searchParams.set('syncStatus', params.syncStatus);
    if (params.search) searchParams.set('search', params.search);

    const query = searchParams.toString();
    return apiFetch<PaginatedResponse<ChannelProduct>>(
      `/api/admin/channel-products${query ? `?${query}` : ''}`
    );
  },

  /**
   * 获取渠道商品详情
   */
  getChannelProduct: async (id: string): Promise<{ data: ChannelProductDetail }> => {
    return apiFetch<{ data: ChannelProductDetail }>(`/api/admin/channel-products/${id}`);
  },

  /**
   * 激活渠道商品
   */
  activateChannelProduct: async (
    id: string
  ): Promise<{ message: string; data: ChannelProduct }> => {
    return apiFetch(`/api/admin/channel-products/${id}/activate`, {
      method: 'POST',
    });
  },

  /**
   * 暂停渠道商品
   */
  pauseChannelProduct: async (
    id: string
  ): Promise<{ message: string; data: ChannelProduct }> => {
    return apiFetch(`/api/admin/channel-products/${id}/pause`, {
      method: 'POST',
    });
  },

  /**
   * 下架渠道商品（从外部渠道移除）
   */
  delistChannelProduct: async (
    id: string
  ): Promise<{ message: string; data: ChannelProduct }> => {
    return apiFetch(`/api/admin/channel-products/${id}/delist`, {
      method: 'POST',
    });
  },

  /**
   * 手动触发同步
   */
  triggerSync: async (
    id: string
  ): Promise<{ message: string; data: ChannelProduct; correctedSources: number }> => {
    return apiFetch(`/api/admin/channel-products/${id}/sync`, {
      method: 'POST',
    });
  },

  /**
   * 获取同步日志
   */
  getSyncLogs: async (
    id: string,
    params: {
      page?: number;
      limit?: number;
      status?: SyncLogStatus;
      operation?: SyncOperation;
    } = {}
  ): Promise<PaginatedResponse<ChannelProductSyncLog>> => {
    const searchParams = new URLSearchParams();
    if (params.page) searchParams.set('page', String(params.page));
    if (params.limit) searchParams.set('limit', String(params.limit));
    if (params.status) searchParams.set('status', params.status);
    if (params.operation) searchParams.set('operation', params.operation);

    const query = searchParams.toString();
    return apiFetch<PaginatedResponse<ChannelProductSyncLog>>(
      `/api/admin/channel-products/${id}/sync-logs${query ? `?${query}` : ''}`
    );
  },

  /**
   * 获取库存来源
   */
  getSources: async (id: string): Promise<{ data: ChannelProductSource[] }> => {
    return apiFetch<{ data: ChannelProductSource[] }>(
      `/api/admin/channel-products/${id}/sources`
    );
  },
};
