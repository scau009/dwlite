import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal, Form, Input, App, Alert } from 'antd';
import { ExclamationCircleOutlined } from '@ant-design/icons';

import { merchantApi } from '@/lib/merchant-api';

interface CreateApiKeyModalProps {
  open: boolean;
  onClose: () => void;
  onSuccess: (secret: string) => void;
}

export function CreateApiKeyModal({ open, onClose, onSuccess }: CreateApiKeyModalProps) {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);

  const handleSubmit = async () => {
    try {
      const values = await form.validateFields();
      setLoading(true);

      const result = await merchantApi.createApiKey({
        name: values.name,
        ipWhitelist: values.ipWhitelist
          ? values.ipWhitelist.split('\n').map((ip: string) => ip.trim()).filter(Boolean)
          : null,
      });

      message.success(t('apiKeys.created'));
      onSuccess(result.secret);
    } catch (error) {
      const err = error as { error?: string };
      if (err.error) {
        message.error(err.error);
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <Modal
      title={t('apiKeys.createTitle')}
      open={open}
      onCancel={onClose}
      onOk={handleSubmit}
      confirmLoading={loading}
      okText={t('common.create')}
      cancelText={t('common.cancel')}
      width={600}
      destroyOnClose
    >
      <Alert
        type="info"
        icon={<ExclamationCircleOutlined />}
        message={t('apiKeys.createWarning')}
        className="mb-4"
        showIcon
      />

      <Form form={form} layout="vertical">
        <Form.Item
          name="name"
          label={t('apiKeys.nameLabel')}
          rules={[
            { required: true, message: t('apiKeys.nameRequired') },
            { max: 100, message: t('apiKeys.nameMaxLength') },
          ]}
        >
          <Input placeholder={t('apiKeys.namePlaceholder')} />
        </Form.Item>

        <Form.Item
          name="ipWhitelist"
          label={t('apiKeys.ipWhitelistLabel')}
          extra={t('apiKeys.ipWhitelistHelp')}
        >
          <Input.TextArea
            rows={3}
            placeholder={t('apiKeys.ipWhitelistPlaceholder')}
          />
        </Form.Item>
      </Form>
    </Modal>
  );
}
