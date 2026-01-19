import { useRef, useState, useEffect } from 'react';
import { useNavigate } from 'react-router';
import { useTranslation } from 'react-i18next';
import { ProTable, type ActionType, type ProColumns } from '@ant-design/pro-components';
import { Button, Tag, Space, Badge } from 'antd';
import { ExclamationCircleOutlined } from '@ant-design/icons';

import {
  platformOrderApi,
  type PlatformOrder,
  type OrderStatus,
  type PaymentStatus,
} from '@/lib/platform-order-api';
import { channelApi, type SalesChannel } from '@/lib/channel-api';

// Order status color mapping
const orderStatusColors: Record<OrderStatus, string> = {
  pending: 'warning',
  allocating: 'processing',
  allocated: 'cyan',
  allocation_failed: 'error',
  fulfilling: 'processing',
  shipped: 'blue',
  delivered: 'geekblue',
  completed: 'success',
  cancelled: 'default',
};

// Payment status color mapping
const paymentStatusColors: Record<PaymentStatus, string> = {
  pending: 'default',
  paid: 'success',
  refunded: 'error',
  partial_refunded: 'warning',
};

export function PlatformOrdersListPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const actionRef = useRef<ActionType>(null);
  const [channels, setChannels] = useState<SalesChannel[]>([]);

  // Load sales channels for filter
  useEffect(() => {
    channelApi.getChannels({ limit: 100 }).then((res) => {
      setChannels(res.data);
    });
  }, []);

  // Handle view detail
  const handleView = (order: PlatformOrder) => {
    navigate(`/fulfillment/orders/${order.id}`);
  };

  // Build channel value enum for filter
  const channelValueEnum = channels.reduce(
    (acc, channel) => {
      acc[channel.id] = { text: channel.name };
      return acc;
    },
    {} as Record<string, { text: string }>
  );

  const columns: ProColumns<PlatformOrder>[] = [
    {
      title: t('fulfillment.platformOrderNo'),
      dataIndex: 'orderNo',
      width: 180,
      ellipsis: true,
      copyable: true,
      render: (_, record) => (
        <a onClick={() => handleView(record)} className="font-medium">
          {record.orderNo}
        </a>
      ),
    },
    {
      title: t('fulfillment.externalOrderNo'),
      dataIndex: 'externalOrderNo',
      width: 180,
      search: false,
      ellipsis: true,
      copyable: true,
      render: (_, record) => record.externalOrderNo || '-',
    },
    {
      title: t('fulfillment.salesChannel'),
      dataIndex: 'salesChannelId',
      width: 120,
      valueType: 'select',
      valueEnum: channelValueEnum,
      render: (_, record) => (
        <Tag>{record.salesChannel.name}</Tag>
      ),
    },
    {
      title: t('common.status'),
      dataIndex: 'status',
      width: 100,
      valueType: 'select',
      valueEnum: {
        pending: { text: t('fulfillment.orderStatusPending') },
        allocating: { text: t('fulfillment.orderStatusAllocating') },
        allocated: { text: t('fulfillment.orderStatusAllocated') },
        allocation_failed: { text: t('fulfillment.orderStatusAllocationFailed') },
        fulfilling: { text: t('fulfillment.orderStatusFulfilling') },
        shipped: { text: t('fulfillment.orderStatusShipped') },
        delivered: { text: t('fulfillment.orderStatusDelivered') },
        completed: { text: t('fulfillment.orderStatusCompleted') },
        cancelled: { text: t('fulfillment.orderStatusCancelled') },
      },
      render: (_, record) => (
        <Tag color={orderStatusColors[record.status]}>{record.statusLabel}</Tag>
      ),
    },
    {
      title: t('fulfillment.paymentStatus'),
      dataIndex: 'paymentStatus',
      width: 100,
      valueType: 'select',
      valueEnum: {
        pending: { text: t('fulfillment.paymentStatusPending') },
        paid: { text: t('fulfillment.paymentStatusPaid') },
        refunded: { text: t('fulfillment.paymentStatusRefunded') },
        partial_refunded: { text: t('fulfillment.paymentStatusPartialRefunded') },
      },
      render: (_, record) => (
        <Tag color={paymentStatusColors[record.paymentStatus]}>{record.paymentStatusLabel}</Tag>
      ),
    },
    {
      title: t('fulfillment.orderAmount'),
      dataIndex: 'totalAmount',
      width: 120,
      search: false,
      render: (_, record) => (
        <span className="font-medium">
          {record.currency} {record.totalAmount}
        </span>
      ),
    },
    {
      title: t('fulfillment.receiver'),
      dataIndex: 'receiverName',
      width: 100,
      search: false,
      ellipsis: true,
    },
    {
      title: t('fulfillment.itemCount'),
      dataIndex: 'itemCount',
      width: 80,
      search: false,
      align: 'center',
    },
    {
      title: t('fulfillment.relatedExceptions'),
      dataIndex: 'exceptionCount',
      width: 100,
      search: false,
      align: 'center',
      render: (_, record) => {
        if (record.exceptionCount === 0) {
          return <span className="text-gray-400">-</span>;
        }
        return (
          <Badge count={record.pendingExceptionCount} size="small" offset={[5, 0]}>
            <Tag color="red" icon={<ExclamationCircleOutlined />}>
              {record.exceptionCount}
            </Tag>
          </Badge>
        );
      },
    },
    {
      title: t('fulfillment.placedAt'),
      dataIndex: 'placedAt',
      width: 160,
      valueType: 'dateRange',
      search: {
        transform: (value) => ({
          startDate: value?.[0],
          endDate: value?.[1],
        }),
      },
      render: (_, record) => new Date(record.placedAt).toLocaleString(),
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
      <ProTable<PlatformOrder>
        actionRef={actionRef}
        columns={columns}
        rowKey="id"
        request={async (params) => {
          try {
            const result = await platformOrderApi.getList({
              page: params.current,
              limit: params.pageSize,
              salesChannelId: params.salesChannelId,
              status: params.status,
              paymentStatus: params.paymentStatus,
              search: params.orderNo,
              startDate: params.startDate,
              endDate: params.endDate,
            });
            return {
              data: result.items,
              success: true,
              total: result.total,
            };
          } catch (error) {
            console.error('Failed to fetch platform orders:', error);
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
        scroll={{ x: 1500 }}
      />
    </div>
  );
}
