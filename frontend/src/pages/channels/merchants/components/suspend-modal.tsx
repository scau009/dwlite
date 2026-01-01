import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal, Form, Input, App } from 'antd';

import { channelApi, type MerchantChannel } from '@/lib/channel-api';

interface SuspendModalProps {
  open: boolean;
  merchantChannel: MerchantChannel | null;
  onClose: () => void;
  onSuccess: () => void;
}

interface FormValues {
  reason: string;
}

export function SuspendModal({ open, merchantChannel, onClose, onSuccess }: SuspendModalProps) {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [form] = Form.useForm<FormValues>();
  const [loading, setLoading] = useState(false);

  const handleSubmit = async () => {
    if (!merchantChannel) return;

    try {
      const values = await form.validateFields();
      setLoading(true);

      await channelApi.suspendChannel(merchantChannel.id, values.reason);
      message.success(t('merchantChannels.suspended'));
      form.resetFields();
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

  const handleClose = () => {
    form.resetFields();
    onClose();
  };

  return (
    <Modal
      title={t('merchantChannels.suspendTitle')}
      open={open}
      onCancel={handleClose}
      onOk={handleSubmit}
      confirmLoading={loading}
      okText={t('common.confirm')}
      cancelText={t('common.cancel')}
      okButtonProps={{ danger: true }}
      destroyOnHidden
      width={450}
    >
      {merchantChannel && (
        <div className="mb-4 p-3 bg-gray-50 rounded">
          <div className="text-sm text-gray-500">{t('merchantChannels.merchant')}</div>
          <div className="font-medium">{merchantChannel.merchant.name}</div>
          <div className="text-sm text-gray-500 mt-2">{t('merchantChannels.channel')}</div>
          <div className="font-medium">{merchantChannel.salesChannel.name}</div>
        </div>
      )}

      <Form
        form={form}
        layout="vertical"
      >
        <Form.Item
          name="reason"
          label={t('merchantChannels.suspendReason')}
          rules={[
            { max: 500, message: t('merchantChannels.reasonMaxLength') },
          ]}
        >
          <Input.TextArea
            rows={3}
            placeholder={t('merchantChannels.suspendReasonPlaceholder')}
          />
        </Form.Item>
      </Form>
    </Modal>
  );
}
