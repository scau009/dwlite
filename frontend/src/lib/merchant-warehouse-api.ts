import { apiFetch } from './api-client';

// Types
export interface MerchantWarehouse {
  id: string;
  code: string;
  name: string;
  shortName: string | null;
  type: 'self' | 'third_party' | 'bonded' | 'overseas';
  status: 'active' | 'maintenance' | 'disabled';
  countryCode: string;
  province: string | null;
  city: string | null;
  contactName: string | null;
  contactPhone: string | null;
  createdAt: string;
  updatedAt: string;
}

export interface MerchantWarehouseDetail extends MerchantWarehouse {
  description: string | null;
  district: string | null;
  address: string | null;
  postalCode: string | null;
  contactEmail: string | null;
  fullAddress: string | null;
}

export interface PaginatedResponse<T> {
  data: T[];
  total: number;
  page: number;
  limit: number;
}

export interface MerchantWarehouseListParams {
  page?: number;
  limit?: number;
  name?: string;
  code?: string;
  type?: string;
  status?: string;
}

export interface CreateMerchantWarehouseParams {
  code: string;
  name: string;
  shortName?: string;
  type: 'self' | 'third_party' | 'bonded' | 'overseas';
  status?: 'active' | 'maintenance' | 'disabled';
  description?: string;
  countryCode?: string;
  province?: string;
  city?: string;
  district?: string;
  address?: string;
  postalCode?: string;
  contactName?: string;
  contactPhone?: string;
  contactEmail?: string;
}

export interface UpdateMerchantWarehouseParams {
  name?: string;
  shortName?: string;
  type?: 'self' | 'third_party' | 'bonded' | 'overseas';
  status?: 'active' | 'maintenance' | 'disabled';
  description?: string;
  countryCode?: string;
  province?: string;
  city?: string;
  district?: string;
  address?: string;
  postalCode?: string;
  contactName?: string;
  contactPhone?: string;
  contactEmail?: string;
}

export const merchantWarehouseApi = {
  /**
   * Get merchant warehouses list
   */
  getWarehouses: async (
    params: MerchantWarehouseListParams = {}
  ): Promise<PaginatedResponse<MerchantWarehouse>> => {
    const searchParams = new URLSearchParams();
    if (params.page) searchParams.set('page', String(params.page));
    if (params.limit) searchParams.set('limit', String(params.limit));
    if (params.name) searchParams.set('name', params.name);
    if (params.code) searchParams.set('code', params.code);
    if (params.type) searchParams.set('type', params.type);
    if (params.status) searchParams.set('status', params.status);

    const query = searchParams.toString();
    return apiFetch<PaginatedResponse<MerchantWarehouse>>(
      `/api/merchant/warehouses${query ? `?${query}` : ''}`
    );
  },

  /**
   * Get warehouse detail
   */
  getWarehouse: async (id: string): Promise<{ data: MerchantWarehouseDetail }> => {
    return apiFetch<{ data: MerchantWarehouseDetail }>(`/api/merchant/warehouses/${id}`);
  },

  /**
   * Create warehouse
   */
  createWarehouse: async (
    data: CreateMerchantWarehouseParams
  ): Promise<{ message: string; data: MerchantWarehouse }> => {
    return apiFetch('/api/merchant/warehouses', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },

  /**
   * Update warehouse
   */
  updateWarehouse: async (
    id: string,
    data: UpdateMerchantWarehouseParams
  ): Promise<{ message: string; data: MerchantWarehouse }> => {
    return apiFetch(`/api/merchant/warehouses/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  },

  /**
   * Delete warehouse
   */
  deleteWarehouse: async (id: string): Promise<{ message: string }> => {
    return apiFetch(`/api/merchant/warehouses/${id}`, {
      method: 'DELETE',
    });
  },
};
