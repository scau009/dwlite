import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal, Form, Input, App } from 'antd';

import { adminApiKeyApi, type AdminApiKey } from '@/lib/admin-api-key-api';

interface EditIpWhitelistModalProps {
  open: boolean;
  apiKey: AdminApiKey;
  onClose: () => void;
  onSuccess: () => void;
}

export function EditIpWhitelistModal({
  open,
  apiKey,
  onClose,
  onSuccess,
}: EditIpWhitelistModalProps) {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (open && apiKey) {
      // Need to fetch the detail to get ipWhitelist
      adminApiKeyApi.getApiKey(apiKey.id).then((detail) => {
        form.setFieldsValue({
          ipWhitelist: detail.ipWhitelist?.join('\n') || '',
        });
      });
    }
  }, [open, apiKey, form]);

  const handleSubmit = async () => {
    try {
      const values = await form.validateFields();
      setLoading(true);

      const ipWhitelist = values.ipWhitelist
        ? values.ipWhitelist.split('\n').map((ip: string) => ip.trim()).filter(Boolean)
        : null;

      await adminApiKeyApi.updateApiKeyIpWhitelist(apiKey.id, ipWhitelist);
      message.success(t('apiKeys.ipWhitelistUpdated'));
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

  return (
    <Modal
      title={t('apiKeys.editIpWhitelistTitle')}
      open={open}
      onCancel={onClose}
      onOk={handleSubmit}
      confirmLoading={loading}
      okText={t('common.save')}
      cancelText={t('common.cancel')}
      destroyOnClose
    >
      <Form form={form} layout="vertical">
        <Form.Item
          name="ipWhitelist"
          label={t('apiKeys.ipWhitelistLabel')}
          extra={t('apiKeys.ipWhitelistHelp')}
        >
          <Input.TextArea
            rows={5}
            placeholder={t('apiKeys.ipWhitelistPlaceholder')}
          />
        </Form.Item>
      </Form>
    </Modal>
  );
}
