import { useRef } from 'react';
import { useNavigate } from 'react-router';
import { useTranslation } from 'react-i18next';
import { ProTable, type ActionType, type ProColumns } from '@ant-design/pro-components';
import { Button, Tag, Space } from 'antd';

import {
  inboundApi,
  type InboundException,
  type InboundExceptionType,
  type InboundExceptionStatus,
} from '@/lib/inbound-api';

// Status color mapping
const statusColors: Record<InboundExceptionStatus, string> = {
  pending: 'warning',
  processing: 'processing',
  resolved: 'success',
  closed: 'default',
};

// Exception type color mapping
const typeColors: Record<InboundExceptionType, string> = {
  quantity_short: 'orange',
  quantity_over: 'blue',
  damaged: 'red',
  wrong_item: 'purple',
  quality_issue: 'magenta',
  packaging: 'cyan',
  expired: 'volcano',
  other: 'default',
};

export function InboundExceptionsListPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const actionRef = useRef<ActionType>(null);

  // Get status label
  const getStatusLabel = (status: InboundExceptionStatus) => {
    const labels: Record<InboundExceptionStatus, string> = {
      pending: t('inventory.exceptionStatusPending'),
      processing: t('inventory.exceptionStatusProcessing'),
      resolved: t('inventory.exceptionStatusResolved'),
      closed: t('inventory.exceptionStatusClosed'),
    };
    return labels[status] || status;
  };

  // Get exception type label
  const getTypeLabel = (type: InboundExceptionType) => {
    const labels: Record<InboundExceptionType, string> = {
      quantity_short: t('inventory.typeQuantityShort'),
      quantity_over: t('inventory.typeQuantityOver'),
      damaged: t('inventory.typeDamaged'),
      wrong_item: t('inventory.typeWrongItem'),
      quality_issue: t('inventory.typeQualityIssue'),
      packaging: t('inventory.typePackaging'),
      expired: t('inventory.typeExpired'),
      other: t('inventory.typeOther'),
    };
    return labels[type] || type;
  };

  // Handle view detail
  const handleView = (exception: InboundException) => {
    navigate(`/inventory/exceptions/detail/${exception.id}`);
  };

  const columns: ProColumns<InboundException>[] = [
    {
      title: t('inventory.exceptionNo'),
      dataIndex: 'exceptionNo',
      width: 160,
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
      title: t('inventory.exceptionType'),
      dataIndex: 'type',
      width: 120,
      valueType: 'select',
      valueEnum: {
        quantity_short: { text: t('inventory.typeQuantityShort') },
        quantity_over: { text: t('inventory.typeQuantityOver') },
        damaged: { text: t('inventory.typeDamaged') },
        wrong_item: { text: t('inventory.typeWrongItem') },
        quality_issue: { text: t('inventory.typeQualityIssue') },
        packaging: { text: t('inventory.typePackaging') },
        expired: { text: t('inventory.typeExpired') },
        other: { text: t('inventory.typeOther') },
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
        pending: { text: t('inventory.exceptionStatusPending') },
        processing: { text: t('inventory.exceptionStatusProcessing') },
        resolved: { text: t('inventory.exceptionStatusResolved') },
        closed: { text: t('inventory.exceptionStatusClosed') },
      },
      render: (_, record) => (
        <Tag color={statusColors[record.status]}>{getStatusLabel(record.status)}</Tag>
      ),
    },
    {
      title: t('inventory.totalQuantity'),
      dataIndex: 'totalQuantity',
      width: 100,
      search: false,
      align: 'center',
    },
    {
      title: t('inventory.exceptionDescription'),
      dataIndex: 'description',
      width: 200,
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
      title: t('inventory.resolvedAt'),
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
          <Button
            type="link"
            size="small"
            onClick={() => handleView(record)}
          >
            {t('common.view')}
          </Button>
          {record.status === 'pending' && (
            <Button
              type="link"
              size="small"
              onClick={() => handleView(record)}
            >
              {t('inventory.resolveException')}
            </Button>
          )}
        </Space>
      ),
    },
  ];

  return (
    <div className="space-y-4">
      <ProTable<InboundException>
        actionRef={actionRef}
        columns={columns}
        rowKey="id"
        request={async (params) => {
          try {
            const result = await inboundApi.getInboundExceptions({
              page: params.current,
              limit: params.pageSize,
              status: params.status,
              type: params.type,
              search: params.exceptionNo,
            });
            return {
              data: result.data,
              success: true,
              total: result.total,
            };
          } catch (error) {
            console.error('Failed to fetch exceptions:', error);
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
