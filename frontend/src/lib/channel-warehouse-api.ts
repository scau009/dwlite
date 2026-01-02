import { apiFetch } from './api-client';

// Types
export interface ChannelWarehouse {
  id: string;
  salesChannelId: string;
  warehouseId: string;
  warehouse: {
    id: string;
    code: string;
    name: string;
    type: 'self' | 'third_party' | 'bonded' | 'overseas';
    countryCode: string;
    status: 'active' | 'maintenance' | 'disabled';
  };
  priority: number;
  status: 'active' | 'disabled';
  remark: string | null;
  createdAt: string;
  updatedAt: string;
}

export interface Warehouse {
  id: string;
  code: string;
  name: string;
  type: 'self' | 'third_party' | 'bonded' | 'overseas';
  countryCode: string;
  status: 'active' | 'maintenance' | 'disabled';
}

export interface AddChannelWarehouseParams {
  warehouseId: string;
  priority?: number;
  remark?: string;
}

export interface UpdateChannelWarehouseParams {
  priority?: number;
  status?: 'active' | 'disabled';
  remark?: string;
}

export interface BatchAddWarehousesParams {
  warehouseIds: string[];
}

export interface UpdatePrioritiesParams {
  items: Array<{ id: string; priority: number }>;
}

// API
export const channelWarehouseApi = {
  /**
   * Get channel warehouses
   */
  getChannelWarehouses: async (channelId: string): Promise<{ data: ChannelWarehouse[] }> => {
    return apiFetch(`/api/admin/sales-channels/${channelId}/warehouses`);
  },

  /**
   * Get available warehouses (not yet configured)
   */
  getAvailableWarehouses: async (channelId: string): Promise<{ data: Warehouse[] }> => {
    return apiFetch(`/api/admin/sales-channels/${channelId}/warehouses/available`);
  },

  /**
   * Add single warehouse
   */
  addWarehouse: async (
    channelId: string,
    data: AddChannelWarehouseParams
  ): Promise<{ message: string; data: ChannelWarehouse }> => {
    return apiFetch(`/api/admin/sales-channels/${channelId}/warehouses`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },

  /**
   * Remove warehouse
   */
  removeWarehouse: async (channelId: string, id: string): Promise<{ message: string }> => {
    return apiFetch(`/api/admin/sales-channels/${channelId}/warehouses/${id}`, {
      method: 'DELETE',
    });
  },

  /**
   * Update warehouse config
   */
  updateWarehouse: async (
    channelId: string,
    id: string,
    data: UpdateChannelWarehouseParams
  ): Promise<{ message: string; data: ChannelWarehouse }> => {
    return apiFetch(`/api/admin/sales-channels/${channelId}/warehouses/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  },

  /**
   * Batch add warehouses
   */
  batchAddWarehouses: async (
    channelId: string,
    data: BatchAddWarehousesParams
  ): Promise<{ message: string; added: number; skipped: number; data: ChannelWarehouse[] }> => {
    return apiFetch(`/api/admin/sales-channels/${channelId}/warehouses/batch`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },

  /**
   * Update priorities (for drag-drop)
   */
  updatePriorities: async (
    channelId: string,
    data: UpdatePrioritiesParams
  ): Promise<{ message: string; data: ChannelWarehouse[] }> => {
    return apiFetch(`/api/admin/sales-channels/${channelId}/warehouses/priorities`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  },
};
