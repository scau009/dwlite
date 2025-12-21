import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal, Form, Input, App } from 'antd';

import { inboundApi, type InboundOrder } from '@/lib/inbound-api';

interface CancelOrderModalProps {
  open: boolean;
  order: InboundOrder | null;
  onClose: () => void;
  onSuccess: () => void;
}

export function CancelOrderModal({
  open,
  order,
  onClose,
  onSuccess,
}: CancelOrderModalProps) {
  const { t } = useTranslation();
  const [form] = Form.useForm();
  const { message } = App.useApp();
  const [loading, setLoading] = useState(false);

  const handleSubmit = async () => {
    if (!order) return;

    try {
      const values = await form.validateFields();
      setLoading(true);

      await inboundApi.cancelInboundOrder(order.id, {
        reason: values.reason,
      });

      message.success(t('inventory.orderCancelled'));
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

  return (
    <Modal
      title={t('inventory.cancelOrder')}
      open={open}
      onCancel={onClose}
      onOk={handleSubmit}
      confirmLoading={loading}
      okButtonProps={{ danger: true }}
      okText={t('inventory.cancelOrder')}
      destroyOnClose
      width={400}
    >
      <p className="text-gray-500 mb-4">{t('inventory.confirmCancelDesc')}</p>
      <Form form={form} layout="vertical">
        <Form.Item
          name="reason"
          label={t('inventory.cancelReason')}
          rules={[{ required: true, message: t('inventory.cancelReasonRequired') }]}
        >
          <Input.TextArea
            rows={3}
            placeholder={t('inventory.cancelReasonRequired')}
            maxLength={500}
            showCount
          />
        </Form.Item>
      </Form>
    </Modal>
  );
}
