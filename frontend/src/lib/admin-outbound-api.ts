import { apiFetch } from './api-client';
import type {
  OutboundOrderStatus,
  OutboundOrderType,
  OutboundOrderItem,
  StockType,
} from './outbound-api';

// ========== Types ==========

export interface AdminOutboundOrder {
  id: string;
  outboundNo: string;
  outboundType: OutboundOrderType;
  status: OutboundOrderStatus;
  syncStatus: string;
  merchant: {
    id: string;
    name: string;
  };
  warehouse: {
    id: string;
    name: string;
  };
  receiverName: string;
  totalQuantity: number;
  shippingCarrier: string | null;
  trackingNumber: string | null;
  shippedAt: string | null;
  createdAt: string;
}

export interface AdminOutboundOrderItem {
  id: string;
  productSku: {
    id: string | null;
    skuName: string | null;
    colorName: string | null;
  };
  styleNumber: string | null;
  productName: string | null;
  productImage: string | null;
  stockType: StockType;
  quantity: number;
}

export interface AdminOutboundOrderDetail extends AdminOutboundOrder {
  externalId: string | null;
  receiverPhone: string;
  receiverAddress: string;
  receiverPostalCode: string | null;
  remark: string | null;
  cancelReason: string | null;
  pickingStartedAt: string | null;
  pickingCompletedAt: string | null;
  packingStartedAt: string | null;
  packingCompletedAt: string | null;
  cancelledAt: string | null;
  fulfillment: {
    id: string;
    fulfillmentNo: string;
  } | null;
  items: AdminOutboundOrderItem[];
}

export interface AdminOutboundOrderListParams {
  page?: number;
  limit?: number;
  merchantId?: string;
  warehouseId?: string;
  status?: OutboundOrderStatus;
  outboundType?: OutboundOrderType;
  search?: string;
  trackingNumber?: string;
  startDate?: string;
  endDate?: string;
}

export interface AdminOutboundOrderListResponse {
  items: AdminOutboundOrder[];
  total: number;
  page: number;
  limit: number;
}

// ========== API ==========

export const adminOutboundApi = {
  /**
   * Get admin outbound orders list with filters
   */
  getOutboundOrders: async (
    params: AdminOutboundOrderListParams = {}
  ): Promise<AdminOutboundOrderListResponse> => {
    const queryParams = new URLSearchParams();
    if (params.page) queryParams.append('page', params.page.toString());
    if (params.limit) queryParams.append('limit', params.limit.toString());
    if (params.merchantId) queryParams.append('merchantId', params.merchantId);
    if (params.warehouseId) queryParams.append('warehouseId', params.warehouseId);
    if (params.status) queryParams.append('status', params.status);
    if (params.outboundType) queryParams.append('outboundType', params.outboundType);
    if (params.search) queryParams.append('search', params.search);
    if (params.trackingNumber) queryParams.append('trackingNumber', params.trackingNumber);
    if (params.startDate) queryParams.append('startDate', params.startDate);
    if (params.endDate) queryParams.append('endDate', params.endDate);

    const query = queryParams.toString();
    return await apiFetch<AdminOutboundOrderListResponse>(
      `/api/admin/outbound/orders${query ? `?${query}` : ''}`
    );
  },

  /**
   * Get admin outbound order detail
   */
  getOutboundOrder: async (id: string): Promise<AdminOutboundOrderDetail> => {
    const response = await apiFetch<{ data: AdminOutboundOrderDetail }>(
      `/api/admin/outbound/orders/${id}`
    );
    return response.data;
  },
};

// Re-export types from outbound-api for convenience
export type { OutboundOrderStatus, OutboundOrderType, OutboundOrderItem, StockType };
