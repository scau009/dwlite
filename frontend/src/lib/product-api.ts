import { apiFetch } from './api-client';

// Types
export type ProductStatus = 'draft' | 'active' | 'inactive';
export type SizeUnit = 'EU' | 'US' | 'UK' | 'CM';

export const SIZE_UNITS: { value: SizeUnit; label: string }[] = [
  { value: 'EU', label: 'EU (欧码)' },
  { value: 'US', label: 'US (美码)' },
  { value: 'UK', label: 'UK (英码)' },
  { value: 'CM', label: 'CM (厘米)' },
];

export interface Product {
  id: string;
  name: string;
  slug: string;
  styleNumber: string;
  season: string;
  color: string | null;
  status: ProductStatus;
  isActive: boolean;
  brandId: string | null;
  brandName: string | null;
  categoryId: string | null;
  categoryName: string | null;
  skuCount: number;
  priceRange: { min: number | null; max: number | null };
  primaryImageUrl: string | null;
  tags: Array<{ id: string; name: string }>;
  createdAt: string;
  updatedAt: string;
}

export interface ProductDetail extends Product {
  description: string | null;
  tags: Array<{ id: string; name: string }>;
  skus: ProductSku[];
  images: ProductImage[];
}

export interface ProductSku {
  id: string;
  sizeUnit: SizeUnit | null;
  sizeValue: string | null;
  specInfo: Record<string, string> | null;
  specDescription: string;
  price: string;  // 参考价
  originalPrice: string | null;  // 发售价
  isActive: boolean;
  sortOrder: number;
  createdAt: string;
  updatedAt: string;
}

export interface ProductImage {
  id: string;
  url: string;
  thumbnailUrl: string | null;
  cosKey: string;
  isPrimary: boolean;
  sortOrder: number;
  fileSize: number | null;
  width: number | null;
  height: number | null;
  dimensions: string | null;
  humanFileSize: string | null;
  createdAt: string;
}

export interface PaginatedResponse<T> {
  data: T[];
  total: number;
  page: number;
  limit: number;
}

export interface ProductListParams {
  page?: number;
  limit?: number;
  search?: string;
  brandId?: string;
  categoryId?: string;
  season?: string;
  status?: ProductStatus;
  isActive?: boolean;
  sortBy?: string;
  sortOrder?: 'ASC' | 'DESC';
}

export interface CreateProductParams {
  name: string;
  slug?: string;
  styleNumber: string;
  season: string;
  color?: string;
  description?: string;
  brandId?: string;
  categoryId?: string;
  status?: ProductStatus;
  tagIds?: string[];
}

export interface UpdateProductParams {
  name?: string;
  slug?: string;
  styleNumber?: string;
  season?: string;
  color?: string;
  description?: string;
  brandId?: string;
  categoryId?: string;
  status?: ProductStatus;
  isActive?: boolean;
  tagIds?: string[];
}

export interface CreateSkuParams {
  sizeUnit?: SizeUnit;
  sizeValue?: string;
  specInfo?: Record<string, string>;
  price: string;  // 参考价
  originalPrice?: string;  // 发售价
  isActive?: boolean;
  sortOrder?: number;
}

export interface UpdateSkuParams {
  sizeUnit?: SizeUnit;
  sizeValue?: string;
  specInfo?: Record<string, string>;
  price?: string;  // 参考价
  originalPrice?: string;  // 发售价
  isActive?: boolean;
  sortOrder?: number;
}

export interface BatchCreateSkuParams {
  sizeUnit: 'US' | 'EU' | 'UK';  // CM is not allowed for quick add
  price: string;  // 参考价
  originalPrice?: string;  // 发售价
}

export interface BatchCreateSkuResult {
  message: string;
  createdCount: number;
  skippedCount: number;
  skippedSizes: string[];
  skus: ProductSku[];
}

export const productApi = {
  /**
   * 获取商品列表
   */
  getProducts: async (params: ProductListParams = {}): Promise<PaginatedResponse<Product>> => {
    const searchParams = new URLSearchParams();
    if (params.page) searchParams.set('page', String(params.page));
    if (params.limit) searchParams.set('limit', String(params.limit));
    if (params.search) searchParams.set('search', params.search);
    if (params.brandId) searchParams.set('brandId', params.brandId);
    if (params.categoryId) searchParams.set('categoryId', params.categoryId);
    if (params.season) searchParams.set('season', params.season);
    if (params.status) searchParams.set('status', params.status);
    if (params.isActive !== undefined) searchParams.set('isActive', String(params.isActive));
    if (params.sortBy) searchParams.set('sortBy', params.sortBy);
    if (params.sortOrder) searchParams.set('sortOrder', params.sortOrder);

    const query = searchParams.toString();
    return apiFetch<PaginatedResponse<Product>>(
      `/api/admin/products${query ? `?${query}` : ''}`
    );
  },

  /**
   * 获取商品详情
   */
  getProduct: async (id: string): Promise<ProductDetail> => {
    return apiFetch<ProductDetail>(`/api/admin/products/${id}`);
  },

  /**
   * 创建商品
   */
  createProduct: async (data: CreateProductParams): Promise<{ message: string; product: ProductDetail }> => {
    return apiFetch('/api/admin/products', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },

  /**
   * 更新商品
   */
  updateProduct: async (
    id: string,
    data: UpdateProductParams
  ): Promise<{ message: string; product: ProductDetail }> => {
    return apiFetch(`/api/admin/products/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  },

  /**
   * 删除商品
   */
  deleteProduct: async (id: string): Promise<{ message: string }> => {
    return apiFetch(`/api/admin/products/${id}`, {
      method: 'DELETE',
    });
  },

  /**
   * 更新商品状态
   */
  updateProductStatus: async (
    id: string,
    status: ProductStatus
  ): Promise<{ message: string; product: Product }> => {
    return apiFetch(`/api/admin/products/${id}/status`, {
      method: 'PUT',
      body: JSON.stringify({ status }),
    });
  },

  // SKU operations

  /**
   * 创建 SKU
   */
  createSku: async (
    productId: string,
    data: CreateSkuParams
  ): Promise<{ message: string; sku: ProductSku }> => {
    return apiFetch(`/api/admin/products/${productId}/skus`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },

  /**
   * 批量创建 SKU (快捷添加尺码)
   */
  batchCreateSkus: async (
    productId: string,
    data: BatchCreateSkuParams
  ): Promise<BatchCreateSkuResult> => {
    return apiFetch(`/api/admin/products/${productId}/skus/batch`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },

  /**
   * 更新 SKU
   */
  updateSku: async (
    productId: string,
    skuId: string,
    data: UpdateSkuParams
  ): Promise<{ message: string; sku: ProductSku }> => {
    return apiFetch(`/api/admin/products/${productId}/skus/${skuId}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  },

  /**
   * 删除 SKU
   */
  deleteSku: async (productId: string, skuId: string): Promise<{ message: string }> => {
    return apiFetch(`/api/admin/products/${productId}/skus/${skuId}`, {
      method: 'DELETE',
    });
  },

  /**
   * 切换 SKU 状态
   */
  updateSkuStatus: async (
    productId: string,
    skuId: string,
    isActive: boolean
  ): Promise<{ message: string; sku: ProductSku }> => {
    return apiFetch(`/api/admin/products/${productId}/skus/${skuId}/status`, {
      method: 'PUT',
      body: JSON.stringify({ isActive }),
    });
  },

  // Image operations

  /**
   * 上传商品图片
   */
  uploadImage: async (
    productId: string,
    file: File
  ): Promise<{ message: string; image: ProductImage }> => {
    const formData = new FormData();
    formData.append('file', file);

    return apiFetch(`/api/admin/products/${productId}/images`, {
      method: 'POST',
      body: formData,
      headers: {}, // Let browser set Content-Type for multipart
    });
  },

  /**
   * 删除商品图片
   */
  deleteImage: async (productId: string, imageId: string): Promise<{ message: string }> => {
    return apiFetch(`/api/admin/products/${productId}/images/${imageId}`, {
      method: 'DELETE',
    });
  },

  /**
   * 设置主图
   */
  setImagePrimary: async (
    productId: string,
    imageId: string
  ): Promise<{ message: string; image: ProductImage }> => {
    return apiFetch(`/api/admin/products/${productId}/images/${imageId}/primary`, {
      method: 'PUT',
    });
  },

  /**
   * 排序图片
   */
  sortImages: async (
    productId: string,
    imageIds: string[]
  ): Promise<{ message: string }> => {
    return apiFetch(`/api/admin/products/${productId}/images/sort`, {
      method: 'PUT',
      body: JSON.stringify({ imageIds }),
    });
  },
};
