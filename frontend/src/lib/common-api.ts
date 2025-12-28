import { apiFetch } from './api-client';

// Types
export interface Carrier {
  code: string;
  name: string;
}

// API functions
export const commonApi = {
  /**
   * 获取物流公司选项列表
   */
  getCarrierOptions: async (): Promise<Carrier[]> => {
    const response = await apiFetch<{ data: Carrier[] }>('/api/common/carriers');
    return response.data;
  },
};
