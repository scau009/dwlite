import { useRef, useState, useEffect } from 'react';
import { useNavigate } from 'react-router';
import { useTranslation } from 'react-i18next';
import { ProTable, type ActionType, type ProColumns } from '@ant-design/pro-components';
import { Button, Tag, Space, Tooltip } from 'antd';
import { WarningOutlined } from '@ant-design/icons';

import {
  fulfillmentApi,
  type Fulfillment,
  type FulfillmentStatus,
  type FulfillmentType,
} from '@/lib/fulfillment-api';
import { merchantApi, type Merchant } from '@/lib/merchant-api';
import { warehouseApi, type Warehouse } from '@/lib/warehouse-api';

// Fulfillment status color mapping
const statusColors: Record<FulfillmentStatus, string> = {
  pending: 'warning',
  processing: 'processing',
  shipped: 'blue',
  delivered: 'geekblue',
  completed: 'success',
  cancelled: 'default',
  rejected: 'error',
  expired: 'orange',
};

// Fulfillment type color mapping
const typeColors: Record<FulfillmentType, string> = {
  platform_warehouse: 'cyan',
  merchant_warehouse: 'purple',
};

export function FulfillmentOrdersListPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const actionRef = useRef<ActionType>(null);
  const [merchants, setMerchants] = useState<Merchant[]>([]);
  const [warehouses, setWarehouses] = useState<Warehouse[]>([]);

  // Load merchants and warehouses for filters
  useEffect(() => {
    merchantApi.getMerchants({ limit: 100 }).then((res) => {
      setMerchants(res.data);
    });
    warehouseApi.getWarehouses({ limit: 100 }).then((res) => {
      setWarehouses(res.data);
    });
  }, []);

  // Handle view detail
  const handleView = (fulfillment: Fulfillment) => {
    navigate(`/fulfillment/fulfillment-orders/${fulfillment.id}`);
  };

  // Handle view related order
  const handleViewOrder = (orderId: string) => {
    navigate(`/fulfillment/orders/${orderId}`);
  };

  // Build value enums for filters
  const merchantValueEnum = merchants.reduce(
    (acc, merchant) => {
      acc[merchant.id] = { text: merchant.name };
      return acc;
    },
    {} as Record<string, { text: string }>
  );

  const warehouseValueEnum = warehouses.reduce(
    (acc, warehouse) => {
      acc[warehouse.id] = { text: warehouse.name };
      return acc;
    },
    {} as Record<string, { text: string }>
  );

  const columns: ProColumns<Fulfillment>[] = [
    {
      title: t('fulfillmentOrder.fulfillmentNo'),
      dataIndex: 'fulfillmentNo',
      width: 180,
      ellipsis: true,
      copyable: true,
      render: (_, record) => (
        <a onClick={() => handleView(record)} className="font-medium">
          {record.fulfillmentNo}
        </a>
      ),
    },
    {
      title: t('fulfillmentOrder.relatedOrder'),
      dataIndex: ['order', 'orderNo'],
      width: 180,
      ellipsis: true,
      copyable: true,
      render: (_, record) => (
        <a onClick={() => handleViewOrder(record.order.id)} className="font-medium">
          {record.order.orderNo}
        </a>
      ),
    },
    {
      title: t('fulfillmentOrder.type'),
      dataIndex: 'fulfillmentType',
      width: 120,
      valueType: 'select',
      valueEnum: {
        platform_warehouse: { text: t('fulfillmentOrder.typePlatform') },
        merchant_warehouse: { text: t('fulfillmentOrder.typeMerchant') },
      },
      render: (_, record) => (
        <Tag color={typeColors[record.fulfillmentType]}>{record.fulfillmentTypeLabel}</Tag>
      ),
    },
    {
      title: t('common.status'),
      dataIndex: 'status',
      width: 100,
      valueType: 'select',
      valueEnum: {
        pending: { text: t('fulfillmentOrder.statusPending') },
        processing: { text: t('fulfillmentOrder.statusProcessing') },
        shipped: { text: t('fulfillmentOrder.statusShipped') },
        delivered: { text: t('fulfillmentOrder.statusDelivered') },
        completed: { text: t('fulfillmentOrder.statusCompleted') },
        cancelled: { text: t('fulfillmentOrder.statusCancelled') },
        rejected: { text: t('fulfillmentOrder.statusRejected') },
        expired: { text: t('fulfillmentOrder.statusExpired') },
      },
      render: (_, record) => (
        <Space>
          <Tag color={statusColors[record.status]}>{record.statusLabel}</Tag>
          {record.isOverdue && record.status === 'pending' && (
            <Tooltip title={t('fulfillmentOrder.overdueWarning')}>
              <WarningOutlined className="text-orange-500" />
            </Tooltip>
          )}
        </Space>
      ),
    },
    {
      title: t('common.merchant'),
      dataIndex: 'merchantId',
      width: 140,
      valueType: 'select',
      valueEnum: merchantValueEnum,
      render: (_, record) => record.merchant?.name || '-',
    },
    {
      title: t('common.warehouse'),
      dataIndex: 'warehouseId',
      width: 140,
      valueType: 'select',
      valueEnum: warehouseValueEnum,
      render: (_, record) => record.warehouse.name,
    },
    {
      title: t('fulfillmentOrder.itemCount'),
      dataIndex: 'itemCount',
      width: 80,
      search: false,
      align: 'center',
    },
    {
      title: t('fulfillmentOrder.totalQuantity'),
      dataIndex: 'totalQuantity',
      width: 80,
      search: false,
      align: 'center',
    },
    {
      title: t('fulfillmentOrder.logistics'),
      key: 'logistics',
      width: 160,
      search: false,
      render: (_, record) => {
        if (!record.shippingCarrier && !record.trackingNumber) {
          return <span className="text-gray-400">-</span>;
        }
        return (
          <div className="text-sm">
            {record.shippingCarrier && <div>{record.shippingCarrier}</div>}
            {record.trackingNumber && (
              <div className="text-gray-500">{record.trackingNumber}</div>
            )}
          </div>
        );
      },
    },
    {
      title: t('fulfillmentOrder.deadline'),
      dataIndex: 'deadlineAt',
      width: 160,
      search: false,
      render: (_, record) => {
        if (!record.deadlineAt) return '-';
        const isOverdue = record.isOverdue;
        return (
          <span className={isOverdue ? 'text-red-500' : ''}>
            {new Date(record.deadlineAt).toLocaleString()}
          </span>
        );
      },
    },
    {
      title: t('common.createdAt'),
      dataIndex: 'createdAt',
      width: 160,
      valueType: 'dateRange',
      search: {
        transform: (value) => ({
          startDate: value?.[0],
          endDate: value?.[1],
        }),
      },
      render: (_, record) => new Date(record.createdAt).toLocaleString(),
    },
    {
      title: t('common.actions'),
      valueType: 'option',
      width: 100,
      fixed: 'right',
      render: (_, record) => (
        <Space size="small">
          <Button type="link" size="small" onClick={() => handleView(record)}>
            {t('common.view')}
          </Button>
        </Space>
      ),
    },
  ];

  return (
    <div className="space-y-4">
      <ProTable<Fulfillment>
        actionRef={actionRef}
        columns={columns}
        rowKey="id"
        request={async (params) => {
          try {
            const result = await fulfillmentApi.getList({
              page: params.current,
              limit: params.pageSize,
              status: params.status,
              fulfillmentType: params.fulfillmentType,
              merchantId: params.merchantId,
              warehouseId: params.warehouseId,
              search: params.fulfillmentNo,
              startDate: params.startDate,
              endDate: params.endDate,
            });
            return {
              data: result.items,
              success: true,
              total: result.total,
            };
          } catch (error) {
            console.error('Failed to fetch fulfillments:', error);
            return {
              data: [],
              success: false,
              total: 0,
            };
          }
        }}
        search={{
          labelWidth: 'auto',
          defaultCollapsed: false,
        }}
        options={{
          density: true,
          fullScreen: true,
          reload: true,
        }}
        pagination={{
          defaultPageSize: 20,
          showSizeChanger: true,
        }}
        scroll={{ x: 1600 }}
      />
    </div>
  );
}
