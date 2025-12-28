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
  Image,
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
} from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';

import {
  inboundApi,
  type InboundException,
  type InboundExceptionType,
  type InboundExceptionStatus,
  type ExceptionItem,
} from '@/lib/inbound-api';

const { Text } = Typography;

// Status color mapping
const statusColors: Record<InboundExceptionStatus, string> = {
  pending: 'warning',
  processing: 'processing',
  resolved: 'success',
  closed: 'default',
};

// Exception type color mapping
const typeColors: Record<InboundExceptionType, string> = {
  quantity_short: 'orange',
  quantity_over: 'blue',
  damaged: 'red',
  wrong_item: 'purple',
  quality_issue: 'magenta',
  packaging: 'cyan',
  expired: 'volcano',
  other: 'default',
};

export function InboundExceptionDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { t } = useTranslation();
  const { message } = App.useApp();

  const [exception, setException] = useState<InboundException | null>(null);
  const [loading, setLoading] = useState(true);
  const [resolveModalOpen, setResolveModalOpen] = useState(false);
  const [resolving, setResolving] = useState(false);
  const [resolutionOptions, setResolutionOptions] = useState<{ value: string; label: string }[]>([]);
  const [form] = Form.useForm();

  const loadException = async () => {
    if (!id) return;
    setLoading(true);
    try {
      const data = await inboundApi.getInboundException(id);
      setException(data);
    } catch {
      message.error(t('common.error'));
    } finally {
      setLoading(false);
    }
  };

  const loadResolutionOptions = async () => {
    try {
      const options = await inboundApi.getResolutionOptions();
      setResolutionOptions(options);
    } catch {
      // Silently fail, will show empty select
    }
  };

  useEffect(() => {
    loadException();
    loadResolutionOptions();
  }, [id]);

  // Get status label
  const getStatusLabel = (status: InboundExceptionStatus) => {
    const labels: Record<InboundExceptionStatus, string> = {
      pending: t('inventory.exceptionStatusPending'),
      processing: t('inventory.exceptionStatusProcessing'),
      resolved: t('inventory.exceptionStatusResolved'),
      closed: t('inventory.exceptionStatusClosed'),
    };
    return labels[status] || status;
  };

  // Get exception type label
  const getTypeLabel = (type: InboundExceptionType) => {
    const labels: Record<InboundExceptionType, string> = {
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

  // Handle resolve exception
  const handleResolve = async () => {
    try {
      const values = await form.validateFields();
      setResolving(true);

      await inboundApi.resolveInboundException(id!, {
        resolution: values.resolution,
        resolutionNotes: values.resolutionNotes,
      });

      message.success(t('inventory.exceptionResolved'));
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

  // Exception items columns
  const itemColumns: ColumnsType<ExceptionItem> = [
    {
      title: t('inventory.productName'),
      dataIndex: 'productName',
      width: 200,
      ellipsis: true,
      render: (name: string | null) => name || '-',
    },
    {
      title: t('inventory.colorName'),
      dataIndex: 'colorName',
      width: 120,
      render: (color: string | null) => color || '-',
    },
    {
      title: t('inventory.skuName'),
      dataIndex: 'skuName',
      width: 120,
      render: (sku: string | null) => sku ? <Text code>{sku}</Text> : '-',
    },
    {
      title: t('inventory.quantity'),
      dataIndex: 'quantity',
      width: 100,
      align: 'center',
    },
  ];

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
          <Button type="primary" onClick={() => navigate('/inventory/exceptions')}>
            {t('common.back')}
          </Button>
        </Empty>
      </Card>
    );
  }

  const canResolve = exception.status === 'pending';

  return (
    <div className="flex flex-col gap-4">
      {/* Header */}
      <div className="flex items-center justify-between flex-wrap gap-4">
        <div className="flex items-center gap-4">
          <Button icon={<ArrowLeftOutlined />} onClick={() => navigate('/inventory/exceptions')}>
            {t('common.back')}
          </Button>
          <Space>
            <ExclamationCircleOutlined className="text-xl text-orange-500" />
            <h1 className="text-xl font-semibold m-0">{exception.exceptionNo}</h1>
          </Space>
          <Tag color={statusColors[exception.status]}>{getStatusLabel(exception.status)}</Tag>
        </div>
        {canResolve && (
          <Button
            type="primary"
            icon={<CheckCircleOutlined />}
            onClick={() => setResolveModalOpen(true)}
          >
            {t('inventory.resolveException')}
          </Button>
        )}
      </div>

      {/* Basic Info */}
      <Card title={t('detail.basicInfo')}>
        <Descriptions column={{ xs: 1, sm: 2, md: 3 }} size="small">
          <Descriptions.Item label={t('inventory.exceptionNo')}>
            <Text code copyable>
              {exception.exceptionNo}
            </Text>
          </Descriptions.Item>
          <Descriptions.Item label={t('inventory.exceptionType')}>
            <Tag color={typeColors[exception.type]}>
              {exception.typeLabel || getTypeLabel(exception.type)}
            </Tag>
          </Descriptions.Item>
          <Descriptions.Item label={t('common.status')}>
            <Tag color={statusColors[exception.status]}>{getStatusLabel(exception.status)}</Tag>
          </Descriptions.Item>
          <Descriptions.Item label={t('inventory.totalQuantity')}>
            {exception.totalQuantity}
          </Descriptions.Item>
          <Descriptions.Item label={t('common.createdAt')}>
            {new Date(exception.createdAt).toLocaleString()}
          </Descriptions.Item>
          {exception.resolvedAt && (
            <Descriptions.Item label={t('inventory.resolvedAt')}>
              {new Date(exception.resolvedAt).toLocaleString()}
            </Descriptions.Item>
          )}
        </Descriptions>

        {exception.description && (
          <div className="mt-3 pt-3 border-t">
            <Text type="secondary">{t('inventory.exceptionDescription')}:</Text>
            <p className="mt-1">{exception.description}</p>
          </div>
        )}

        {exception.resolution && (
          <div className="mt-3 pt-3 border-t">
            <Text type="secondary">{t('inventory.resolution')}:</Text>
            <p className="mt-1">{exception.resolution}</p>
            {exception.resolutionNotes && (
              <p className="mt-1 text-gray-500">{exception.resolutionNotes}</p>
            )}
          </div>
        )}
      </Card>

      {/* Exception Items */}
      <Card title={`${t('inventory.exceptionItems')} (${exception.items.length})`}>
        <Table
          columns={itemColumns}
          dataSource={exception.items}
          rowKey="id"
          pagination={false}
          size="small"
        />
      </Card>

      {/* Evidence Images */}
      {exception.evidenceImages && exception.evidenceImages.length > 0 && (
        <Card title={t('inventory.evidenceImages')}>
          <Image.PreviewGroup>
            <Space wrap>
              {exception.evidenceImages.map((url, index) => (
                <Image
                  key={index}
                  src={url}
                  width={120}
                  height={120}
                  style={{ objectFit: 'cover' }}
                />
              ))}
            </Space>
          </Image.PreviewGroup>
        </Card>
      )}

      {/* Resolve Modal */}
      <Modal
        title={t('inventory.resolveException')}
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
            label={t('inventory.resolution')}
            rules={[{ required: true, message: t('inventory.resolutionRequired') }]}
          >
            <Select
              placeholder={t('inventory.selectResolution')}
              options={resolutionOptions}
            />
          </Form.Item>

          <Form.Item name="resolutionNotes" label={t('inventory.resolutionNotes')}>
            <Input.TextArea
              rows={3}
              placeholder={t('inventory.enterResolutionNotes')}
              maxLength={500}
              showCount
            />
          </Form.Item>
        </Form>
      </Modal>
    </div>
  );
}
