import { useState, useRef, useCallback } from 'react';
import { useNavigate, Link } from 'react-router';
import { useTranslation } from 'react-i18next';
import { Card, Tag, App, Select, DatePicker, Input } from 'antd';
import { ProTable } from '@ant-design/pro-components';
import type { ProColumns, ActionType } from '@ant-design/pro-components';
import dayjs from 'dayjs';

import {
  adminOutboundApi,
  type AdminOutboundOrder,
  type AdminOutboundOrderListParams,
  type OutboundOrderStatus,
  type OutboundOrderType,
} from '@/lib/admin-outbound-api';
import { merchantApi, type Merchant } from '@/lib/merchant-api';
import { warehouseApi, type Warehouse } from '@/lib/warehouse-api';

const { RangePicker } = DatePicker;

// Status color mapping
const statusColors: Record<OutboundOrderStatus, string> = {
  draft: 'default',
  pending: 'processing',
  picking: 'purple',
  packing: 'cyan',
  ready: 'blue',
  shipped: 'success',
  cancelled: 'error',
};

// Outbound type color mapping
const typeColors: Record<OutboundOrderType, string> = {
  sales: 'blue',
  return_to_merchant: 'orange',
  transfer: 'cyan',
  scrap: 'red',
};

export function AdminOutboundOrdersListPage() {
  const navigate = useNavigate();
  const { t } = useTranslation();
  const { message } = App.useApp();
  const actionRef = useRef<ActionType>(null);

  // Remote search state for merchants
  const [merchantOptions, setMerchantOptions] = useState<{ label: string; value: string }[]>([]);
  const [merchantLoading, setMerchantLoading] = useState(false);
  const merchantSearchTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Remote search state for warehouses
  const [warehouseOptions, setWarehouseOptions] = useState<{ label: string; value: string }[]>([]);
  const [warehouseLoading, setWarehouseLoading] = useState(false);
  const warehouseSearchTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Search merchants with debounce
  const searchMerchants = useCallback(async (searchText: string) => {
    if (merchantSearchTimer.current) {
      clearTimeout(merchantSearchTimer.current);
    }
    merchantSearchTimer.current = setTimeout(async () => {
      setMerchantLoading(true);
      try {
        const res = await merchantApi.getMerchants({
          limit: 50,
          status: 'approved',
          name: searchText || undefined,
        });
        setMerchantOptions(
          (res.data || []).map((m: Merchant) => ({ label: m.name, value: m.id }))
        );
      } catch {
        console.warn('Failed to search merchants');
      } finally {
        setMerchantLoading(false);
      }
    }, 300);
  }, []);

  // Search warehouses with debounce
  const searchWarehouses = useCallback(async (searchText: string) => {
    if (warehouseSearchTimer.current) {
      clearTimeout(warehouseSearchTimer.current);
    }
    warehouseSearchTimer.current = setTimeout(async () => {
      setWarehouseLoading(true);
      try {
        const res = await warehouseApi.getWarehouses({
          limit: 50,
          category: 'platform',
          name: searchText || undefined,
        });
        setWarehouseOptions(
          (res.data || []).map((w: Warehouse) => ({ label: w.name, value: w.id }))
        );
      } catch {
        console.warn('Failed to search warehouses');
      } finally {
        setWarehouseLoading(false);
      }
    }, 300);
  }, []);

  // Get status label
  const getStatusLabel = useCallback(
    (status: OutboundOrderStatus) => {
      const labels: Record<OutboundOrderStatus, string> = {
        draft: t('outbound.statusDraft'),
        pending: t('outbound.statusPending'),
        picking: t('outbound.statusPicking'),
        packing: t('outbound.statusPacking'),
        ready: t('outbound.statusReady'),
        shipped: t('outbound.statusShipped'),
        cancelled: t('outbound.statusCancelled'),
      };
      return labels[status] || status;
    },
    [t]
  );

  // Get outbound type label
  const getOutboundTypeLabel = useCallback(
    (type: OutboundOrderType) => {
      const labels: Record<OutboundOrderType, string> = {
        sales: t('adminOutbound.typeSales'),
        return_to_merchant: t('adminOutbound.typeReturnToMerchant'),
        transfer: t('adminOutbound.typeTransfer'),
        scrap: t('adminOutbound.typeScrap'),
      };
      return labels[type] || type;
    },
    [t]
  );

  const columns: ProColumns<AdminOutboundOrder>[] = [
    {
      title: t('outbound.outboundNo'),
      dataIndex: 'outboundNo',
      width: 180,
      fixed: 'left',
      render: (_, record) => (
        <Link to={`/admin/outbound/orders/${record.id}`} className="text-blue-600 hover:text-blue-800">
          {record.outboundNo}
        </Link>
      ),
      renderFormItem: (_, { type }, form) => {
        if (type === 'form') {
          return null;
        }
        return (
          <Input
            placeholder={t('adminOutbound.searchOutboundNo')}
            allowClear
            onChange={(e) => {
              form.setFieldValue('search', e.target.value);
            }}
          />
        );
      },
    },
    {
      title: t('adminOutbound.merchant'),
      dataIndex: ['merchant', 'name'],
      width: 140,
      ellipsis: true,
      renderFormItem: () => (
        <Select
          placeholder={t('adminOutbound.allMerchants')}
          allowClear
          showSearch
          filterOption={false}
          loading={merchantLoading}
          options={merchantOptions}
          onSearch={searchMerchants}
          onFocus={() => searchMerchants('')}
          notFoundContent={merchantLoading ? t('common.loading') : t('common.noData')}
        />
      ),
    },
    {
      title: t('inventory.warehouse'),
      dataIndex: ['warehouse', 'name'],
      width: 140,
      ellipsis: true,
      renderFormItem: () => (
        <Select
          placeholder={t('adminOutbound.allWarehouses')}
          allowClear
          showSearch
          filterOption={false}
          loading={warehouseLoading}
          options={warehouseOptions}
          onSearch={searchWarehouses}
          onFocus={() => searchWarehouses('')}
          notFoundContent={warehouseLoading ? t('common.loading') : t('common.noData')}
        />
      ),
    },
    {
      title: t('adminOutbound.outboundType'),
      dataIndex: 'outboundType',
      width: 120,
      render: (_, record) => (
        <Tag color={typeColors[record.outboundType]}>{getOutboundTypeLabel(record.outboundType)}</Tag>
      ),
      renderFormItem: () => (
        <Select
          placeholder={t('common.all')}
          allowClear
          options={[
            { label: t('adminOutbound.typeSales'), value: 'sales' },
            { label: t('adminOutbound.typeReturnToMerchant'), value: 'return_to_merchant' },
            { label: t('adminOutbound.typeTransfer'), value: 'transfer' },
            { label: t('adminOutbound.typeScrap'), value: 'scrap' },
          ]}
        />
      ),
    },
    {
      title: t('common.status'),
      dataIndex: 'status',
      width: 100,
      render: (_, record) => (
        <Tag color={statusColors[record.status]}>{getStatusLabel(record.status)}</Tag>
      ),
      renderFormItem: () => (
        <Select
          placeholder={t('common.all')}
          allowClear
          options={[
            { label: t('outbound.statusDraft'), value: 'draft' },
            { label: t('outbound.statusPending'), value: 'pending' },
            { label: t('outbound.statusPicking'), value: 'picking' },
            { label: t('outbound.statusPacking'), value: 'packing' },
            { label: t('outbound.statusReady'), value: 'ready' },
            { label: t('outbound.statusShipped'), value: 'shipped' },
            { label: t('outbound.statusCancelled'), value: 'cancelled' },
          ]}
        />
      ),
    },
    {
      title: t('outbound.receiverName'),
      dataIndex: 'receiverName',
      width: 120,
      ellipsis: true,
      search: false,
    },
    {
      title: t('inventory.quantity'),
      dataIndex: 'totalQuantity',
      width: 80,
      align: 'center',
      search: false,
    },
    {
      title: t('inventory.carrier'),
      dataIndex: 'shippingCarrier',
      width: 100,
      ellipsis: true,
      search: false,
      render: (_, record) => record.shippingCarrier || '-',
    },
    {
      title: t('inventory.trackingNumber'),
      dataIndex: 'trackingNumber',
      hideInTable: true,
      renderFormItem: () => (
        <Input placeholder={t('inventory.searchByTrackingNumber')} allowClear />
      ),
    },
    {
      title: t('outbound.shippedAt'),
      dataIndex: 'shippedAt',
      width: 170,
      search: false,
      render: (date) => (date ? dayjs(date as string).format('YYYY-MM-DD HH:mm') : '-'),
    },
    {
      title: t('common.createdAt'),
      dataIndex: 'createdAt',
      width: 170,
      render: (date) => dayjs(date as string).format('YYYY-MM-DD HH:mm'),
      renderFormItem: () => <RangePicker style={{ width: '100%' }} />,
    },
    {
      title: t('common.actions'),
      key: 'actions',
      width: 80,
      fixed: 'right',
      search: false,
      render: (_, record) => (
        <a onClick={() => navigate(`/admin/outbound/orders/${record.id}`)}>
          {t('common.view')}
        </a>
      ),
    },
  ];

  return (
    <Card>
      <ProTable<AdminOutboundOrder>
        actionRef={actionRef}
        columns={columns}
        rowKey="id"
        scroll={{ x: 1500 }}
        search={{
          labelWidth: 'auto',
          collapsed: false,
          collapseRender: false,
        }}
        request={async (params) => {
          const apiParams: AdminOutboundOrderListParams = {
            page: params.current || 1,
            limit: params.pageSize || 20,
          };

          // Map form fields to API params
          if (params.search) {
            apiParams.search = params.search;
          }
          if (params['merchant.name']) {
            apiParams.merchantId = params['merchant.name'];
          }
          if (params['warehouse.name']) {
            apiParams.warehouseId = params['warehouse.name'];
          }
          if (params.outboundType) {
            apiParams.outboundType = params.outboundType;
          }
          if (params.status) {
            apiParams.status = params.status;
          }
          if (params.trackingNumber) {
            apiParams.trackingNumber = params.trackingNumber;
          }
          // Date range handling
          if (params.createdAt && Array.isArray(params.createdAt) && params.createdAt.length === 2) {
            apiParams.startDate = dayjs(params.createdAt[0]).format('YYYY-MM-DD');
            apiParams.endDate = dayjs(params.createdAt[1]).format('YYYY-MM-DD');
          }

          try {
            const result = await adminOutboundApi.getOutboundOrders(apiParams);
            return {
              data: result.items,
              total: result.total,
              success: true,
            };
          } catch {
            message.error(t('common.loadError'));
            return {
              data: [],
              total: 0,
              success: false,
            };
          }
        }}
        pagination={{
          showSizeChanger: true,
          showQuickJumper: true,
          defaultPageSize: 20,
        }}
        dateFormatter="string"
        headerTitle={t('adminOutbound.title')}
        options={{
          density: true,
          fullScreen: true,
          reload: () => actionRef.current?.reload(),
          setting: true,
        }}
      />
    </Card>
  );
}
