import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal, Form, Checkbox, App, Alert, Descriptions, Space, Tag } from 'antd';

import { channelApi, type MerchantChannel, type FulfillmentType } from '@/lib/channel-api';

interface Props {
  open: boolean;
  merchantChannel: MerchantChannel | null;
  onClose: () => void;
  onSuccess: () => void;
}

const fulfillmentOptions: { value: FulfillmentType; labelKey: string; descKey: string }[] = [
  {
    value: 'consignment',
    labelKey: 'merchantChannels.fulfillmentConsignment',
    descKey: 'merchantChannels.fulfillmentConsignmentDesc',
  },
  {
    value: 'self_fulfillment',
    labelKey: 'merchantChannels.fulfillmentSelfFulfillment',
    descKey: 'merchantChannels.fulfillmentSelfFulfillmentDesc',
  },
];

export function ApproveModal({ open, merchantChannel, onClose, onSuccess }: Props) {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (open && merchantChannel) {
      // 默认勾选商户申请的所有模式
      form.setFieldsValue({
        approvedFulfillmentTypes: merchantChannel.requestedFulfillmentTypes || [],
      });
    }
  }, [open, merchantChannel, form]);

  const handleSubmit = async () => {
    if (!merchantChannel) return;

    try {
      const values = await form.validateFields();
      setLoading(true);

      await channelApi.approveChannel(merchantChannel.id, values.approvedFulfillmentTypes);
      message.success(t('merchantChannels.approved'));
      form.resetFields();
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

  const handleCancel = () => {
    form.resetFields();
    onClose();
  };

  if (!merchantChannel) return null;

  // 只显示商户申请过的模式
  const availableOptions = fulfillmentOptions.filter((opt) =>
    merchantChannel.requestedFulfillmentTypes?.includes(opt.value)
  );

  return (
    <Modal
      title={t('merchantChannels.approveApplication')}
      open={open}
      onOk={handleSubmit}
      onCancel={handleCancel}
      confirmLoading={loading}
      okText={t('merchantChannels.approve')}
      cancelText={t('common.cancel')}
      width={560}
    >
      <div className="mb-4">
        <Descriptions column={1} bordered size="small">
          <Descriptions.Item label={t('merchantChannels.merchant')}>
            {merchantChannel.merchant.name}
          </Descriptions.Item>
          <Descriptions.Item label={t('merchantChannels.channel')}>
            <Space>
              {merchantChannel.salesChannel.logoUrl && (
                <img
                  src={merchantChannel.salesChannel.logoUrl}
                  alt={merchantChannel.salesChannel.name}
                  className="w-5 h-5 rounded"
                />
              )}
              <span>{merchantChannel.salesChannel.name}</span>
            </Space>
          </Descriptions.Item>
          <Descriptions.Item label={t('merchantChannels.requestedFulfillmentTypes')}>
            <Space>
              {merchantChannel.requestedFulfillmentTypes?.map((type) => (
                <Tag key={type} color="blue">
                  {type === 'consignment'
                    ? t('merchantChannels.fulfillmentConsignment')
                    : t('merchantChannels.fulfillmentSelfFulfillment')}
                </Tag>
              ))}
            </Space>
          </Descriptions.Item>
        </Descriptions>
      </div>

      <Alert
        message={t('merchantChannels.approveHint')}
        type="info"
        showIcon
        className="mb-4"
      />

      <Form form={form} layout="vertical">
        <Form.Item
          name="approvedFulfillmentTypes"
          label={t('merchantChannels.selectApprovedFulfillmentTypes')}
          rules={[
            {
              required: true,
              message: t('merchantChannels.atLeastOneFulfillmentType'),
            },
            {
              type: 'array',
              min: 1,
              message: t('merchantChannels.atLeastOneFulfillmentType'),
            },
          ]}
        >
          <Checkbox.Group className="w-full">
            <div className="flex flex-col gap-3">
              {availableOptions.map((option) => (
                <Checkbox key={option.value} value={option.value}>
                  <div>
                    <div className="font-medium">{t(option.labelKey)}</div>
                    <div className="text-xs text-gray-500">{t(option.descKey)}</div>
                  </div>
                </Checkbox>
              ))}
            </div>
          </Checkbox.Group>
        </Form.Item>
      </Form>
    </Modal>
  );
}
