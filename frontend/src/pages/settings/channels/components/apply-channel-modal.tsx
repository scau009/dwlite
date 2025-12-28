import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal, Form, Input, Avatar, Space, Tag, Descriptions, App } from 'antd';
import { ShopOutlined } from '@ant-design/icons';

import {
  merchantChannelApi,
  type AvailableSalesChannel,
} from '@/lib/merchant-channel-api';

const { TextArea } = Input;

interface Props {
  open: boolean;
  channel: AvailableSalesChannel | null;
  onClose: () => void;
  onSuccess: () => void;
}

const businessTypeColorMap: Record<string, string> = {
  import: 'blue',
  export: 'green',
};

export function ApplyChannelModal({ open, channel, onClose, onSuccess }: Props) {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);

  const handleSubmit = async () => {
    if (!channel) return;

    try {
      const values = await form.validateFields();
      setLoading(true);

      await merchantChannelApi.applyChannel(channel.id, values.remark);
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
              <code className="text-xs bg-gray-100 px-1 rounded">
                {channel.code}
              </code>
            </Space>
          </Descriptions.Item>
          <Descriptions.Item label={t('channels.businessType.label')}>
            <Tag color={businessTypeColorMap[channel.businessType]}>
              {t(`channels.businessType.${channel.businessType}`)}
            </Tag>
          </Descriptions.Item>
          {channel.description && (
            <Descriptions.Item label={t('channels.description')}>
              {channel.description}
            </Descriptions.Item>
          )}
        </Descriptions>
      </div>

      <Form form={form} layout="vertical">
        <Form.Item
          name="remark"
          label={t('myChannels.applicationRemark')}
          extra={t('myChannels.applicationRemarkHint')}
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
