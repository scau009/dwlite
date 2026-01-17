import { useState, useEffect, useCallback, useMemo } from 'react';
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
  Table,
  Image,
  Typography,
  Timeline,
  Input,
} from 'antd';
import {
  ArrowLeftOutlined,
  TruckOutlined,
  ExclamationCircleOutlined,
} from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';

import {
  adminInboundApi,
  type AdminInboundOrderDetail,
  type InboundOrderStatus,
  type InboundOrderItem,
  type InboundException,
} from '@/lib/admin-inbound-api';
import { getCurrencySymbol } from '@/lib/merchant-listing-api';

const { Text } = Typography;

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

export function AdminInboundOrderDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { t } = useTranslation();
  const { message } = App.useApp();

  const [order, setOrder] = useState<AdminInboundOrderDetail | null>(null);
  const [loading, setLoading] = useState(true);

  // Search and pagination state for items
  const [itemSearchKeyword, setItemSearchKeyword] = useState('');
  const [itemCurrentPage, setItemCurrentPage] = useState(1);
  const itemPageSize = 10;

  const loadOrder = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    try {
      const data = await adminInboundApi.getInboundOrder(id);
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

  // Get status label
  const getStatusLabel = useCallback(
    (status: InboundOrderStatus) => {
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
    },
    [t]
  );

  // Filter items based on search keyword
  const filteredItems = useMemo(() => {
    if (!order?.items) return [];
    if (!itemSearchKeyword.trim()) return order.items;

    const keyword = itemSearchKeyword.toLowerCase().trim();
    return order.items.filter((item) => {
      const productName = item.productName?.toLowerCase() || '';
      const styleNumber = item.styleNumber?.toLowerCase() || '';
      const skuName = item.productSku?.skuName?.toLowerCase() || '';
      const colorName = item.productSku?.colorName?.toLowerCase() || '';

      return (
        productName.includes(keyword) ||
        styleNumber.includes(keyword) ||
        skuName.includes(keyword) ||
        colorName.includes(keyword)
      );
    });
  }, [order?.items, itemSearchKeyword]);

  // Reset page when search changes
  useEffect(() => {
    setItemCurrentPage(1);
  }, [itemSearchKeyword]);

  // Item table columns (read-only)
  const itemColumns: ColumnsType<InboundOrderItem> = [
    {
      title: t('inventory.productImage'),
      dataIndex: 'productImage',
      width: 80,
      render: (image: string | null) =>
        image ? (
          <Image
            src={image}
            width={60}
            height={60}
            style={{ objectFit: 'contain', background: '#f5f5f5' }}
          />
        ) : (
          <div className="w-[60px] h-[60px] bg-gray-100 flex items-center justify-center text-gray-400">
            N/A
          </div>
        ),
    },
    {
      title: t('inventory.productName'),
      dataIndex: 'productName',
      width: 180,
      render: (name: string | null) => name || '-',
    },
    {
      title: t('inventory.styleNumber'),
      dataIndex: 'styleNumber',
      width: 120,
      render: (styleNumber: string | null) => styleNumber || '-',
    },
    {
      title: t('inventory.skuName'),
      dataIndex: ['productSku', 'skuName'],
      width: 80,
      render: (skuName: string | null) => skuName || '-',
    },
    {
      title: t('inventory.unitCost'),
      dataIndex: 'unitCost',
      width: 100,
      align: 'right',
      render: (cost: string | null, record: InboundOrderItem) => {
        const currencySymbol = getCurrencySymbol(record.currency);
        return cost ? `${currencySymbol}${cost}` : '-';
      },
    },
    {
      title: t('inventory.expectedQuantity'),
      dataIndex: 'expectedQuantity',
      width: 100,
      align: 'center',
    },
    {
      title: t('inventory.itemReceivedQuantity'),
      dataIndex: 'receivedQuantity',
      width: 100,
      align: 'center',
      render: (qty: number, record) => (
        <span className={qty < record.expectedQuantity ? 'text-orange-500' : 'text-green-500'}>
          {qty}
        </span>
      ),
    },
    {
      title: t('inventory.damagedQuantity'),
      dataIndex: 'damagedQuantity',
      width: 100,
      align: 'center',
      render: (qty: number) => <span className={qty > 0 ? 'text-red-500' : ''}>{qty}</span>,
    },
  ];

  // Helper to get exception type label
  const getExceptionTypeLabel = useCallback(
    (type: string) => {
      const labels: Record<string, string> = {
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
    },
    [t]
  );

  // Helper to get exception status label
  const getExceptionStatusLabel = useCallback(
    (status: string) => {
      const labels: Record<string, string> = {
        pending: t('inventory.exceptionStatusPending'),
        processing: t('inventory.exceptionStatusProcessing'),
        resolved: t('inventory.exceptionStatusResolved'),
        closed: t('inventory.exceptionStatusClosed'),
      };
      return labels[status] || status;
    },
    [t]
  );

  // Exception columns
  const exceptionColumns: ColumnsType<InboundException> = [
    {
      title: t('inventory.exceptionNo'),
      dataIndex: 'exceptionNo',
      width: 160,
    },
    {
      title: t('inventory.exceptionType'),
      dataIndex: 'type',
      width: 120,
      render: (type: string) => {
        const colors: Record<string, string> = {
          quantity_short: 'orange',
          quantity_over: 'blue',
          damaged: 'red',
          wrong_item: 'purple',
          quality_issue: 'magenta',
          packaging: 'gold',
          expired: 'volcano',
          other: 'default',
        };
        return <Tag color={colors[type] || 'default'}>{getExceptionTypeLabel(type)}</Tag>;
      },
    },
    {
      title: t('common.status'),
      dataIndex: 'status',
      width: 100,
      render: (status: string) => {
        const colors: Record<string, string> = {
          pending: 'warning',
          processing: 'processing',
          resolved: 'success',
          closed: 'default',
        };
        return <Tag color={colors[status]}>{getExceptionStatusLabel(status)}</Tag>;
      },
    },
    {
      title: t('inventory.differenceQuantity'),
      dataIndex: 'totalQuantity',
      width: 100,
      align: 'center',
      render: (qty: number) => (
        <span className={qty !== 0 ? 'text-red-500 font-medium' : ''}>{qty}</span>
      ),
    },
    {
      title: t('inventory.exceptionDescription'),
      dataIndex: 'description',
      ellipsis: true,
    },
    {
      title: t('common.createdAt'),
      dataIndex: 'createdAt',
      width: 160,
      render: (date: string) => new Date(date).toLocaleString(),
    },
    {
      title: t('inventory.resolution'),
      dataIndex: 'resolution',
      width: 120,
      render: (resolution: string | null) =>
        resolution
          ? t(`inventory.resolution${resolution.charAt(0).toUpperCase() + resolution.slice(1)}`)
          : '-',
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
          <Button type="primary" onClick={() => navigate('/admin/inbound/orders')}>
            {t('common.back')}
          </Button>
        </Empty>
      </Card>
    );
  }

  const showShipment = order.shipment !== null;
  const showExceptions = order.exceptions.length > 0;

  return (
    <div className="flex flex-col gap-4">
      {/* Header */}
      <div className="flex items-center justify-between flex-wrap gap-4">
        <div className="flex items-center gap-4">
          <Button icon={<ArrowLeftOutlined />} onClick={() => navigate('/admin/inbound/orders')}>
            {t('common.back')}
          </Button>
          <span className="text-lg font-semibold">{order.orderNo}</span>
          <Tag color={statusColors[order.status]}>{getStatusLabel(order.status)}</Tag>
        </div>
      </div>

      {/* Basic Info */}
      <Card title={t('detail.basicInfo')}>
        <Descriptions column={{ xs: 1, sm: 2, md: 3 }} size="small">
          <Descriptions.Item label={t('inventory.orderNo')}>
            <Text code copyable>
              {order.orderNo}
            </Text>
          </Descriptions.Item>
          <Descriptions.Item label={t('adminInbound.merchant')}>
            {order.merchant.name}
          </Descriptions.Item>
          <Descriptions.Item label={t('inventory.warehouse')}>{order.warehouse.name}</Descriptions.Item>
          <Descriptions.Item label={t('common.status')}>
            <Tag color={statusColors[order.status]}>{getStatusLabel(order.status)}</Tag>
          </Descriptions.Item>
          <Descriptions.Item label={t('inventory.totalSkuCount')}>
            {order.totalSkuCount}
          </Descriptions.Item>
          <Descriptions.Item label={t('inventory.totalQuantity')}>
            {order.totalQuantity}
          </Descriptions.Item>
          <Descriptions.Item label={t('inventory.receivedQuantity')}>
            <span
              className={
                order.receivedQuantity < order.totalQuantity ? 'text-orange-500' : 'text-green-500'
              }
            >
              {order.receivedQuantity}
            </span>
          </Descriptions.Item>
          <Descriptions.Item label={t('inventory.expectedArrivalDate')}>
            {order.expectedArrivalDate
              ? new Date(order.expectedArrivalDate).toLocaleDateString()
              : '-'}
          </Descriptions.Item>
          <Descriptions.Item label={t('common.createdAt')}>
            {new Date(order.createdAt).toLocaleString()}
          </Descriptions.Item>
          {order.submittedAt && (
            <Descriptions.Item label={t('inventory.submittedAt')}>
              {new Date(order.submittedAt).toLocaleString()}
            </Descriptions.Item>
          )}
          {order.completedAt && (
            <Descriptions.Item label={t('inventory.completedAt')}>
              {new Date(order.completedAt).toLocaleString()}
            </Descriptions.Item>
          )}
        </Descriptions>
        {order.merchantNotes && (
          <div className="mt-3 pt-3 ">
            <Text type="secondary">{t('inventory.merchantNotes')}:</Text>
            <p className="mt-1">{order.merchantNotes}</p>
          </div>
        )}
        {order.warehouseNotes && (
          <div className="mt-3 pt-3 ">
            <Text type="secondary">{t('inventory.warehouseNotes')}:</Text>
            <p className="mt-1">{order.warehouseNotes}</p>
          </div>
        )}
        {order.cancelReason && (
          <div className="mt-3 pt-3 ">
            <Text type="danger">{t('inventory.cancelReason')}:</Text>
            <p className="mt-1 text-red-500">{order.cancelReason}</p>
          </div>
        )}
      </Card>

      {/* Exceptions - placed between Basic Info and Items */}
      {showExceptions && (
        <Card
          title={
            <Space>
              <ExclamationCircleOutlined className="text-orange-500" />
              {t('inventory.exceptions')} ({order.exceptions.length})
            </Space>
          }
        >
          <Table
            columns={exceptionColumns}
            dataSource={order.exceptions}
            rowKey="id"
            pagination={false}
            scroll={{ x: 900 }}
            size="small"
          />
        </Card>
      )}

      {/* Items */}
      <Card title={`${t('inventory.orderItems')} (${order.items.length})`}>
        {/* Search input */}
        <div className="mb-3">
          <Input.Search
            placeholder={t('inventory.searchItemsPlaceholder')}
            allowClear
            value={itemSearchKeyword}
            onChange={(e) => setItemSearchKeyword(e.target.value)}
            style={{ maxWidth: 320 }}
          />
        </div>
        <Table
          columns={itemColumns}
          dataSource={filteredItems}
          rowKey="id"
          pagination={{
            current: itemCurrentPage,
            pageSize: itemPageSize,
            total: filteredItems.length,
            onChange: setItemCurrentPage,
            showSizeChanger: false,
            showTotal: (total) =>
              t('common.showing', {
                from: Math.min((itemCurrentPage - 1) * itemPageSize + 1, total),
                to: Math.min(itemCurrentPage * itemPageSize, total),
                total,
              }),
          }}
          scroll={{ x: 1100 }}
          size="small"
        />
      </Card>

      {/* Shipment Info */}
      {showShipment && order.shipment && (
        <Card
          title={
            <Space>
              <TruckOutlined />
              {t('inventory.shipmentInfo')}
            </Space>
          }
        >
          <Descriptions column={{ xs: 1, sm: 2, md: 3 }} size="small">
            <Descriptions.Item label={t('inventory.carrierName')}>
              {order.shipment.carrierName || order.shipment.carrierCode}
            </Descriptions.Item>
            <Descriptions.Item label={t('inventory.trackingNumber')}>
              <Text code copyable>
                {order.shipment.trackingNumber}
              </Text>
            </Descriptions.Item>
            <Descriptions.Item label={t('common.status')}>
              <Tag>{order.shipment.status}</Tag>
            </Descriptions.Item>
            <Descriptions.Item label={t('inventory.senderName')}>
              {order.shipment.senderName}
            </Descriptions.Item>
            <Descriptions.Item label={t('inventory.senderPhone')}>
              {order.shipment.senderPhone}
            </Descriptions.Item>
            <Descriptions.Item label={t('inventory.boxCount')}>{order.shipment.boxCount}</Descriptions.Item>
            {order.shipment.totalWeight && (
              <Descriptions.Item label={t('inventory.totalWeight')}>
                {order.shipment.totalWeight} kg
              </Descriptions.Item>
            )}
            <Descriptions.Item label={t('inventory.shippedAt')}>
              {new Date(order.shipment.shippedAt).toLocaleString()}
            </Descriptions.Item>
            {order.shipment.estimatedArrivalDate && (
              <Descriptions.Item label={t('inventory.estimatedArrivalDate')}>
                {new Date(order.shipment.estimatedArrivalDate).toLocaleDateString()}
              </Descriptions.Item>
            )}
            {order.shipment.deliveredAt && (
              <Descriptions.Item label={t('inventory.deliveredAt')}>
                {new Date(order.shipment.deliveredAt).toLocaleString()}
              </Descriptions.Item>
            )}
          </Descriptions>
          <Descriptions column={1} size="small" className="mt-2">
            <Descriptions.Item label={t('inventory.senderAddress')}>
              {order.shipment.senderAddress}
            </Descriptions.Item>
          </Descriptions>
        </Card>
      )}

      {/* Order Timeline */}
      <Card title={t('inventory.orderTimeline')}>
        <Timeline
          items={[
            {
              color: 'green',
              children: (
                <div>
                  <Text strong>{t('inventory.orderCreated')}</Text>
                  <br />
                  <Text type="secondary">{new Date(order.createdAt).toLocaleString()}</Text>
                </div>
              ),
            },
            ...(order.submittedAt
              ? [
                  {
                    color: 'blue',
                    children: (
                      <div>
                        <Text strong>{t('inventory.orderSubmitted')}</Text>
                        <br />
                        <Text type="secondary">{new Date(order.submittedAt).toLocaleString()}</Text>
                      </div>
                    ),
                  },
                ]
              : []),
            ...(order.shippedAt
              ? [
                  {
                    color: 'cyan',
                    children: (
                      <div>
                        <Text strong>{t('inventory.orderShipped')}</Text>
                        <br />
                        <Text type="secondary">{new Date(order.shippedAt).toLocaleString()}</Text>
                      </div>
                    ),
                  },
                ]
              : []),
            ...(order.completedAt
              ? [
                  {
                    color: 'green',
                    children: (
                      <div>
                        <Text strong>{t('inventory.orderCompleted')}</Text>
                        <br />
                        <Text type="secondary">{new Date(order.completedAt).toLocaleString()}</Text>
                      </div>
                    ),
                  },
                ]
              : []),
            ...(order.status === 'cancelled'
              ? [
                  {
                    color: 'red',
                    children: (
                      <div>
                        <Text strong>{t('inventory.orderCancelled')}</Text>
                        <br />
                        {order.cancelReason && <Text type="secondary">{order.cancelReason}</Text>}
                      </div>
                    ),
                  },
                ]
              : []),
          ]}
        />
      </Card>
    </div>
  );
}
