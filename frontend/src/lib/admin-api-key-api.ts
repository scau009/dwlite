import { apiFetch } from './api-client';

// Types
export type ApiKeyType = 'warehouse' | 'merchant';
export type ApiKeyStatus = 'active' | 'suspended' | 'revoked';

export interface AdminApiKey {
  id: string;
  keyId: string;
  name: string;
  type: ApiKeyType;
  status: ApiKeyStatus;
  permissions: string[];
  lastUsedAt: string | null;
  expiresAt: string | null;
  createdAt: string;
  merchant?: { id: string; name: string } | null;
}

export interface AdminApiKeyDetail extends AdminApiKey {
  ipWhitelist: string[] | null;
  createdBy: { id: string; email: string } | null;
}

export interface PaginatedResponse<T> {
  data: T[];
  total: number;
  page: number;
  limit: number;
}

export interface AdminApiKeyListParams {
  page?: number;
  limit?: number;
  type?: string;
  status?: string;
  merchantId?: string;
}

export interface CreateAdminApiKeyRequest {
  name: string;
  type: ApiKeyType;
  merchantId: string;
  permissions: string[];
  ipWhitelist?: string[] | null;
  expiresAt?: string | null;
}

export const adminApiKeyApi = {
  /**
   * Get list of API keys (admin)
   */
  getApiKeys: async (
    params: AdminApiKeyListParams = {}
  ): Promise<PaginatedResponse<AdminApiKey>> => {
    const searchParams = new URLSearchParams();
    if (params.page) searchParams.set('page', String(params.page));
    if (params.limit) searchParams.set('limit', String(params.limit));
    if (params.type) searchParams.set('type', params.type);
    if (params.status) searchParams.set('status', params.status);
    if (params.merchantId) searchParams.set('merchantId', params.merchantId);

    const query = searchParams.toString();
    return apiFetch<PaginatedResponse<AdminApiKey>>(
      `/api/admin/api-keys${query ? `?${query}` : ''}`
    );
  },

  /**
   * Get API key detail
   */
  getApiKey: async (id: string): Promise<AdminApiKeyDetail> => {
    return apiFetch<AdminApiKeyDetail>(`/api/admin/api-keys/${id}`);
  },

  /**
   * Create API key
   */
  createApiKey: async (
    data: CreateAdminApiKeyRequest
  ): Promise<{ apiKey: AdminApiKeyDetail; secret: string }> => {
    return apiFetch(`/api/admin/api-keys`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },

  /**
   * Update API key status
   */
  updateApiKeyStatus: async (
    id: string,
    status: 'active' | 'suspended'
  ): Promise<{ message: string; apiKey: AdminApiKey }> => {
    return apiFetch(`/api/admin/api-keys/${id}/status`, {
      method: 'PUT',
      body: JSON.stringify({ status }),
    });
  },

  /**
   * Update API key permissions
   */
  updateApiKeyPermissions: async (
    id: string,
    permissions: string[]
  ): Promise<{ message: string; apiKey: AdminApiKey }> => {
    return apiFetch(`/api/admin/api-keys/${id}/permissions`, {
      method: 'PUT',
      body: JSON.stringify({ permissions }),
    });
  },

  /**
   * Update API key IP whitelist
   */
  updateApiKeyIpWhitelist: async (
    id: string,
    ipWhitelist: string[] | null
  ): Promise<{ message: string; apiKey: AdminApiKey }> => {
    return apiFetch(`/api/admin/api-keys/${id}/ip-whitelist`, {
      method: 'PUT',
      body: JSON.stringify({ ipWhitelist }),
    });
  },

  /**
   * Delete API key
   */
  deleteApiKey: async (id: string): Promise<{ message: string }> => {
    return apiFetch(`/api/admin/api-keys/${id}`, {
      method: 'DELETE',
    });
  },

  /**
   * Get available merchant permissions
   */
  getMerchantPermissions: async (): Promise<{ permissions: string[] }> => {
    return apiFetch<{ permissions: string[] }>(
      '/api/admin/api-keys/permissions/merchant'
    );
  },
};
