import { apiFetch } from './api-client';
import type {
  InboundOrderStatus,
  InboundOrderDetail,
  InboundException,
  InboundShipment,
  InboundOrderItem,
} from './inbound-api';

// ========== Types ==========

export interface AdminInboundOrder {
  id: string;
  orderNo: string;
  status: InboundOrderStatus;
  merchant: {
    id: string;
    name: string;
  };
  warehouse: {
    id: string;
    name: string;
  };
  totalSkuCount: number;
  totalQuantity: number;
  receivedQuantity: number;
  expectedArrivalDate: string | null;
  shippedAt: string | null;
  completedAt: string | null;
  createdAt: string;
}

export interface AdminInboundOrderDetail extends Omit<AdminInboundOrder, 'warehouse' | 'merchant'> {
  merchant: {
    id: string;
    name: string;
  };
  warehouse: {
    id: string;
    name: string;
  };
  merchantNotes: string | null;
  warehouseNotes: string | null;
  cancelReason: string | null;
  submittedAt: string | null;
  arrivedAt: string | null;
  cancelledAt: string | null;
  items: InboundOrderItem[];
  shipment: InboundShipment | null;
  exceptions: InboundException[];
}

export interface AdminInboundOrderListParams {
  page?: number;
  limit?: number;
  merchantId?: string;
  warehouseId?: string;
  status?: InboundOrderStatus;
  search?: string;
  trackingNumber?: string;
  startDate?: string;
  endDate?: string;
}

export interface AdminInboundOrderListResponse {
  items: AdminInboundOrder[];
  total: number;
  page: number;
  limit: number;
}

// ========== API ==========

export const adminInboundApi = {
  /**
   * Get admin inbound orders list with filters
   */
  getInboundOrders: async (
    params: AdminInboundOrderListParams = {}
  ): Promise<AdminInboundOrderListResponse> => {
    const queryParams = new URLSearchParams();
    if (params.page) queryParams.append('page', params.page.toString());
    if (params.limit) queryParams.append('limit', params.limit.toString());
    if (params.merchantId) queryParams.append('merchantId', params.merchantId);
    if (params.warehouseId) queryParams.append('warehouseId', params.warehouseId);
    if (params.status) queryParams.append('status', params.status);
    if (params.search) queryParams.append('search', params.search);
    if (params.trackingNumber) queryParams.append('trackingNumber', params.trackingNumber);
    if (params.startDate) queryParams.append('startDate', params.startDate);
    if (params.endDate) queryParams.append('endDate', params.endDate);

    const query = queryParams.toString();
    return await apiFetch<AdminInboundOrderListResponse>(
      `/api/admin/inbound/orders${query ? `?${query}` : ''}`
    );
  },

  /**
   * Get admin inbound order detail
   */
  getInboundOrder: async (id: string): Promise<AdminInboundOrderDetail> => {
    const response = await apiFetch<{ data: AdminInboundOrderDetail }>(
      `/api/admin/inbound/orders/${id}`
    );
    return response.data;
  },
};

// Re-export types from inbound-api for convenience
export type { InboundOrderStatus, InboundOrderDetail, InboundException, InboundShipment, InboundOrderItem };
