import { apiFetch } from './api-client';

// Types
export interface Category {
  id: string;
  name: string;
  slug: string;
  parentId: string | null;
  parentName: string | null;
  level: number;
  sortOrder: number;
  isActive: boolean;
  hasChildren: boolean;
  createdAt: string;
  updatedAt: string;
}

export interface CategoryDetail extends Category {
  description: string | null;
  productCount: number;
  childCount: number;
}

export interface CategoryTreeNode {
  id: string;
  name: string;
  slug: string;
  level: number;
  sortOrder: number;
  isActive: boolean;
  children: CategoryTreeNode[];
}

export interface PaginatedResponse<T> {
  data: T[];
  total: number;
  page: number;
  limit: number;
}

export interface CategoryListParams {
  page?: number;
  limit?: number;
  name?: string;
  parentId?: string;
  isActive?: boolean;
}

export interface CreateCategoryParams {
  name: string;
  slug?: string;
  parentId?: string;
  description?: string;
  sortOrder?: number;
  isActive?: boolean;
}

export interface UpdateCategoryParams {
  name?: string;
  slug?: string;
  parentId?: string;
  description?: string;
  sortOrder?: number;
  isActive?: boolean;
}

export const categoryApi = {
  /**
   * 获取分类列表
   */
  getCategories: async (params: CategoryListParams = {}): Promise<PaginatedResponse<Category>> => {
    const searchParams = new URLSearchParams();
    if (params.page) searchParams.set('page', String(params.page));
    if (params.limit) searchParams.set('limit', String(params.limit));
    if (params.name) searchParams.set('name', params.name);
    if (params.parentId) searchParams.set('parentId', params.parentId);
    if (params.isActive !== undefined) searchParams.set('isActive', String(params.isActive));

    const query = searchParams.toString();
    return apiFetch<PaginatedResponse<Category>>(
      `/api/admin/categories${query ? `?${query}` : ''}`
    );
  },

  /**
   * 获取分类树
   */
  getCategoryTree: async (activeOnly = false): Promise<{ data: CategoryTreeNode[] }> => {
    const query = activeOnly ? '?activeOnly=true' : '';
    return apiFetch<{ data: CategoryTreeNode[] }>(`/api/admin/categories/tree${query}`);
  },

  /**
   * 获取分类详情
   */
  getCategory: async (id: string): Promise<CategoryDetail> => {
    return apiFetch<CategoryDetail>(`/api/admin/categories/${id}`);
  },

  /**
   * 创建分类
   */
  createCategory: async (data: CreateCategoryParams): Promise<{ message: string; category: CategoryDetail }> => {
    return apiFetch('/api/admin/categories', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },

  /**
   * 更新分类
   */
  updateCategory: async (
    id: string,
    data: UpdateCategoryParams
  ): Promise<{ message: string; category: CategoryDetail }> => {
    return apiFetch(`/api/admin/categories/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  },

  /**
   * 删除分类
   */
  deleteCategory: async (id: string): Promise<{ message: string }> => {
    return apiFetch(`/api/admin/categories/${id}`, {
      method: 'DELETE',
    });
  },

  /**
   * 切换分类状态
   */
  updateCategoryStatus: async (
    id: string,
    isActive: boolean
  ): Promise<{ message: string; category: Category }> => {
    return apiFetch(`/api/admin/categories/${id}/status`, {
      method: 'PUT',
      body: JSON.stringify({ isActive }),
    });
  },
};
