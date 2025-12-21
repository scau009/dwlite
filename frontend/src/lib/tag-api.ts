import { apiFetch } from './api-client';

// Types
export interface Tag {
  id: string;
  name: string;
  slug: string;
  color: string | null;
  sortOrder: number;
  isActive: boolean;
  createdAt: string;
}

export interface TagDetail extends Tag {
  productCount: number;
}

export interface PaginatedResponse<T> {
  data: T[];
  total: number;
  page: number;
  limit: number;
}

export interface TagListParams {
  page?: number;
  limit?: number;
  name?: string;
  isActive?: boolean;
}

export interface CreateTagParams {
  name: string;
  slug?: string;
  color?: string;
  sortOrder?: number;
  isActive?: boolean;
}

export interface UpdateTagParams {
  name?: string;
  slug?: string;
  color?: string;
  sortOrder?: number;
  isActive?: boolean;
}

export const tagApi = {
  /**
   * 获取标签列表
   */
  getTags: async (params: TagListParams = {}): Promise<PaginatedResponse<Tag>> => {
    const searchParams = new URLSearchParams();
    if (params.page) searchParams.set('page', String(params.page));
    if (params.limit) searchParams.set('limit', String(params.limit));
    if (params.name) searchParams.set('name', params.name);
    if (params.isActive !== undefined) searchParams.set('isActive', String(params.isActive));

    const query = searchParams.toString();
    return apiFetch<PaginatedResponse<Tag>>(
      `/api/admin/tags${query ? `?${query}` : ''}`
    );
  },

  /**
   * 获取标签详情
   */
  getTag: async (id: string): Promise<TagDetail> => {
    return apiFetch<TagDetail>(`/api/admin/tags/${id}`);
  },

  /**
   * 创建标签
   */
  createTag: async (data: CreateTagParams): Promise<{ message: string; tag: TagDetail }> => {
    return apiFetch('/api/admin/tags', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },

  /**
   * 更新标签
   */
  updateTag: async (
    id: string,
    data: UpdateTagParams
  ): Promise<{ message: string; tag: TagDetail }> => {
    return apiFetch(`/api/admin/tags/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  },

  /**
   * 删除标签
   */
  deleteTag: async (id: string): Promise<{ message: string }> => {
    return apiFetch(`/api/admin/tags/${id}`, {
      method: 'DELETE',
    });
  },

  /**
   * 切换标签状态
   */
  updateTagStatus: async (
    id: string,
    isActive: boolean
  ): Promise<{ message: string; tag: Tag }> => {
    return apiFetch(`/api/admin/tags/${id}/status`, {
      method: 'PUT',
      body: JSON.stringify({ isActive }),
    });
  },
};
