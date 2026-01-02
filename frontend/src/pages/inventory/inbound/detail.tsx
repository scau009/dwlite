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
} from 'antd';
import {
  ArrowLeftOutlined,
  EditOutlined,
  DeleteOutlined,
  PlusOutlined,
  SendOutlined,
  CloseCircleOutlined,
  TruckOutlined,
  ExclamationCircleOutlined,
} from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';

import {
  inboundApi,
  type InboundOrderDetail,
  type InboundOrderStatus,
  type InboundOrderItem,
  type InboundException,
} from '@/lib/inbound-api';
import { InboundOrderFormModal } from './components/inbound-order-form-modal';
import { CancelOrderModal } from './components/cancel-order-modal';
import { InboundOrderItemModal } from './components/inbound-order-item-modal';
import { ProductSelectorModal } from './components/product-selector-modal';
import { ShipOrderModal } from './components/ship-order-modal';
import { BatchUpdateQuantityModal } from './components/batch-update-quantity-modal';
import { ResolveExceptionModal } from './components/resolve-exception-modal';

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

export function InboundOrderDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { t } = useTranslation();
  const { modal, message } = App.useApp();

  const [order, setOrder] = useState<InboundOrderDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState(false);

  // Modal states
  const [editModalOpen, setEditModalOpen] = useState(false);
  const [cancelModalOpen, setCancelModalOpen] = useState(false);
  const [itemModalOpen, setItemModalOpen] = useState(false);
  const [editingItem, setEditingItem] = useState<InboundOrderItem | null>(null);
  const [productSelectorOpen, setProductSelectorOpen] = useState(false);
  const [shipModalOpen, setShipModalOpen] = useState(false);
  const [batchQuantityModalOpen, setBatchQuantityModalOpen] = useState(false);
  const [resolveExceptionModalOpen, setResolveExceptionModalOpen] = useState(false);
  const [selectedExceptionForResolve, setSelectedExceptionForResolve] = useState<InboundException | null>(null);

  // Batch selection state
  const [selectedRowKeys, setSelectedRowKeys] = useState<React.Key[]>([]);

  // Search and pagination state for items
  const [itemSearchKeyword, setItemSearchKeyword] = useState('');
  const [itemCurrentPage, setItemCurrentPage] = useState(1);
  const itemPageSize = 10;

  const loadOrder = async () => {
    if (!id) return;
    setLoading(true);
    try {
      const data = await inboundApi.getInboundOrder(id);
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

  // Handle submit order
  const handleSubmit = () => {
    modal.confirm({
      title: t('inventory.confirmSubmit'),
      content: t('inventory.confirmSubmitDesc'),
      okText: t('common.confirm'),
      cancelText: t('common.cancel'),
      onOk: async () => {
        setActionLoading(true);
        try {
          await inboundApi.submitInboundOrder(id!);
          message.success(t('inventory.orderSubmitted'));
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

  // Handle delete order
  const handleDelete = () => {
    modal.confirm({
      title: t('inventory.confirmDelete'),
      content: t('inventory.confirmDeleteDesc'),
      okText: t('common.confirm'),
      cancelText: t('common.cancel'),
      okButtonProps: { danger: true },
      onOk: async () => {
        setActionLoading(true);
        try {
          await inboundApi.deleteInboundOrder(id!);
          message.success(t('inventory.orderDeleted'));
          navigate('/inventory/inbound');
        } catch (error) {
          const err = error as { error?: string };
          message.error(err.error || t('common.error'));
        } finally {
          setActionLoading(false);
        }
      },
    });
  };

  // Handle delete item
  const handleDeleteItem = (item: InboundOrderItem) => {
    modal.confirm({
      title: t('inventory.confirmDeleteItem'),
      content: t('inventory.confirmDeleteItemDesc'),
      okText: t('common.confirm'),
      cancelText: t('common.cancel'),
      okButtonProps: { danger: true },
      onOk: async () => {
        try {
          await inboundApi.deleteInboundOrderItem(item.id);
          message.success(t('inventory.itemDeleted'));
          // Remove deleted item from selection
          setSelectedRowKeys(prev => prev.filter(key => key !== item.id));
          loadOrder();
        } catch (error) {
          const err = error as { error?: string };
          message.error(err.error || t('common.error'));
        }
      },
    });
  };

  // Handle batch delete items
  const handleBatchDelete = () => {
    if (selectedRowKeys.length === 0) return;

    modal.confirm({
      title: t('inventory.confirmBatchDelete'),
      content: t('inventory.confirmBatchDeleteDesc', { count: selectedRowKeys.length }),
      okText: t('common.confirm'),
      cancelText: t('common.cancel'),
      okButtonProps: { danger: true },
      onOk: async () => {
        try {
          await Promise.all(
            selectedRowKeys.map(id => inboundApi.deleteInboundOrderItem(id as string))
          );
          message.success(t('inventory.batchDeleteSuccess', { count: selectedRowKeys.length }));
          setSelectedRowKeys([]);
          loadOrder();
        } catch (error) {
          const err = error as { error?: string };
          message.error(err.error || t('common.error'));
        }
      },
    });
  };

  // Get selected items for batch operations
  const selectedItems = order?.items.filter(item => selectedRowKeys.includes(item.id)) || [];

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
  const itemColumns: ColumnsType<InboundOrderItem> = [
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
      render: (qty: number) => (
        <span className={qty > 0 ? 'text-red-500' : ''}>{qty}</span>
      ),
    },
  ];

  // Add action column for draft orders
  if (order?.status === 'draft') {
    itemColumns.push({
      title: t('common.actions'),
      key: 'actions',
      width: 120,
      fixed: 'right',
      render: (_, record) => (
        <Space size="small">
          <Button
            type="text"
            size="small"
            icon={<EditOutlined />}
            onClick={() => {
              setEditingItem(record);
              setItemModalOpen(true);
            }}
          />
          <Button
            type="text"
            size="small"
            danger
            icon={<DeleteOutlined />}
            onClick={() => handleDeleteItem(record)}
          />
        </Space>
      ),
    });
  }

  // Helper to get exception type label
  const getExceptionTypeLabel = (type: string) => {
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
  };

  // Helper to get exception status label
  const getExceptionStatusLabel = (status: string) => {
    const labels: Record<string, string> = {
      pending: t('inventory.exceptionStatusPending'),
      processing: t('inventory.exceptionStatusProcessing'),
      resolved: t('inventory.exceptionStatusResolved'),
      closed: t('inventory.exceptionStatusClosed'),
    };
    return labels[status] || status;
  };

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
      title: t('common.actions'),
      key: 'actions',
      width: 100,
      fixed: 'right',
      render: (_, record) => (
        record.status === 'pending' || record.status === 'processing' ? (
          <Button
            type="link"
            size="small"
            onClick={() => {
              setSelectedExceptionForResolve(record);
              setResolveExceptionModalOpen(true);
            }}
          >
            {t('inventory.resolveException')}
          </Button>
        ) : (
          <Text type="secondary">{record.resolution ? t(`inventory.resolution${record.resolution.charAt(0).toUpperCase() + record.resolution.slice(1)}`) : '-'}</Text>
        )
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
          <Button type="primary" onClick={() => navigate('/inventory/inbound')}>
            {t('common.back')}
          </Button>
        </Empty>
      </Card>
    );
  }

  const isDraft = order.status === 'draft';
  const isPending = order.status === 'pending';
  const canCancel = ['draft', 'pending', 'shipped'].includes(order.status);
  const showShipment = order.shipment !== null;
  const showExceptions = order.exceptions.length > 0;

  return (
    <div className="flex flex-col gap-4">
      {/* Header */}
      <div className="flex items-center justify-between flex-wrap gap-4">
        <div className="flex items-center gap-4">
          <Button icon={<ArrowLeftOutlined />} onClick={() => navigate('/inventory/inbound')}>
            {t('common.back')}
          </Button>
          <span className="text-lg font-semibold">{order.orderNo}</span>
          <Tag color={statusColors[order.status]}>{getStatusLabel(order.status)}</Tag>
        </div>
        <Space wrap>
          {isDraft && (
            <>
              <Button icon={<EditOutlined />} onClick={() => setEditModalOpen(true)}>
                {t('common.edit')}
              </Button>
              <Button
                type="primary"
                icon={<SendOutlined />}
                onClick={handleSubmit}
                loading={actionLoading}
              >
                {t('inventory.submitOrder')}
              </Button>
              <Button danger icon={<DeleteOutlined />} onClick={handleDelete}>
                {t('common.delete')}
              </Button>
            </>
          )}
          {isPending && (
            <Button
              type="primary"
              icon={<TruckOutlined />}
              onClick={() => setShipModalOpen(true)}
            >
              {t('inventory.shipOrder')}
            </Button>
          )}
          {canCancel && !isDraft && (
            <Button danger icon={<CloseCircleOutlined />} onClick={() => setCancelModalOpen(true)}>
              {t('inventory.cancelOrder')}
            </Button>
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
          <Descriptions.Item label={t('inventory.warehouse')}>
            {order.warehouse.name}
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
      <Card
        title={`${t('inventory.orderItems')} (${order.items.length})`}
        extra={
          isDraft && (
            <Button
              type="primary"
              icon={<PlusOutlined />}
              onClick={() => setProductSelectorOpen(true)}
            >
              {t('inventory.addItem')}
            </Button>
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
        {/* Batch operation toolbar */}
        {isDraft && selectedRowKeys.length > 0 && (
          <div className="mb-3 p-3 bg-blue-50 rounded flex items-center justify-between">
            <span className="text-blue-600">
              {t('common.selected', { count: selectedRowKeys.length })}
            </span>
            <Space>
              <Button onClick={() => setBatchQuantityModalOpen(true)}>
                {t('inventory.batchUpdateQuantity')}
              </Button>
              <Button
                danger
                icon={<DeleteOutlined />}
                onClick={handleBatchDelete}
              >
                {t('inventory.batchDelete')}
              </Button>
            </Space>
          </div>
        )}
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
            showTotal: total => t('common.showing', {
              from: Math.min((itemCurrentPage - 1) * itemPageSize + 1, total),
              to: Math.min(itemCurrentPage * itemPageSize, total),
              total,
            }),
          }}
          scroll={{ x: 1000 }}
          size="small"
          rowSelection={
            isDraft
              ? {
                  selectedRowKeys,
                  onChange: setSelectedRowKeys,
                }
              : undefined
          }
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
          <Divider />
          <Descriptions column={1} size="small">
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
                        <Text type="secondary">
                          {new Date(order.submittedAt).toLocaleString()}
                        </Text>
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
                        <br />
                        {order.cancelReason && (
                          <Text type="secondary">{order.cancelReason}</Text>
                        )}
                      </div>
                    ),
                  },
                ]
              : []),
          ]}
        />
      </Card>

      {/* Modals */}
      <InboundOrderFormModal
        open={editModalOpen}
        order={order}
        onClose={() => setEditModalOpen(false)}
        onSuccess={() => {
          setEditModalOpen(false);
          loadOrder();
        }}
      />

      <CancelOrderModal
        open={cancelModalOpen}
        order={order}
        onClose={() => setCancelModalOpen(false)}
        onSuccess={() => {
          setCancelModalOpen(false);
          loadOrder();
        }}
      />

      <InboundOrderItemModal
        open={itemModalOpen}
        orderId={id!}
        item={editingItem}
        onClose={() => {
          setItemModalOpen(false);
          setEditingItem(null);
        }}
        onSuccess={() => {
          setItemModalOpen(false);
          setEditingItem(null);
          loadOrder();
        }}
      />

      <ProductSelectorModal
        open={productSelectorOpen}
        orderId={id!}
        onClose={() => setProductSelectorOpen(false)}
        onSuccess={() => {
          setProductSelectorOpen(false);
          loadOrder();
        }}
      />

      <ShipOrderModal
        open={shipModalOpen}
        orderId={id!}
        onClose={() => setShipModalOpen(false)}
        onSuccess={() => {
          setShipModalOpen(false);
          loadOrder();
        }}
      />

      <BatchUpdateQuantityModal
        open={batchQuantityModalOpen}
        items={selectedItems}
        onClose={() => setBatchQuantityModalOpen(false)}
        onSuccess={() => {
          setBatchQuantityModalOpen(false);
          setSelectedRowKeys([]);
          loadOrder();
        }}
      />

      <ResolveExceptionModal
        open={resolveExceptionModalOpen}
        exception={selectedExceptionForResolve}
        onClose={() => {
          setResolveExceptionModalOpen(false);
          setSelectedExceptionForResolve(null);
        }}
        onSuccess={() => {
          setResolveExceptionModalOpen(false);
          setSelectedExceptionForResolve(null);
          loadOrder();
        }}
      />
    </div>
  );
}
