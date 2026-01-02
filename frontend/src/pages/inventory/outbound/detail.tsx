import { useState, useEffect, useMemo } from 'react';
import { useParams, useNavigate, Link } from 'react-router';
import { useTranslation } from 'react-i18next';
import {
  Card,
  Button,
  Tag,
  Space,
  App,
  Spin,
  Empty,
  Descriptions,
  Table,
  Image,
  Typography,
  Timeline,
  Popconfirm,
  Alert,
} from 'antd';
import {
  ArrowLeftOutlined,
  CheckCircleOutlined,
  ClockCircleOutlined,
  SendOutlined,
  CloseCircleOutlined,
  PlusOutlined,
  DeleteOutlined,
  ExclamationCircleOutlined,
} from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';

import {
  outboundApi,
  type OutboundOrderDetail,
  type OutboundOrderStatus,
  type OutboundOrderType,
  type OutboundOrderItem,
} from '@/lib/outbound-api';
import { InventorySelectorModal } from './components/inventory-selector-modal';

const { Text } = Typography;

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

export function OutboundOrderDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { t } = useTranslation();
  const { message, modal } = App.useApp();

  const [order, setOrder] = useState<OutboundOrderDetail | null>(null);
  const [loading, setLoading] = useState(true);

  // Add item modal state
  const [addItemModalOpen, setAddItemModalOpen] = useState(false);

  // Actions state
  const [submitting, setSubmitting] = useState(false);
  const [deleting, setDeleting] = useState(false);

  const loadOrder = async () => {
    if (!id) return;
    setLoading(true);
    try {
      const data = await outboundApi.getOutboundOrder(id);
      setOrder(data);
    } catch {
      message.error(t('common.error'));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadOrder();
  }, [id]);

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

  // Get existing SKU codes to filter in modal
  const existingSkuCodes = useMemo(() => {
    if (!order) return [];
    return order.items.map(item => `${item.styleNumber}-${item.skuName}`);
  }, [order?.items]);

  // Handle add item modal success
  const handleAddItemSuccess = () => {
    setAddItemModalOpen(false);
    loadOrder();
  };

  // Handle remove item
  const handleRemoveItem = async (itemId: string) => {
    if (!id) return;
    try {
      const result = await outboundApi.removeOutboundItem(id, itemId);
      setOrder(result.data);
      message.success(t('outbound.itemRemoved'));
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    }
  };

  // Handle delete order
  const handleDeleteOrder = async () => {
    if (!id) return;
    setDeleting(true);
    try {
      await outboundApi.deleteOutboundOrder(id);
      message.success(t('outbound.orderDeleted'));
      navigate('/inventory/outbound');
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setDeleting(false);
    }
  };

  // Handle submit order
  const handleSubmitOrder = () => {
    modal.confirm({
      title: t('outbound.submitOrder'),
      icon: <ExclamationCircleOutlined />,
      content: t('outbound.confirmSubmitOrder'),
      okText: t('common.confirm'),
      cancelText: t('common.cancel'),
      onOk: async () => {
        if (!id) return;
        setSubmitting(true);
        try {
          await outboundApi.submitOutboundOrder(id);
          message.success(t('outbound.orderSubmitted'));
          loadOrder();
        } catch (error) {
          const err = error as { error?: string };
          message.error(err.error || t('common.error'));
        } finally {
          setSubmitting(false);
        }
      },
    });
  };

  // Build timeline items
  const buildTimelineItems = () => {
    if (!order) return [];

    const items = [];

    // Created
    items.push({
      color: 'green',
      dot: <CheckCircleOutlined />,
      children: (
        <div>
          <Text strong>{t('outbound.timelineCreated')}</Text>
          <div className="text-gray-500 text-sm">
            {new Date(order.createdAt).toLocaleString()}
          </div>
        </div>
      ),
    });

    // Picking started
    if (order.pickingStartedAt) {
      items.push({
        color: 'green',
        dot: <CheckCircleOutlined />,
        children: (
          <div>
            <Text strong>{t('outbound.timelinePickingStarted')}</Text>
            <div className="text-gray-500 text-sm">
              {new Date(order.pickingStartedAt).toLocaleString()}
            </div>
          </div>
        ),
      });
    }

    // Picking completed
    if (order.pickingCompletedAt) {
      items.push({
        color: 'green',
        dot: <CheckCircleOutlined />,
        children: (
          <div>
            <Text strong>{t('outbound.timelinePickingCompleted')}</Text>
            <div className="text-gray-500 text-sm">
              {new Date(order.pickingCompletedAt).toLocaleString()}
            </div>
          </div>
        ),
      });
    }

    // Packing started
    if (order.packingStartedAt) {
      items.push({
        color: 'green',
        dot: <CheckCircleOutlined />,
        children: (
          <div>
            <Text strong>{t('outbound.timelinePackingStarted')}</Text>
            <div className="text-gray-500 text-sm">
              {new Date(order.packingStartedAt).toLocaleString()}
            </div>
          </div>
        ),
      });
    }

    // Packing completed
    if (order.packingCompletedAt) {
      items.push({
        color: 'green',
        dot: <CheckCircleOutlined />,
        children: (
          <div>
            <Text strong>{t('outbound.timelinePackingCompleted')}</Text>
            <div className="text-gray-500 text-sm">
              {new Date(order.packingCompletedAt).toLocaleString()}
            </div>
          </div>
        ),
      });
    }

    // Shipped
    if (order.shippedAt) {
      items.push({
        color: 'green',
        dot: <SendOutlined />,
        children: (
          <div>
            <Text strong>{t('outbound.timelineShipped')}</Text>
            <div className="text-gray-500 text-sm">
              {new Date(order.shippedAt).toLocaleString()}
            </div>
            {order.shippingCarrier && (
              <div className="text-gray-500 text-sm">
                {order.shippingCarrier}: {order.trackingNumber}
              </div>
            )}
          </div>
        ),
      });
    }

    // Cancelled
    if (order.cancelledAt) {
      items.push({
        color: 'red',
        dot: <CloseCircleOutlined />,
        children: (
          <div>
            <Text strong type="danger">{t('outbound.timelineCancelled')}</Text>
            <div className="text-gray-500 text-sm">
              {new Date(order.cancelledAt).toLocaleString()}
            </div>
            {order.cancelReason && (
              <div className="text-red-500 text-sm">{order.cancelReason}</div>
            )}
          </div>
        ),
      });
    }

    // Current status (if not completed)
    if (!order.shippedAt && !order.cancelledAt) {
      items.push({
        color: 'blue',
        dot: <ClockCircleOutlined />,
        children: (
          <div>
            <Text strong type="secondary">{getStatusLabel(order.status)}</Text>
          </div>
        ),
      });
    }

    return items;
  };

  // Item columns
  const itemColumns: ColumnsType<OutboundOrderItem> = [
    {
      title: t('inventory.productImage'),
      dataIndex: 'productImage',
      width: 80,
      render: (url: string | null) =>
        url ? (
          <Image
            src={url}
            width={60}
            height={60}
            style={{ objectFit: 'cover' }}
            preview={{ mask: null }}
          />
        ) : (
          <div className="w-[60px] h-[60px] bg-gray-100 flex items-center justify-center text-gray-400 text-xs">
            {t('common.noImages')}
          </div>
        ),
    },
    {
      title: t('inventory.productName'),
      dataIndex: 'productName',
      ellipsis: true,
      render: (name: string | null) => name || '-',
    },
    {
      title: t('products.styleNumber'),
      dataIndex: 'styleNumber',
      width: 150,
      render: (code: string | null) =>
        code ? <Text code>{code}</Text> : '-',
    },
    {
      title: t('products.color'),
      dataIndex: 'colorName',
      width: 100,
      render: (color: string | null) => color || '-',
    },
    {
      title: t('inventory.skuName'),
      dataIndex: 'skuName',
      width: 80,
      align: 'center',
      render: (size: string | null) => size || '-',
    },
    {
      title: t('outbound.stockType'),
      dataIndex: 'stockType',
      width: 100,
      align: 'center',
      render: (stockType: string) => (
        <Tag color={stockType === 'normal' ? 'green' : 'orange'}>
          {stockType === 'normal' ? t('outbound.normalStock') : t('outbound.damagedStock')}
        </Tag>
      ),
    },
    {
      title: t('inventory.quantity'),
      dataIndex: 'quantity',
      width: 100,
      align: 'center',
    },
  ];

  // Add actions column for draft orders
  if (order?.status === 'draft') {
    itemColumns.push({
      title: t('common.actions'),
      key: 'actions',
      width: 80,
      align: 'center',
      render: (_, record) => (
        <Popconfirm
          title={t('outbound.removeItem')}
          onConfirm={() => handleRemoveItem(record.id)}
          okText={t('common.confirm')}
          cancelText={t('common.cancel')}
        >
          <Button type="text" danger size="small" icon={<DeleteOutlined />} />
        </Popconfirm>
      ),
    });
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Spin size="large" />
      </div>
    );
  }

  if (!order) {
    return (
      <Card>
        <Empty description={t('common.noData')}>
          <Button type="primary" onClick={() => navigate('/inventory/outbound')}>
            {t('common.back')}
          </Button>
        </Empty>
      </Card>
    );
  }

  const isDraft = order.status === 'draft';

  return (
    <div className="flex flex-col gap-4">
      {/* Header */}
      <div className="flex items-center justify-between flex-wrap gap-4">
        <div className="flex items-center gap-4">
          <Button icon={<ArrowLeftOutlined />} onClick={() => navigate('/inventory/outbound')}>
            {t('common.back')}
          </Button>
          <span className="text-lg font-semibold">{order.outboundNo}</span>
          <Tag color={statusColors[order.status]}>{getStatusLabel(order.status)}</Tag>
          <Tag color={typeColors[order.outboundType]}>
            {order.outboundTypeLabel || getTypeLabel(order.outboundType)}
          </Tag>
        </div>
        {isDraft && (
          <Space>
            <Popconfirm
              title={t('outbound.confirmDeleteOrder')}
              onConfirm={handleDeleteOrder}
              okText={t('common.confirm')}
              cancelText={t('common.cancel')}
            >
              <Button danger loading={deleting}>
                {t('outbound.deleteOrder')}
              </Button>
            </Popconfirm>
            <Button
              type="primary"
              onClick={handleSubmitOrder}
              loading={submitting}
              disabled={order.items.length === 0}
            >
              {t('outbound.submitOrder')}
            </Button>
          </Space>
        )}
      </div>

      {/* Draft Alert */}
      {isDraft && (
        <Alert
          message={t('outbound.statusDraft')}
          description={order.items.length === 0 ? t('outbound.noItemsYet') : t('outbound.addItemDescription')}
          type="info"
          showIcon
        />
      )}

      {/* Basic Info */}
      <Card title={t('outbound.basicInfo')}>
        <Descriptions column={{ xs: 1, sm: 2, md: 3, xxl: 4 }} size="small">
          <Descriptions.Item label={t('outbound.orderNo')}>
            <Text code copyable>
              {order.outboundNo}
            </Text>
          </Descriptions.Item>
          <Descriptions.Item label={t('common.status')}>
            <Tag color={statusColors[order.status]}>{getStatusLabel(order.status)}</Tag>
          </Descriptions.Item>
          <Descriptions.Item label={t('outbound.outboundType')}>
            <Tag color={typeColors[order.outboundType]}>
              {order.outboundTypeLabel || getTypeLabel(order.outboundType)}
            </Tag>
          </Descriptions.Item>
          <Descriptions.Item label={t('outbound.warehouse')}>
            {order.warehouse.name}
          </Descriptions.Item>
          <Descriptions.Item label={t('outbound.totalQuantity')}>
            {order.totalQuantity}
          </Descriptions.Item>
          <Descriptions.Item label={t('common.createdAt')}>
            {new Date(order.createdAt).toLocaleString()}
          </Descriptions.Item>
          {order.shippedAt && (
            <Descriptions.Item label={t('inventory.shippedAt')}>
              {new Date(order.shippedAt).toLocaleString()}
            </Descriptions.Item>
          )}
          {order.cancelledAt && (
            <Descriptions.Item label={t('outbound.cancelledAt')}>
              {new Date(order.cancelledAt).toLocaleString()}
            </Descriptions.Item>
          )}
        </Descriptions>
        {order.remark && (
          <div className="mt-3 pt-3 border-t">
            <Text type="secondary">{t('outbound.remark')}:</Text>
            <p className="mt-1">{order.remark}</p>
          </div>
        )}
        {order.cancelReason && (
          <div className="mt-3 pt-3 border-t">
            <Text type="danger">{t('outbound.cancelReason')}:</Text>
            <p className="mt-1 text-red-500">{order.cancelReason}</p>
          </div>
        )}
      </Card>

      {/* Receiver Info */}
      <Card title={t('outbound.receiverInfo')}>
        <Descriptions column={{ xs: 1, sm: 2, md: 3 }} size="small">
          <Descriptions.Item label={t('outbound.receiverName')}>
            {order.receiverName}
          </Descriptions.Item>
          <Descriptions.Item label={t('outbound.receiverPhone')}>
            {order.receiverPhone}
          </Descriptions.Item>
          <Descriptions.Item label={t('outbound.postalCode')}>
            {order.receiverPostalCode || '-'}
          </Descriptions.Item>
          <Descriptions.Item label={t('outbound.receiverAddress')} span={3}>
            {order.receiverAddress}
          </Descriptions.Item>
        </Descriptions>
      </Card>

      {/* Shipping Info - show when shipped or has tracking info */}
      {(order.shippingCarrier || order.trackingNumber || order.shippedAt) && (
        <Card title={t('outbound.shippingInfo')}>
          <Descriptions column={{ xs: 1, sm: 2, md: 3 }} size="small">
            <Descriptions.Item label={t('outbound.carrier')}>
              {order.shippingCarrier || '-'}
            </Descriptions.Item>
            <Descriptions.Item label={t('outbound.trackingNumber')}>
              {order.trackingNumber ? (
                <Text code copyable>{order.trackingNumber}</Text>
              ) : '-'}
            </Descriptions.Item>
            {order.shippedAt && (
              <Descriptions.Item label={t('inventory.shippedAt')}>
                {new Date(order.shippedAt).toLocaleString()}
              </Descriptions.Item>
            )}
          </Descriptions>
        </Card>
      )}

      {/* Related Order */}
      {order.relatedOrder && (
        <Card title={t('outbound.relatedOrder')} size="small">
          <Space>
            <Text>{t('orders.orderNo')}:</Text>
            <Link to={`/orders/${order.relatedOrder.id}`}>
              <Text code>{order.relatedOrder.orderNo}</Text>
            </Link>
          </Space>
        </Card>
      )}

      {/* Order Items */}
      <Card
        title={`${t('outbound.orderItems')} (${order.items.length})`}
        extra={
          isDraft && (
            <Button
              type="primary"
              size="small"
              icon={<PlusOutlined />}
              onClick={() => setAddItemModalOpen(true)}
            >
              {t('outbound.addItem')}
            </Button>
          )
        }
      >
        <Table
          columns={itemColumns}
          dataSource={order.items}
          rowKey="id"
          pagination={order.items.length > 10 ? { pageSize: 10 } : false}
          scroll={{ x: 800 }}
          size="small"
        />
      </Card>

      {/* Timeline - show when not draft */}
      {!isDraft && (
        <Card title={t('outbound.timeline')}>
          <Timeline items={buildTimelineItems()} />
        </Card>
      )}

      {/* Add Item Modal */}
      {order && (
        <InventorySelectorModal
          open={addItemModalOpen}
          orderId={order.id}
          warehouseId={order.warehouse.id}
          existingSkuCodes={existingSkuCodes}
          onClose={() => setAddItemModalOpen(false)}
          onSuccess={handleAddItemSuccess}
        />
      )}
    </div>
  );
}
