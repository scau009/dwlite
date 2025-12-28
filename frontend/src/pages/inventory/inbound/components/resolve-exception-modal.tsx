import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal, Form, Select, Input, App, Descriptions, Tag, Table } from 'antd';
import type { ColumnsType } from 'antd/es/table';

import { inboundApi, type InboundException, type ExceptionItem } from '@/lib/inbound-api';

interface ResolveExceptionModalProps {
  open: boolean;
  exception: InboundException | null;
  onClose: () => void;
  onSuccess: () => void;
}

export function ResolveExceptionModal({
  open,
  exception,
  onClose,
  onSuccess,
}: ResolveExceptionModalProps) {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);
  const [resolutionOptions, setResolutionOptions] = useState<{ value: string; label: string }[]>([]);

  // Load resolution options from API
  useEffect(() => {
    const loadOptions = async () => {
      try {
        const options = await inboundApi.getResolutionOptions();
        setResolutionOptions(options);
      } catch {
        // Silently fail
      }
    };
    loadOptions();
  }, []);

  // Reset form when exception changes
  useEffect(() => {
    if (open && exception) {
      form.resetFields();
    }
  }, [open, exception, form]);

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

  const handleSubmit = async () => {
    if (!exception) return;

    try {
      const values = await form.validateFields();
      setLoading(true);

      await inboundApi.resolveInboundException(exception.id, {
        resolution: values.resolution,
        resolutionNotes: values.resolutionNotes,
      });

      message.success(t('inventory.exceptionResolved'));
      onSuccess();
    } catch (error) {
      const err = error as { error?: string };
      if (err.error) {
        message.error(err.error);
      }
    } finally {
      setLoading(false);
    }
  };

  // Exception items columns
  const itemColumns: ColumnsType<ExceptionItem> = [
    {
      title: t('inventory.productName'),
      dataIndex: 'productName',
      render: (name: string | null) => name || '-',
    },
    {
      title: t('inventory.colorName'),
      dataIndex: 'colorName',
      width: 100,
      render: (color: string | null) => color || '-',
    },
    {
      title: t('inventory.skuName'),
      dataIndex: 'skuName',
      width: 100,
      render: (sku: string | null) => sku || '-',
    },
    {
      title: t('inventory.quantity'),
      dataIndex: 'quantity',
      width: 100,
      align: 'center',
      render: (qty: number) => (
        <span className="text-red-500 font-medium">{qty}</span>
      ),
    },
  ];

  if (!exception) return null;

  return (
    <Modal
      title={t('inventory.resolveException')}
      open={open}
      onCancel={onClose}
      onOk={handleSubmit}
      confirmLoading={loading}
      width={640}
      destroyOnHidden
    >
      {/* Exception Summary */}
      <Descriptions column={2} size="small" className="mb-4">
        <Descriptions.Item label={t('inventory.exceptionNo')}>
          {exception.exceptionNo}
        </Descriptions.Item>
        <Descriptions.Item label={t('inventory.exceptionType')}>
          <Tag color="orange">{getExceptionTypeLabel(exception.type)}</Tag>
        </Descriptions.Item>
        <Descriptions.Item label={t('inventory.differenceQuantity')}>
          <span className="text-red-500 font-medium">{exception.totalQuantity}</span>
        </Descriptions.Item>
        <Descriptions.Item label={t('common.createdAt')}>
          {new Date(exception.createdAt).toLocaleString()}
        </Descriptions.Item>
        <Descriptions.Item label={t('inventory.exceptionDescription')} span={2}>
          {exception.description}
        </Descriptions.Item>
      </Descriptions>

      {/* Exception Items */}
      {exception.items && exception.items.length > 0 && (
        <div className="mb-4 mt-4">
          <div className="text-sm font-medium mb-2">{t('inventory.exceptionItems')}</div>
          <Table
            columns={itemColumns}
            dataSource={exception.items}
            rowKey="id"
            pagination={false}
            size="small"
          />
        </div>
      )}

      {/* Resolution Form */}
      <Form
        form={form}
        layout="vertical"
        initialValues={{
          resolution: undefined,
          resolutionNotes: '',
        }}
      >
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

        <Form.Item
          name="resolutionNotes"
          label={t('inventory.resolutionNotes')}
        >
          <Input.TextArea
            rows={3}
            placeholder={t('inventory.enterResolutionNotes')}
          />
        </Form.Item>
      </Form>
    </Modal>
  );
}
