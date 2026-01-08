import { apiFetch } from './api-client';

// ========== Types ==========

export type OrderExceptionType =
  | 'inventory_insufficient'
  | 'price_below_platform'
  | 'product_not_matched'
  | 'other';

export type OrderExceptionStatus = 'pending' | 'resolved' | 'closed';

export type OrderExceptionResolution = 'confirm' | 'cancel' | 'adjusted';

export interface OrderException {
  id: string;
  exceptionNo: string;
  type: OrderExceptionType;
  typeLabel: string;
  status: OrderExceptionStatus;
  statusLabel: string;
  description: string;
  details?: Record<string, unknown>;
  resolution?: OrderExceptionResolution;
  resolutionLabel?: string;
  resolutionNotes?: string;
  resolvedBy?: string;
  resolvedAt?: string;
  createdAt: string;
  updatedAt: string;
  order: {
    id: string;
    externalOrderNo: string;
    status: string;
    totalAmount: string;
  };
}

export interface OrderExceptionListParams {
  page?: number;
  pageSize?: number;
  status?: OrderExceptionStatus;
  type?: OrderExceptionType;
  orderId?: string;
  salesChannelId?: string;
}

export interface OrderExceptionListResponse {
  data: OrderException[];
  pagination: {
    page: number;
    pageSize: number;
    total: number;
    totalPages: number;
  };
}

export interface OrderExceptionStats {
  byStatus: Record<string, number>;
  byType: Record<string, number>;
}

export interface ResolveExceptionParams {
  resolution: OrderExceptionResolution;
  notes?: string;
}

export interface TypeOption {
  value: string;
  label: string;
}

export interface ResolutionOption {
  value: string;
  label: string;
}

// ========== API Methods ==========

export const orderExceptionApi = {
  /**
   * Get order exceptions list with pagination
   */
  getList: async (params: OrderExceptionListParams = {}): Promise<OrderExceptionListResponse> => {
    const queryParams = new URLSearchParams();
    if (params.page) queryParams.append('page', params.page.toString());
    if (params.pageSize) queryParams.append('pageSize', params.pageSize.toString());
    if (params.status) queryParams.append('status', params.status);
    if (params.type) queryParams.append('type', params.type);
    if (params.orderId) queryParams.append('orderId', params.orderId);
    if (params.salesChannelId) queryParams.append('salesChannelId', params.salesChannelId);

    const query = queryParams.toString();
    return await apiFetch<OrderExceptionListResponse>(
      `/api/order-exceptions${query ? `?${query}` : ''}`
    );
  },

  /**
   * Get order exception detail
   */
  getDetail: async (id: string): Promise<{ data: OrderException }> => {
    return await apiFetch<{ data: OrderException }>(`/api/order-exceptions/${id}`);
  },

  /**
   * Resolve an order exception
   */
  resolve: async (
    id: string,
    params: ResolveExceptionParams
  ): Promise<{ data: OrderException; message: string }> => {
    return await apiFetch<{ data: OrderException; message: string }>(
      `/api/order-exceptions/${id}/resolve`,
      {
        method: 'POST',
        body: JSON.stringify(params),
      }
    );
  },

  /**
   * Close an order exception
   */
  close: async (id: string): Promise<{ data: OrderException; message: string }> => {
    return await apiFetch<{ data: OrderException; message: string }>(
      `/api/order-exceptions/${id}/close`,
      {
        method: 'POST',
      }
    );
  },

  /**
   * Get exception statistics
   */
  getStats: async (): Promise<{ data: OrderExceptionStats }> => {
    return await apiFetch<{ data: OrderExceptionStats }>('/api/order-exceptions/stats/summary');
  },

  /**
   * Get exception type options
   */
  getTypeOptions: async (): Promise<{ data: TypeOption[] }> => {
    return await apiFetch<{ data: TypeOption[] }>('/api/order-exceptions/options/types');
  },

  /**
   * Get resolution options
   */
  getResolutionOptions: async (): Promise<{ data: ResolutionOption[] }> => {
    return await apiFetch<{ data: ResolutionOption[] }>('/api/order-exceptions/options/resolutions');
  },
};
