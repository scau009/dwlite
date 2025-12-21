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
  Timeline,
  Typography,
} from 'antd';
import {
  ArrowLeftOutlined,
  TruckOutlined,
  CheckCircleOutlined,
  ClockCircleOutlined,
  ExclamationCircleOutlined,
  EnvironmentOutlined,
} from '@ant-design/icons';

import { inboundApi, type InboundShipment, type InboundShipmentStatus } from '@/lib/inbound-api';

const { Text } = Typography;

// Status color mapping
const statusColors: Record<InboundShipmentStatus, string> = {
  pending: 'default',
  picked: 'processing',
  in_transit: 'cyan',
  delivered: 'success',
  exception: 'error',
};

export function InboundShipmentDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { t } = useTranslation();
  const { message } = App.useApp();

  const [shipment, setShipment] = useState<InboundShipment | null>(null);
  const [loading, setLoading] = useState(true);

  const loadShipment = async () => {
    if (!id) return;
    setLoading(true);
    try {
      const data = await inboundApi.getInboundShipment(id);
      setShipment(data);
    } catch {
      message.error(t('common.error'));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadShipment();
  }, [id]);

  // Get status label
  const getStatusLabel = (status: InboundShipmentStatus) => {
    const labels: Record<InboundShipmentStatus, string> = {
      pending: t('inventory.shipmentStatusPending'),
      picked: t('inventory.shipmentStatusPicked'),
      in_transit: t('inventory.shipmentStatusInTransit'),
      delivered: t('inventory.shipmentStatusDelivered'),
      exception: t('inventory.shipmentStatusException'),
    };
    return labels[status] || status;
  };

  // Get timeline items based on status
  const getTimelineItems = () => {
    if (!shipment) return [];

    const items = [];

    // Shipped
    items.push({
      color: 'green',
      dot: <TruckOutlined />,
      children: (
        <div>
          <Text strong>{t('inventory.timelineShipped')}</Text>
          <br />
          <Text type="secondary">{new Date(shipment.shippedAt).toLocaleString()}</Text>
          <br />
          <Text type="secondary">
            {t('inventory.carrierName')}: {shipment.carrierName || shipment.carrierCode}
          </Text>
        </div>
      ),
    });

    // In Transit (if applicable)
    if (['in_transit', 'delivered', 'exception'].includes(shipment.status)) {
      items.push({
        color: 'blue',
        dot: <EnvironmentOutlined />,
        children: (
          <div>
            <Text strong>{t('inventory.timelineInTransit')}</Text>
            <br />
            <Text type="secondary">{t('inventory.trackingNumber')}: {shipment.trackingNumber}</Text>
          </div>
        ),
      });
    }

    // Delivered
    if (shipment.status === 'delivered' && shipment.deliveredAt) {
      items.push({
        color: 'green',
        dot: <CheckCircleOutlined />,
        children: (
          <div>
            <Text strong>{t('inventory.timelineDelivered')}</Text>
            <br />
            <Text type="secondary">{new Date(shipment.deliveredAt).toLocaleString()}</Text>
          </div>
        ),
      });
    }

    // Exception
    if (shipment.status === 'exception') {
      items.push({
        color: 'red',
        dot: <ExclamationCircleOutlined />,
        children: (
          <div>
            <Text strong type="danger">{t('inventory.timelineException')}</Text>
            <br />
            <Text type="secondary">{t('inventory.shipmentExceptionHint')}</Text>
          </div>
        ),
      });
    }

    // Pending estimated arrival
    if (['pending', 'picked', 'in_transit'].includes(shipment.status) && shipment.estimatedArrivalDate) {
      items.push({
        color: 'gray',
        dot: <ClockCircleOutlined />,
        children: (
          <div>
            <Text strong>{t('inventory.timelineEstimatedArrival')}</Text>
            <br />
            <Text type="secondary">
              {new Date(shipment.estimatedArrivalDate).toLocaleDateString()}
            </Text>
          </div>
        ),
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

  if (!shipment) {
    return (
      <Card>
        <Empty description={t('common.noData')}>
          <Button type="primary" onClick={() => navigate('/inventory/shipments')}>
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
          <Button icon={<ArrowLeftOutlined />} onClick={() => navigate('/inventory/shipments')}>
            {t('common.back')}
          </Button>
          <Space>
            <TruckOutlined className="text-xl" />
            <h1 className="text-xl font-semibold m-0 font-mono">{shipment.trackingNumber}</h1>
          </Space>
          <Tag color={statusColors[shipment.status]}>{getStatusLabel(shipment.status)}</Tag>
        </div>
      </div>

      {/* Basic Info */}
      <Card title={t('detail.basicInfo')}>
        <Descriptions column={{ xs: 1, sm: 2, md: 3 }} size="small">
          <Descriptions.Item label={t('inventory.trackingNumber')}>
            <Text code copyable>
              {shipment.trackingNumber}
            </Text>
          </Descriptions.Item>
          <Descriptions.Item label={t('inventory.carrierName')}>
            {shipment.carrierName || shipment.carrierCode}
          </Descriptions.Item>
          <Descriptions.Item label={t('common.status')}>
            <Tag color={statusColors[shipment.status]}>{getStatusLabel(shipment.status)}</Tag>
          </Descriptions.Item>
          <Descriptions.Item label={t('inventory.boxCount')}>{shipment.boxCount}</Descriptions.Item>
          <Descriptions.Item label={t('inventory.totalWeight')}>
            {shipment.totalWeight ? `${shipment.totalWeight} kg` : '-'}
          </Descriptions.Item>
          <Descriptions.Item label={t('inventory.shippedAt')}>
            {new Date(shipment.shippedAt).toLocaleString()}
          </Descriptions.Item>
          {shipment.estimatedArrivalDate && (
            <Descriptions.Item label={t('inventory.estimatedArrivalDate')}>
              {new Date(shipment.estimatedArrivalDate).toLocaleDateString()}
            </Descriptions.Item>
          )}
          {shipment.deliveredAt && (
            <Descriptions.Item label={t('inventory.deliveredAt')}>
              {new Date(shipment.deliveredAt).toLocaleString()}
            </Descriptions.Item>
          )}
        </Descriptions>
      </Card>

      {/* Sender Info */}
      <Card title={t('inventory.senderInfo')}>
        <Descriptions column={{ xs: 1, sm: 2 }} size="small">
          <Descriptions.Item label={t('inventory.senderName')}>
            {shipment.senderName}
          </Descriptions.Item>
          <Descriptions.Item label={t('inventory.senderPhone')}>
            {shipment.senderPhone}
          </Descriptions.Item>
          <Descriptions.Item label={t('inventory.senderAddress')} span={2}>
            {shipment.senderAddress}
          </Descriptions.Item>
        </Descriptions>
      </Card>

      {/* Tracking Timeline */}
      <Card title={t('inventory.trackingHistory')}>
        <Timeline items={getTimelineItems()} />
      </Card>
    </div>
  );
}
