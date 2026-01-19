import { useState, useEffect, useCallback } from 'react';
import { useParams, useNavigate, Link } from 'react-router';
import { useTranslation } from 'react-i18next';
import {
  Card,
  Button,
  Tag,
  App,
  Spin,
  Empty,
  Descriptions,
  Table,
  Typography,
  Steps,
  Image,
  Timeline,
} from 'antd';
import {
  ArrowLeftOutlined,
  CarryOutOutlined,
  InboxOutlined,
  SendOutlined,
  CheckCircleOutlined,
  ClockCircleOutlined,
} from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';
import dayjs from 'dayjs';

import {
  adminOutboundApi,
  type AdminOutboundOrderDetail,
  type AdminOutboundOrderItem,
  type OutboundOrderStatus,
  type OutboundOrderType,
  type StockType,
} from '@/lib/admin-outbound-api';

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

// Status step mapping
const statusSteps: Record<OutboundOrderStatus, number> = {
  draft: -1,
  pending: 0,
  picking: 1,
  packing: 2,
  ready: 3,
  shipped: 4,
  cancelled: -1,
};

// Outbound type color mapping
const typeColors: Record<OutboundOrderType, string> = {
  sales: 'blue',
  return_to_merchant: 'orange',
  transfer: 'cyan',
  scrap: 'red',
};

export function AdminOutboundOrderDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { t } = useTranslation();
  const { message } = App.useApp();

  const [order, setOrder] = useState<AdminOutboundOrderDetail | null>(null);
  const [loading, setLoading] = useState(true);

  const loadOrder = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    try {
      const response = await adminOutboundApi.getOutboundOrder(id);
      setOrder(response);
    } catch {
      message.error(t('common.error'));
    } finally {
      setLoading(false);
    }
  }, [id, message, t]);

  useEffect(() => {
    loadOrder();
  }, [loadOrder]);

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

  // Get outbound type label
  const getOutboundTypeLabel = (type: OutboundOrderType) => {
    const labels: Record<OutboundOrderType, string> = {
      sales: t('adminOutbound.typeSales'),
      return_to_merchant: t('adminOutbound.typeReturnToMerchant'),
      transfer: t('adminOutbound.typeTransfer'),
      scrap: t('adminOutbound.typeScrap'),
    };
    return labels[type] || type;
  };

  // Item table columns
  const itemColumns: ColumnsType<AdminOutboundOrderItem> = [
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
            style={{ objectFit: 'contain' }}
            preview={{ mask: null }}
          />
        ) : (
          <div className="w-[60px] h-[60px] bg-gray-100 flex items-center justify-center text-gray-400 text-xs">
            {t('common.noImages')}
          </div>
        ),
    },
    {
      title: t('warehouseOps.productInfo'),
      dataIndex: 'productName',
      width: 200,
      render: (_, record) => (
        <div>
          <div>{record.productName || '-'}</div>
          {record.styleNumber && (
            <Text type="secondary" className="text-xs">{record.styleNumber}</Text>
          )}
        </div>
      ),
    },
    {
      title: t('warehouseOps.skuName'),
      dataIndex: ['productSku', 'skuName'],
      width: 120,
      align: 'center',
      render: (_, record) => (
        <div>
          <div>{record.productSku?.skuName || '-'}</div>
          {record.productSku?.colorName && (
            <Text type="secondary" className="text-xs">{record.productSku.colorName}</Text>
          )}
        </div>
      ),
    },
    {
      title: t('outbound.stockType'),
      dataIndex: 'stockType',
      width: 100,
      align: 'center',
      render: (stockType: StockType) => (
        <Tag color={stockType === 'normal' ? 'green' : 'orange'}>
          {stockType === 'normal' ? t('outbound.normalStock') : t('outbound.damagedStock')}
        </Tag>
      ),
    },
    {
      title: t('warehouseOps.quantity'),
      dataIndex: 'quantity',
      width: 80,
      align: 'center',
    },
  ];

  // Build timeline items
  const buildTimelineItems = () => {
    if (!order) return [];

    const items = [
      {
        label: t('outbound.timelineCreated'),
        time: order.createdAt,
        color: 'blue',
      },
    ];

    if (order.pickingStartedAt) {
      items.push({
        label: t('outbound.timelinePickingStarted'),
        time: order.pickingStartedAt,
        color: 'purple',
      });
    }

    if (order.pickingCompletedAt) {
      items.push({
        label: t('outbound.timelinePickingCompleted'),
        time: order.pickingCompletedAt,
        color: 'purple',
      });
    }

    if (order.packingStartedAt) {
      items.push({
        label: t('outbound.timelinePackingStarted'),
        time: order.packingStartedAt,
        color: 'cyan',
      });
    }

    if (order.packingCompletedAt) {
      items.push({
        label: t('outbound.timelinePackingCompleted'),
        time: order.packingCompletedAt,
        color: 'cyan',
      });
    }

    if (order.shippedAt) {
      items.push({
        label: t('outbound.timelineShipped'),
        time: order.shippedAt,
        color: 'green',
      });
    }

    if (order.cancelledAt) {
      items.push({
        label: t('outbound.timelineCancelled'),
        time: order.cancelledAt,
        color: 'red',
      });
    }

    return items;
  };

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
          <Button type="primary" onClick={() => navigate('/admin/outbound/orders')}>
            {t('common.back')}
          </Button>
        </Empty>
      </Card>
    );
  }

  const isCancelled = order.status === 'cancelled';
  const currentStep = statusSteps[order.status];
  const showSteps = !isCancelled && currentStep >= 0;

  return (
    <div className="flex flex-col gap-4">
      {/* Header */}
      <div className="flex items-center justify-between flex-wrap gap-4">
        <div className="flex items-center gap-4">
          <Button icon={<ArrowLeftOutlined />} onClick={() => navigate('/admin/outbound/orders')}>
            {t('common.back')}
          </Button>
          <span className="text-lg font-semibold">{order.outboundNo}</span>
          <Tag color={statusColors[order.status]}>{getStatusLabel(order.status)}</Tag>
        </div>
      </div>

      {/* Progress Steps */}
      {showSteps && (
        <Card size="small" style={{ padding: '24px' }}>
          <Steps
            current={currentStep}
            size="small"
            items={[
              {
                title: t('outbound.statusPending'),
                icon: <ClockCircleOutlined />,
                description: order.createdAt
                  ? dayjs(order.createdAt).format('YYYY-MM-DD HH:mm')
                  : undefined,
              },
              {
                title: t('outbound.statusPicking'),
                icon: <CarryOutOutlined />,
                description: order.pickingStartedAt
                  ? dayjs(order.pickingStartedAt).format('YYYY-MM-DD HH:mm')
                  : undefined,
              },
              {
                title: t('outbound.statusPacking'),
                icon: <InboxOutlined />,
                description: order.packingStartedAt
                  ? dayjs(order.packingStartedAt).format('YYYY-MM-DD HH:mm')
                  : undefined,
              },
              {
                title: t('outbound.statusReady'),
                icon: <CheckCircleOutlined />,
                description: order.packingCompletedAt
                  ? dayjs(order.packingCompletedAt).format('YYYY-MM-DD HH:mm')
                  : undefined,
              },
              {
                title: t('outbound.statusShipped'),
                icon: <SendOutlined />,
                description: order.shippedAt
                  ? dayjs(order.shippedAt).format('YYYY-MM-DD HH:mm')
                  : undefined,
              },
            ]}
          />
        </Card>
      )}

      {/* Basic Info */}
      <Card title={t('detail.basicInfo')}>
        <Descriptions column={{ xs: 1, sm: 2, md: 3 }} size="small">
          <Descriptions.Item label={t('outbound.outboundNo')}>
            <Text code copyable>
              {order.outboundNo}
            </Text>
          </Descriptions.Item>
          <Descriptions.Item label={t('adminOutbound.outboundType')}>
            <Tag color={typeColors[order.outboundType]}>{getOutboundTypeLabel(order.outboundType)}</Tag>
          </Descriptions.Item>
          <Descriptions.Item label={t('common.status')}>
            <Tag color={statusColors[order.status]}>{getStatusLabel(order.status)}</Tag>
          </Descriptions.Item>
          <Descriptions.Item label={t('warehouseOps.totalItems')}>
            {order.totalQuantity}
          </Descriptions.Item>
          {order.externalId && (
            <Descriptions.Item label={t('warehouseOps.externalId')}>
              <Text code copyable>
                {order.externalId}
              </Text>
            </Descriptions.Item>
          )}
          <Descriptions.Item label={t('common.createdAt')}>
            {dayjs(order.createdAt).format('YYYY-MM-DD HH:mm:ss')}
          </Descriptions.Item>
        </Descriptions>

        {order.remark && (
          <div className="mt-3 pt-3 border-t">
            <Text type="secondary">{t('common.remark')}:</Text>
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

      {/* Merchant & Warehouse Info */}
      <Card title={t('adminOutbound.merchantAndWarehouse')}>
        <Descriptions column={{ xs: 1, sm: 2 }} size="small">
          <Descriptions.Item label={t('adminOutbound.merchant')}>
            {order.merchant.name}
          </Descriptions.Item>
          <Descriptions.Item label={t('inventory.warehouse')}>
            {order.warehouse.name}
          </Descriptions.Item>
        </Descriptions>
      </Card>

      {/* Receiver Info */}
      <Card title={t('adminOutbound.receiverInfo')}>
        <Descriptions column={{ xs: 1, sm: 2 }} size="small">
          <Descriptions.Item label={t('warehouseOps.receiver')}>
            {order.receiverName}
          </Descriptions.Item>
          <Descriptions.Item label={t('warehouseOps.receiverPhone')}>
            {order.receiverPhone}
          </Descriptions.Item>
        </Descriptions>
        <Descriptions column={1} size="small" className="mt-4">
          <Descriptions.Item label={t('warehouseOps.receiverAddress')}>
            {order.receiverAddress}
            {order.receiverPostalCode && ` (${order.receiverPostalCode})`}
          </Descriptions.Item>
        </Descriptions>
      </Card>

      {/* Shipping Info */}
      {(order.shippingCarrier || order.trackingNumber) && (
        <Card title={t('adminOutbound.shippingInfo')}>
          <Descriptions column={{ xs: 1, sm: 2, md: 3 }} size="small">
            <Descriptions.Item label={t('warehouseOps.carrier')}>
              {order.shippingCarrier || '-'}
            </Descriptions.Item>
            <Descriptions.Item label={t('warehouseOps.trackingNumber')}>
              {order.trackingNumber ? (
                <Text code copyable>
                  {order.trackingNumber}
                </Text>
              ) : (
                '-'
              )}
            </Descriptions.Item>
            {order.shippedAt && (
              <Descriptions.Item label={t('outbound.shippedAt')}>
                {dayjs(order.shippedAt).format('YYYY-MM-DD HH:mm:ss')}
              </Descriptions.Item>
            )}
          </Descriptions>
        </Card>
      )}

      {/* Fulfillment Info */}
      {order.fulfillment && (
        <Card title={t('adminOutbound.fulfillmentInfo')}>
          <Descriptions column={1} size="small">
            <Descriptions.Item label={t('fulfillment.fulfillmentNo')}>
              <Link
                to={`/fulfillment/fulfillment-orders/${order.fulfillment.id}`}
                className="text-blue-600 hover:text-blue-800"
              >
                {order.fulfillment.fulfillmentNo}
              </Link>
            </Descriptions.Item>
          </Descriptions>
        </Card>
      )}

      {/* Items */}
      <Card title={`${t('warehouseOps.orderItems')} (${order.items.length})`}>
        <Table
          columns={itemColumns}
          dataSource={order.items}
          rowKey="id"
          pagination={false}
          scroll={{ x: 800 }}
          size="small"
        />
      </Card>

      {/* Timeline */}
      <Card title={t('outbound.timeline')}>
        <Timeline
          mode="left"
          items={buildTimelineItems().map((item) => ({
            color: item.color,
            children: (
              <div>
                <Text strong>{item.label}</Text>
                <br />
                <Text type="secondary">
                  {dayjs(item.time).format('YYYY-MM-DD HH:mm:ss')}
                </Text>
              </div>
            ),
          }))}
        />
      </Card>
    </div>
  );
}
