import { useState, useRef, useCallback } from 'react';
import { useNavigate, Link } from 'react-router';
import { useTranslation } from 'react-i18next';
import { Card, Tag, App, Select, DatePicker, Input } from 'antd';
import { ProTable } from '@ant-design/pro-components';
import type { ProColumns, ActionType } from '@ant-design/pro-components';
import dayjs from 'dayjs';

import {
  adminInboundApi,
  type AdminInboundOrder,
  type AdminInboundOrderListParams,
  type InboundOrderStatus,
} from '@/lib/admin-inbound-api';
import { merchantApi, type Merchant } from '@/lib/merchant-api';
import { warehouseApi, type Warehouse } from '@/lib/warehouse-api';

const { RangePicker } = DatePicker;

// Status color mapping
const statusColors: Record<InboundOrderStatus, string> = {
  draft: 'default',
  pending: 'processing',
  shipped: 'cyan',
  arrived: 'blue',
  receiving: 'purple',
  completed: 'success',
  partial_completed: 'warning',
  cancelled: 'error',
};

export function AdminInboundOrdersListPage() {
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
    (status: InboundOrderStatus) => {
      const labels: Record<InboundOrderStatus, string> = {
        draft: t('inventory.statusDraft'),
        pending: t('inventory.statusPending'),
        shipped: t('inventory.statusShipped'),
        arrived: t('inventory.statusArrived'),
        receiving: t('inventory.statusReceiving'),
        completed: t('inventory.statusCompleted'),
        partial_completed: t('inventory.statusPartialCompleted'),
        cancelled: t('inventory.statusCancelled'),
      };
      return labels[status] || status;
    },
    [t]
  );

  const columns: ProColumns<AdminInboundOrder>[] = [
    {
      title: t('inventory.orderNo'),
      dataIndex: 'orderNo',
      width: 180,
      fixed: 'left',
      render: (_, record) => (
        <Link to={`/admin/inbound/orders/${record.id}`} className="text-blue-600 hover:text-blue-800">
          {record.orderNo}
        </Link>
      ),
      renderFormItem: (_, { type }, form) => {
        if (type === 'form') {
          return null;
        }
        return (
          <Input
            placeholder={t('inventory.orderNo')}
            allowClear
            onChange={(e) => {
              form.setFieldValue('search', e.target.value);
            }}
          />
        );
      },
    },
    {
      title: t('adminInbound.merchant'),
      dataIndex: ['merchant', 'name'],
      width: 140,
      ellipsis: true,
      renderFormItem: () => (
        <Select
          placeholder={t('adminInbound.allMerchants')}
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
          placeholder={t('adminInbound.allWarehouses')}
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
      title: t('common.status'),
      dataIndex: 'status',
      width: 120,
      render: (_, record) => (
        <Tag color={statusColors[record.status]}>{getStatusLabel(record.status)}</Tag>
      ),
      renderFormItem: () => (
        <Select
          placeholder={t('common.all')}
          allowClear
          options={[
            { label: t('inventory.statusDraft'), value: 'draft' },
            { label: t('inventory.statusPending'), value: 'pending' },
            { label: t('inventory.statusShipped'), value: 'shipped' },
            { label: t('inventory.statusArrived'), value: 'arrived' },
            { label: t('inventory.statusReceiving'), value: 'receiving' },
            { label: t('inventory.statusCompleted'), value: 'completed' },
            { label: t('inventory.statusPartialCompleted'), value: 'partial_completed' },
            { label: t('inventory.statusCancelled'), value: 'cancelled' },
          ]}
        />
      ),
    },
    {
      title: t('inventory.totalSkuCount'),
      dataIndex: 'totalSkuCount',
      width: 100,
      align: 'center',
      search: false,
    },
    {
      title: t('inventory.quantity'),
      key: 'quantity',
      width: 120,
      align: 'center',
      search: false,
      render: (_, record) => (
        <span>
          <span
            className={
              record.receivedQuantity < record.totalQuantity ? 'text-orange-500' : 'text-green-500'
            }
          >
            {record.receivedQuantity}
          </span>
          <span className="text-gray-400"> / </span>
          <span>{record.totalQuantity}</span>
        </span>
      ),
    },
    {
      title: t('inventory.expectedArrivalDate'),
      dataIndex: 'expectedArrivalDate',
      width: 130,
      search: false,
      render: (date) => (date ? dayjs(date as string).format('YYYY-MM-DD') : '-'),
    },
    {
      title: t('inventory.shippedAt'),
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
      title: t('inventory.trackingNumber'),
      dataIndex: 'trackingNumber',
      hideInTable: true,
      renderFormItem: () => (
        <Input placeholder={t('inventory.searchByTrackingNumber')} allowClear />
      ),
    },
    {
      title: t('common.actions'),
      key: 'actions',
      width: 80,
      fixed: 'right',
      search: false,
      render: (_, record) => (
        <a onClick={() => navigate(`/admin/inbound/orders/${record.id}`)}>
          {t('common.view')}
        </a>
      ),
    },
  ];

  return (
    <Card>
      <ProTable<AdminInboundOrder>
        actionRef={actionRef}
        columns={columns}
        rowKey="id"
        scroll={{ x: 1400 }}
        search={{
          labelWidth: 'auto',
          collapsed: false,
          collapseRender: false,
        }}
        request={async (params) => {
          const apiParams: AdminInboundOrderListParams = {
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
            const result = await adminInboundApi.getInboundOrders(apiParams);
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
        headerTitle={t('adminInbound.title')}
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
