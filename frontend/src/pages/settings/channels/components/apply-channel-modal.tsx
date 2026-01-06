import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal, Form, Input, Avatar, Space, Descriptions, App, Checkbox, Alert } from 'antd';
import { ShopOutlined } from '@ant-design/icons';

import {
  merchantChannelApi,
  type AvailableSalesChannel,
  type FulfillmentType
} from '@/lib/merchant-channel-api';

const { TextArea } = Input;

interface Props {
  open: boolean;
  channel: AvailableSalesChannel | null;
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

export function ApplyChannelModal({ open, channel, onClose, onSuccess }: Props) {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (open) {
      form.resetFields();
    }
  }, [open, form]);

  const handleSubmit = async () => {
    if (!channel) return;

    try {
      const values = await form.validateFields();
      setLoading(true);

      await merchantChannelApi.applyChannel({
        salesChannelId: channel.id,
        fulfillmentTypes: values.fulfillmentTypes,
        remark: values.remark,
      });
      message.success(t('myChannels.applicationSubmitted'));
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

  if (!channel) return null;

  return (
    <Modal
      title={t('myChannels.applyForChannel')}
      open={open}
      onOk={handleSubmit}
      onCancel={handleCancel}
      confirmLoading={loading}
      okText={t('myChannels.submitApplication')}
      cancelText={t('common.cancel')}
      width={560}
    >
      <div className="mb-6">
        <Descriptions column={1} bordered size="small">
          <Descriptions.Item label={t('channels.name')}>
            <Space>
              {channel.logoUrl ? (
                <Avatar src={channel.logoUrl} size={24} shape="square" />
              ) : (
                <Avatar icon={<ShopOutlined />} size={24} shape="square" />
              )}
              <span>{channel.name}</span>
            </Space>
          </Descriptions.Item>
          {channel.description && (
            <Descriptions.Item label={t('channels.description')}>
              {channel.description}
            </Descriptions.Item>
          )}
        </Descriptions>
      </div>

      <Alert
        message={t('myChannels.fulfillmentTypesHint')}
        type="info"
        showIcon
        className="mb-4"
      />

      <Form
        form={form}
        layout="vertical"
        initialValues={{
          fulfillmentTypes: ['consignment'],
        }}
      >
        <Form.Item
          name="fulfillmentTypes"
          label={t('merchantChannels.fulfillmentType')}
          rules={[
            {
              required: true,
              message: t('merchantChannels.fulfillmentTypeRequired'),
            },
            {
              type: 'array',
              min: 1,
              message: t('merchantChannels.fulfillmentTypeRequired'),
            },
          ]}
        >
          <Checkbox.Group className="w-full">
            <div className="flex flex-col gap-3">
              {fulfillmentOptions.map((option) => (
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

        <Form.Item
          name="remark"
          label={t('myChannels.applicationRemark')}
        >
          <TextArea
            rows={3}
            maxLength={255}
            showCount
            placeholder={t('myChannels.applicationRemarkPlaceholder')}
          />
        </Form.Item>
      </Form>
    </Modal>
  );
}
