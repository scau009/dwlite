import { useState, useEffect, useCallback } from 'react';
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
} from 'antd';
import {
  ArrowLeftOutlined,
  ExclamationCircleOutlined,
} from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';

import {
  platformOrderApi,
  type PlatformOrderDetail,
  type OrderItem,
  type OrderExceptionSummary,
  type OrderStatus,
  type PaymentStatus,
  type AllocationStatus,
} from '@/lib/platform-order-api';

const { Text } = Typography;

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

// Allocation status color mapping
const allocationStatusColors: Record<AllocationStatus, string> = {
  pending: 'default',
  partial: 'warning',
  full: 'success',
  failed: 'error',
};

export function PlatformOrderDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { t } = useTranslation();
  const { message } = App.useApp();

  const [order, setOrder] = useState<PlatformOrderDetail | null>(null);
  const [loading, setLoading] = useState(true);

  const loadOrder = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    try {
      const { data } = await platformOrderApi.getDetail(id);
      setOrder(data);
    } catch {
      message.error(t('common.error'));
    } finally {
      setLoading(false);
    }
  }, [id, message, t]);

  useEffect(() => {
    loadOrder();
  }, [loadOrder]);

  // Navigate to exception detail
  const handleViewException = (exception: OrderExceptionSummary) => {
    navigate(`/fulfillment/order-exceptions/${exception.id}`);
  };

  // Order item columns
  const itemColumns: ColumnsType<OrderItem> = [
    {
      title: t('fulfillment.productImage'),
      dataIndex: 'productImage',
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
      dataIndex: 'productName',
      width: 200,
      ellipsis: true,
      render: (_, record) => record.productName || record.externalProductName || '-',
    },
    {
      title: t('products.skuCode'),
      dataIndex: 'skuCode',
      width: 120,
      render: (skuCode) => skuCode || '-',
    },
    {
      title: t('products.color'),
      dataIndex: 'colorCode',
      width: 80,
      render: (color) => color || '-',
    },
    {
      title: t('products.size'),
      dataIndex: 'sizeValue',
      width: 60,
      render: (size) => size || '-',
    },
    {
      title: t('common.quantity'),
      dataIndex: 'quantity',
      width: 80,
      align: 'center',
    },
    {
      title: t('fulfillment.allocatedQuantity'),
      dataIndex: 'allocatedQuantity',
      width: 100,
      align: 'center',
      render: (_, record) => `${record.allocatedQuantity}/${record.quantity}`,
    },
    {
      title: t('fulfillment.unitPrice'),
      dataIndex: 'unitPrice',
      width: 100,
      align: 'right',
    },
    {
      title: t('fulfillment.totalPrice'),
      dataIndex: 'totalPrice',
      width: 100,
      align: 'right',
    },
    {
      title: t('fulfillment.allocationStatus'),
      dataIndex: 'allocationStatus',
      width: 100,
      render: (_, record) => (
        <Tag color={allocationStatusColors[record.allocationStatus]}>
          {record.allocationStatusLabel}
        </Tag>
      ),
    },
  ];

  // Exception columns
  const exceptionColumns: ColumnsType<OrderExceptionSummary> = [
    {
      title: t('fulfillment.exceptionNo'),
      dataIndex: 'exceptionNo',
      width: 180,
      render: (_, record) => (
        <a onClick={() => handleViewException(record)} className="font-medium">
          {record.exceptionNo}
        </a>
      ),
    },
    {
      title: t('fulfillment.exceptionType'),
      dataIndex: 'typeLabel',
      width: 140,
    },
    {
      title: t('common.status'),
      dataIndex: 'status',
      width: 100,
      render: (_, record) => {
        const color =
          record.status === 'pending'
            ? 'warning'
            : record.status === 'resolved'
              ? 'success'
              : 'default';
        return <Tag color={color}>{record.statusLabel}</Tag>;
      },
    },
    {
      title: t('fulfillment.exceptionDescription'),
      dataIndex: 'description',
      ellipsis: true,
    },
    {
      title: t('common.createdAt'),
      dataIndex: 'createdAt',
      width: 160,
      render: (date) => new Date(date).toLocaleString(),
    },
    {
      title: t('common.actions'),
      key: 'actions',
      width: 100,
      render: (_, record) => (
        <Button type="link" size="small" onClick={() => handleViewException(record)}>
          {t('fulfillment.viewException')}
        </Button>
      ),
    },
  ];

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
          <Button type="primary" onClick={() => navigate('/fulfillment/orders')}>
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
            onClick={() => navigate('/fulfillment/orders')}
          >
            {t('common.back')}
          </Button>
          <Space>
            <span className="text-lg font-semibold">{order.orderNo}</span>
          </Space>
          <Tag color={orderStatusColors[order.status]}>{order.statusLabel}</Tag>
        </div>
      </div>

      {/* Basic Info */}
      <Card title={t('detail.basicInfo')}>
        <Descriptions column={{ xs: 1, sm: 2, md: 3 }} size="small">
          <Descriptions.Item label={t('fulfillment.platformOrderNo')}>
            <Text code copyable>
              {order.orderNo}
            </Text>
          </Descriptions.Item>
          <Descriptions.Item label={t('fulfillment.externalOrderNo')}>
            {order.externalOrderNo ? (
              <Text code copyable>
                {order.externalOrderNo}
              </Text>
            ) : (
              '-'
            )}
          </Descriptions.Item>
          <Descriptions.Item label={t('fulfillment.salesChannel')}>
            <Tag>{order.salesChannel.name}</Tag>
          </Descriptions.Item>
          <Descriptions.Item label={t('common.status')}>
            <Tag color={orderStatusColors[order.status]}>{order.statusLabel}</Tag>
          </Descriptions.Item>
          <Descriptions.Item label={t('fulfillment.paymentStatus')}>
            <Tag color={paymentStatusColors[order.paymentStatus]}>
              {order.paymentStatusLabel}
            </Tag>
          </Descriptions.Item>
          <Descriptions.Item label={t('fulfillment.placedAt')}>
            {new Date(order.placedAt).toLocaleString()}
          </Descriptions.Item>
          {order.paidAt && (
            <Descriptions.Item label={t('fulfillment.paidAt')}>
              {new Date(order.paidAt).toLocaleString()}
            </Descriptions.Item>
          )}
          <Descriptions.Item label={t('common.createdAt')}>
            {new Date(order.createdAt).toLocaleString()}
          </Descriptions.Item>
        </Descriptions>

        {/* Amount Info */}
        <div className="mt-4">
          <Descriptions column={{ xs: 1, sm: 2, md: 4 }} size="small">
            <Descriptions.Item label={t('fulfillment.productAmount')}>
              {order.currency} {order.productAmount}
            </Descriptions.Item>
            <Descriptions.Item label={t('fulfillment.shippingAmount')}>
              {order.currency} {order.shippingAmount}
            </Descriptions.Item>
            <Descriptions.Item label={t('fulfillment.discountAmount')}>
              {order.currency} {order.discountAmount}
            </Descriptions.Item>
            <Descriptions.Item label={t('fulfillment.orderAmount')}>
              <Text strong className="text-lg">
                {order.currency} {order.totalAmount}
              </Text>
            </Descriptions.Item>
          </Descriptions>
        </div>

        {/* Failure reason if allocation failed */}
        {order.allocationFailReason && (
          <div className="mt-4">
            <Text type="danger">{t('fulfillment.allocationFailReason')}:</Text>
            <p className="mt-1 text-red-500">{order.allocationFailReason}</p>
          </div>
        )}
      </Card>

      {/* Receiver Info */}
      <Card title={t('fulfillment.receiverInfo')}>
        <Descriptions column={{ xs: 1, sm: 2, md: 3 }} size="small">
          <Descriptions.Item label={t('fulfillment.receiver')}>
            {order.receiverName}
          </Descriptions.Item>
          <Descriptions.Item label={t('fulfillment.receiverPhone')}>
            {order.receiverPhone}
          </Descriptions.Item>
          <Descriptions.Item label={t('fulfillment.receiverProvince')}>
            {order.receiverProvince || '-'}
          </Descriptions.Item>
          <Descriptions.Item label={t('fulfillment.receiverCity')}>
            {order.receiverCity || '-'}
          </Descriptions.Item>
          <Descriptions.Item label={t('fulfillment.receiverDistrict')}>
            {order.receiverDistrict || '-'}
          </Descriptions.Item>
          <Descriptions.Item label={t('fulfillment.receiverPostalCode')}>
            {order.receiverPostalCode || '-'}
          </Descriptions.Item>
          <Descriptions.Item label={t('fulfillment.receiverAddress')} span={3}>
            {order.receiverAddress || '-'}
          </Descriptions.Item>
          {order.buyerRemark && (
            <Descriptions.Item label={t('fulfillment.buyerRemark')} span={3}>
              {order.buyerRemark}
            </Descriptions.Item>
          )}
          {order.sellerRemark && (
            <Descriptions.Item label={t('fulfillment.sellerRemark')} span={3}>
              {order.sellerRemark}
            </Descriptions.Item>
          )}
        </Descriptions>
      </Card>

      {/* Order Items */}
      <Card title={t('fulfillment.orderItems')}>
        <Table
          columns={itemColumns}
          dataSource={order.items}
          rowKey="id"
          pagination={false}
          scroll={{ x: 1100 }}
          size="small"
        />
      </Card>

      {/* Related Exceptions */}
      {order.exceptions.length > 0 && (
        <Card
          title={
            <Space>
              <ExclamationCircleOutlined className="text-orange-500" />
              {t('fulfillment.relatedExceptions')}
              <Tag color="red">{order.exceptions.length}</Tag>
            </Space>
          }
        >
          <Table
            columns={exceptionColumns}
            dataSource={order.exceptions}
            rowKey="id"
            pagination={false}
            size="small"
          />
        </Card>
      )}
    </div>
  );
}
