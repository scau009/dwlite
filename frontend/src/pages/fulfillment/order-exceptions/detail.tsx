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
  Modal,
  Form,
  Select,
  Input,
} from 'antd';
import {
  ArrowLeftOutlined,
  ExclamationCircleOutlined,
  CheckCircleOutlined,
  CloseCircleOutlined,
} from '@ant-design/icons';

import {
  orderExceptionApi,
  type OrderException,
  type OrderExceptionType,
  type OrderExceptionStatus,
  type ResolutionOption,
} from '@/lib/order-exception-api';

const { Text } = Typography;

// Status color mapping
const statusColors: Record<OrderExceptionStatus, string> = {
  pending: 'warning',
  resolved: 'success',
  closed: 'default',
};

// Exception type color mapping
const typeColors: Record<OrderExceptionType, string> = {
  inventory_insufficient: 'red',
  price_below_platform: 'orange',
  product_not_matched: 'purple',
  other: 'default',
};

export function OrderExceptionDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { t } = useTranslation();
  const { message } = App.useApp();

  const [exception, setException] = useState<OrderException | null>(null);
  const [loading, setLoading] = useState(true);
  const [resolveModalOpen, setResolveModalOpen] = useState(false);
  const [resolving, setResolving] = useState(false);
  const [closing, setClosing] = useState(false);
  const [resolutionOptions, setResolutionOptions] = useState<ResolutionOption[]>([]);
  const [form] = Form.useForm();

  const loadException = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    try {
      const { data } = await orderExceptionApi.getDetail(id);
      setException(data);
    } catch {
      message.error(t('common.error'));
    } finally {
      setLoading(false);
    }
  }, [id, message, t]);

  const loadResolutionOptions = async () => {
    try {
      const { data } = await orderExceptionApi.getResolutionOptions();
      setResolutionOptions(data);
    } catch {
      // Silently fail, will show empty select
    }
  };

  useEffect(() => {
    loadException();
    loadResolutionOptions();
  }, [loadException]);

  // Get status label
  const getStatusLabel = (status: OrderExceptionStatus) => {
    const labels: Record<OrderExceptionStatus, string> = {
      pending: t('fulfillment.statusPending'),
      resolved: t('fulfillment.statusResolved'),
      closed: t('fulfillment.statusClosed'),
    };
    return labels[status] || status;
  };

  // Get exception type label
  const getTypeLabel = (type: OrderExceptionType) => {
    const labels: Record<OrderExceptionType, string> = {
      inventory_insufficient: t('fulfillment.typeInventoryInsufficient'),
      price_below_platform: t('fulfillment.typePriceBelowPlatform'),
      product_not_matched: t('fulfillment.typeProductNotMatched'),
      other: t('fulfillment.typeOther'),
    };
    return labels[type] || type;
  };

  // Handle resolve exception
  const handleResolve = async () => {
    try {
      const values = await form.validateFields();
      setResolving(true);

      await orderExceptionApi.resolve(id!, {
        resolution: values.resolution,
        notes: values.notes,
      });

      message.success(t('fulfillment.exceptionResolved'));
      setResolveModalOpen(false);
      form.resetFields();
      loadException();
    } catch (error) {
      if (error && typeof error === 'object' && 'errorFields' in error) {
        return;
      }
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setResolving(false);
    }
  };

  // Handle close exception
  const handleClose = async () => {
    try {
      setClosing(true);
      await orderExceptionApi.close(id!);
      message.success(t('fulfillment.exceptionClosed'));
      loadException();
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setClosing(false);
    }
  };

  // Render exception details
  const renderDetails = (details: Record<string, unknown>) => {
    return (
      <Descriptions column={{ xs: 1, sm: 2 }} size="small">
        {Object.entries(details).map(([key, value]) => (
          <Descriptions.Item key={key} label={key}>
            {typeof value === 'object' ? JSON.stringify(value) : String(value)}
          </Descriptions.Item>
        ))}
      </Descriptions>
    );
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Spin size="large" />
      </div>
    );
  }

  if (!exception) {
    return (
      <Card>
        <Empty description={t('common.noData')}>
          <Button type="primary" onClick={() => navigate('/fulfillment/order-exceptions')}>
            {t('common.back')}
          </Button>
        </Empty>
      </Card>
    );
  }

  const canResolve = exception.status === 'pending';
  const canClose = exception.status === 'pending' || exception.status === 'resolved';

  return (
    <div className="flex flex-col gap-4">
      {/* Header */}
      <div className="flex items-center justify-between flex-wrap gap-4">
        <div className="flex items-center gap-4">
          <Button
            icon={<ArrowLeftOutlined />}
            onClick={() => navigate('/fulfillment/order-exceptions')}
          >
            {t('common.back')}
          </Button>
          <Space>
            <ExclamationCircleOutlined className="text-xl text-orange-500" />
            <span className="text-lg font-semibold">{exception.exceptionNo}</span>
          </Space>
          <Tag color={statusColors[exception.status]}>
            {exception.statusLabel || getStatusLabel(exception.status)}
          </Tag>
        </div>
        <Space>
          {canResolve && (
            <Button
              type="primary"
              icon={<CheckCircleOutlined />}
              onClick={() => setResolveModalOpen(true)}
            >
              {t('fulfillment.resolveException')}
            </Button>
          )}
          {canClose && exception.status !== 'closed' && (
            <Button
              icon={<CloseCircleOutlined />}
              onClick={handleClose}
              loading={closing}
            >
              {t('fulfillment.closeException')}
            </Button>
          )}
        </Space>
      </div>

      {/* Basic Info */}
      <Card title={t('detail.basicInfo')}>
        <Descriptions column={{ xs: 1, sm: 2, md: 3 }} size="small">
          <Descriptions.Item label={t('fulfillment.exceptionNo')}>
            <Text code copyable>
              {exception.exceptionNo}
            </Text>
          </Descriptions.Item>
          <Descriptions.Item label={t('fulfillment.exceptionType')}>
            <Tag color={typeColors[exception.type]}>
              {exception.typeLabel || getTypeLabel(exception.type)}
            </Tag>
          </Descriptions.Item>
          <Descriptions.Item label={t('common.status')}>
            <Tag color={statusColors[exception.status]}>
              {exception.statusLabel || getStatusLabel(exception.status)}
            </Tag>
          </Descriptions.Item>
          <Descriptions.Item label={t('common.createdAt')}>
            {new Date(exception.createdAt).toLocaleString()}
          </Descriptions.Item>
          {exception.resolvedAt && (
            <Descriptions.Item label={t('fulfillment.resolvedAt')}>
              {new Date(exception.resolvedAt).toLocaleString()}
            </Descriptions.Item>
          )}
          {exception.resolvedBy && (
            <Descriptions.Item label={t('fulfillment.resolvedBy')}>
              {exception.resolvedBy}
            </Descriptions.Item>
          )}
        </Descriptions>

        {exception.description && (
          <div className="mt-3">
            <Text type="secondary">{t('fulfillment.exceptionDescription')}:</Text>
            <p className="mt-1">{exception.description}</p>
          </div>
        )}

        {exception.resolution && (
          <div className="mt-3">
            <Text type="secondary">{t('fulfillment.resolution')}:</Text>
            <p className="mt-1">
              <Tag color="blue">{exception.resolutionLabel || exception.resolution}</Tag>
            </p>
            {exception.resolutionNotes && (
              <p className="mt-1 text-gray-500">{exception.resolutionNotes}</p>
            )}
          </div>
        )}
      </Card>

      {/* Order Info */}
      <Card title={t('fulfillment.orderInfo')}>
        <Descriptions column={{ xs: 1, sm: 2, md: 3 }} size="small">
          <Descriptions.Item label={t('fulfillment.orderNo')}>
            <Text code copyable>
              {exception.order.externalOrderNo}
            </Text>
          </Descriptions.Item>
          <Descriptions.Item label={t('fulfillment.orderStatus')}>
            {exception.order.status}
          </Descriptions.Item>
          <Descriptions.Item label={t('fulfillment.orderAmount')}>
            {exception.order.totalAmount}
          </Descriptions.Item>
        </Descriptions>
      </Card>

      {/* Exception Details */}
      {exception.details && Object.keys(exception.details).length > 0 && (
        <Card title={t('fulfillment.exceptionDetails')}>
          {renderDetails(exception.details)}
        </Card>
      )}

      {/* Resolve Modal */}
      <Modal
        title={t('fulfillment.resolveException')}
        open={resolveModalOpen}
        onCancel={() => {
          setResolveModalOpen(false);
          form.resetFields();
        }}
        onOk={handleResolve}
        confirmLoading={resolving}
        destroyOnHidden
        width={500}
      >
        <Form form={form} layout="vertical" className="mt-4">
          <Form.Item
            name="resolution"
            label={t('fulfillment.resolution')}
            rules={[{ required: true, message: t('fulfillment.resolutionRequired') }]}
          >
            <Select
              placeholder={t('fulfillment.selectResolution')}
              options={resolutionOptions}
            />
          </Form.Item>

          <Form.Item name="notes" label={t('fulfillment.resolutionNotes')}>
            <Input.TextArea
              rows={3}
              placeholder={t('fulfillment.enterResolutionNotes')}
              maxLength={500}
              showCount
            />
          </Form.Item>
        </Form>
      </Modal>
    </div>
  );
}
