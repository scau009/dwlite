import { useRef } from 'react';
import { useNavigate } from 'react-router';
import { useTranslation } from 'react-i18next';
import { ProTable, type ActionType, type ProColumns } from '@ant-design/pro-components';
import { Button, Tag, Space, Tooltip, Statistic, Card, Row, Col } from 'antd';
import { EyeOutlined, InboxOutlined, CheckCircleOutlined, ClockCircleOutlined } from '@ant-design/icons';

import {
  warehouseOpsApi,
  type WarehouseInboundOrder,
  type WarehouseInboundStatus,
} from '@/lib/warehouse-operations-api';

// Status color mapping
const statusColors: Record<WarehouseInboundStatus, string> = {
  draft: 'default',
  pending: 'processing',
  shipped: 'cyan',
  arrived: 'blue',
  receiving: 'purple',
  completed: 'success',
  partial_completed: 'warning',
  cancelled: 'error',
};

export function WarehouseInboundListPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const actionRef = useRef<ActionType>(null);

  // Get status label
  const getStatusLabel = (status: WarehouseInboundStatus) => {
    const labels: Record<WarehouseInboundStatus, string> = {
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
  };

  // Handle view detail
  const handleView = (order: WarehouseInboundOrder) => {
    navigate(`/warehouse/inbound/${order.id}`);
  };

  const columns: ProColumns<WarehouseInboundOrder>[] = [
    {
      title: t('inventory.orderNo'),
      dataIndex: 'orderNo',
      width: 160,
      ellipsis: true,
      copyable: true,
      fieldProps: {
        placeholder: t('common.search') + '...',
      },
      render: (_, record) => (
        <a onClick={() => handleView(record)} className="font-medium">
          {record.orderNo}
        </a>
      ),
    },
    {
      title: t('warehouseOps.merchant'),
      dataIndex: ['merchant', 'companyName'],
      width: 150,
      ellipsis: true,
      search: false,
    },
    {
      title: t('common.status'),
      dataIndex: 'status',
      width: 120,
      valueType: 'select',
      valueEnum: {
        shipped: { text: t('inventory.statusShipped') },
        arrived: { text: t('inventory.statusArrived') },
        receiving: { text: t('inventory.statusReceiving') },
        completed: { text: t('inventory.statusCompleted') },
        partial_completed: { text: t('inventory.statusPartialCompleted') },
      },
      render: (_, record) => (
        <Tag color={statusColors[record.status]}>{getStatusLabel(record.status)}</Tag>
      ),
    },
    {
      title: t('inventory.totalSkuCount'),
      dataIndex: 'totalSkuCount',
      width: 80,
      search: false,
      align: 'center',
    },
    {
      title: t('warehouseOps.receivedProgress'),
      dataIndex: 'totalQuantity',
      width: 120,
      search: false,
      align: 'center',
      render: (_, record) => (
        <span>
          <span
            className={
              record.receivedQuantity < record.totalQuantity ? 'text-orange-500' : 'text-green-500'
            }
          >
            {record.receivedQuantity}
          </span>{' '}
          / {record.totalQuantity}
        </span>
      ),
    },
    {
      title: t('inventory.expectedArrivalDate'),
      dataIndex: 'expectedArrivalDate',
      width: 120,
      search: false,
      render: (_, record) =>
        record.expectedArrivalDate
          ? new Date(record.expectedArrivalDate).toLocaleDateString()
          : '-',
    },
    {
      title: t('inventory.shippedAt'),
      dataIndex: 'shippedAt',
      width: 160,
      search: false,
      render: (_, record) =>
        record.shippedAt ? new Date(record.shippedAt).toLocaleString() : '-',
    },
    {
      title: t('common.createdAt'),
      dataIndex: 'createdAt',
      width: 160,
      search: false,
      render: (_, record) => new Date(record.createdAt).toLocaleString(),
    },
    {
      title: t('common.actions'),
      valueType: 'option',
      width: 80,
      fixed: 'right',
      render: (_, record) => (
        <Space size="small">
          <Tooltip title={t('common.view')}>
            <Button
              type="text"
              size="small"
              icon={<EyeOutlined />}
              onClick={() => handleView(record)}
            />
          </Tooltip>
        </Space>
      ),
    },
  ];

  return (
    <div className="space-y-4">
      <div className="mb-4">
        <h1 className="text-xl font-semibold">{t('warehouseOps.inboundTitle')}</h1>
        <p className="text-gray-500">{t('warehouseOps.inboundDescription')}</p>
      </div>

      {/* Quick Stats */}
      <Row gutter={16} className="mb-4">
        <Col xs={12} sm={6}>
          <Card size="small">
            <Statistic
              title={t('warehouseOps.awaitingArrival')}
              value={0}
              prefix={<ClockCircleOutlined />}
              valueStyle={{ color: '#1890ff' }}
            />
          </Card>
        </Col>
        <Col xs={12} sm={6}>
          <Card size="small">
            <Statistic
              title={t('warehouseOps.pendingReceiving')}
              value={0}
              prefix={<InboxOutlined />}
              valueStyle={{ color: '#722ed1' }}
            />
          </Card>
        </Col>
        <Col xs={12} sm={6}>
          <Card size="small">
            <Statistic
              title={t('warehouseOps.completedToday')}
              value={0}
              prefix={<CheckCircleOutlined />}
              valueStyle={{ color: '#52c41a' }}
            />
          </Card>
        </Col>
      </Row>

      <ProTable<WarehouseInboundOrder>
        actionRef={actionRef}
        columns={columns}
        rowKey="id"
        request={async params => {
          try {
            const result = await warehouseOpsApi.getInboundOrders({
              page: params.current,
              limit: params.pageSize,
              status: params.status as WarehouseInboundStatus,
              orderNo: params.orderNo,
            });
            return {
              data: result.data,
              success: true,
              total: result.meta.total,
            };
          } catch (error) {
            console.error('Failed to fetch inbound orders:', error);
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
        scroll={{ x: 1100 }}
      />
    </div>
  );
}
