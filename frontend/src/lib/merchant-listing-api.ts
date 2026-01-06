import { apiFetch } from './api-client';

// Types
export type FulfillmentType = 'consignment' | 'self_fulfillment';
export type PricingModel = 'self_pricing' | 'platform_managed';
export type AllocationMode = 'shared' | 'dedicated';
export type ListingStatus = 'draft' | 'active' | 'paused' | 'sold_out';

export interface MerchantListing {
  id: string;
  merchantInventory: {
    id: string;
    warehouse: {
      id: string;
      name: string;
      code: string;
      category: 'platform' | 'merchant';
    };
    quantityAvailable: number;
    quantityAllocated: number;
  };
  productSku: {
    id: string;
    sizeValue: string;
    sizeUnit: string;
    barcode: string | null;
  };
  product: {
    id: string;
    name: string;
    styleNumber: string;
    color: string;
    imageUrl: string | null;
  };
  merchantSalesChannel: {
    id: string;
    approvedFulfillmentTypes: FulfillmentType[];
  };
  salesChannel: {
    id: string;
    name: string;
    code: string;
    logoUrl: string | null;
    currency: string;
  };
  fulfillmentType: FulfillmentType;
  pricingModel: PricingModel;
  allocationMode: AllocationMode;
  allocatedQuantity: number | null;
  soldQuantity: number;
  availableQuantity: number;
  price: string;
  compareAtPrice: string | null;
  status: ListingStatus;
  remark: string | null;
  priceRuleExpression: string | null;
  stockRuleExpression: string | null;
  createdAt: string;
  updatedAt: string;
}

export interface AvailableInventory {
  id: string;
  warehouse: {
    id: string;
    name: string;
    code: string;
    category: 'platform' | 'merchant';
  };
  productSku: {
    id: string;
    sizeValue: string;
    sizeUnit: string;
  };
  product: {
    id: string;
    name: string;
    styleNumber: string;
    color: string;
    imageUrl: string | null;
  };
  quantityAvailable: number;
  quantityAllocated: number;
  shareableQuantity: number;
}

export interface AvailableChannel {
  id: string;
  salesChannel: {
    id: string;
    name: string;
    code: string;
    logoUrl: string | null;
    currency: string;
  };
  approvedFulfillmentTypes: FulfillmentType[];
  status: string;
  hasPlatformWarehouse: boolean;
}

// Currency symbol mapping
export const CURRENCY_SYMBOLS: Record<string, string> = {
  'CNY': '¥',
  'USD': '$',
  'EUR': '€',
  'GBP': '£',
  'JPY': '¥',
  'HKD': 'HK$',
  'KRW': '₩',
  'SGD': 'S$',
};

export const getCurrencySymbol = (currency: string): string => {
  return CURRENCY_SYMBOLS[currency] || currency;
};

export interface ListingListParams {
  page?: number;
  limit?: number;
  channelId?: string;
  status?: ListingStatus;
  fulfillmentType?: FulfillmentType;
  pricingModel?: PricingModel;
  search?: string;
}

export interface CreateListingRequest {
  merchantInventoryId: string;
  merchantSalesChannelId: string;
  fulfillmentType: FulfillmentType;
  pricingModel: PricingModel;
  allocationMode: AllocationMode;
  allocatedQuantity?: number;
  price: string;
  compareAtPrice?: string;
  remark?: string;
  priceRuleExpression?: string;
  stockRuleExpression?: string;
}

export interface UpdateListingRequest {
  price: string;
  compareAtPrice?: string;
  allocationMode?: AllocationMode;
  allocatedQuantity?: number;
  remark?: string;
  priceRuleExpression?: string;
  stockRuleExpression?: string;
}

export interface BatchListingItemRequest {
  merchantInventoryId: string;
  fulfillmentType: FulfillmentType;
  pricingModel: PricingModel;
  allocationMode: AllocationMode;
  allocatedQuantity?: number;
  price: string;
  compareAtPrice?: string;
  remark?: string;
  priceRuleExpression?: string;
  stockRuleExpression?: string;
}

export interface BatchCreateListingRequest {
  merchantSalesChannelId: string;
  listings: BatchListingItemRequest[];
}

export interface BatchCreateListingResult {
  message: string;
  successCount: number;
  failedCount: number;
  results: {
    success: Array<{
      index: number;
      inventoryId: string;
      listing: MerchantListing;
    }>;
    failed: Array<{
      index: number;
      inventoryId: string;
      error: string;
    }>;
  };
}

// Rule calculation types
export interface RuleDetail {
  id: string;
  name: string;
  expression: string;
  conditionExpression?: string;
  inputValue: number;
  outputValue: number;
}

export interface PricingDefaults {
  calculatedPrice: string;
  baseCost: string;
  hasRules: boolean;
  rules: RuleDetail[];
}

export interface AllocationDefaults {
  calculatedQuantity: number;
  availableQuantity: number;
  hasRules: boolean;
  rules: RuleDetail[];
}

export interface InventoryDefaults {
  inventoryId: string;
  pricing: PricingDefaults;
  allocation: AllocationDefaults;
}

export interface CalculateDefaultsRequest {
  merchantSalesChannelId: string;
  inventoryIds: string[];
}

// Operation Log Types
export type OperationType =
  | 'create'
  | 'update_price'
  | 'update_compare_price'
  | 'update_allocation'
  | 'update_remark'
  | 'activate'
  | 'pause'
  | 'delete';

export interface ListingOperationLog {
  id: string;
  listingId: string;
  operatorEmail: string;
  operation: OperationType;
  changes: {
    before?: Record<string, unknown>;
    after?: Record<string, unknown>;
  } | null;
  createdAt: string;
}

export interface OperationLogListParams {
  page?: number;
  limit?: number;
  operation?: OperationType;
  listingId?: string;
  startDate?: string;
  endDate?: string;
}

export interface PaginatedResponse<T> {
  data: T[];
  total: number;
  page: number;
  limit: number;
}

export const merchantListingApi = {
  /**
   * 获取上架列表
   */
  getListings: async (
    params: ListingListParams = {}
  ): Promise<PaginatedResponse<MerchantListing>> => {
    const searchParams = new URLSearchParams();
    if (params.page) searchParams.set('page', String(params.page));
    if (params.limit) searchParams.set('limit', String(params.limit));
    if (params.channelId) searchParams.set('channelId', params.channelId);
    if (params.status) searchParams.set('status', params.status);
    if (params.fulfillmentType) searchParams.set('fulfillmentType', params.fulfillmentType);
    if (params.pricingModel) searchParams.set('pricingModel', params.pricingModel);
    if (params.search) searchParams.set('search', params.search);

    const query = searchParams.toString();
    return apiFetch(`/api/merchant/listings${query ? `?${query}` : ''}`);
  },

  /**
   * 获取单个上架详情
   */
  getListing: async (id: string): Promise<{ data: MerchantListing }> => {
    return apiFetch(`/api/merchant/listings/${id}`);
  },

  /**
   * 创建上架
   */
  createListing: async (data: CreateListingRequest): Promise<{ data: MerchantListing }> => {
    return apiFetch('/api/merchant/listings', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },

  /**
   * 批量创建上架
   */
  batchCreateListings: async (data: BatchCreateListingRequest): Promise<BatchCreateListingResult> => {
    return apiFetch('/api/merchant/listings/batch', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },

  /**
   * 更新上架
   */
  updateListing: async (
    id: string,
    data: UpdateListingRequest
  ): Promise<{ data: MerchantListing }> => {
    return apiFetch(`/api/merchant/listings/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  },

  /**
   * 激活上架
   */
  activateListing: async (id: string): Promise<{ data: MerchantListing }> => {
    return apiFetch(`/api/merchant/listings/${id}/activate`, {
      method: 'POST',
    });
  },

  /**
   * 暂停上架
   */
  pauseListing: async (id: string): Promise<{ data: MerchantListing }> => {
    return apiFetch(`/api/merchant/listings/${id}/pause`, {
      method: 'POST',
    });
  },

  /**
   * 删除上架（仅草稿）
   */
  deleteListing: async (id: string): Promise<void> => {
    return apiFetch(`/api/merchant/listings/${id}`, {
      method: 'DELETE',
    });
  },

  /**
   * 获取可上架的库存列表
   */
  getAvailableInventory: async (params: {
    channelId: string;
    page?: number;
    limit?: number;
    search?: string;
  }): Promise<PaginatedResponse<AvailableInventory>> => {
    const searchParams = new URLSearchParams();
    searchParams.set('channelId', params.channelId);
    if (params.page) searchParams.set('page', String(params.page));
    if (params.limit) searchParams.set('limit', String(params.limit));
    if (params.search) searchParams.set('search', params.search);

    return apiFetch(`/api/merchant/listings/available-inventory?${searchParams.toString()}`);
  },

  /**
   * 获取可用的销售渠道列表
   */
  getAvailableChannels: async (): Promise<{ data: AvailableChannel[] }> => {
    return apiFetch('/api/merchant/listings/available-channels');
  },

  /**
   * 根据渠道规则计算上架默认值
   */
  calculateDefaults: async (
    data: CalculateDefaultsRequest
  ): Promise<{ data: InventoryDefaults[] }> => {
    return apiFetch('/api/merchant/listings/calculate-defaults', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },

  /**
   * 获取单个上架的操作日志
   */
  getListingLogs: async (
    listingId: string,
    limit?: number
  ): Promise<{ data: ListingOperationLog[] }> => {
    const searchParams = new URLSearchParams();
    if (limit) searchParams.set('limit', String(limit));
    const query = searchParams.toString();
    return apiFetch(`/api/merchant/listings/${listingId}/logs${query ? `?${query}` : ''}`);
  },

  /**
   * 获取全部操作日志（分页）
   */
  getOperationLogs: async (
    params: OperationLogListParams = {}
  ): Promise<PaginatedResponse<ListingOperationLog>> => {
    const searchParams = new URLSearchParams();
    if (params.page) searchParams.set('page', String(params.page));
    if (params.limit) searchParams.set('limit', String(params.limit));
    if (params.operation) searchParams.set('operation', params.operation);
    if (params.listingId) searchParams.set('listingId', params.listingId);
    if (params.startDate) searchParams.set('startDate', params.startDate);
    if (params.endDate) searchParams.set('endDate', params.endDate);

    const query = searchParams.toString();
    return apiFetch(`/api/merchant/listings-logs${query ? `?${query}` : ''}`);
  },
};
