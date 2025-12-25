import { apiFetch } from './api-client';

// ============ Common Types ============

export interface PaginatedMeta {
  total: number;
  page: number;
  limit: number;
  pages: number;
}

// ============ Inbound Order Types ============

export type WarehouseInboundStatus =
  | 'draft'
  | 'pending'
  | 'shipped'
  | 'arrived'
  | 'receiving'
  | 'completed'
  | 'partial_completed'
  | 'cancelled';

export interface WarehouseInboundOrder {
  id: string;
  orderNo: string;
  status: WarehouseInboundStatus;
  merchant: {
    id: string;
    companyName: string;
  };
  totalSkuCount: number;
  totalQuantity: number;
  receivedQuantity: number;
  expectedArrivalDate: string | null;
  shippedAt: string | null;
  completedAt: string | null;
  createdAt: string;
}

export interface WarehouseInboundItem {
  id: string;
  productSku: {
    id: string | null;
    skuName: string | null;
    colorName: string | null;
  };
  styleNumber: string | null;
  productName: string | null;
  productImage: string | null;
  expectedQuantity: number;
  receivedQuantity: number;
  damagedQuantity: number;
  unitCost: string;
  status: string;
  warehouseRemark: string | null;
  receivedAt: string | null;
}

export interface WarehouseInboundShipment {
  id: string;
  carrierCode: string;
  carrierName: string | null;
  trackingNumber: string;
  status: string;
  senderName: string;
  senderPhone: string;
  senderAddress: string;
  boxCount: number;
  totalWeight: string | null;
  shippedAt: string;
  estimatedArrivalDate: string | null;
  deliveredAt: string | null;
}

export interface WarehouseInboundException {
  id: string;
  exceptionNo: string;
  type: string;
  typeLabel: string;
  status: string;
  items: {
    id: string;
    skuName: string;
    colorName: string;
    productName: string;
    productImage: string | null;
    quantity: number;
  }[];
  totalQuantity: number;
  description: string;
  evidenceImages: string[];
  resolution: string | null;
  resolutionNotes: string | null;
  resolvedAt: string | null;
  createdAt: string;
}

export interface WarehouseInboundOrderDetail extends WarehouseInboundOrder {
  merchantNotes: string | null;
  warehouseNotes: string | null;
  items: WarehouseInboundItem[];
  shipment: WarehouseInboundShipment | null;
  exceptions: WarehouseInboundException[];
}

export interface WarehouseInboundListParams {
  page?: number;
  limit?: number;
  status?: WarehouseInboundStatus;
  orderNo?: string;
}

export interface WarehouseInboundListResponse {
  data: WarehouseInboundOrder[];
  meta: PaginatedMeta;
}

export interface WarehouseInboundStats {
  awaitingArrival: number;
  pendingReceiving: number;
  completedToday: number;
}

export interface WarehouseOutboundStats {
  pendingPicking: number;
  pendingPacking: number;
  readyToShip: number;
  shippedToday: number;
}

export interface WarehouseDashboardTrendItem {
  date: string;
  inboundCount: number;
  outboundCount: number;
}

export interface CompleteReceivingItem {
  itemId: string;
  receivedQuantity: number;
  damagedQuantity?: number;
  warehouseRemark?: string;
}

export interface CompleteReceivingRequest {
  items: CompleteReceivingItem[];
  notes?: string;
}

export interface CreateExceptionItem {
  inboundOrderItemId: string;
  quantity: number;
}

export interface CreateExceptionRequest {
  type: string;
  items: CreateExceptionItem[];
  description: string;
  evidenceImages?: string[];
}

// ============ Outbound Order Types ============

export type WarehouseOutboundStatus =
  | 'pending'
  | 'picking'
  | 'packing'
  | 'ready'
  | 'shipped'
  | 'delivered'
  | 'cancelled';

export type OutboundSyncStatus = 'pending' | 'synced' | 'failed';

export interface WarehouseOutboundOrder {
  id: string;
  outboundNo: string;
  outboundType: string;
  status: WarehouseOutboundStatus;
  syncStatus: OutboundSyncStatus;
  receiverName: string;
  receiverPhone: string;
  receiverAddress: string;
  totalQuantity: number;
  shippingCarrier: string | null;
  trackingNumber: string | null;
  shippedAt: string | null;
  createdAt: string;
}

export interface WarehouseOutboundItem {
  id: string;
  productSkuId: string | null;
  skuName: string | null;
  productName: string | null;
  quantity: number;
  pickedQuantity: number;
}

export interface WarehouseOutboundOrderDetail extends WarehouseOutboundOrder {
  externalId: string | null;
  remark: string | null;
  cancelReason: string | null;
  pickingStartedAt: string | null;
  pickingCompletedAt: string | null;
  packingStartedAt: string | null;
  packingCompletedAt: string | null;
  items: WarehouseOutboundItem[];
}

export interface WarehouseOutboundListParams {
  page?: number;
  limit?: number;
  status?: WarehouseOutboundStatus;
}

export interface WarehouseOutboundListResponse {
  data: WarehouseOutboundOrder[];
  meta: PaginatedMeta;
}

export interface ShipOrderRequest {
  carrier: string;
  trackingNumber: string;
}

// ============ Inventory Types ============

export interface WarehouseInventoryItem {
  id: string;
  merchant: {
    id: string;
    companyName: string;
  };
  product: {
    id: string;
    name: string;
    styleNumber: string | null;
    color: string | null;
    primaryImage: string | null;
  } | null;
  sku: {
    id: string;
    skuName: string;
    sizeUnit: string | null;
    sizeValue: string | null;
  } | null;
  quantityInTransit: number;
  quantityAvailable: number;
  quantityReserved: number;
  quantityDamaged: number;
  quantityAllocated: number;
  averageCost: string;
  safetyStock: number;
  updatedAt: string;
}

export interface WarehouseInventorySummary {
  warehouse: {
    id: string;
    name: string;
    code: string;
  };
  totalInTransit: number;
  totalAvailable: number;
  totalReserved: number;
  totalDamaged: number;
  totalSkuCount: number;
}

export interface WarehouseInventoryListParams {
  page?: number;
  limit?: number;
  search?: string;
  styleNumber?: string;
  hasStock?: boolean;
}

export interface WarehouseInventoryListResponse {
  data: WarehouseInventoryItem[];
  meta: PaginatedMeta;
}

// ============ API Client ============

export const warehouseOpsApi = {
  // ============ Inbound Orders ============

  // Get inbound statistics
  async getInboundStats(): Promise<{ data: WarehouseInboundStats }> {
    return apiFetch<{ data: WarehouseInboundStats }>('/api/warehouse/inbound/stats');
  },

  // Get inbound orders list
  async getInboundOrders(params: WarehouseInboundListParams = {}): Promise<WarehouseInboundListResponse> {
    const searchParams = new URLSearchParams();
    if (params.page) searchParams.set('page', String(params.page));
    if (params.limit) searchParams.set('limit', String(params.limit));
    if (params.status) searchParams.set('status', params.status);
    if (params.orderNo) searchParams.set('orderNo', params.orderNo);

    const queryString = searchParams.toString();
    return apiFetch<WarehouseInboundListResponse>(
      `/api/warehouse/inbound/orders${queryString ? `?${queryString}` : ''}`
    );
  },

  // Get inbound order detail
  async getInboundOrder(id: string): Promise<{ data: WarehouseInboundOrderDetail }> {
    return apiFetch<{ data: WarehouseInboundOrderDetail }>(`/api/warehouse/inbound/orders/${id}`);
  },

  // Complete receiving
  async completeReceiving(
    id: string,
    data: CompleteReceivingRequest
  ): Promise<{ message: string; data: WarehouseInboundOrderDetail }> {
    return apiFetch<{ message: string; data: WarehouseInboundOrderDetail }>(
      `/api/warehouse/inbound/orders/${id}/receive`,
      {
        method: 'POST',
        body: JSON.stringify(data),
      }
    );
  },

  // Create exception
  async createException(
    orderId: string,
    data: CreateExceptionRequest
  ): Promise<{ message: string; data: WarehouseInboundException }> {
    return apiFetch<{ message: string; data: WarehouseInboundException }>(
      `/api/warehouse/inbound/orders/${orderId}/exceptions`,
      {
        method: 'POST',
        body: JSON.stringify(data),
      }
    );
  },

  // Update warehouse notes
  async updateWarehouseNotes(
    id: string,
    notes: string
  ): Promise<{ message: string; data: WarehouseInboundOrderDetail }> {
    return apiFetch<{ message: string; data: WarehouseInboundOrderDetail }>(
      `/api/warehouse/inbound/orders/${id}/notes`,
      {
        method: 'PUT',
        body: JSON.stringify({ notes }),
      }
    );
  },

  // ============ Outbound Orders ============

  // Get outbound statistics
  async getOutboundStats(): Promise<{ data: WarehouseOutboundStats }> {
    return apiFetch<{ data: WarehouseOutboundStats }>('/api/warehouse/outbound/stats');
  },

  // Get outbound orders list
  async getOutboundOrders(params: WarehouseOutboundListParams = {}): Promise<WarehouseOutboundListResponse> {
    const searchParams = new URLSearchParams();
    if (params.page) searchParams.set('page', String(params.page));
    if (params.limit) searchParams.set('limit', String(params.limit));
    if (params.status) searchParams.set('status', params.status);

    const queryString = searchParams.toString();
    return apiFetch<WarehouseOutboundListResponse>(
      `/api/warehouse/outbound/orders${queryString ? `?${queryString}` : ''}`
    );
  },

  // Get outbound order detail
  async getOutboundOrder(id: string): Promise<{ data: WarehouseOutboundOrderDetail }> {
    return apiFetch<{ data: WarehouseOutboundOrderDetail }>(`/api/warehouse/outbound/orders/${id}`);
  },

  // Start picking
  async startPicking(id: string): Promise<{ message: string; data: WarehouseOutboundOrderDetail }> {
    return apiFetch<{ message: string; data: WarehouseOutboundOrderDetail }>(
      `/api/warehouse/outbound/orders/${id}/start-picking`,
      { method: 'POST' }
    );
  },

  // Start packing
  async startPacking(id: string): Promise<{ message: string; data: WarehouseOutboundOrderDetail }> {
    return apiFetch<{ message: string; data: WarehouseOutboundOrderDetail }>(
      `/api/warehouse/outbound/orders/${id}/start-packing`,
      { method: 'POST' }
    );
  },

  // Complete packing
  async completePacking(id: string): Promise<{ message: string; data: WarehouseOutboundOrderDetail }> {
    return apiFetch<{ message: string; data: WarehouseOutboundOrderDetail }>(
      `/api/warehouse/outbound/orders/${id}/complete-packing`,
      { method: 'POST' }
    );
  },

  // Ship order
  async shipOrder(
    id: string,
    data: ShipOrderRequest
  ): Promise<{ message: string; data: WarehouseOutboundOrderDetail }> {
    return apiFetch<{ message: string; data: WarehouseOutboundOrderDetail }>(
      `/api/warehouse/outbound/orders/${id}/ship`,
      {
        method: 'POST',
        body: JSON.stringify(data),
      }
    );
  },

  // ============ Inventory ============

  // Get inventory list
  async getInventory(params: WarehouseInventoryListParams = {}): Promise<WarehouseInventoryListResponse> {
    const searchParams = new URLSearchParams();
    if (params.page) searchParams.set('page', String(params.page));
    if (params.limit) searchParams.set('limit', String(params.limit));
    if (params.search) searchParams.set('search', params.search);
    if (params.styleNumber) searchParams.set('styleNumber', params.styleNumber);
    if (params.hasStock !== undefined) searchParams.set('hasStock', String(params.hasStock));

    const queryString = searchParams.toString();
    return apiFetch<WarehouseInventoryListResponse>(
      `/api/warehouse/inventory${queryString ? `?${queryString}` : ''}`
    );
  },

  // Get inventory summary
  async getInventorySummary(): Promise<{ data: WarehouseInventorySummary }> {
    return apiFetch<{ data: WarehouseInventorySummary }>('/api/warehouse/inventory/summary');
  },

  // ============ Dashboard ============

  // Get dashboard trend data (7-day)
  async getDashboardTrend(): Promise<{ data: WarehouseDashboardTrendItem[] }> {
    return apiFetch<{ data: WarehouseDashboardTrendItem[] }>('/api/warehouse/dashboard/trend');
  },
};