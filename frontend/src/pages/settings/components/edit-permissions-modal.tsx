import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal, Form, Checkbox, App } from 'antd';

import { merchantApi, type MerchantApiKey } from '@/lib/merchant-api';

// All available merchant permissions
const ALL_PERMISSIONS = [
  { value: 'merchant_inventory:read', labelKey: 'apiKeys.perm.inventoryRead' },
  { value: 'merchant_inventory:write', labelKey: 'apiKeys.perm.inventoryWrite' },
  { value: 'merchant_inbound:read', labelKey: 'apiKeys.perm.inboundRead' },
  { value: 'merchant_inbound:write', labelKey: 'apiKeys.perm.inboundWrite' },
  { value: 'fulfillment:read', labelKey: 'apiKeys.perm.fulfillmentRead' },
  { value: 'fulfillment:write', labelKey: 'apiKeys.perm.fulfillmentWrite' },
  { value: 'settlement:read', labelKey: 'apiKeys.perm.settlementRead' },
  { value: 'listing:read', labelKey: 'apiKeys.perm.listingRead' },
  { value: 'listing:write', labelKey: 'apiKeys.perm.listingWrite' },
  { value: 'webhook:manage', labelKey: 'apiKeys.perm.webhookManage' },
];

interface EditPermissionsModalProps {
  open: boolean;
  apiKey: MerchantApiKey;
  onClose: () => void;
  onSuccess: () => void;
}

export function EditPermissionsModal({
  open,
  apiKey,
  onClose,
  onSuccess,
}: EditPermissionsModalProps) {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (open && apiKey) {
      form.setFieldsValue({
        permissions: apiKey.permissions,
      });
    }
  }, [open, apiKey, form]);

  const handleSubmit = async () => {
    try {
      const values = await form.validateFields();
      setLoading(true);

      await merchantApi.updateApiKeyPermissions(apiKey.id, values.permissions);
      message.success(t('apiKeys.permissionsUpdated'));
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
      title={t('apiKeys.editPermissionsTitle')}
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
          name="permissions"
          rules={[{ required: true, message: t('apiKeys.permissionsRequired') }]}
        >
          <Checkbox.Group className="flex flex-col gap-2">
            {ALL_PERMISSIONS.map((perm) => (
              <Checkbox key={perm.value} value={perm.value}>
                {t(perm.labelKey)}
              </Checkbox>
            ))}
          </Checkbox.Group>
        </Form.Item>
      </Form>
    </Modal>
  );
}
