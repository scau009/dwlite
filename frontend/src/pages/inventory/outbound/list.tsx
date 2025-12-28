import { useRef, useState } from 'react';
import { useNavigate } from 'react-router';
import { useTranslation } from 'react-i18next';
import { ProTable, type ActionType, type ProColumns } from '@ant-design/pro-components';
import { Button, Tag, Space, Tooltip } from 'antd';
import { PlusOutlined, EyeOutlined } from '@ant-design/icons';

import {
  outboundApi,
  type OutboundOrder,
  type OutboundOrderStatus,
  type OutboundOrderType,
} from '@/lib/outbound-api';
import { CreateOutboundModal } from './components/create-outbound-modal';

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

// Type color mapping
const typeColors: Record<OutboundOrderType, string> = {
  sales: 'blue',
  return_to_merchant: 'orange',
  transfer: 'cyan',
  scrap: 'default',
};

export function OutboundOrdersListPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const actionRef = useRef<ActionType>(null);

  const [createModalOpen, setCreateModalOpen] = useState(false);

  // Get status label
  const getStatusLabel = (status: OutboundOrderStatus) => {
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
  };

  // Get type label
  const getTypeLabel = (type: OutboundOrderType) => {
    const labels: Record<OutboundOrderType, string> = {
      sales: t('outbound.typeSales'),
      return_to_merchant: t('outbound.typeReturnToMerchant'),
      transfer: t('outbound.typeTransfer'),
      scrap: t('outbound.typeScrap'),
    };
    return labels[type] || type;
  };

  // Handle view detail
  const handleView = (order: OutboundOrder) => {
    navigate(`/inventory/outbound/detail/${order.id}`);
  };

  // Handle create
  const handleCreate = () => {
    setCreateModalOpen(true);
  };

  const columns: ProColumns<OutboundOrder>[] = [
    {
      title: t('outbound.orderNo'),
      dataIndex: 'outboundNo',
      width: 160,
      ellipsis: true,
      copyable: true,
      fieldProps: {
        placeholder: t('common.search') + '...',
      },
      render: (_, record) => (
        <a onClick={() => handleView(record)} className="font-medium">
          {record.outboundNo}
        </a>
      ),
    },
    {
      title: t('outbound.outboundType'),
      dataIndex: 'outboundType',
      width: 120,
      valueType: 'select',
      valueEnum: {
        sales: { text: t('outbound.typeSales') },
        return_to_merchant: { text: t('outbound.typeReturnToMerchant') },
        transfer: { text: t('outbound.typeTransfer') },
        scrap: { text: t('outbound.typeScrap') },
      },
      render: (_, record) => (
        <Tag color={typeColors[record.outboundType]}>
          {record.outboundTypeLabel || getTypeLabel(record.outboundType)}
        </Tag>
      ),
    },
    {
      title: t('common.status'),
      dataIndex: 'status',
      width: 100,
      valueType: 'select',
      valueEnum: {
        draft: { text: t('outbound.statusDraft') },
        pending: { text: t('outbound.statusPending') },
        picking: { text: t('outbound.statusPicking') },
        packing: { text: t('outbound.statusPacking') },
        ready: { text: t('outbound.statusReady') },
        shipped: { text: t('outbound.statusShipped') },
        cancelled: { text: t('outbound.statusCancelled') },
      },
      render: (_, record) => (
        <Tag color={statusColors[record.status]}>
          {record.statusLabel || getStatusLabel(record.status)}
        </Tag>
      ),
    },
    {
      title: t('outbound.warehouse'),
      dataIndex: ['warehouse', 'name'],
      width: 120,
      ellipsis: true,
      search: false,
    },
    {
      title: t('outbound.receiver'),
      dataIndex: 'receiverName',
      width: 120,
      ellipsis: true,
      search: false,
    },
    {
      title: t('outbound.totalQuantity'),
      dataIndex: 'totalQuantity',
      width: 80,
      search: false,
      align: 'center',
    },
    {
      title: t('outbound.trackingNumber'),
      dataIndex: 'trackingNumber',
      width: 160,
      ellipsis: true,
      copyable: true,
      fieldProps: {
        placeholder: t('common.search') + '...',
      },
      render: (_, record) => record.trackingNumber || '-',
    },
    {
      title: t('outbound.carrier'),
      dataIndex: 'shippingCarrier',
      width: 100,
      search: false,
      render: (_, record) => record.shippingCarrier || '-',
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
        <h1 className="text-xl font-semibold">{t('outbound.title')}</h1>
        <p className="text-gray-500">{t('outbound.description')}</p>
      </div>

      <ProTable<OutboundOrder>
        actionRef={actionRef}
        columns={columns}
        rowKey="id"
        request={async (params) => {
          try {
            const result = await outboundApi.getOutboundOrders({
              page: params.current,
              limit: params.pageSize,
              status: params.status as OutboundOrderStatus,
              outboundType: params.outboundType as OutboundOrderType,
              outboundNo: params.outboundNo,
              trackingNumber: params.trackingNumber,
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
        toolBarRender={() => [
          <Button
            key="add"
            type="primary"
            icon={<PlusOutlined />}
            onClick={handleCreate}
          >
            {t('outbound.createOutbound')}
          </Button>,
        ]}
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
        scroll={{ x: 1200 }}
      />

      <CreateOutboundModal
        open={createModalOpen}
        onClose={() => setCreateModalOpen(false)}
        onSuccess={() => {
          setCreateModalOpen(false);
          actionRef.current?.reload();
        }}
      />
    </div>
  );
}
