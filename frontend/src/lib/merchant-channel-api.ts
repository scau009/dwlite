import { apiFetch } from './api-client';

// Types
export type FulfillmentType = 'consignment' | 'self_fulfillment';
export type PricingModel = 'self_pricing' | 'platform_managed';

export interface AvailableSalesChannel {
  id: string;
  code: string;
  name: string;
  logoUrl: string | null;
  description: string | null;
}

export interface MyMerchantChannel {
  id: string;
  requestedFulfillmentTypes: FulfillmentType[];
  approvedFulfillmentTypes: FulfillmentType[] | null;
  status: 'pending' | 'active' | 'suspended' | 'disabled' | 'rejected';
  remark: string | null;
  approvedAt: string | null;
  createdAt: string;
  updatedAt: string;
  salesChannel: {
    id: string;
    code: string;
    name: string;
    logoUrl: string | null;
  };
}

export interface ChannelWarehouse {
  id: string;
  code: string;
  name: string;
  type: 'self' | 'third_party' | 'bonded' | 'overseas';
  countryCode: string;
  fullAddress: string;
  province: string | null;
  city: string | null;
}

export interface MyChannelListParams {
  page?: number;
  limit?: number;
  status?: string;
}

export interface PaginatedResponse<T> {
  data: T[];
  total: number;
  page: number;
  limit: number;
}

export const merchantChannelApi = {
  /**
   * 获取可申请的销售渠道列表
   */
  getAvailableChannels: async (): Promise<{ data: AvailableSalesChannel[] }> => {
    return apiFetch('/api/merchant/sales-channels');
  },

  /**
   * 获取我的渠道申请/连接列表
   */
  getMyChannels: async (
    params: MyChannelListParams = {}
  ): Promise<PaginatedResponse<MyMerchantChannel>> => {
    const searchParams = new URLSearchParams();
    if (params.page) searchParams.set('page', String(params.page));
    if (params.limit) searchParams.set('limit', String(params.limit));
    if (params.status) searchParams.set('status', params.status);

    const query = searchParams.toString();
    return apiFetch(`/api/merchant/my-channels${query ? `?${query}` : ''}`);
  },

  /**
   * 申请销售渠道
   */
  applyChannel: async (data: {
    salesChannelId: string;
    fulfillmentTypes: FulfillmentType[];
    remark?: string;
  }): Promise<{ message: string; merchantChannel: MyMerchantChannel }> => {
    return apiFetch('/api/merchant/my-channels', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },

  /**
   * 取消申请（pending）或停用渠道（active）
   */
  cancelOrDisable: async (
    id: string
  ): Promise<{ message: string; merchantChannel?: MyMerchantChannel }> => {
    return apiFetch(`/api/merchant/my-channels/${id}`, {
      method: 'DELETE',
    });
  },

  /**
   * 重新启用已停用的渠道
   */
  enableChannel: async (
    id: string
  ): Promise<{ message: string; merchantChannel: MyMerchantChannel }> => {
    return apiFetch(`/api/merchant/my-channels/${id}/enable`, {
      method: 'POST',
    });
  },

  /**
   * 获取渠道的可用仓库列表
   */
  getChannelWarehouses: async (
    channelId: string
  ): Promise<{ data: ChannelWarehouse[] }> => {
    return apiFetch(`/api/merchant/my-channels/${channelId}/warehouses`);
  },
};
