import { apiFetch } from './api-client';

// ========== Types ==========

export type InboundOrderStatus =
  | 'draft'
  | 'pending'
  | 'shipped'
  | 'arrived'
  | 'receiving'
  | 'completed'
  | 'partial_completed'
  | 'cancelled';

export type InboundShipmentStatus =
  | 'pending'
  | 'picked'
  | 'in_transit'
  | 'delivered'
  | 'exception';

export type InboundExceptionType =
  | 'quantity_short'
  | 'quantity_over'
  | 'damaged'
  | 'wrong_item'
  | 'quality_issue'
  | 'packaging'
  | 'expired'
  | 'other';

export type InboundExceptionStatus = 'pending' | 'processing' | 'resolved' | 'closed';

export interface InboundOrder {
  id: string;
  orderNo: string;
  status: InboundOrderStatus;
  warehouse: {
    id: string;
    name: string;
  };
  totalSkuCount: number;
  totalQuantity: number;
  receivedQuantity: number;
  expectedArrivalDate: string | null;
  submittedAt: string | null;
  shippedAt: string | null;
  completedAt: string | null;
  createdAt: string;
}

export interface InboundOrderDetail extends Omit<InboundOrder, 'warehouse'> {
  warehouse: {
    id: string;
    name: string;
  };
  merchantNotes: string | null;
  warehouseNotes: string | null;
  cancelReason: string | null;
  items: InboundOrderItem[];
  shipment: InboundShipment | null;
  exceptions: InboundException[];
}

export interface InboundOrderItem {
  id: string;
  productSku: {
    id: string | null;
    skuName: string | null;
    colorName: string | null;
  };
  styleNumber: string | null;
  productName: string | null;
  productImage: string | null;
  expectedQuantity: number;
  receivedQuantity: number;
  damagedQuantity: number;
  unitCost: string | null;
  status: string;
  warehouseRemark: string | null;
  receivedAt: string | null;
}

export interface InboundShipment {
  id: string;
  carrierCode: string;
  carrierName: string | null;
  trackingNumber: string;
  status: InboundShipmentStatus;
  senderName: string;
  senderPhone: string;
  senderAddress: string;
  boxCount: number;
  totalWeight: string | null;
  shippedAt: string;
  estimatedArrivalDate: string | null;
  deliveredAt: string | null;
}

export interface InboundException {
  id: string;
  exceptionNo: string;
  type: InboundExceptionType;
  typeLabel: string;
  status: InboundExceptionStatus;
  items: ExceptionItem[];
  totalQuantity: number;
  description: string;
  evidenceImages: string[] | null;
  resolution: string | null;
  resolutionNotes: string | null;
  resolvedAt: string | null;
  createdAt: string;
}

export interface ExceptionItem {
  id: string;
  skuName: string | null;
  colorName: string | null;
  productName: string | null;
  productImage: string | null;
  quantity: number;
}

// ========== Request/Response Types ==========

export interface InboundOrderListParams {
  status?: InboundOrderStatus;
  limit?: number;
  page?: number;
  search?: string;
  trackingNumber?: string;
  warehouseId?: string;
  startDate?: string;
  endDate?: string;
}

export interface CreateInboundOrderParams {
  warehouseId: string;
  expectedArrivalDate?: string;
  merchantNotes?: string;
}

export interface UpdateInboundOrderParams {
  warehouseId?: string;
  expectedArrivalDate?: string;
  merchantNotes?: string;
}

export interface AddOrderItemParams {
  productSkuId: string;
  expectedQuantity: number;
  unitCost?: string;
}

export interface UpdateOrderItemParams {
  expectedQuantity: number;
  unitCost?: string;
}

export interface ShipOrderParams {
  carrierCode: string;
  carrierName?: string;
  trackingNumber: string;
  senderName: string;
  senderPhone: string;
  senderAddress: string;
  boxCount: number;
  totalWeight?: string;
  estimatedArrivalDate?: string;
}

export interface CancelOrderParams {
  reason: string;
}

export interface PaginatedResponse<T> {
  data: T[];
  total: number;
  page: number;
  limit: number;
}

export interface AvailableWarehouse {
  id: string;
  code: string;
  name: string;
  shortName: string | null;
  type: string;
  fullAddress: string;
  city: string | null;
  province: string | null;
}

export interface InboundProductSku {
  id: string;
  skuName: string | null;
  sizeUnit: string | null;
  sizeValue: string | null;
  price: string;
  isActive: boolean;
}

export interface InboundProduct {
  id: string;
  name: string;
  styleNumber: string;
  color: string | null;
  primaryImageUrl: string | null;
  brandName: string | null;
  skus: InboundProductSku[];
}

export interface ProductDiscoveryParams {
  page?: number;
  limit?: number;
  search?: string;
  brandId?: string;
  categoryId?: string;
}

export interface ProductDiscoveryResponse {
  data: InboundProduct[];
  meta: {
    total: number;
    page: number;
    limit: number;
    pages: number;
  };
}

// ========== API Methods ==========

export const inboundApi = {
  // ========== Warehouses ==========

  /**
   * Get available warehouses for inbound orders
   */
  getAvailableWarehouses: async (): Promise<AvailableWarehouse[]> => {
    const response = await apiFetch<{ data: AvailableWarehouse[] }>('/api/inbound/warehouses');
    return response.data || [];
  },

  // ========== Products ==========

  /**
   * Search products for adding to inbound order
   */
  searchProducts: async (search: string, limit = 10): Promise<InboundProduct[]> => {
    const queryParams = new URLSearchParams();
    if (search) queryParams.append('search', search);
    queryParams.append('limit', limit.toString());

    const query = queryParams.toString();
    const response = await apiFetch<{ data: InboundProduct[]; total: number }>(
      `/api/inbound/products${query ? `?${query}` : ''}`
    );
    return response.data || [];
  },

  /**
   * Search products for discovery page with pagination
   */
  searchProductsForDiscovery: async (
    params: ProductDiscoveryParams = {}
  ): Promise<ProductDiscoveryResponse> => {
    const queryParams = new URLSearchParams();
    if (params.page) queryParams.append('page', params.page.toString());
    if (params.limit) queryParams.append('limit', params.limit.toString());
    if (params.search) queryParams.append('search', params.search);
    if (params.brandId) queryParams.append('brandId', params.brandId);
    if (params.categoryId) queryParams.append('categoryId', params.categoryId);

    const query = queryParams.toString();
    return await apiFetch<ProductDiscoveryResponse>(
      `/api/inbound/products${query ? `?${query}` : ''}`
    );
  },

  // ========== Inbound Orders ==========

  /**
   * Get inbound orders list
   */
  getInboundOrders: async (
    params: InboundOrderListParams = {}
  ): Promise<PaginatedResponse<InboundOrder>> => {
    const queryParams = new URLSearchParams();
    if (params.status) queryParams.append('status', params.status);
    if (params.limit) queryParams.append('limit', params.limit.toString());
    if (params.page) queryParams.append('page', params.page.toString());
    if (params.search) queryParams.append('search', params.search);
    if (params.trackingNumber) queryParams.append('trackingNumber', params.trackingNumber);
    if (params.warehouseId) queryParams.append('warehouseId', params.warehouseId);
    if (params.startDate) queryParams.append('startDate', params.startDate);
    if (params.endDate) queryParams.append('endDate', params.endDate);

    const query = queryParams.toString();
    const response = await apiFetch<{ data: InboundOrder[] }>(
      `/api/inbound/orders${query ? `?${query}` : ''}`
    );
    const data = response.data || [];
    return {
      data,
      total: data.length,
      page: params.page || 1,
      limit: params.limit || 20,
    };
  },

  /**
   * Get inbound order detail
   */
  getInboundOrder: async (id: string): Promise<InboundOrderDetail> => {
    const response = await apiFetch<{ data: InboundOrderDetail }>(`/api/inbound/orders/${id}`);
    return response.data;
  },

  /**
   * Create inbound order
   */
  createInboundOrder: async (
    params: CreateInboundOrderParams
  ): Promise<{ message: string; data: InboundOrderDetail }> => {
    return await apiFetch<{ message: string; data: InboundOrderDetail }>('/api/inbound/orders', {
      method: 'POST',
      body: JSON.stringify(params),
    });
  },

  /**
   * Update inbound order (draft only)
   */
  updateInboundOrder: async (
    id: string,
    params: UpdateInboundOrderParams
  ): Promise<{ message: string; data: InboundOrderDetail }> => {
    return await apiFetch<{ message: string; data: InboundOrderDetail }>(
      `/api/inbound/orders/${id}`,
      {
        method: 'PUT',
        body: JSON.stringify(params),
      }
    );
  },

  /**
   * Delete inbound order (draft only)
   */
  deleteInboundOrder: async (id: string): Promise<{ message: string }> => {
    return await apiFetch<{ message: string }>(`/api/inbound/orders/${id}`, {
      method: 'DELETE',
    });
  },

  /**
   * Submit inbound order (draft → pending)
   */
  submitInboundOrder: async (id: string): Promise<{ message: string; data: InboundOrder }> => {
    return await apiFetch<{ message: string; data: InboundOrder }>(
      `/api/inbound/orders/${id}/submit`,
      {
        method: 'POST',
      }
    );
  },

  /**
   * Cancel inbound order
   */
  cancelInboundOrder: async (
    id: string,
    params: CancelOrderParams
  ): Promise<{ message: string; data: InboundOrder }> => {
    return await apiFetch<{ message: string; data: InboundOrder }>(
      `/api/inbound/orders/${id}/cancel`,
      {
        method: 'POST',
        body: JSON.stringify(params),
      }
    );
  },

  /**
   * Ship inbound order (pending → shipped)
   */
  shipInboundOrder: async (
    id: string,
    params: ShipOrderParams
  ): Promise<{ message: string; data: InboundOrderDetail }> => {
    return await apiFetch<{ message: string; data: InboundOrderDetail }>(
      `/api/inbound/orders/${id}/ship`,
      {
        method: 'POST',
        body: JSON.stringify(params),
      }
    );
  },

  // ========== Inbound Order Items ==========

  /**
   * Add item to inbound order
   */
  addInboundOrderItem: async (
    orderId: string,
    params: AddOrderItemParams
  ): Promise<{ message: string; data: InboundOrderItem }> => {
    return await apiFetch<{ message: string; data: InboundOrderItem }>(
      `/api/inbound/orders/${orderId}/items`,
      {
        method: 'POST',
        body: JSON.stringify(params),
      }
    );
  },

  /**
   * Update inbound order item
   */
  updateInboundOrderItem: async (
    itemId: string,
    params: UpdateOrderItemParams
  ): Promise<{ message: string; data: InboundOrderItem }> => {
    return await apiFetch<{ message: string; data: InboundOrderItem }>(
      `/api/inbound/orders/items/${itemId}`,
      {
        method: 'PUT',
        body: JSON.stringify(params),
      }
    );
  },

  /**
   * Delete inbound order item
   */
  deleteInboundOrderItem: async (itemId: string): Promise<{ message: string }> => {
    return await apiFetch<{ message: string }>(`/api/inbound/orders/items/${itemId}`, {
      method: 'DELETE',
    });
  },

  // ========== Inbound Shipments ==========

  /**
   * Get inbound shipments list
   */
  getInboundShipments: async (params: any = {}): Promise<PaginatedResponse<InboundShipment>> => {
    const queryParams = new URLSearchParams();
    if (params.limit) queryParams.append('limit', params.limit.toString());
    if (params.page) queryParams.append('page', params.page.toString());

    const query = queryParams.toString();
    const response = await apiFetch<{ data: InboundShipment[] }>(
      `/api/inbound/shipments${query ? `?${query}` : ''}`
    );
    const data = response.data || [];
    return {
      data,
      total: data.length,
      page: params.page || 1,
      limit: params.limit || 20,
    };
  },

  /**
   * Get inbound shipment detail
   */
  getInboundShipment: async (id: string): Promise<InboundShipment> => {
    const response = await apiFetch<{ data: InboundShipment }>(`/api/inbound/shipments/${id}`);
    return response.data;
  },

  // ========== Inbound Exceptions ==========

  /**
   * Get inbound exceptions list
   */
  getInboundExceptions: async (params: any = {}): Promise<PaginatedResponse<InboundException>> => {
    const queryParams = new URLSearchParams();
    if (params.limit) queryParams.append('limit', params.limit.toString());
    if (params.page) queryParams.append('page', params.page.toString());

    const query = queryParams.toString();
    const response = await apiFetch<{ data: InboundException[] }>(
      `/api/inbound/exceptions${query ? `?${query}` : ''}`
    );
    const data = response.data || [];
    return {
      data,
      total: data.length,
      page: params.page || 1,
      limit: params.limit || 20,
    };
  },

  /**
   * Get inbound exception detail
   */
  getInboundException: async (id: string): Promise<InboundException> => {
    const response = await apiFetch<{ data: InboundException }>(`/api/inbound/exceptions/${id}`);
    return response.data;
  },

  /**
   * Resolve inbound exception
   */
  resolveInboundException: async (
    id: string,
    params: { resolution: string; resolutionNotes?: string; claimAmount?: string }
  ): Promise<{ message: string; data: InboundException }> => {
    return await apiFetch<{ message: string; data: InboundException }>(
      `/api/inbound/exceptions/${id}/resolve`,
      {
        method: 'POST',
        body: JSON.stringify(params),
      }
    );
  },
};

// ========== Merchant Inventory Types ==========

export type StockStatus =
  | 'in_transit'
  | 'available'
  | 'reserved'
  | 'damaged'
  | 'has_stock'
  | 'low_stock';

export interface MerchantInventoryItem {
  id: string;
  warehouse: {
    id: string;
    code: string;
    name: string;
  };
  product: {
    id: string;
    name: string;
    styleNumber: string | null;
    color: string | null;
    primaryImage: string | null;
  } | null;
  sku: {
    id: string;
    skuName: string;
    sizeUnit: string | null;
    sizeValue: string | null;
  };
  quantityInTransit: number;
  quantityAvailable: number;
  quantityReserved: number;
  quantityDamaged: number;
  quantityAllocated: number;
  averageCost: string | null;
  safetyStock: number | null;
  isBelowSafetyStock: boolean;
  lastInboundAt: string | null;
  lastOutboundAt: string | null;
  updatedAt: string;
}

export interface MerchantInventorySummary {
  totalInTransit: number;
  totalAvailable: number;
  totalReserved: number;
  totalDamaged: number;
  totalSkuCount: number;
  warehouseCount: number;
}

export interface MerchantInventoryListParams {
  page?: number;
  limit?: number;
  search?: string;
  warehouseId?: string;
  stockStatus?: StockStatus;
  hasStock?: boolean;
}

export interface MerchantInventoryListResponse {
  data: MerchantInventoryItem[];
  meta: {
    total: number;
    page: number;
    limit: number;
    pages: number;
  };
}

export interface InventoryWarehouse {
  id: string;
  code: string;
  name: string;
}

// ========== Merchant Inventory API ==========

export const merchantInventoryApi = {
  /**
   * Get merchant inventory list
   */
  getInventoryList: async (
    params: MerchantInventoryListParams = {}
  ): Promise<MerchantInventoryListResponse> => {
    const queryParams = new URLSearchParams();
    if (params.page) queryParams.append('page', params.page.toString());
    if (params.limit) queryParams.append('limit', params.limit.toString());
    if (params.search) queryParams.append('search', params.search);
    if (params.warehouseId) queryParams.append('warehouseId', params.warehouseId);
    if (params.stockStatus) queryParams.append('stockStatus', params.stockStatus);
    if (params.hasStock) queryParams.append('hasStock', 'true');

    const query = queryParams.toString();
    return await apiFetch<MerchantInventoryListResponse>(
      `/api/merchant/inventory${query ? `?${query}` : ''}`
    );
  },

  /**
   * Get merchant inventory summary
   */
  getInventorySummary: async (): Promise<{ data: MerchantInventorySummary }> => {
    return await apiFetch<{ data: MerchantInventorySummary }>('/api/merchant/inventory/summary');
  },

  /**
   * Get available warehouses for filtering
   */
  getWarehouses: async (): Promise<{ data: InventoryWarehouse[] }> => {
    return await apiFetch<{ data: InventoryWarehouse[] }>('/api/merchant/inventory/warehouses');
  },
};