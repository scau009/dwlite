import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal, Form, Input, Checkbox, App, Alert, Select, DatePicker } from 'antd';
import { ExclamationCircleOutlined } from '@ant-design/icons';

import { adminApiKeyApi } from '@/lib/admin-api-key-api';
import { merchantApi } from '@/lib/merchant-api';

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

interface CreateApiKeyModalProps {
  open: boolean;
  onClose: () => void;
  onSuccess: (secret: string) => void;
}

interface MerchantOption {
  label: string;
  value: string;
}

export function CreateApiKeyModal({ open, onClose, onSuccess }: CreateApiKeyModalProps) {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);
  const [merchantOptions, setMerchantOptions] = useState<MerchantOption[]>([]);
  const [merchantSearchLoading, setMerchantSearchLoading] = useState(false);

  useEffect(() => {
    if (open) {
      form.resetFields();
      // Default select all permissions
      form.setFieldsValue({
        permissions: ALL_PERMISSIONS.map((p) => p.value),
      });
      setMerchantOptions([]);
    }
  }, [open, form]);

  const handleMerchantSearch = async (value: string) => {
    if (!value || value.length < 2) {
      setMerchantOptions([]);
      return;
    }

    setMerchantSearchLoading(true);
    try {
      const result = await merchantApi.getMerchants({
        name: value,
        limit: 20,
        status: 'approved',
      });
      setMerchantOptions(
        result.data.map((m) => ({
          label: m.name,
          value: m.id,
        }))
      );
    } catch {
      setMerchantOptions([]);
    } finally {
      setMerchantSearchLoading(false);
    }
  };

  const handleSubmit = async () => {
    try {
      const values = await form.validateFields();
      setLoading(true);

      const result = await adminApiKeyApi.createApiKey({
        name: values.name,
        type: 'merchant',
        merchantId: values.merchantId,
        permissions: values.permissions,
        ipWhitelist: values.ipWhitelist
          ? values.ipWhitelist.split('\n').map((ip: string) => ip.trim()).filter(Boolean)
          : null,
        expiresAt: values.expiresAt ? values.expiresAt.toISOString() : null,
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
      title={t('adminApiKeys.createTitle')}
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
          name="merchantId"
          label={t('adminApiKeys.selectMerchant')}
          rules={[{ required: true, message: t('adminApiKeys.merchantRequired') }]}
        >
          <Select
            showSearch
            placeholder={t('adminApiKeys.searchMerchantPlaceholder')}
            filterOption={false}
            onSearch={handleMerchantSearch}
            loading={merchantSearchLoading}
            options={merchantOptions}
            notFoundContent={merchantSearchLoading ? t('common.loading') : null}
          />
        </Form.Item>

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
          name="permissions"
          label={t('apiKeys.permissionsLabel')}
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

        <Form.Item
          name="expiresAt"
          label={t('adminApiKeys.expiresAt')}
          extra={t('adminApiKeys.expiresAtHelp')}
        >
          <DatePicker
            showTime
            className="w-full"
            placeholder={t('adminApiKeys.noExpiration')}
          />
        </Form.Item>
      </Form>
    </Modal>
  );
}
