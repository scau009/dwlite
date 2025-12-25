import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal, Form, Input, InputNumber, Select, App } from 'antd';

import { channelApi, type SalesChannel, type SalesChannelDetail } from '@/lib/channel-api';

interface ChannelFormModalProps {
  open: boolean;
  channel: SalesChannel | null;
  onClose: () => void;
  onSuccess: () => void;
}

interface FormValues {
  code: string;
  name: string;
  logoUrl?: string;
  description?: string;
  businessType: 'import' | 'export';
  status: 'active' | 'maintenance' | 'disabled';
  sortOrder: number;
}

export function ChannelFormModal({ open, channel, onClose, onSuccess }: ChannelFormModalProps) {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [form] = Form.useForm<FormValues>();
  const [loading, setLoading] = useState(false);
  const [detailLoading, setDetailLoading] = useState(false);

  const isEdit = !!channel;

  useEffect(() => {
    if (open) {
      if (channel) {
        setDetailLoading(true);
        channelApi.getChannel(channel.id)
          .then((detail: SalesChannelDetail) => {
            form.setFieldsValue({
              code: detail.code,
              name: detail.name,
              logoUrl: detail.logoUrl || undefined,
              description: detail.description || undefined,
              businessType: detail.businessType,
              status: detail.status,
              sortOrder: detail.sortOrder,
            });
          })
          .catch((err) => {
            message.error(err.error || t('common.error'));
          })
          .finally(() => {
            setDetailLoading(false);
          });
      } else {
        form.resetFields();
        form.setFieldsValue({
          businessType: 'import',
          status: 'active',
          sortOrder: 0,
        });
      }
    }
  }, [open, channel, form, message, t]);

  const handleSubmit = async () => {
    try {
      const values = await form.validateFields();
      setLoading(true);

      if (isEdit) {
        const { code, ...updateData } = values;
        void code; // code cannot be updated
        await channelApi.updateChannel(channel!.id, updateData);
        message.success(t('channels.updated'));
      } else {
        await channelApi.createChannel(values);
        message.success(t('channels.created'));
      }

      onSuccess();
    } catch (error) {
      if (error && typeof error === 'object' && 'errorFields' in error) {
        return;
      }
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setLoading(false);
    }
  };

  return (
    <Modal
      title={isEdit ? t('channels.edit') : t('channels.add')}
      open={open}
      onCancel={onClose}
      onOk={handleSubmit}
      confirmLoading={loading}
      okText={t('common.save')}
      cancelText={t('common.cancel')}
      destroyOnClose
      width={500}
    >
      <Form
        form={form}
        layout="vertical"
        className="mt-4"
        disabled={detailLoading}
      >
        <Form.Item
          name="code"
          label={t('channels.code')}
          rules={[
            { required: true, message: t('channels.codeRequired') },
            { pattern: /^[A-Z0-9_]+$/, message: t('channels.codeInvalid') },
            { max: 50, message: t('channels.codeMaxLength') },
          ]}
          tooltip={t('channels.codeTooltip')}
        >
          <Input
            placeholder={t('channels.codePlaceholder')}
            disabled={isEdit}
          />
        </Form.Item>

        <Form.Item
          name="name"
          label={t('channels.name')}
          rules={[
            { required: true, message: t('channels.nameRequired') },
            { max: 100, message: t('channels.nameMaxLength') },
          ]}
        >
          <Input placeholder={t('channels.namePlaceholder')} />
        </Form.Item>

        <Form.Item
          name="businessType"
          label={t('channels.businessType')}
          rules={[{ required: true, message: t('channels.businessTypeRequired') }]}
        >
          <Select
            options={[
              { value: 'import', label: t('channels.businessTypeImport') },
              { value: 'export', label: t('channels.businessTypeExport') },
            ]}
          />
        </Form.Item>

        <Form.Item
          name="logoUrl"
          label={t('channels.logoUrl')}
          rules={[
            { type: 'url', message: t('channels.logoUrlInvalid') },
          ]}
        >
          <Input placeholder={t('channels.logoUrlPlaceholder')} />
        </Form.Item>

        <Form.Item
          name="description"
          label={t('channels.descriptionLabel')}
          rules={[
            { max: 500, message: t('channels.descriptionMaxLength') },
          ]}
        >
          <Input.TextArea
            rows={3}
            placeholder={t('channels.descriptionPlaceholder')}
          />
        </Form.Item>

        <Form.Item
          name="status"
          label={t('channels.status')}
        >
          <Select
            options={[
              { value: 'active', label: t('channels.statusActive') },
              { value: 'maintenance', label: t('channels.statusMaintenance') },
              { value: 'disabled', label: t('channels.statusDisabled') },
            ]}
          />
        </Form.Item>

        <Form.Item
          name="sortOrder"
          label={t('channels.sortOrder')}
          tooltip={t('channels.sortOrderTooltip')}
        >
          <InputNumber min={0} max={9999} style={{ width: '100%' }} />
        </Form.Item>
      </Form>
    </Modal>
  );
}
