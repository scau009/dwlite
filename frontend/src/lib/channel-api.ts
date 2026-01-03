import { apiFetch } from './api-client';

// Types
export interface SalesChannel {
  id: string;
  code: string;
  name: string;
  logoUrl: string | null;
  status: 'active' | 'maintenance' | 'disabled';
  sortOrder: number;
  createdAt: string;
  updatedAt: string;
}

export interface SalesChannelDetail extends SalesChannel {
  description: string | null;
  config: Record<string, unknown> | null;
  configSchema: Record<string, unknown> | null;
  merchantCount: number;
}

export type FulfillmentType = 'consignment' | 'self_fulfillment';

export interface MerchantChannel {
  id: string;
  status: 'pending' | 'active' | 'rejected' | 'suspended' | 'disabled';
  requestedFulfillmentTypes: FulfillmentType[];
  approvedFulfillmentTypes: FulfillmentType[] | null;
  remark: string | null;
  approvedAt: string | null;
  approvedBy: string | null;
  createdAt: string;
  updatedAt: string;
  merchant: {
    id: string;
    name: string;
    contactName?: string;
    contactPhone?: string;
  };
  salesChannel: {
    id: string;
    code: string;
    name: string;
    logoUrl: string | null;
    configSchema?: Record<string, unknown> | null;
  };
  config?: Record<string, unknown> | null;
}

export interface PaginatedResponse<T> {
  data: T[];
  total: number;
  page: number;
  limit: number;
}

export interface ChannelListParams {
  page?: number;
  limit?: number;
  name?: string;
  code?: string;
  status?: string;
}

export interface MerchantChannelListParams {
  page?: number;
  limit?: number;
  merchantId?: string;
  salesChannelId?: string;
  status?: string;
}

export interface CreateChannelParams {
  code: string;
  name: string;
  logoUrl?: string;
  description?: string;
  config?: Record<string, unknown>;
  configSchema?: Record<string, unknown>;
  status?: 'active' | 'maintenance' | 'disabled';
  sortOrder?: number;
}

export interface UpdateChannelParams {
  name?: string;
  logoUrl?: string;
  description?: string;
  config?: Record<string, unknown>;
  configSchema?: Record<string, unknown>;
  sortOrder?: number;
}

export const channelApi = {
  // ==================== Sales Channel CRUD ====================

  /**
   * 获取销售渠道列表
   */
  getChannels: async (params: ChannelListParams = {}): Promise<PaginatedResponse<SalesChannel>> => {
    const searchParams = new URLSearchParams();
    if (params.page) searchParams.set('page', String(params.page));
    if (params.limit) searchParams.set('limit', String(params.limit));
    if (params.name) searchParams.set('name', params.name);
    if (params.code) searchParams.set('code', params.code);
    if (params.status) searchParams.set('status', params.status);

    const query = searchParams.toString();
    return apiFetch<PaginatedResponse<SalesChannel>>(
      `/api/admin/sales-channels${query ? `?${query}` : ''}`
    );
  },

  /**
   * 获取销售渠道详情
   */
  getChannel: async (id: string): Promise<SalesChannelDetail> => {
    return apiFetch<SalesChannelDetail>(`/api/admin/sales-channels/${id}`);
  },

  /**
   * 创建销售渠道
   */
  createChannel: async (
    data: CreateChannelParams
  ): Promise<{ message: string; channel: SalesChannelDetail }> => {
    return apiFetch('/api/admin/sales-channels', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },

  /**
   * 更新销售渠道
   */
  updateChannel: async (
    id: string,
    data: UpdateChannelParams
  ): Promise<{ message: string; channel: SalesChannelDetail }> => {
    return apiFetch(`/api/admin/sales-channels/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  },

  /**
   * 删除销售渠道
   */
  deleteChannel: async (id: string): Promise<{ message: string }> => {
    return apiFetch(`/api/admin/sales-channels/${id}`, {
      method: 'DELETE',
    });
  },

  /**
   * 更新销售渠道状态
   */
  updateChannelStatus: async (
    id: string,
    status: 'active' | 'maintenance' | 'disabled'
  ): Promise<{ message: string; channel: SalesChannel }> => {
    return apiFetch(`/api/admin/sales-channels/${id}/status`, {
      method: 'PUT',
      body: JSON.stringify({ status }),
    });
  },

  // ==================== Merchant Channel Management ====================

  /**
   * 获取商户渠道关联列表
   */
  getMerchantChannels: async (
    params: MerchantChannelListParams = {}
  ): Promise<PaginatedResponse<MerchantChannel>> => {
    const searchParams = new URLSearchParams();
    if (params.page) searchParams.set('page', String(params.page));
    if (params.limit) searchParams.set('limit', String(params.limit));
    if (params.merchantId) searchParams.set('merchantId', params.merchantId);
    if (params.salesChannelId) searchParams.set('salesChannelId', params.salesChannelId);
    if (params.status) searchParams.set('status', params.status);

    const query = searchParams.toString();
    return apiFetch<PaginatedResponse<MerchantChannel>>(
      `/api/admin/merchant-channels${query ? `?${query}` : ''}`
    );
  },

  /**
   * 获取待审批的商户渠道申请
   */
  getPendingApprovals: async (): Promise<{ data: MerchantChannel[]; total: number }> => {
    return apiFetch('/api/admin/merchant-channels/pending');
  },

  /**
   * 获取待审批数量
   */
  getPendingCount: async (): Promise<{ count: number }> => {
    return apiFetch('/api/admin/merchant-channels/pending-count');
  },

  /**
   * 获取商户渠道详情
   */
  getMerchantChannel: async (id: string): Promise<MerchantChannel> => {
    return apiFetch<MerchantChannel>(`/api/admin/merchant-channels/${id}`);
  },

  /**
   * 审批通过商户渠道申请
   * @param id 商户渠道ID
   * @param approvedFulfillmentTypes 批准的履约模式，不传则批准所有申请的模式
   */
  approveChannel: async (
    id: string,
    approvedFulfillmentTypes?: FulfillmentType[]
  ): Promise<{ message: string; merchantChannel: MerchantChannel }> => {
    return apiFetch(`/api/admin/merchant-channels/${id}/approve`, {
      method: 'POST',
      body: JSON.stringify(
        approvedFulfillmentTypes ? { approvedFulfillmentTypes } : {}
      ),
    });
  },

  /**
   * 拒绝商户渠道申请
   */
  rejectChannel: async (
    id: string,
    reason: string
  ): Promise<{ message: string; merchantChannel: MerchantChannel }> => {
    return apiFetch(`/api/admin/merchant-channels/${id}/reject`, {
      method: 'POST',
      body: JSON.stringify({ reason }),
    });
  },

  /**
   * 暂停商户渠道
   */
  suspendChannel: async (
    id: string,
    reason?: string
  ): Promise<{ message: string; merchantChannel: MerchantChannel }> => {
    return apiFetch(`/api/admin/merchant-channels/${id}/suspend`, {
      method: 'POST',
      body: JSON.stringify({ reason }),
    });
  },

  /**
   * 启用商户渠道
   */
  enableChannel: async (
    id: string
  ): Promise<{ message: string; merchantChannel: MerchantChannel }> => {
    return apiFetch(`/api/admin/merchant-channels/${id}/enable`, {
      method: 'POST',
    });
  },
};
