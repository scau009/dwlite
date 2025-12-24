import { useRef, useState } from 'react';
import { useNavigate } from 'react-router';
import { useTranslation } from 'react-i18next';
import { ProTable, type ActionType, type ProColumns } from '@ant-design/pro-components';
import { Button, Tag, App, Space, Tooltip, Dropdown } from 'antd';
import {
  PlusOutlined,
  EyeOutlined,
  EditOutlined,
  DeleteOutlined,
  SendOutlined,
  CloseCircleOutlined,
  MoreOutlined,
} from '@ant-design/icons';

import { inboundApi, type InboundOrder, type InboundOrderStatus } from '@/lib/inbound-api';
import { InboundOrderFormModal } from './components/inbound-order-form-modal';
import { CancelOrderModal } from './components/cancel-order-modal';

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

export function InboundOrdersListPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const actionRef = useRef<ActionType>(null);
  const { message, modal } = App.useApp();

  const [formModalOpen, setFormModalOpen] = useState(false);
  const [editingOrder, setEditingOrder] = useState<InboundOrder | null>(null);
  const [cancelModalOpen, setCancelModalOpen] = useState(false);
  const [cancellingOrder, setCancellingOrder] = useState<InboundOrder | null>(null);
  const [actionLoading, setActionLoading] = useState<string | null>(null);

  // Get status label
  const getStatusLabel = (status: InboundOrderStatus) => {
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
  };

  // Handle view detail
  const handleView = (order: InboundOrder) => {
    navigate(`/inventory/inbound/detail/${order.id}`);
  };

  // Handle create
  const handleCreate = () => {
    setEditingOrder(null);
    setFormModalOpen(true);
  };

  // Handle edit (draft only)
  const handleEdit = (order: InboundOrder) => {
    setEditingOrder(order);
    setFormModalOpen(true);
  };

  // Handle submit order (draft → pending)
  const handleSubmit = async (order: InboundOrder) => {
    modal.confirm({
      title: t('inventory.confirmSubmit'),
      content: t('inventory.confirmSubmitDesc'),
      okText: t('common.confirm'),
      cancelText: t('common.cancel'),
      onOk: async () => {
        setActionLoading(order.id);
        try {
          await inboundApi.submitInboundOrder(order.id);
          message.success(t('inventory.orderSubmitted'));
          actionRef.current?.reload();
        } catch (error) {
          const err = error as { error?: string };
          message.error(err.error || t('common.error'));
        } finally {
          setActionLoading(null);
        }
      },
    });
  };

  // Handle cancel order
  const handleCancel = (order: InboundOrder) => {
    setCancellingOrder(order);
    setCancelModalOpen(true);
  };

  // Handle delete order (draft only)
  const handleDelete = async (order: InboundOrder) => {
    modal.confirm({
      title: t('inventory.confirmDelete'),
      content: t('inventory.confirmDeleteDesc'),
      okText: t('common.confirm'),
      cancelText: t('common.cancel'),
      okButtonProps: { danger: true },
      onOk: async () => {
        setActionLoading(order.id);
        try {
          await inboundApi.deleteInboundOrder(order.id);
          message.success(t('inventory.orderDeleted'));
          actionRef.current?.reload();
        } catch (error) {
          const err = error as { error?: string };
          message.error(err.error || t('common.error'));
        } finally {
          setActionLoading(null);
        }
      },
    });
  };

  // Get action menu items based on order status
  const getActionMenuItems = (order: InboundOrder) => {
    const items = [];

    // View is always available
    items.push({
      key: 'view',
      icon: <EyeOutlined />,
      label: t('common.view'),
      onClick: () => handleView(order),
    });

    // Edit only for draft
    if (order.status === 'draft') {
      items.push({
        key: 'edit',
        icon: <EditOutlined />,
        label: t('common.edit'),
        onClick: () => handleEdit(order),
      });
    }

    // Submit only for draft
    if (order.status === 'draft') {
      items.push({
        key: 'submit',
        icon: <SendOutlined />,
        label: t('inventory.submitOrder'),
        onClick: () => handleSubmit(order),
      });
    }

    // Cancel for draft and pending
    if (['draft', 'pending', 'shipped'].includes(order.status)) {
      items.push({
        key: 'cancel',
        icon: <CloseCircleOutlined />,
        label: t('inventory.cancelOrder'),
        danger: true,
        onClick: () => handleCancel(order),
      });
    }

    // Delete only for draft
    if (order.status === 'draft') {
      items.push({
        type: 'divider' as const,
      });
      items.push({
        key: 'delete',
        icon: <DeleteOutlined />,
        label: t('common.delete'),
        danger: true,
        onClick: () => handleDelete(order),
      });
    }

    return items;
  };

  const columns: ProColumns<InboundOrder>[] = [
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
      title: t('inventory.trackingNumber'),
      dataIndex: 'trackingNumber',
      width: 160,
      ellipsis: true,
      copyable: true,
      fieldProps: {
        placeholder: t('inventory.searchByTrackingNumber'),
      },
      hideInTable: true,
    },
    {
      title: t('inventory.warehouse'),
      dataIndex: ['warehouse', 'name'],
      width: 120,
      ellipsis: true,
      search: false,
    },
    {
      title: t('common.status'),
      dataIndex: 'status',
      width: 100,
      valueType: 'select',
      valueEnum: {
        draft: { text: t('inventory.statusDraft') },
        pending: { text: t('inventory.statusPending') },
        shipped: { text: t('inventory.statusShipped') },
        arrived: { text: t('inventory.statusArrived') },
        receiving: { text: t('inventory.statusReceiving') },
        completed: { text: t('inventory.statusCompleted') },
        partial_completed: { text: t('inventory.statusPartialCompleted') },
        cancelled: { text: t('inventory.statusCancelled') },
      },
      render: (_, record) => (
        <Tag color={statusColors[record.status]}>
          {getStatusLabel(record.status)}
        </Tag>
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
      title: t('inventory.totalQuantity'),
      dataIndex: 'totalQuantity',
      width: 100,
      search: false,
      align: 'center',
      render: (_, record) => (
        <span>
          {record.receivedQuantity} / {record.totalQuantity}
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
      sorter: true,
      defaultSortOrder: 'descend',
      render: (_, record) => new Date(record.createdAt).toLocaleString(),
    },
    {
      title: t('common.actions'),
      valueType: 'option',
      width: 100,
      fixed: 'right',
      render: (_, record) => {
        const isLoading = actionLoading === record.id;
        return (
          <Space size="small">
            <Tooltip title={t('common.view')}>
              <Button
                type="text"
                size="small"
                icon={<EyeOutlined />}
                onClick={() => handleView(record)}
              />
            </Tooltip>
            <Dropdown
              menu={{ items: getActionMenuItems(record) }}
              trigger={['click']}
              disabled={isLoading}
            >
              <Button
                type="text"
                size="small"
                icon={<MoreOutlined />}
                loading={isLoading}
              />
            </Dropdown>
          </Space>
        );
      },
    },
  ];

  return (
    <div className="space-y-4">
      <div className="mb-4">
        <h1 className="text-xl font-semibold">{t('inventory.title')}</h1>
        <p className="text-gray-500">{t('inventory.description')}</p>
      </div>

      <ProTable<InboundOrder>
        actionRef={actionRef}
        columns={columns}
        rowKey="id"
        request={async (params) => {
          try {
            const result = await inboundApi.getInboundOrders({
              page: params.current,
              limit: params.pageSize,
              status: params.status as InboundOrderStatus,
              search: params.orderNo,
              trackingNumber: params.trackingNumber,
            });
            return {
              data: result.data,
              success: true,
              total: result.total,
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
        toolBarRender={() => [
          <Button
            key="add"
            type="primary"
            icon={<PlusOutlined />}
            onClick={handleCreate}
          >
            {t('inventory.createOrder')}
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

      <InboundOrderFormModal
        open={formModalOpen}
        order={editingOrder}
        onClose={() => {
          setFormModalOpen(false);
          setEditingOrder(null);
        }}
        onSuccess={() => {
          setFormModalOpen(false);
          setEditingOrder(null);
          actionRef.current?.reload();
        }}
      />

      <CancelOrderModal
        open={cancelModalOpen}
        order={cancellingOrder}
        onClose={() => {
          setCancelModalOpen(false);
          setCancellingOrder(null);
        }}
        onSuccess={() => {
          setCancelModalOpen(false);
          setCancellingOrder(null);
          actionRef.current?.reload();
        }}
      />
    </div>
  );
}
