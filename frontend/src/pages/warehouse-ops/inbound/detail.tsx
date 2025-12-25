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
  Input,
  Modal,
  Collapse,
} from 'antd';
import {
  ArrowLeftOutlined,
  CheckCircleOutlined,
  ExclamationCircleOutlined,
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
  const { message } = App.useApp();

  const [order, setOrder] = useState<WarehouseInboundOrderDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState(false);

  // Single item receiving state
  const [showItemReceiveModal, setShowItemReceiveModal] = useState(false);
  const [itemReceivingData, setItemReceivingData] = useState<CompleteReceivingItem | null>(null);
  const [receivingItem, setReceivingItem] = useState<WarehouseInboundItem | null>(null);

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

  // Handle receive single item - open modal
  const handleReceiveItem = (item: WarehouseInboundItem) => {
    setReceivingItem(item);
    setItemReceivingData({
      itemId: item.id,
      receivedQuantity: item.expectedQuantity,
      damagedQuantity: 0,
      warehouseRemark: '',
    });
    setShowItemReceiveModal(true);
  };

  // Handle confirm single item receiving
  const handleConfirmReceiveItem = async () => {
    if (!itemReceivingData) return;

    setActionLoading(true);
    try {
      await warehouseOpsApi.completeReceiving(id!, {
        items: [itemReceivingData],
      });
      message.success(t('warehouseOps.itemReceived'));
      setShowItemReceiveModal(false);
      setReceivingItem(null);
      setItemReceivingData(null);
      loadOrder();
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setActionLoading(false);
    }
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
      title: t('common.status'),
      dataIndex: 'status',
      width: 80,
      align: 'center',
      render: (status: string) => (
        <Tag color={status === 'received' ? 'success' : 'default'}>
          {status === 'received' ? t('warehouseOps.itemStatusReceived') : t('warehouseOps.itemStatusPending')}
        </Tag>
      ),
    },
    {
      title: t('common.actions'),
      dataIndex: 'id',
      width: 100,
      fixed: 'right' as const,
      render: (_: string, record: WarehouseInboundItem) => (
        record.status !== 'received' && canReceive ? (
          <Button
            type="primary"
            size="small"
            icon={<CheckCircleOutlined />}
            onClick={() => handleReceiveItem(record)}
          >
            {t('warehouseOps.receiveItem')}
          </Button>
        ) : null
      ),
    },
  ];

  // Exception status helpers
  const getExceptionStatusLabel = (status: string) => {
    const labels: Record<string, string> = {
      pending: t('warehouseOps.exceptionStatusPending'),
      processing: t('warehouseOps.exceptionStatusProcessing'),
      resolved: t('warehouseOps.exceptionStatusResolved'),
      closed: t('warehouseOps.exceptionStatusClosed'),
    };
    return labels[status] || status;
  };

  const getExceptionStatusColor = (status: string) => {
    const colors: Record<string, string> = {
      pending: 'warning',
      processing: 'processing',
      resolved: 'success',
      closed: 'default',
    };
    return colors[status] || 'default';
  };

  const isPendingException = (status: string) => ['pending', 'processing'].includes(status);

  // Render exception card content
  const renderExceptionContent = (exception: WarehouseInboundException) => {
    const items = exception.items ?? [];
    const evidenceImages = exception.evidenceImages ?? [];

    return (
      <div className="space-y-3">
        {/* Description */}
        {exception.description && (
          <div className="text-gray-600 mt-4">{exception.description}</div>
        )}

        {/* Exception Items */}
        {items.length > 0 && (
          <div>
            <Text type="secondary" className="text-xs block mb-2">{t('warehouseOps.exceptionItems')}</Text>
            <div className="flex flex-wrap gap-2">
              {items.map(item => (
                <div key={item.id} className="flex gap-2 p-2 bg-gray-50 rounded border min-w-[180px]">
                  {item.productImage ? (
                    <img
                      src={item.productImage}
                      alt={item.productName || ''}
                      className="w-10 h-10 object-cover rounded"
                    />
                  ) : (
                    <div className="w-10 h-10 bg-gray-200 flex items-center justify-center text-gray-400 text-xs rounded">
                      N/A
                    </div>
                  )}
                  <div className="flex-1 min-w-0">
                    <div className="text-sm truncate">{item.productName || '-'}</div>
                    <div className="text-xs text-gray-500">
                      {item.skuName} / {item.colorName}
                    </div>
                    <div className="text-xs">
                      <span className="text-red-500 font-medium">× {item.quantity}</span>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Evidence Images */}
        {evidenceImages.length > 0 && (
          <div>
            <Text type="secondary" className="text-xs block mb-2">{t('warehouseOps.evidenceImages')}</Text>
            <div className="flex flex-wrap gap-2">
              {evidenceImages.map((img, idx) => (
                <a key={idx} href={img} target="_blank" rel="noopener noreferrer">
                  <img
                    src={img}
                    alt={`evidence-${idx}`}
                    className="w-16 h-16 object-cover rounded border hover:opacity-80"
                  />
                </a>
              ))}
            </div>
          </div>
        )}

        {/* Resolution Info */}
        {exception.status === 'resolved' && exception.resolution && (
          <div className="p-2 bg-green-50 rounded border border-green-200">
            <Text type="secondary" className="text-xs">{t('warehouseOps.resolution')}: </Text>
            <span className="text-sm">{exception.resolution}</span>
            {exception.resolutionNotes && (
              <span className="text-sm text-gray-600 ml-2">({exception.resolutionNotes})</span>
            )}
            {exception.resolvedAt && (
              <span className="text-xs text-gray-500 ml-2">
                {new Date(exception.resolvedAt).toLocaleString()}
              </span>
            )}
          </div>
        )}
      </div>
    );
  };

  // Build exception collapse items
  const exceptionCollapseItems = useMemo(() => {
    if (!order?.exceptions) return [];
    return order.exceptions.map(exception => ({
      key: exception.id,
      label: (
        <div className="flex items-center justify-between w-full">
          <div className="flex items-center gap-3">
            <Text code copyable={{ text: exception.exceptionNo }}>{exception.exceptionNo}</Text>
            <Tag color={getExceptionStatusColor(exception.status)}>
              {getExceptionStatusLabel(exception.status)}
            </Tag>
            <span className="text-gray-500 text-sm">{exception.typeLabel}</span>
            <span className="text-red-500 text-sm">× {exception.totalQuantity}</span>
          </div>
          <span className="text-gray-400 text-xs mr-2">
            {new Date(exception.createdAt).toLocaleString()}
          </span>
        </div>
      ),
      children: renderExceptionContent(exception),
      className: isPendingException(exception.status) ? 'exception-pending' : '',
    }));
  }, [order?.exceptions, t]);

  // Default active keys: pending/processing exceptions
  const defaultActiveExceptionKeys = useMemo(() => {
    if (!order?.exceptions) return [];
    return order.exceptions
      .filter(e => isPendingException(e.status))
      .map(e => e.id);
  }, [order?.exceptions]);

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
      </div>

      {/* Basic Info */}
      <Card title={t('detail.basicInfo')}>
        <Descriptions column={{ xs: 1, sm: 2, md: 3, xxl: 4 }} size="small">
          <Descriptions.Item label={t('inventory.orderNo')}>
            <Text code copyable>
              {order.orderNo}
            </Text>
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
          {order.shipment && (
            <>
              <Descriptions.Item label={t('inventory.carrierName')}>
                {order.shipment.carrierName || order.shipment.carrierCode || '-'}
              </Descriptions.Item>
              <Descriptions.Item label={t('inventory.trackingNumber')}>
                <Text code copyable>
                  {order.shipment.trackingNumber}
                </Text>
              </Descriptions.Item>
              <Descriptions.Item label={t('inventory.boxCount')}>
                {order.shipment.boxCount}
              </Descriptions.Item>
            </>
          )}
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
      </Card>

      {/* Exceptions - between basic info and items */}
      {showExceptions && (
        <div className="exception-section">
          <div className="flex items-center gap-2 mb-3">
            <ExclamationCircleOutlined className="text-orange-500" />
            <span className="font-medium">{t('inventory.exceptions')} ({order.exceptions.length})</span>
          </div>
          <style>{`
            .exception-section .ant-collapse-item.exception-pending > .ant-collapse-header {
              background-color: #fffbe6;
              border-left: 3px solid #faad14;
            }
            .exception-section .ant-collapse-item.exception-pending {
              border: 1px solid #ffe58f;
              border-radius: 6px;
              margin-bottom: 8px;
            }
            .exception-section .ant-collapse-item:not(.exception-pending) {
              margin-bottom: 8px;
            }
            .exception-section .ant-collapse {
              background: transparent;
            }
            .exception-section .ant-collapse > .ant-collapse-item {
              background: #fff;
              border-radius: 6px;
            }
          `}</style>
          <Collapse
            items={exceptionCollapseItems}
            defaultActiveKey={defaultActiveExceptionKeys}
            expandIconPosition="start"
            bordered={false}
          />
        </div>
      )}

      {/* Items */}
      <Card title={`${t('inventory.orderItems')} (${order.items.length})`}>
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
          scroll={{ x: 1100 }}
          size="small"
        />
      </Card>

      {/* Warehouse Notes */}
      <Card
        title={t('inventory.warehouseNotes')}
        extra={
          !editingNotes ? (
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
          )
        }
      >
        {editingNotes ? (
          <TextArea
            value={notesValue}
            onChange={e => setNotesValue(e.target.value)}
            rows={3}
            placeholder={t('warehouseOps.notesPlaceholder')}
          />
        ) : (
          <p className="m-0">{order.warehouseNotes || '-'}</p>
        )}
      </Card>

      {/* Single Item Receiving Modal */}
      <Modal
        title={t('warehouseOps.receiveItem')}
        open={showItemReceiveModal}
        onOk={handleConfirmReceiveItem}
        onCancel={() => {
          setShowItemReceiveModal(false);
          setReceivingItem(null);
          setItemReceivingData(null);
        }}
        okText={t('common.confirm')}
        cancelText={t('common.cancel')}
        confirmLoading={actionLoading}
      >
        {receivingItem && itemReceivingData && (
          <div className="space-y-4">
            {/* Product Info */}
            <div className="flex gap-3 p-3 bg-gray-50 rounded">
              {receivingItem.productImage ? (
                <Image
                  src={receivingItem.productImage}
                  width={60}
                  height={60}
                  style={{ objectFit: 'cover' }}
                  preview={false}
                />
              ) : (
                <div className="w-[60px] h-[60px] bg-gray-200 flex items-center justify-center text-gray-400 rounded">
                  N/A
                </div>
              )}
              <div className="flex-1 min-w-0">
                <div className="font-medium truncate">
                  {receivingItem.productName || '-'}
                </div>
                <div className="text-gray-500 text-sm">
                  {receivingItem.styleNumber || '-'} / {receivingItem.productSku?.skuName || '-'}
                </div>
                <div className="text-gray-500 text-sm">
                  {t('inventory.expectedQuantity')}: <span className="font-medium text-gray-700">{receivingItem.expectedQuantity}</span>
                </div>
              </div>
            </div>

            {/* Received Quantity */}
            <div>
              <label className="block text-sm text-gray-600 mb-1">
                {t('inventory.itemReceivedQuantity')} <span className="text-red-500">*</span>
              </label>
              <Input
                type="number"
                min={0}
                max={receivingItem.expectedQuantity}
                value={itemReceivingData.receivedQuantity}
                onChange={e => {
                  const val = parseInt(e.target.value, 10) || 0;
                  setItemReceivingData({
                    ...itemReceivingData,
                    receivedQuantity: Math.min(val, receivingItem.expectedQuantity),
                    damagedQuantity: Math.min(itemReceivingData.damagedQuantity ?? 0, val),
                  });
                }}
              />
            </div>

            {/* Damaged Quantity */}
            <div>
              <label className="block text-sm text-gray-600 mb-1">
                {t('inventory.damagedQuantity')}
              </label>
              <Input
                type="number"
                min={0}
                max={itemReceivingData.receivedQuantity}
                value={itemReceivingData.damagedQuantity ?? 0}
                onChange={e => {
                  const val = parseInt(e.target.value, 10) || 0;
                  setItemReceivingData({
                    ...itemReceivingData,
                    damagedQuantity: Math.min(val, itemReceivingData.receivedQuantity),
                  });
                }}
              />
              <div className="text-xs text-gray-400 mt-1">
                {t('warehouseOps.damagedQuantityHint')}
              </div>
            </div>

            {/* Warehouse Remark */}
            <div>
              <label className="block text-sm text-gray-600 mb-1">
                {t('warehouseOps.receivingNotes')}
              </label>
              <TextArea
                value={itemReceivingData.warehouseRemark || ''}
                onChange={e =>
                  setItemReceivingData({
                    ...itemReceivingData,
                    warehouseRemark: e.target.value,
                  })
                }
                rows={2}
                placeholder={t('warehouseOps.receivingNotesPlaceholder')}
              />
            </div>
          </div>
        )}
      </Modal>
    </div>
  );
}
