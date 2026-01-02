import { useRef } from 'react';
import { useNavigate } from 'react-router';
import { useTranslation } from 'react-i18next';
import { ProTable, type ActionType, type ProColumns } from '@ant-design/pro-components';
import { Button, Tag, Space } from 'antd';

import {
  warehouseOpsApi,
  type WarehouseOutboundOrder,
  type WarehouseOutboundStatus,
} from '@/lib/warehouse-operations-api';

// Status color mapping
const statusColors: Record<WarehouseOutboundStatus, string> = {
  pending: 'processing',
  picking: 'cyan',
  packing: 'blue',
  ready: 'purple',
  shipped: 'success',
  delivered: 'green',
  cancelled: 'error',
};

export function WarehouseOutboundListPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const actionRef = useRef<ActionType>(null);

  // Get status label
  const getStatusLabel = (status: WarehouseOutboundStatus) => {
    const labels: Record<WarehouseOutboundStatus, string> = {
      pending: t('warehouseOps.outboundStatusPending'),
      picking: t('warehouseOps.outboundStatusPicking'),
      packing: t('warehouseOps.outboundStatusPacking'),
      ready: t('warehouseOps.outboundStatusReady'),
      shipped: t('warehouseOps.outboundStatusShipped'),
      delivered: t('warehouseOps.outboundStatusDelivered'),
      cancelled: t('warehouseOps.outboundStatusCancelled'),
    };
    return labels[status] || status;
  };

  // Handle view detail
  const handleView = (order: WarehouseOutboundOrder) => {
    navigate(`/warehouse/outbound/${order.id}`);
  };

  const columns: ProColumns<WarehouseOutboundOrder>[] = [
    {
      title: t('warehouseOps.outboundNo'),
      dataIndex: 'outboundNo',
      width: 160,
      ellipsis: true,
      copyable: true,
      search: false,
      render: (_, record) => (
        <a onClick={() => handleView(record)} className="font-medium">
          {record.outboundNo}
        </a>
      ),
    },
    {
      title: t('warehouseOps.outboundType'),
      dataIndex: 'outboundType',
      width: 100,
      search: false,
      render: (_, record) => {
        const typeLabels: Record<string, string> = {
          sales: t('warehouseOps.outboundTypeSales'),
          transfer: t('warehouseOps.outboundTypeTransfer'),
          return: t('warehouseOps.outboundTypeReturn'),
        };
        return typeLabels[record.outboundType] || record.outboundType;
      },
    },
    {
      title: t('common.status'),
      dataIndex: 'status',
      width: 100,
      valueType: 'select',
      valueEnum: {
        pending: { text: t('warehouseOps.outboundStatusPending') },
        picking: { text: t('warehouseOps.outboundStatusPicking') },
        packing: { text: t('warehouseOps.outboundStatusPacking') },
        ready: { text: t('warehouseOps.outboundStatusReady') },
        shipped: { text: t('warehouseOps.outboundStatusShipped') },
      },
      render: (_, record) => (
        <Tag color={statusColors[record.status]}>{getStatusLabel(record.status)}</Tag>
      ),
    },
    {
      title: t('warehouseOps.receiver'),
      dataIndex: 'receiverName',
      width: 100,
      search: false,
      ellipsis: true,
    },
    {
      title: t('warehouseOps.receiverPhone'),
      dataIndex: 'receiverPhone',
      width: 120,
      search: false,
    },
    {
      title: t('warehouseOps.receiverAddress'),
      dataIndex: 'receiverAddress',
      width: 200,
      search: false,
      ellipsis: true,
    },
    {
      title: t('warehouseOps.totalItems'),
      dataIndex: 'totalQuantity',
      width: 80,
      search: false,
      align: 'center',
    },
    {
      title: t('warehouseOps.carrier'),
      dataIndex: 'shippingCarrier',
      width: 100,
      search: false,
      render: (_, record) => record.shippingCarrier || '-',
    },
    {
      title: t('warehouseOps.trackingNumber'),
      dataIndex: 'trackingNumber',
      width: 140,
      search: false,
      ellipsis: true,
      render: (_, record) => record.trackingNumber || '-',
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
          <Button
            type="link"
            size="small"
            onClick={() => handleView(record)}
          >
            {t('common.view')}
          </Button>
        </Space>
      ),
    },
  ];

  return (
    <div className="space-y-4">
      <ProTable<WarehouseOutboundOrder>
        actionRef={actionRef}
        columns={columns}
        rowKey="id"
        request={async params => {
          try {
            const result = await warehouseOpsApi.getOutboundOrders({
              page: params.current,
              limit: params.pageSize,
              status: params.status as WarehouseOutboundStatus,
            });
            return {
              data: result.data,
              success: true,
              total: result.meta.total,
            };
          } catch (error) {
            console.error('Failed to fetch outbound orders:', error);
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
        scroll={{ x: 1300 }}
      />
    </div>
  );
}
