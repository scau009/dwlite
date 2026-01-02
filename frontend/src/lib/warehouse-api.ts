import { apiFetch } from './api-client';

// Warehouse types
export type WarehouseType = 'self' | 'third_party' | 'bonded' | 'overseas';
export type WarehouseCategory = 'platform' | 'merchant';
export type WarehouseStatus = 'active' | 'maintenance' | 'disabled';

export interface Warehouse {
  id: string;
  code: string;
  name: string;
  shortName: string | null;
  type: WarehouseType;
  category: WarehouseCategory;
  countryCode: string;
  status: WarehouseStatus;
  sortOrder: number;
  fullAddress: string;
  city: string | null;
  province: string | null;
  contactName: string;
  contactPhone: string;
  merchant?: {
    id: string;
    name: string;
  };
  createdAt: string;
  updatedAt: string;
}

export interface WarehouseDetail extends Warehouse {
  description: string | null;
  timezone: string | null;
  district: string | null;
  address: string | null;
  postalCode: string | null;
  longitude: string | null;
  latitude: string | null;
  contactEmail: string | null;
  internalNotes: string | null;
}

export interface CreateWarehouseRequest {
  code: string;
  name: string;
  shortName?: string;
  type: WarehouseType;
  category: WarehouseCategory;
  merchantId?: string;
  description?: string;
  countryCode?: string;
  timezone?: string;
  province?: string;
  city?: string;
  district?: string;
  address?: string;
  postalCode?: string;
  longitude?: string;
  latitude?: string;
  contactName?: string;
  contactPhone?: string;
  contactEmail?: string;
  internalNotes?: string;
  status?: WarehouseStatus;
  sortOrder?: number;
}

// eslint-disable-next-line @typescript-eslint/no-empty-object-type
export interface UpdateWarehouseRequest extends Partial<CreateWarehouseRequest> {}

export interface WarehouseListParams {
  page?: number;
  limit?: number;
  name?: string;
  code?: string;
  type?: WarehouseType;
  category?: WarehouseCategory;
  status?: WarehouseStatus;
  countryCode?: string;
}

export interface WarehouseListResponse {
  data: Warehouse[];
  total: number;
  page: number;
  limit: number;
}

// Warehouse User types
export interface WarehouseUser {
  id: string;
  email: string;
  accountType: 'warehouse';
  isVerified: boolean;
  warehouse: {
    id: string;
    code: string;
    name: string;
  } | null;
  createdAt: string;
  updatedAt: string;
}

export interface WarehouseUserListParams {
  page?: number;
  limit?: number;
  warehouseId?: string;
}

export interface WarehouseUserListResponse {
  data: WarehouseUser[];
  meta: {
    total: number;
    page: number;
    limit: number;
    pages: number;
  };
}

export interface CreateWarehouseUserRequest {
  email: string;
  password: string;
  warehouseId: string;
}

export interface UpdateWarehouseUserRequest {
  email?: string;
  password?: string;
  warehouseId?: string;
}

export const warehouseApi = {
  // Get warehouse list
  async getWarehouses(params: WarehouseListParams = {}): Promise<WarehouseListResponse> {
    const searchParams = new URLSearchParams();
    if (params.page) searchParams.set('page', String(params.page));
    if (params.limit) searchParams.set('limit', String(params.limit));
    if (params.name) searchParams.set('name', params.name);
    if (params.code) searchParams.set('code', params.code);
    if (params.type) searchParams.set('type', params.type);
    if (params.category) searchParams.set('category', params.category);
    if (params.status) searchParams.set('status', params.status);
    if (params.countryCode) searchParams.set('countryCode', params.countryCode);

    const queryString = searchParams.toString();
    return apiFetch<WarehouseListResponse>(`/api/admin/warehouses${queryString ? `?${queryString}` : ''}`);
  },

  // Get warehouse detail
  async getWarehouse(id: string): Promise<WarehouseDetail> {
    return apiFetch<WarehouseDetail>(`/api/admin/warehouses/${id}`);
  },

  // Create warehouse
  async createWarehouse(data: CreateWarehouseRequest): Promise<{ message: string; data: WarehouseDetail }> {
    return apiFetch<{ message: string; data: WarehouseDetail }>('/api/admin/warehouses', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },

  // Update warehouse
  async updateWarehouse(id: string, data: UpdateWarehouseRequest): Promise<{ message: string; data: WarehouseDetail }> {
    return apiFetch<{ message: string; data: WarehouseDetail }>(`/api/admin/warehouses/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  },

  // Delete warehouse
  async deleteWarehouse(id: string): Promise<{ message: string }> {
    return apiFetch<{ message: string }>(`/api/admin/warehouses/${id}`, {
      method: 'DELETE',
    });
  },

  // === Warehouse Users API ===

  // Get warehouse users list
  async getWarehouseUsers(params: WarehouseUserListParams = {}): Promise<WarehouseUserListResponse> {
    const searchParams = new URLSearchParams();
    if (params.page) searchParams.set('page', String(params.page));
    if (params.limit) searchParams.set('limit', String(params.limit));
    if (params.warehouseId) searchParams.set('warehouseId', params.warehouseId);

    const queryString = searchParams.toString();
    return apiFetch<WarehouseUserListResponse>(`/api/admin/warehouse-users${queryString ? `?${queryString}` : ''}`);
  },

  // Get warehouse user detail
  async getWarehouseUser(id: string): Promise<{ data: WarehouseUser }> {
    return apiFetch<{ data: WarehouseUser }>(`/api/admin/warehouse-users/${id}`);
  },

  // Create warehouse user
  async createWarehouseUser(data: CreateWarehouseUserRequest): Promise<{ message: string; data: WarehouseUser }> {
    return apiFetch<{ message: string; data: WarehouseUser }>('/api/admin/warehouse-users', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },

  // Update warehouse user
  async updateWarehouseUser(id: string, data: UpdateWarehouseUserRequest): Promise<{ message: string; data: WarehouseUser }> {
    return apiFetch<{ message: string; data: WarehouseUser }>(`/api/admin/warehouse-users/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  },

  // Delete warehouse user
  async deleteWarehouseUser(id: string): Promise<{ message: string }> {
    return apiFetch<{ message: string }>(`/api/admin/warehouse-users/${id}`, {
      method: 'DELETE',
    });
  },
};
