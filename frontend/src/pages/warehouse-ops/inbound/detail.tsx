import { useState, useEffect, useMemo } from 'react';
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
  Divider,
  Timeline,
  Input,
  InputNumber,
} from 'antd';
import {
  ArrowLeftOutlined,
  CheckCircleOutlined,
  ExclamationCircleOutlined,
  TruckOutlined,
  EditOutlined,
  SaveOutlined,
} from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';

import {
  warehouseOpsApi,
  type WarehouseInboundOrderDetail,
  type WarehouseInboundStatus,
  type WarehouseInboundItem,
  type WarehouseInboundException,
  type CompleteReceivingItem,
} from '@/lib/warehouse-operations-api';

const { Text } = Typography;
const { TextArea } = Input;

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

export function WarehouseInboundDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { t } = useTranslation();
  const { modal, message } = App.useApp();

  const [order, setOrder] = useState<WarehouseInboundOrderDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState(false);

  // Receiving state
  const [isReceivingMode, setIsReceivingMode] = useState(false);
  const [receivingData, setReceivingData] = useState<Record<string, CompleteReceivingItem>>({});
  const [receivingNotes, setReceivingNotes] = useState('');

  // Notes editing state
  const [editingNotes, setEditingNotes] = useState(false);
  const [notesValue, setNotesValue] = useState('');

  // Search and pagination state for items
  const [itemSearchKeyword, setItemSearchKeyword] = useState('');
  const [itemCurrentPage, setItemCurrentPage] = useState(1);
  const itemPageSize = 10;

  const loadOrder = async () => {
    if (!id) return;
    setLoading(true);
    try {
      const response = await warehouseOpsApi.getInboundOrder(id);
      setOrder(response.data);
      setNotesValue(response.data.warehouseNotes || '');
    } catch {
      message.error(t('common.error'));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadOrder();
  }, [id]);

  // Initialize receiving data when entering receiving mode
  useEffect(() => {
    if (isReceivingMode && order) {
      const initialData: Record<string, CompleteReceivingItem> = {};
      order.items.forEach(item => {
        initialData[item.id] = {
          itemId: item.id,
          receivedQuantity: item.expectedQuantity,
          damagedQuantity: 0,
          warehouseRemark: '',
        };
      });
      setReceivingData(initialData);
    }
  }, [isReceivingMode, order]);

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

  // Handle complete receiving
  const handleCompleteReceiving = () => {
    modal.confirm({
      title: t('warehouseOps.confirmReceiving'),
      content: t('warehouseOps.confirmReceivingDesc'),
      okText: t('common.confirm'),
      cancelText: t('common.cancel'),
      onOk: async () => {
        setActionLoading(true);
        try {
          const items = Object.values(receivingData);
          await warehouseOpsApi.completeReceiving(id!, {
            items,
            notes: receivingNotes || undefined,
          });
          message.success(t('warehouseOps.receivingCompleted'));
          setIsReceivingMode(false);
          setReceivingData({});
          setReceivingNotes('');
          loadOrder();
        } catch (error) {
          const err = error as { error?: string };
          message.error(err.error || t('common.error'));
        } finally {
          setActionLoading(false);
        }
      },
    });
  };

  // Handle save notes
  const handleSaveNotes = async () => {
    setActionLoading(true);
    try {
      await warehouseOpsApi.updateWarehouseNotes(id!, notesValue);
      message.success(t('common.success'));
      setEditingNotes(false);
      loadOrder();
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setActionLoading(false);
    }
  };

  // Update receiving item
  const updateReceivingItem = (
    itemId: string,
    field: 'receivedQuantity' | 'damagedQuantity' | 'warehouseRemark',
    value: number | string
  ) => {
    setReceivingData(prev => ({
      ...prev,
      [itemId]: {
        ...prev[itemId],
        [field]: value,
      },
    }));
  };

  // Filter items based on search keyword
  const filteredItems = useMemo(() => {
    if (!order?.items) return [];
    if (!itemSearchKeyword.trim()) return order.items;

    const keyword = itemSearchKeyword.toLowerCase().trim();
    return order.items.filter(item => {
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

  // Item table columns
  const itemColumns: ColumnsType<WarehouseInboundItem> = [
    {
      title: t('inventory.productImage'),
      dataIndex: 'productImage',
      width: 80,
      render: (image: string | null) =>
        image ? (
          <Image src={image} width={60} height={60} style={{ objectFit: 'cover' }} />
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
      title: t('inventory.colorName'),
      dataIndex: ['productSku', 'colorName'],
      width: 80,
      render: (color: string | null) => color || '-',
    },
    {
      title: t('inventory.expectedQuantity'),
      dataIndex: 'expectedQuantity',
      width: 80,
      align: 'center',
    },
  ];

  // Add receiving columns when in receiving mode
  if (isReceivingMode) {
    itemColumns.push(
      {
        title: t('warehouseOps.actualReceived'),
        dataIndex: 'id',
        width: 100,
        render: (itemId: string, record) => (
          <InputNumber
            min={0}
            max={record.expectedQuantity}
            value={receivingData[itemId]?.receivedQuantity}
            onChange={val => updateReceivingItem(itemId, 'receivedQuantity', val || 0)}
            size="small"
            style={{ width: 80 }}
          />
        ),
      },
      {
        title: t('warehouseOps.damagedQuantity'),
        dataIndex: 'id',
        width: 100,
        render: (itemId: string, record) => (
          <InputNumber
            min={0}
            max={record.expectedQuantity}
            value={receivingData[itemId]?.damagedQuantity}
            onChange={val => updateReceivingItem(itemId, 'damagedQuantity', val || 0)}
            size="small"
            style={{ width: 80 }}
          />
        ),
      },
      {
        title: t('warehouseOps.remark'),
        dataIndex: 'id',
        width: 150,
        render: (itemId: string) => (
          <Input
            value={receivingData[itemId]?.warehouseRemark}
            onChange={e => updateReceivingItem(itemId, 'warehouseRemark', e.target.value)}
            placeholder={t('warehouseOps.remarkPlaceholder')}
            size="small"
          />
        ),
      }
    );
  } else {
    // Show received quantities in normal mode
    itemColumns.push(
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
        width: 80,
        align: 'center',
        render: (qty: number) => (
          <span className={qty > 0 ? 'text-red-500' : ''}>{qty}</span>
        ),
      },
      {
        title: t('warehouseOps.remark'),
        dataIndex: 'warehouseRemark',
        width: 150,
        ellipsis: true,
        render: (remark: string | null) => remark || '-',
      }
    );
  }

  // Exception columns
  const exceptionColumns: ColumnsType<WarehouseInboundException> = [
    {
      title: t('inventory.exceptionNo'),
      dataIndex: 'exceptionNo',
      width: 140,
    },
    {
      title: t('inventory.exceptionType'),
      dataIndex: 'typeLabel',
      width: 120,
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
        return <Tag color={colors[status]}>{status}</Tag>;
      },
    },
    {
      title: t('warehouseOps.exceptionQuantity'),
      dataIndex: 'totalQuantity',
      width: 100,
      align: 'center',
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
          <Button type="primary" onClick={() => navigate('/warehouse/inbound')}>
            {t('common.back')}
          </Button>
        </Empty>
      </Card>
    );
  }

  const canReceive = ['shipped', 'arrived', 'receiving'].includes(order.status);
  const showShipment = order.shipment !== null;
  const showExceptions = order.exceptions.length > 0;

  return (
    <div className="flex flex-col gap-4">
      {/* Header */}
      <div className="flex items-center justify-between flex-wrap gap-4">
        <div className="flex items-center gap-4">
          <Button icon={<ArrowLeftOutlined />} onClick={() => navigate('/warehouse/inbound')}>
            {t('common.back')}
          </Button>
          <h1 className="text-xl font-semibold m-0">{order.orderNo}</h1>
          <Tag color={statusColors[order.status]}>{getStatusLabel(order.status)}</Tag>
        </div>
        <Space wrap>
          {canReceive && !isReceivingMode && (
            <Button
              type="primary"
              icon={<CheckCircleOutlined />}
              onClick={() => setIsReceivingMode(true)}
            >
              {t('warehouseOps.startReceiving')}
            </Button>
          )}
          {isReceivingMode && (
            <>
              <Button onClick={() => setIsReceivingMode(false)}>{t('common.cancel')}</Button>
              <Button
                type="primary"
                icon={<CheckCircleOutlined />}
                onClick={handleCompleteReceiving}
                loading={actionLoading}
              >
                {t('warehouseOps.completeReceiving')}
              </Button>
            </>
          )}
        </Space>
      </div>

      {/* Basic Info */}
      <Card title={t('detail.basicInfo')}>
        <Descriptions column={{ xs: 1, sm: 2, md: 3 }} size="small">
          <Descriptions.Item label={t('inventory.orderNo')}>
            <Text code copyable>
              {order.orderNo}
            </Text>
          </Descriptions.Item>
          <Descriptions.Item label={t('warehouseOps.merchant')}>
            {order.merchant.companyName}
          </Descriptions.Item>
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
          {order.completedAt && (
            <Descriptions.Item label={t('inventory.completedAt')}>
              {new Date(order.completedAt).toLocaleString()}
            </Descriptions.Item>
          )}
        </Descriptions>
        {order.merchantNotes && (
          <div className="mt-3 pt-3 border-t">
            <Text type="secondary">{t('inventory.merchantNotes')}:</Text>
            <p className="mt-1">{order.merchantNotes}</p>
          </div>
        )}

        {/* Warehouse Notes */}
        <div className="mt-3 pt-3 border-t">
          <div className="flex items-center justify-between mb-2">
            <Text type="secondary">{t('inventory.warehouseNotes')}:</Text>
            {!editingNotes ? (
              <Button
                type="text"
                size="small"
                icon={<EditOutlined />}
                onClick={() => setEditingNotes(true)}
              >
                {t('common.edit')}
              </Button>
            ) : (
              <Space size="small">
                <Button size="small" onClick={() => setEditingNotes(false)}>
                  {t('common.cancel')}
                </Button>
                <Button
                  type="primary"
                  size="small"
                  icon={<SaveOutlined />}
                  onClick={handleSaveNotes}
                  loading={actionLoading}
                >
                  {t('common.save')}
                </Button>
              </Space>
            )}
          </div>
          {editingNotes ? (
            <TextArea
              value={notesValue}
              onChange={e => setNotesValue(e.target.value)}
              rows={3}
              placeholder={t('warehouseOps.notesPlaceholder')}
            />
          ) : (
            <p className="mt-1">{order.warehouseNotes || '-'}</p>
          )}
        </div>
      </Card>

      {/* Receiving Notes (only in receiving mode) */}
      {isReceivingMode && (
        <Card title={t('warehouseOps.receivingNotes')} size="small">
          <TextArea
            value={receivingNotes}
            onChange={e => setReceivingNotes(e.target.value)}
            rows={2}
            placeholder={t('warehouseOps.receivingNotesPlaceholder')}
          />
        </Card>
      )}

      {/* Items */}
      <Card
        title={`${t('inventory.orderItems')} (${order.items.length})`}
        extra={
          isReceivingMode && (
            <Tag color="purple">{t('warehouseOps.receivingModeActive')}</Tag>
          )
        }
      >
        {/* Search input */}
        <div className="mb-3">
          <Input.Search
            placeholder={t('inventory.searchItemsPlaceholder')}
            allowClear
            value={itemSearchKeyword}
            onChange={e => setItemSearchKeyword(e.target.value)}
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
            showTotal: total =>
              t('common.showing', {
                from: Math.min((itemCurrentPage - 1) * itemPageSize + 1, total),
                to: Math.min(itemCurrentPage * itemPageSize, total),
                total,
              }),
          }}
          scroll={{ x: isReceivingMode ? 1200 : 1000 }}
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
            <Descriptions.Item label={t('inventory.boxCount')}>
              {order.shipment.boxCount}
            </Descriptions.Item>
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
          <Divider />
          <Descriptions column={1} size="small">
            <Descriptions.Item label={t('inventory.senderAddress')}>
              {order.shipment.senderAddress}
            </Descriptions.Item>
          </Descriptions>
        </Card>
      )}

      {/* Exceptions */}
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
            scroll={{ x: 800 }}
            size="small"
          />
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
            ...(order.shippedAt
              ? [
                  {
                    color: 'cyan',
                    children: (
                      <div>
                        <Text strong>{t('inventory.orderShipped')}</Text>
                        <br />
                        <Text type="secondary">
                          {new Date(order.shippedAt).toLocaleString()}
                        </Text>
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
                        <Text type="secondary">
                          {new Date(order.completedAt).toLocaleString()}
                        </Text>
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
