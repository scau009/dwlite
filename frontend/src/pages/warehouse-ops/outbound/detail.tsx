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
  Table,
  Typography,
  Timeline,
  Modal,
  Form,
  Input,
  Steps,
} from 'antd';
import {
  ArrowLeftOutlined,
  CarryOutOutlined,
  InboxOutlined,
  SendOutlined,
  CheckCircleOutlined,
} from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';

import {
  warehouseOpsApi,
  type WarehouseOutboundOrderDetail,
  type WarehouseOutboundStatus,
  type WarehouseOutboundItem,
} from '@/lib/warehouse-operations-api';

const { Text } = Typography;

// Status color mapping
const statusColors: Record<WarehouseOutboundStatus, string> = {
  pending: 'processing',
  picking: 'cyan',
  packing: 'blue',
  ready: 'purple',
  shipped: 'success',
  delivered: 'green',
  cancelled: 'error',
};

// Status step mapping
const statusSteps: Record<WarehouseOutboundStatus, number> = {
  pending: 0,
  picking: 1,
  packing: 2,
  ready: 3,
  shipped: 4,
  delivered: 5,
  cancelled: -1,
};

export function WarehouseOutboundDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { t } = useTranslation();
  const { modal, message } = App.useApp();
  const [form] = Form.useForm();

  const [order, setOrder] = useState<WarehouseOutboundOrderDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState(false);
  const [shipModalOpen, setShipModalOpen] = useState(false);

  const loadOrder = async () => {
    if (!id) return;
    setLoading(true);
    try {
      const response = await warehouseOpsApi.getOutboundOrder(id);
      setOrder(response.data);
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
  const getStatusLabel = (status: WarehouseOutboundStatus) => {
    const labels: Record<WarehouseOutboundStatus, string> = {
      pending: t('warehouseOps.outboundStatusPending'),
      picking: t('warehouseOps.outboundStatusPicking'),
      packing: t('warehouseOps.outboundStatusPacking'),
      ready: t('warehouseOps.outboundStatusReady'),
      shipped: t('warehouseOps.outboundStatusShipped'),
      delivered: t('warehouseOps.outboundStatusDelivered'),
      cancelled: t('warehouseOps.outboundStatusCancelled'),
    };
    return labels[status] || status;
  };

  // Handle start picking
  const handleStartPicking = () => {
    modal.confirm({
      title: t('warehouseOps.confirmStartPicking'),
      content: t('warehouseOps.confirmStartPickingDesc'),
      okText: t('common.confirm'),
      cancelText: t('common.cancel'),
      onOk: async () => {
        setActionLoading(true);
        try {
          await warehouseOpsApi.startPicking(id!);
          message.success(t('warehouseOps.pickingStarted'));
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

  // Handle start packing
  const handleStartPacking = () => {
    modal.confirm({
      title: t('warehouseOps.confirmStartPacking'),
      content: t('warehouseOps.confirmStartPackingDesc'),
      okText: t('common.confirm'),
      cancelText: t('common.cancel'),
      onOk: async () => {
        setActionLoading(true);
        try {
          await warehouseOpsApi.startPacking(id!);
          message.success(t('warehouseOps.packingStarted'));
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

  // Handle complete packing
  const handleCompletePacking = () => {
    modal.confirm({
      title: t('warehouseOps.confirmCompletePacking'),
      content: t('warehouseOps.confirmCompletePackingDesc'),
      okText: t('common.confirm'),
      cancelText: t('common.cancel'),
      onOk: async () => {
        setActionLoading(true);
        try {
          await warehouseOpsApi.completePacking(id!);
          message.success(t('warehouseOps.packingCompleted'));
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

  // Handle ship order
  const handleShipOrder = async () => {
    try {
      const values = await form.validateFields();
      setActionLoading(true);
      await warehouseOpsApi.shipOrder(id!, {
        carrier: values.carrier,
        trackingNumber: values.trackingNumber,
      });
      message.success(t('warehouseOps.orderShipped'));
      setShipModalOpen(false);
      form.resetFields();
      loadOrder();
    } catch (error) {
      if (error && typeof error === 'object' && 'error' in error) {
        message.error((error as { error?: string }).error || t('common.error'));
      }
    } finally {
      setActionLoading(false);
    }
  };

  // Item table columns
  const itemColumns: ColumnsType<WarehouseOutboundItem> = [
    {
      title: t('warehouseOps.productName'),
      dataIndex: 'productName',
      width: 200,
      render: (name: string | null) => name || '-',
    },
    {
      title: t('warehouseOps.skuName'),
      dataIndex: 'skuName',
      width: 100,
      render: (skuName: string | null) => skuName || '-',
    },
    {
      title: t('warehouseOps.quantity'),
      dataIndex: 'quantity',
      width: 80,
      align: 'center',
    },
    {
      title: t('warehouseOps.pickedQuantity'),
      dataIndex: 'pickedQuantity',
      width: 100,
      align: 'center',
      render: (picked: number, record) => (
        <span className={picked < record.quantity ? 'text-orange-500' : 'text-green-500'}>
          {picked}
        </span>
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
          <Button type="primary" onClick={() => navigate('/warehouse/outbound')}>
            {t('common.back')}
          </Button>
        </Empty>
      </Card>
    );
  }

  const isPending = order.status === 'pending';
  const isPicking = order.status === 'picking';
  const isPacking = order.status === 'packing';
  const isReady = order.status === 'ready';
  const isCancelled = order.status === 'cancelled';
  const currentStep = statusSteps[order.status];

  return (
    <div className="flex flex-col gap-4">
      {/* Header */}
      <div className="flex items-center justify-between flex-wrap gap-4">
        <div className="flex items-center gap-4">
          <Button icon={<ArrowLeftOutlined />} onClick={() => navigate('/warehouse/outbound')}>
            {t('common.back')}
          </Button>
          <h1 className="text-xl font-semibold m-0">{order.outboundNo}</h1>
          <Tag color={statusColors[order.status]}>{getStatusLabel(order.status)}</Tag>
        </div>
        <Space wrap>
          {isPending && (
            <Button
              type="primary"
              icon={<CarryOutOutlined />}
              onClick={handleStartPicking}
              loading={actionLoading}
            >
              {t('warehouseOps.startPicking')}
            </Button>
          )}
          {isPicking && (
            <Button
              type="primary"
              icon={<InboxOutlined />}
              onClick={handleStartPacking}
              loading={actionLoading}
            >
              {t('warehouseOps.startPacking')}
            </Button>
          )}
          {isPacking && (
            <Button
              type="primary"
              icon={<CheckCircleOutlined />}
              onClick={handleCompletePacking}
              loading={actionLoading}
            >
              {t('warehouseOps.completePacking')}
            </Button>
          )}
          {isReady && (
            <Button
              type="primary"
              icon={<SendOutlined />}
              onClick={() => setShipModalOpen(true)}
              loading={actionLoading}
            >
              {t('warehouseOps.shipOrder')}
            </Button>
          )}
        </Space>
      </div>

      {/* Progress Steps */}
      {!isCancelled && (
        <Card size="small">
          <Steps
            current={currentStep}
            size="small"
            items={[
              { title: t('warehouseOps.outboundStatusPending'), icon: <InboxOutlined /> },
              { title: t('warehouseOps.outboundStatusPicking'), icon: <CarryOutOutlined /> },
              { title: t('warehouseOps.outboundStatusPacking'), icon: <InboxOutlined /> },
              { title: t('warehouseOps.outboundStatusReady'), icon: <CheckCircleOutlined /> },
              { title: t('warehouseOps.outboundStatusShipped'), icon: <SendOutlined /> },
            ]}
          />
        </Card>
      )}

      {/* Basic Info */}
      <Card title={t('detail.basicInfo')}>
        <Descriptions column={{ xs: 1, sm: 2, md: 3 }} size="small">
          <Descriptions.Item label={t('warehouseOps.outboundNo')}>
            <Text code copyable>
              {order.outboundNo}
            </Text>
          </Descriptions.Item>
          <Descriptions.Item label={t('warehouseOps.outboundType')}>
            {order.outboundType}
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
            {new Date(order.createdAt).toLocaleString()}
          </Descriptions.Item>
        </Descriptions>

        {/* Shipping Info */}
        {(order.shippingCarrier || order.trackingNumber) && (
          <>
            <div className="mt-3 pt-3 border-t">
              <Text strong className="block mb-2">
                {t('warehouseOps.shippingInfo')}
              </Text>
              <Descriptions column={{ xs: 1, sm: 2 }} size="small">
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
                  <Descriptions.Item label={t('warehouseOps.shippedAt')}>
                    {new Date(order.shippedAt).toLocaleString()}
                  </Descriptions.Item>
                )}
              </Descriptions>
            </div>
          </>
        )}

        {order.remark && (
          <div className="mt-3 pt-3 border-t">
            <Text type="secondary">{t('warehouseOps.remark')}:</Text>
            <p className="mt-1">{order.remark}</p>
          </div>
        )}

        {order.cancelReason && (
          <div className="mt-3 pt-3 border-t">
            <Text type="danger">{t('warehouseOps.cancelReason')}:</Text>
            <p className="mt-1 text-red-500">{order.cancelReason}</p>
          </div>
        )}
      </Card>

      {/* Receiver Info */}
      <Card title={t('warehouseOps.receiverInfo')}>
        <Descriptions column={{ xs: 1, sm: 2, md: 3 }} size="small">
          <Descriptions.Item label={t('warehouseOps.receiver')}>
            {order.receiverName}
          </Descriptions.Item>
          <Descriptions.Item label={t('warehouseOps.receiverPhone')}>
            {order.receiverPhone}
          </Descriptions.Item>
          <Descriptions.Item label={t('warehouseOps.receiverAddress')} span={3}>
            {order.receiverAddress}
          </Descriptions.Item>
        </Descriptions>
      </Card>

      {/* Items */}
      <Card title={`${t('warehouseOps.orderItems')} (${order.items.length})`}>
        <Table
          columns={itemColumns}
          dataSource={order.items}
          rowKey="id"
          pagination={false}
          scroll={{ x: 500 }}
          size="small"
        />
      </Card>

      {/* Order Timeline */}
      <Card title={t('warehouseOps.orderTimeline')}>
        <Timeline
          items={[
            {
              color: 'green',
              children: (
                <div>
                  <Text strong>{t('warehouseOps.orderCreated')}</Text>
                  <br />
                  <Text type="secondary">{new Date(order.createdAt).toLocaleString()}</Text>
                </div>
              ),
            },
            ...(order.pickingStartedAt
              ? [
                  {
                    color: 'cyan',
                    children: (
                      <div>
                        <Text strong>{t('warehouseOps.timelinePickingStarted')}</Text>
                        <br />
                        <Text type="secondary">
                          {new Date(order.pickingStartedAt).toLocaleString()}
                        </Text>
                      </div>
                    ),
                  },
                ]
              : []),
            ...(order.pickingCompletedAt
              ? [
                  {
                    color: 'cyan',
                    children: (
                      <div>
                        <Text strong>{t('warehouseOps.timelinePickingCompleted')}</Text>
                        <br />
                        <Text type="secondary">
                          {new Date(order.pickingCompletedAt).toLocaleString()}
                        </Text>
                      </div>
                    ),
                  },
                ]
              : []),
            ...(order.packingStartedAt
              ? [
                  {
                    color: 'blue',
                    children: (
                      <div>
                        <Text strong>{t('warehouseOps.timelinePackingStarted')}</Text>
                        <br />
                        <Text type="secondary">
                          {new Date(order.packingStartedAt).toLocaleString()}
                        </Text>
                      </div>
                    ),
                  },
                ]
              : []),
            ...(order.packingCompletedAt
              ? [
                  {
                    color: 'purple',
                    children: (
                      <div>
                        <Text strong>{t('warehouseOps.timelinePackingCompleted')}</Text>
                        <br />
                        <Text type="secondary">
                          {new Date(order.packingCompletedAt).toLocaleString()}
                        </Text>
                      </div>
                    ),
                  },
                ]
              : []),
            ...(order.shippedAt
              ? [
                  {
                    color: 'green',
                    children: (
                      <div>
                        <Text strong>{t('warehouseOps.orderShipped')}</Text>
                        <br />
                        <Text type="secondary">{new Date(order.shippedAt).toLocaleString()}</Text>
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
                        <Text strong>{t('warehouseOps.orderCancelled')}</Text>
                        {order.cancelReason && (
                          <>
                            <br />
                            <Text type="secondary">{order.cancelReason}</Text>
                          </>
                        )}
                      </div>
                    ),
                  },
                ]
              : []),
          ]}
        />
      </Card>

      {/* Ship Order Modal */}
      <Modal
        title={t('warehouseOps.shipOrder')}
        open={shipModalOpen}
        onCancel={() => {
          setShipModalOpen(false);
          form.resetFields();
        }}
        onOk={handleShipOrder}
        confirmLoading={actionLoading}
        okText={t('warehouseOps.confirmShip')}
        cancelText={t('common.cancel')}
      >
        <Form form={form} layout="vertical">
          <Form.Item
            name="carrier"
            label={t('warehouseOps.carrier')}
            rules={[{ required: true, message: t('warehouseOps.carrierRequired') }]}
          >
            <Input placeholder={t('warehouseOps.carrierPlaceholder')} />
          </Form.Item>
          <Form.Item
            name="trackingNumber"
            label={t('warehouseOps.trackingNumber')}
            rules={[{ required: true, message: t('warehouseOps.trackingNumberRequired') }]}
          >
            <Input placeholder={t('warehouseOps.trackingNumberPlaceholder')} />
          </Form.Item>
        </Form>
      </Modal>
    </div>
  );
}
