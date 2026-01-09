import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router';
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
  Typography,
  Table,
  Image,
  Timeline,
} from 'antd';
import {
  ArrowLeftOutlined,
  WarningOutlined,
  CheckCircleOutlined,
  ClockCircleOutlined,
  CloseCircleOutlined,
  CarOutlined,
  InboxOutlined,
  SendOutlined,
  ExclamationCircleOutlined,
} from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';

import {
  fulfillmentApi,
  type FulfillmentDetail,
  type FulfillmentItem,
  type FulfillmentStatus,
  type FulfillmentType,
} from '@/lib/fulfillment-api';

const { Text, Link } = Typography;

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

export function FulfillmentOrderDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { t } = useTranslation();
  const { message } = App.useApp();

  const [fulfillment, setFulfillment] = useState<FulfillmentDetail | null>(null);
  const [loading, setLoading] = useState(true);

  const loadFulfillment = async () => {
    if (!id) return;
    setLoading(true);
    try {
      const { data } = await fulfillmentApi.getDetail(id);
      setFulfillment(data);
    } catch {
      message.error(t('common.error'));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadFulfillment();
  }, [id]);

  // Navigate to order detail
  const handleViewOrder = () => {
    if (fulfillment) {
      navigate(`/fulfillment/orders/${fulfillment.order.id}`);
    }
  };

  // Navigate to outbound order
  const handleViewOutbound = () => {
    if (fulfillment?.outboundOrder) {
      navigate(`/warehouse/outbound/${fulfillment.outboundOrder.id}`);
    }
  };

  // Build timeline items
  const getTimelineItems = (data: FulfillmentDetail) => {
    const items = [];

    items.push({
      color: 'green',
      dot: <InboxOutlined />,
      children: (
        <div>
          <div className="font-medium">{t('fulfillmentOrder.timelineCreated')}</div>
          <div className="text-gray-500 text-sm">
            {new Date(data.createdAt).toLocaleString()}
          </div>
        </div>
      ),
    });

    if (data.notifiedAt) {
      items.push({
        color: 'blue',
        dot: <SendOutlined />,
        children: (
          <div>
            <div className="font-medium">{t('fulfillmentOrder.timelineNotified')}</div>
            <div className="text-gray-500 text-sm">
              {new Date(data.notifiedAt).toLocaleString()}
            </div>
          </div>
        ),
      });
    }

    if (data.shippedAt) {
      items.push({
        color: 'blue',
        dot: <CarOutlined />,
        children: (
          <div>
            <div className="font-medium">{t('fulfillmentOrder.timelineShipped')}</div>
            <div className="text-gray-500 text-sm">
              {new Date(data.shippedAt).toLocaleString()}
            </div>
            {data.shippingCarrier && (
              <div className="text-gray-500 text-sm">
                {data.shippingCarrier}: {data.trackingNumber}
              </div>
            )}
          </div>
        ),
      });
    }

    if (data.deliveredAt) {
      items.push({
        color: 'green',
        dot: <CheckCircleOutlined />,
        children: (
          <div>
            <div className="font-medium">{t('fulfillmentOrder.timelineDelivered')}</div>
            <div className="text-gray-500 text-sm">
              {new Date(data.deliveredAt).toLocaleString()}
            </div>
          </div>
        ),
      });
    }

    if (data.completedAt) {
      items.push({
        color: 'green',
        dot: <CheckCircleOutlined />,
        children: (
          <div>
            <div className="font-medium">{t('fulfillmentOrder.timelineCompleted')}</div>
            <div className="text-gray-500 text-sm">
              {new Date(data.completedAt).toLocaleString()}
            </div>
          </div>
        ),
      });
    }

    if (data.cancelledAt) {
      items.push({
        color: 'gray',
        dot: <CloseCircleOutlined />,
        children: (
          <div>
            <div className="font-medium">{t('fulfillmentOrder.timelineCancelled')}</div>
            <div className="text-gray-500 text-sm">
              {new Date(data.cancelledAt).toLocaleString()}
            </div>
            {data.cancelReason && (
              <div className="text-red-500 text-sm">{data.cancelReason}</div>
            )}
          </div>
        ),
      });
    }

    if (data.rejectedAt) {
      items.push({
        color: 'red',
        dot: <ExclamationCircleOutlined />,
        children: (
          <div>
            <div className="font-medium">
              {data.status === 'expired'
                ? t('fulfillmentOrder.timelineExpired')
                : t('fulfillmentOrder.timelineRejected')}
            </div>
            <div className="text-gray-500 text-sm">
              {new Date(data.rejectedAt).toLocaleString()}
            </div>
            {data.rejectionReason && (
              <div className="text-red-500 text-sm">{data.rejectionReason}</div>
            )}
          </div>
        ),
      });
    }

    // Add deadline warning for pending merchant warehouse orders
    if (data.deadlineAt && data.status === 'pending') {
      const isOverdue = data.isOverdue;
      items.push({
        color: isOverdue ? 'red' : 'orange',
        dot: isOverdue ? <WarningOutlined /> : <ClockCircleOutlined />,
        children: (
          <div>
            <div className={`font-medium ${isOverdue ? 'text-red-500' : ''}`}>
              {isOverdue
                ? t('fulfillmentOrder.timelineOverdue')
                : t('fulfillmentOrder.timelineDeadline')}
            </div>
            <div className={`text-sm ${isOverdue ? 'text-red-500' : 'text-gray-500'}`}>
              {new Date(data.deadlineAt).toLocaleString()}
            </div>
          </div>
        ),
      });
    }

    return items;
  };

  // Item columns
  const itemColumns: ColumnsType<FulfillmentItem> = [
    {
      title: t('fulfillment.productImage'),
      dataIndex: ['orderItem', 'productImage'],
      width: 80,
      render: (image) =>
        image ? (
          <Image
            src={image}
            width={50}
            height={50}
            style={{ objectFit: 'cover' }}
            fallback="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=="
          />
        ) : (
          <div className="w-[50px] h-[50px] bg-gray-100 flex items-center justify-center text-gray-400">
            N/A
          </div>
        ),
    },
    {
      title: t('fulfillment.productName'),
      dataIndex: ['orderItem', 'productName'],
      width: 200,
      ellipsis: true,
      render: (name) => name || '-',
    },
    {
      title: t('products.skuCode'),
      dataIndex: ['orderItem', 'skuCode'],
      width: 120,
      render: (skuCode) => skuCode || '-',
    },
    {
      title: t('products.color'),
      dataIndex: ['orderItem', 'colorCode'],
      width: 80,
      render: (color) => color || '-',
    },
    {
      title: t('products.size'),
      dataIndex: ['orderItem', 'sizeValue'],
      width: 60,
      render: (size) => size || '-',
    },
    {
      title: t('fulfillmentOrder.allocatedQuantity'),
      dataIndex: 'quantity',
      width: 100,
      align: 'center',
    },
    {
      title: t('fulfillmentOrder.orderQuantity'),
      dataIndex: ['orderItem', 'quantity'],
      width: 100,
      align: 'center',
    },
    {
      title: t('fulfillmentOrder.listPrice'),
      dataIndex: 'listPrice',
      width: 100,
      align: 'right',
      render: (price) => price || '-',
    },
    {
      title: t('fulfillmentOrder.settlementPrice'),
      dataIndex: 'settlementPrice',
      width: 100,
      align: 'right',
      render: (price) => price || '-',
    },
    {
      title: t('common.merchant'),
      dataIndex: ['merchant', 'name'],
      width: 120,
      render: (name) => name || '-',
    },
  ];

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Spin size="large" />
      </div>
    );
  }

  if (!fulfillment) {
    return (
      <Card>
        <Empty description={t('common.noData')}>
          <Button type="primary" onClick={() => navigate('/fulfillment/fulfillment-orders')}>
            {t('common.back')}
          </Button>
        </Empty>
      </Card>
    );
  }

  return (
    <div className="flex flex-col gap-4">
      {/* Header */}
      <div className="flex items-center justify-between flex-wrap gap-4">
        <div className="flex items-center gap-4">
          <Button
            icon={<ArrowLeftOutlined />}
            onClick={() => navigate('/fulfillment/fulfillment-orders')}
          >
            {t('common.back')}
          </Button>
          <Space>
            <span className="text-lg font-semibold">{fulfillment.fulfillmentNo}</span>
          </Space>
          <Tag color={statusColors[fulfillment.status]}>{fulfillment.statusLabel}</Tag>
          <Tag color={typeColors[fulfillment.fulfillmentType]}>
            {fulfillment.fulfillmentTypeLabel}
          </Tag>
          {fulfillment.isOverdue && fulfillment.status === 'pending' && (
            <Tag icon={<WarningOutlined />} color="error">
              {t('fulfillmentOrder.overdue')}
            </Tag>
          )}
        </div>
      </div>

      {/* Basic Info */}
      <Card title={t('detail.basicInfo')}>
        <Descriptions column={{ xs: 1, sm: 2, md: 3 }} size="small">
          <Descriptions.Item label={t('fulfillmentOrder.fulfillmentNo')}>
            <Text code copyable>
              {fulfillment.fulfillmentNo}
            </Text>
          </Descriptions.Item>
          <Descriptions.Item label={t('fulfillmentOrder.type')}>
            <Tag color={typeColors[fulfillment.fulfillmentType]}>
              {fulfillment.fulfillmentTypeLabel}
            </Tag>
          </Descriptions.Item>
          <Descriptions.Item label={t('common.status')}>
            <Tag color={statusColors[fulfillment.status]}>{fulfillment.statusLabel}</Tag>
          </Descriptions.Item>
          <Descriptions.Item label={t('fulfillmentOrder.relatedOrder')}>
            <Link onClick={handleViewOrder}>{fulfillment.order.orderNo}</Link>
          </Descriptions.Item>
          <Descriptions.Item label={t('common.merchant')}>
            {fulfillment.merchant?.name || '-'}
          </Descriptions.Item>
          <Descriptions.Item label={t('common.warehouse')}>
            {fulfillment.warehouse.name}
          </Descriptions.Item>
          <Descriptions.Item label={t('fulfillmentOrder.allocationSource')}>
            {fulfillment.allocationSourceLabel || '-'}
          </Descriptions.Item>
          <Descriptions.Item label={t('fulfillmentOrder.allocationAttempt')}>
            {fulfillment.allocationAttempt}
          </Descriptions.Item>
          <Descriptions.Item label={t('common.createdAt')}>
            {new Date(fulfillment.createdAt).toLocaleString()}
          </Descriptions.Item>
          {fulfillment.deadlineAt && (
            <Descriptions.Item label={t('fulfillmentOrder.deadline')}>
              <span className={fulfillment.isOverdue ? 'text-red-500' : ''}>
                {new Date(fulfillment.deadlineAt).toLocaleString()}
              </span>
            </Descriptions.Item>
          )}
          {fulfillment.remark && (
            <Descriptions.Item label={t('common.remark')} span={3}>
              {fulfillment.remark}
            </Descriptions.Item>
          )}
        </Descriptions>
      </Card>

      {/* Order Info */}
      <Card title={t('fulfillmentOrder.orderInfo')}>
        <Descriptions column={{ xs: 1, sm: 2, md: 3 }} size="small">
          <Descriptions.Item label={t('fulfillment.platformOrderNo')}>
            <Link onClick={handleViewOrder}>{fulfillment.order.orderNo}</Link>
          </Descriptions.Item>
          <Descriptions.Item label={t('fulfillment.externalOrderNo')}>
            {fulfillment.order.externalOrderNo || '-'}
          </Descriptions.Item>
          <Descriptions.Item label={t('fulfillment.orderAmount')}>
            {fulfillment.order.currency} {fulfillment.order.totalAmount}
          </Descriptions.Item>
          <Descriptions.Item label={t('fulfillment.receiver')}>
            {fulfillment.order.receiverName}
          </Descriptions.Item>
          <Descriptions.Item label={t('fulfillment.receiverAddress')} span={2}>
            {fulfillment.order.receiverFullAddress}
          </Descriptions.Item>
        </Descriptions>
      </Card>

      {/* Logistics Info */}
      {(fulfillment.shippingCarrier || fulfillment.trackingNumber) && (
        <Card title={t('fulfillmentOrder.logistics')}>
          <Descriptions column={{ xs: 1, sm: 2, md: 3 }} size="small">
            <Descriptions.Item label={t('fulfillmentOrder.shippingCarrier')}>
              {fulfillment.shippingCarrier || '-'}
            </Descriptions.Item>
            <Descriptions.Item label={t('fulfillmentOrder.trackingNumber')}>
              {fulfillment.trackingNumber ? (
                <Text code copyable>
                  {fulfillment.trackingNumber}
                </Text>
              ) : (
                '-'
              )}
            </Descriptions.Item>
            {fulfillment.trackingUrl && (
              <Descriptions.Item label={t('fulfillmentOrder.trackingUrl')}>
                <a href={fulfillment.trackingUrl} target="_blank" rel="noopener noreferrer">
                  {t('fulfillmentOrder.trackLink')}
                </a>
              </Descriptions.Item>
            )}
            {fulfillment.shippedAt && (
              <Descriptions.Item label={t('fulfillmentOrder.shippedAt')}>
                {new Date(fulfillment.shippedAt).toLocaleString()}
              </Descriptions.Item>
            )}
          </Descriptions>
        </Card>
      )}

      {/* Outbound Order Info (for platform warehouse) */}
      {fulfillment.outboundOrder && (
        <Card title={t('fulfillmentOrder.outboundOrder')}>
          <Descriptions column={{ xs: 1, sm: 2, md: 3 }} size="small">
            <Descriptions.Item label={t('inventory.outboundNo')}>
              <Link onClick={handleViewOutbound}>{fulfillment.outboundOrder.orderNo}</Link>
            </Descriptions.Item>
            <Descriptions.Item label={t('common.status')}>
              {fulfillment.outboundOrder.status}
            </Descriptions.Item>
          </Descriptions>
        </Card>
      )}

      {/* Items */}
      <Card title={t('fulfillmentOrder.items')}>
        <Table
          columns={itemColumns}
          dataSource={fulfillment.items}
          rowKey="id"
          pagination={false}
          scroll={{ x: 1200 }}
          size="small"
        />
      </Card>

      {/* Timeline */}
      <Card title={t('fulfillmentOrder.timeline')}>
        <Timeline items={getTimelineItems(fulfillment)} />
      </Card>
    </div>
  );
}
