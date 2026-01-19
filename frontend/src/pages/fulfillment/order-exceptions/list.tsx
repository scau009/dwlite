import { useRef } from 'react';
import { useNavigate } from 'react-router';
import { useTranslation } from 'react-i18next';
import { ProTable, type ActionType, type ProColumns } from '@ant-design/pro-components';
import { Button, Tag, Space } from 'antd';

import {
  orderExceptionApi,
  type OrderException,
  type OrderExceptionType,
  type OrderExceptionStatus,
} from '@/lib/order-exception-api';

// Status color mapping
const statusColors: Record<OrderExceptionStatus, string> = {
  pending: 'warning',
  resolved: 'success',
  closed: 'default',
};

// Exception type color mapping
const typeColors: Record<OrderExceptionType, string> = {
  inventory_insufficient: 'red',
  price_below_platform: 'orange',
  product_not_matched: 'purple',
  other: 'default',
};

export function OrderExceptionsListPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const actionRef = useRef<ActionType>(null);

  // Get status label
  const getStatusLabel = (status: OrderExceptionStatus) => {
    const labels: Record<OrderExceptionStatus, string> = {
      pending: t('fulfillment.statusPending'),
      resolved: t('fulfillment.statusResolved'),
      closed: t('fulfillment.statusClosed'),
    };
    return labels[status] || status;
  };

  // Get exception type label
  const getTypeLabel = (type: OrderExceptionType) => {
    const labels: Record<OrderExceptionType, string> = {
      inventory_insufficient: t('fulfillment.typeInventoryInsufficient'),
      price_below_platform: t('fulfillment.typePriceBelowPlatform'),
      product_not_matched: t('fulfillment.typeProductNotMatched'),
      other: t('fulfillment.typeOther'),
    };
    return labels[type] || type;
  };

  // Handle view detail
  const handleView = (exception: OrderException) => {
    navigate(`/fulfillment/order-exceptions/${exception.id}`);
  };

  const columns: ProColumns<OrderException>[] = [
    {
      title: t('fulfillment.exceptionNo'),
      dataIndex: 'exceptionNo',
      width: 180,
      ellipsis: true,
      copyable: true,
      fieldProps: {
        placeholder: t('common.search') + '...',
      },
      render: (_, record) => (
        <a onClick={() => handleView(record)} className="font-medium">
          {record.exceptionNo}
        </a>
      ),
    },
    {
      title: t('fulfillment.orderNo'),
      dataIndex: ['order', 'externalOrderNo'],
      width: 180,
      search: false,
      ellipsis: true,
      copyable: true,
    },
    {
      title: t('fulfillment.exceptionType'),
      dataIndex: 'type',
      width: 140,
      valueType: 'select',
      valueEnum: {
        inventory_insufficient: { text: t('fulfillment.typeInventoryInsufficient') },
        price_below_platform: { text: t('fulfillment.typePriceBelowPlatform') },
        product_not_matched: { text: t('fulfillment.typeProductNotMatched') },
        other: { text: t('fulfillment.typeOther') },
      },
      render: (_, record) => (
        <Tag color={typeColors[record.type]}>{record.typeLabel || getTypeLabel(record.type)}</Tag>
      ),
    },
    {
      title: t('common.status'),
      dataIndex: 'status',
      width: 100,
      valueType: 'select',
      valueEnum: {
        pending: { text: t('fulfillment.statusPending') },
        resolved: { text: t('fulfillment.statusResolved') },
        closed: { text: t('fulfillment.statusClosed') },
      },
      render: (_, record) => (
        <Tag color={statusColors[record.status]}>{record.statusLabel || getStatusLabel(record.status)}</Tag>
      ),
    },
    {
      title: t('fulfillment.exceptionDescription'),
      dataIndex: 'description',
      width: 250,
      search: false,
      ellipsis: true,
    },
    {
      title: t('common.createdAt'),
      dataIndex: 'createdAt',
      width: 160,
      search: false,
      sorter: true,
      defaultSortOrder: 'descend',
      render: (_, record) => new Date(record.createdAt).toLocaleString(),
    },
    {
      title: t('fulfillment.resolvedAt'),
      dataIndex: 'resolvedAt',
      width: 160,
      search: false,
      render: (_, record) =>
        record.resolvedAt ? new Date(record.resolvedAt).toLocaleString() : '-',
    },
    {
      title: t('common.actions'),
      valueType: 'option',
      width: 140,
      fixed: 'right',
      render: (_, record) => (
        <Space size="small">
          <Button type="link" size="small" onClick={() => handleView(record)}>
            {t('common.view')}
          </Button>
          {record.status === 'pending' && (
            <Button type="link" size="small" onClick={() => handleView(record)}>
              {t('fulfillment.resolveException')}
            </Button>
          )}
        </Space>
      ),
    },
  ];

  return (
    <div className="space-y-4">
      <ProTable<OrderException>
        actionRef={actionRef}
        columns={columns}
        rowKey="id"
        request={async (params) => {
          try {
            const result = await orderExceptionApi.getList({
              page: params.current,
              pageSize: params.pageSize,
              status: params.status,
              type: params.type,
            });
            return {
              data: result.data,
              success: true,
              total: result.pagination.total,
            };
          } catch (error) {
            console.error('Failed to fetch order exceptions:', error);
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
        scroll={{ x: 1400 }}
      />
    </div>
  );
}
