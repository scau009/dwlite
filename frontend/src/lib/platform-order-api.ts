import { apiFetch } from './api-client';

// ========== Types ==========

export type OrderStatus =
  | 'pending'
  | 'allocating'
  | 'allocated'
  | 'allocation_failed'
  | 'fulfilling'
  | 'shipped'
  | 'delivered'
  | 'completed'
  | 'cancelled';

export type PaymentStatus = 'pending' | 'paid' | 'refunded' | 'partial_refunded';

export type AllocationStatus = 'pending' | 'partial' | 'full' | 'failed';

export interface SalesChannelRef {
  id: string;
  code: string;
  name: string;
}

export interface PlatformOrder {
  id: string;
  orderNo: string;
  externalOrderNo: string | null;
  salesChannel: SalesChannelRef;
  status: OrderStatus;
  statusLabel: string;
  paymentStatus: PaymentStatus;
  paymentStatusLabel: string;
  totalAmount: string;
  currency: string;
  receiverName: string;
  receiverCity: string | null;
  itemCount: number;
  hasExceptions: boolean;
  exceptionCount: number;
  pendingExceptionCount: number;
  placedAt: string;
  createdAt: string;
}

export interface OrderItem {
  id: string;
  productName: string | null;
  productImage: string | null;
  skuCode: string | null;
  colorCode: string | null;
  sizeValue: string | null;
  quantity: number;
  allocatedQuantity: number;
  shippedQuantity: number;
  unitPrice: string;
  totalPrice: string;
  discountAmount: string;
  payableAmount: string;
  allocationStatus: AllocationStatus;
  allocationStatusLabel: string;
  externalProductId: string | null;
  externalProductName: string | null;
}

export interface OrderExceptionSummary {
  id: string;
  exceptionNo: string;
  type: string;
  typeLabel: string;
  status: string;
  statusLabel: string;
  description: string;
  createdAt: string;
}

export interface PlatformOrderDetail extends PlatformOrder {
  externalOrderId: string;
  receiverPhone: string;
  receiverProvince: string | null;
  receiverDistrict: string | null;
  receiverAddress: string;
  receiverPostalCode: string | null;
  receiverFullAddress: string;
  productAmount: string;
  shippingAmount: string;
  discountAmount: string;
  buyerRemark: string | null;
  sellerRemark: string | null;
  allocationFailReason: string | null;
  paidAt: string | null;
  allocatedAt: string | null;
  shippedAt: string | null;
  deliveredAt: string | null;
  completedAt: string | null;
  cancelledAt: string | null;
  syncedAt: string;
  updatedAt: string;
  items: OrderItem[];
  exceptions: OrderExceptionSummary[];
}

export interface PlatformOrderListParams {
  page?: number;
  limit?: number;
  salesChannelId?: string;
  status?: OrderStatus;
  paymentStatus?: PaymentStatus;
  search?: string;
  startDate?: string;
  endDate?: string;
}

export interface PlatformOrderListResponse {
  items: PlatformOrder[];
  total: number;
  page: number;
  limit: number;
}

export interface OrderStats {
  byStatus: Record<string, number>;
  byPaymentStatus: Record<string, number>;
  byChannel: Record<string, number>;
}

// ========== API Methods ==========

export const platformOrderApi = {
  /**
   * Get platform orders list with pagination
   */
  getList: async (params: PlatformOrderListParams = {}): Promise<PlatformOrderListResponse> => {
    const queryParams = new URLSearchParams();
    if (params.page) queryParams.append('page', params.page.toString());
    if (params.limit) queryParams.append('limit', params.limit.toString());
    if (params.salesChannelId) queryParams.append('salesChannelId', params.salesChannelId);
    if (params.status) queryParams.append('status', params.status);
    if (params.paymentStatus) queryParams.append('paymentStatus', params.paymentStatus);
    if (params.search) queryParams.append('search', params.search);
    if (params.startDate) queryParams.append('startDate', params.startDate);
    if (params.endDate) queryParams.append('endDate', params.endDate);

    const query = queryParams.toString();
    return await apiFetch<PlatformOrderListResponse>(
      `/api/admin/orders${query ? `?${query}` : ''}`
    );
  },

  /**
   * Get platform order detail
   */
  getDetail: async (id: string): Promise<{ data: PlatformOrderDetail }> => {
    return await apiFetch<{ data: PlatformOrderDetail }>(`/api/admin/orders/${id}`);
  },

  /**
   * Get order statistics
   */
  getStats: async (): Promise<{ data: OrderStats }> => {
    return await apiFetch<{ data: OrderStats }>('/api/admin/orders/stats');
  },
};
