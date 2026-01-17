import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal, Form, Input, Avatar, Space, Descriptions, App, Checkbox, Alert } from 'antd';
import { ShopOutlined } from '@ant-design/icons';

import {
  merchantChannelApi,
  type AvailableSalesChannel,
  type FulfillmentType,
  type MyMerchantChannel,
} from '@/lib/merchant-channel-api';

const { TextArea } = Input;

interface ChannelInfo {
  id: string;
  name: string;
  code: string;
  logoUrl: string | null;
  description?: string | null;
}

interface Props {
  open: boolean;
  channel: AvailableSalesChannel | ChannelInfo | null;
  onClose: () => void;
  onSuccess: () => void;
  // Resubmit mode props
  mode?: 'apply' | 'resubmit';
  existingData?: MyMerchantChannel | null;
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

export function ApplyChannelModal({
  open,
  channel,
  onClose,
  onSuccess,
  mode = 'apply',
  existingData,
}: Props) {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);

  const isResubmit = mode === 'resubmit';

  useEffect(() => {
    if (open) {
      if (isResubmit && existingData) {
        // Prefill form with existing data
        form.setFieldsValue({
          fulfillmentTypes: existingData.requestedFulfillmentTypes,
          remark: '', // Clear remark for new submission
        });
      } else {
        form.resetFields();
      }
    }
  }, [open, form, isResubmit, existingData]);

  const handleSubmit = async () => {
    if (!channel) return;

    try {
      const values = await form.validateFields();
      setLoading(true);

      if (isResubmit && existingData) {
        await merchantChannelApi.resubmitChannel(existingData.id, {
          fulfillmentTypes: values.fulfillmentTypes,
          remark: values.remark,
        });
        message.success(t('myChannels.resubmitSuccess'));
      } else {
        await merchantChannelApi.applyChannel({
          salesChannelId: channel.id,
          fulfillmentTypes: values.fulfillmentTypes,
          remark: values.remark,
        });
        message.success(t('myChannels.applicationSubmitted'));
      }
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
      title={isResubmit ? t('myChannels.resubmitApplication') : t('myChannels.applyForChannel')}
      open={open}
      onOk={handleSubmit}
      onCancel={handleCancel}
      confirmLoading={loading}
      okText={isResubmit ? t('myChannels.resubmit') : t('myChannels.submitApplication')}
      cancelText={t('common.cancel')}
      width={560}
    >
      <div className="mb-6">
        <Descriptions column={1} size="small">
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
          {'description' in channel && channel.description && (
            <Descriptions.Item label={t('channels.description')}>
              {channel.description}
            </Descriptions.Item>
          )}
        </Descriptions>
      </div>

      {isResubmit && existingData?.remark && (
        <div className="mb-6">
          <Alert
            message={t('myChannels.rejectedReason')}
            description={existingData.remark}
            type="error"
            showIcon
          />
        </div>
      )}

      <div className="mb-6">
        <Alert
          message={isResubmit ? t('myChannels.resubmitHint') : t('myChannels.fulfillmentTypesHint')}
          type="info"
          showIcon
        />
      </div>

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
