import { apiFetch } from './api-client';

// ========== Types ==========

export type FulfillmentStatus =
  | 'pending'
  | 'processing'
  | 'shipped'
  | 'delivered'
  | 'completed'
  | 'cancelled'
  | 'rejected'
  | 'expired';

export type FulfillmentType = 'platform_warehouse' | 'merchant_warehouse';

export type AllocationSource = 'auto' | 'manual';

export interface MerchantRef {
  id: string;
  name: string;
}

export interface WarehouseRef {
  id: string;
  name: string;
}

export interface OrderRef {
  id: string;
  orderNo: string;
}

export interface Fulfillment {
  id: string;
  fulfillmentNo: string;
  fulfillmentType: FulfillmentType;
  fulfillmentTypeLabel: string;
  status: FulfillmentStatus;
  statusLabel: string;
  order: OrderRef;
  merchant: MerchantRef | null;
  warehouse: WarehouseRef;
  itemCount: number;
  totalQuantity: number;
  shippingCarrier: string | null;
  trackingNumber: string | null;
  deadlineAt: string | null;
  isOverdue: boolean;
  createdAt: string;
  shippedAt: string | null;
}

export interface FulfillmentItemOrderItem {
  id: string;
  productName: string | null;
  productImage: string | null;
  skuCode: string | null;
  colorCode: string | null;
  sizeValue: string | null;
  quantity: number;
  unitPrice: string;
}

export interface FulfillmentItem {
  id: string;
  quantity: number;
  listPrice: string | null;
  settlementPrice: string | null;
  commissionRate: string | null;
  commissionAmount: string | null;
  settlementTotal: string | null;
  orderItem: FulfillmentItemOrderItem;
  merchant: MerchantRef | null;
  warehouse: WarehouseRef | null;
  createdAt: string;
}

export interface OutboundOrderRef {
  id: string;
  orderNo: string;
  status: string;
  pickingStartedAt?: string;
  pickingCompletedAt?: string;
  packingStartedAt?: string;
  packingCompletedAt?: string;
  shippedAt?: string;
  cancelledAt?: string;
}

export interface OrderDetailRef extends OrderRef {
  externalOrderNo: string | null;
  status: string;
  totalAmount: string;
  currency: string;
  receiverName: string;
  receiverCity: string | null;
  receiverFullAddress: string;
}

export interface FulfillmentDetail extends Fulfillment {
  trackingUrl: string | null;
  allocationSource: AllocationSource | null;
  allocationSourceLabel: string | null;
  allocationAttempt: number;
  notifiedAt: string | null;
  deliveredAt: string | null;
  completedAt: string | null;
  cancelledAt: string | null;
  cancelReason: string | null;
  rejectedAt: string | null;
  rejectionReason: string | null;
  remark: string | null;
  updatedAt: string;
  order: OrderDetailRef;
  items: FulfillmentItem[];
  outboundOrder: OutboundOrderRef | null;
}

export interface FulfillmentListParams {
  page?: number;
  limit?: number;
  status?: FulfillmentStatus;
  fulfillmentType?: FulfillmentType;
  merchantId?: string;
  warehouseId?: string;
  orderId?: string;
  search?: string;
  startDate?: string;
  endDate?: string;
}

export interface FulfillmentListResponse {
  items: Fulfillment[];
  total: number;
  page: number;
  limit: number;
}

// ========== API Methods ==========

export const fulfillmentApi = {
  /**
   * Get fulfillments list with pagination
   */
  getList: async (params: FulfillmentListParams = {}): Promise<FulfillmentListResponse> => {
    const queryParams = new URLSearchParams();
    if (params.page) queryParams.append('page', params.page.toString());
    if (params.limit) queryParams.append('limit', params.limit.toString());
    if (params.status) queryParams.append('status', params.status);
    if (params.fulfillmentType) queryParams.append('fulfillmentType', params.fulfillmentType);
    if (params.merchantId) queryParams.append('merchantId', params.merchantId);
    if (params.warehouseId) queryParams.append('warehouseId', params.warehouseId);
    if (params.orderId) queryParams.append('orderId', params.orderId);
    if (params.search) queryParams.append('search', params.search);
    if (params.startDate) queryParams.append('startDate', params.startDate);
    if (params.endDate) queryParams.append('endDate', params.endDate);

    const query = queryParams.toString();
    return await apiFetch<FulfillmentListResponse>(
      `/api/admin/fulfillments${query ? `?${query}` : ''}`
    );
  },

  /**
   * Get fulfillment detail
   */
  getDetail: async (id: string): Promise<{ data: FulfillmentDetail }> => {
    return await apiFetch<{ data: FulfillmentDetail }>(`/api/admin/fulfillments/${id}`);
  },
};
