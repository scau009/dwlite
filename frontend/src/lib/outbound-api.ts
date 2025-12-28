import { apiFetch } from './api-client';

// ========== Types ==========

export type OutboundOrderStatus =
  | 'draft'
  | 'pending'
  | 'picking'
  | 'packing'
  | 'ready'
  | 'shipped'
  | 'cancelled';

export type OutboundOrderType =
  | 'sales'
  | 'return_to_merchant'
  | 'transfer'
  | 'scrap';

export interface OutboundOrder {
  id: string;
  outboundNo: string;
  outboundType: OutboundOrderType;
  outboundTypeLabel: string;
  status: OutboundOrderStatus;
  statusLabel: string;
  warehouse: {
    id: string;
    name: string;
    shortName: string | null;
  };
  receiverName: string;
  receiverPhone: string;
  shippingCarrier: string | null;
  trackingNumber: string | null;
  totalQuantity: number;
  shippedAt: string | null;
  createdAt: string;
}

export type StockType = 'normal' | 'damaged';

export interface OutboundOrderItem {
  id: string;
  skuCode: string | null;
  colorCode: string | null;
  sizeValue: string | null;
  productName: string | null;
  productImage: string | null;
  stockType: StockType;
  quantity: number;
}

export interface OutboundOrderDetail extends OutboundOrder {
  receiverAddress: string;
  receiverPostalCode: string | null;
  remark: string | null;
  cancelReason: string | null;
  pickingStartedAt: string | null;
  pickingCompletedAt: string | null;
  packingStartedAt: string | null;
  packingCompletedAt: string | null;
  cancelledAt: string | null;
  updatedAt: string;
  relatedOrder: {
    id: string;
    orderNo: string;
  } | null;
  items: OutboundOrderItem[];
}

// ========== Request/Response Types ==========

export interface OutboundOrderListParams {
  status?: OutboundOrderStatus;
  outboundType?: OutboundOrderType;
  warehouseId?: string;
  outboundNo?: string;
  trackingNumber?: string;
  page?: number;
  limit?: number;
}

export interface CreateOutboundOrderParams {
  warehouseId: string;
  receiverName: string;
  receiverPhone: string;
  receiverAddress: string;
  receiverPostalCode?: string;
  remark?: string;
}

export interface AddOutboundItemParams {
  inventoryId: string;
  quantity: number;
  stockType: StockType;
}

export interface PaginatedResponse<T> {
  data: T[];
  meta: {
    total: number;
    page: number;
    limit: number;
    totalPages: number;
  };
}

export interface SelectOption {
  value: string;
  label: string;
}

// ========== API Methods ==========

export const outboundApi = {
  // ========== Options ==========

  /**
   * Get outbound type options
   */
  getTypeOptions: async (): Promise<SelectOption[]> => {
    const response = await apiFetch<{ data: SelectOption[] }>('/api/outbound/type-options');
    return response.data || [];
  },

  /**
   * Get outbound status options
   */
  getStatusOptions: async (): Promise<SelectOption[]> => {
    const response = await apiFetch<{ data: SelectOption[] }>('/api/outbound/status-options');
    return response.data || [];
  },

  // ========== Orders ==========

  /**
   * Get outbound orders list
   */
  getOutboundOrders: async (
    params: OutboundOrderListParams = {}
  ): Promise<PaginatedResponse<OutboundOrder>> => {
    const searchParams = new URLSearchParams();

    if (params.status) searchParams.set('status', params.status);
    if (params.outboundType) searchParams.set('outboundType', params.outboundType);
    if (params.warehouseId) searchParams.set('warehouseId', params.warehouseId);
    if (params.outboundNo) searchParams.set('outboundNo', params.outboundNo);
    if (params.trackingNumber) searchParams.set('trackingNumber', params.trackingNumber);
    if (params.page) searchParams.set('page', String(params.page));
    if (params.limit) searchParams.set('limit', String(params.limit));

    const queryString = searchParams.toString();
    const url = queryString ? `/api/outbound/orders?${queryString}` : '/api/outbound/orders';

    const response = await apiFetch<PaginatedResponse<OutboundOrder>>(url);
    return response;
  },

  /**
   * Get outbound order detail
   */
  getOutboundOrder: async (id: string): Promise<OutboundOrderDetail> => {
    const response = await apiFetch<{ data: OutboundOrderDetail }>(`/api/outbound/orders/${id}`);
    return response.data;
  },

  /**
   * Create outbound order (draft status)
   */
  createOutboundOrder: async (
    params: CreateOutboundOrderParams
  ): Promise<{ message: string; data: OutboundOrder }> => {
    const response = await apiFetch<{ message: string; data: OutboundOrder }>(
      '/api/outbound/orders',
      {
        method: 'POST',
        body: JSON.stringify(params),
      }
    );
    return response;
  },

  /**
   * Delete outbound order (draft only)
   */
  deleteOutboundOrder: async (id: string): Promise<{ message: string }> => {
    const response = await apiFetch<{ message: string }>(
      `/api/outbound/orders/${id}`,
      {
        method: 'DELETE',
      }
    );
    return response;
  },

  /**
   * Submit outbound order (draft → pending)
   */
  submitOutboundOrder: async (
    id: string
  ): Promise<{ message: string; data: OutboundOrder }> => {
    const response = await apiFetch<{ message: string; data: OutboundOrder }>(
      `/api/outbound/orders/${id}/submit`,
      {
        method: 'POST',
      }
    );
    return response;
  },

  /**
   * Add item to outbound order (draft only)
   */
  addOutboundItem: async (
    orderId: string,
    params: AddOutboundItemParams
  ): Promise<{ message: string; data: OutboundOrderDetail }> => {
    const response = await apiFetch<{ message: string; data: OutboundOrderDetail }>(
      `/api/outbound/orders/${orderId}/items`,
      {
        method: 'POST',
        body: JSON.stringify(params),
      }
    );
    return response;
  },

  /**
   * Remove item from outbound order (draft only)
   */
  removeOutboundItem: async (
    orderId: string,
    itemId: string
  ): Promise<{ message: string; data: OutboundOrderDetail }> => {
    const response = await apiFetch<{ message: string; data: OutboundOrderDetail }>(
      `/api/outbound/orders/${orderId}/items/${itemId}`,
      {
        method: 'DELETE',
      }
    );
    return response;
  },
};
