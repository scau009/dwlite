import { apiFetch } from './api-client';

// Types
export interface Brand {
  id: string;
  name: string;
  slug: string;
  logoUrl: string | null;
  sortOrder: number;
  isActive: boolean;
  createdAt: string;
  updatedAt: string;
}

export interface BrandDetail extends Brand {
  description: string | null;
  productCount: number;
}

export interface PaginatedResponse<T> {
  data: T[];
  total: number;
  page: number;
  limit: number;
}

export interface BrandListParams {
  page?: number;
  limit?: number;
  name?: string;
  isActive?: boolean;
}

export interface CreateBrandParams {
  name: string;
  slug?: string;
  logoUrl?: string;
  description?: string;
  sortOrder?: number;
  isActive?: boolean;
}

export interface UpdateBrandParams {
  name?: string;
  slug?: string;
  logoUrl?: string;
  description?: string;
  sortOrder?: number;
  isActive?: boolean;
}

export const brandApi = {
  /**
   * 获取品牌列表
   */
  getBrands: async (params: BrandListParams = {}): Promise<PaginatedResponse<Brand>> => {
    const searchParams = new URLSearchParams();
    if (params.page) searchParams.set('page', String(params.page));
    if (params.limit) searchParams.set('limit', String(params.limit));
    if (params.name) searchParams.set('name', params.name);
    if (params.isActive !== undefined) searchParams.set('isActive', String(params.isActive));

    const query = searchParams.toString();
    return apiFetch<PaginatedResponse<Brand>>(
      `/api/admin/brands${query ? `?${query}` : ''}`
    );
  },

  /**
   * 获取品牌详情
   */
  getBrand: async (id: string): Promise<BrandDetail> => {
    return apiFetch<BrandDetail>(`/api/admin/brands/${id}`);
  },

  /**
   * 创建品牌
   */
  createBrand: async (data: CreateBrandParams): Promise<{ message: string; brand: BrandDetail }> => {
    return apiFetch('/api/admin/brands', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },

  /**
   * 更新品牌
   */
  updateBrand: async (
    id: string,
    data: UpdateBrandParams
  ): Promise<{ message: string; brand: BrandDetail }> => {
    return apiFetch(`/api/admin/brands/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  },

  /**
   * 删除品牌
   */
  deleteBrand: async (id: string): Promise<{ message: string }> => {
    return apiFetch(`/api/admin/brands/${id}`, {
      method: 'DELETE',
    });
  },

  /**
   * 切换品牌状态
   */
  updateBrandStatus: async (
    id: string,
    isActive: boolean
  ): Promise<{ message: string; brand: Brand }> => {
    return apiFetch(`/api/admin/brands/${id}/status`, {
      method: 'PUT',
      body: JSON.stringify({ isActive }),
    });
  },
};