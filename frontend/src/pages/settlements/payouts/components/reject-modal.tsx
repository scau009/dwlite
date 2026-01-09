import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal, Form, Input, App } from 'antd';

import { settlementApi, type Payout } from '@/lib/settlement-api';

interface RejectModalProps {
  open: boolean;
  payout: Payout | null;
  onClose: () => void;
  onSuccess: () => void;
}

export function RejectModal({ open, payout, onClose, onSuccess }: RejectModalProps) {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);

  const handleSubmit = async () => {
    if (!payout) return;

    try {
      const values = await form.validateFields();
      setLoading(true);
      await settlementApi.rejectPayout(payout.id, values.reason);
      form.resetFields();
      onSuccess();
    } catch (error: unknown) {
      if (error instanceof Error && error.message) {
        message.error(error.message);
      }
    } finally {
      setLoading(false);
    }
  };

  const handleCancel = () => {
    form.resetFields();
    onClose();
  };

  if (!payout) return null;

  return (
    <Modal
      title={t('payouts.reject')}
      open={open}
      onOk={handleSubmit}
      onCancel={handleCancel}
      confirmLoading={loading}
      okText={t('common.confirm')}
      cancelText={t('common.cancel')}
      okButtonProps={{ danger: true }}
      destroyOnClose
    >
      <div className="py-4">
        <div className="bg-gray-50 rounded p-4 space-y-2 mb-4">
          <div className="flex justify-between">
            <span className="text-gray-500">{t('payouts.payoutNo')}:</span>
            <span className="font-medium">{payout.payoutNo}</span>
          </div>
          <div className="flex justify-between">
            <span className="text-gray-500">{t('payouts.merchant')}:</span>
            <span>{payout.merchantName}</span>
          </div>
          <div className="flex justify-between">
            <span className="text-gray-500">{t('payouts.amount')}:</span>
            <span className="font-medium text-green-600">
              ¥{parseFloat(payout.amount).toFixed(2)}
            </span>
          </div>
        </div>

        <Form form={form} layout="vertical">
          <Form.Item
            name="reason"
            label={t('payouts.rejectReason')}
            rules={[
              {
                required: true,
                message: t('payouts.rejectReasonRequired'),
              },
            ]}
          >
            <Input.TextArea
              rows={4}
              placeholder={t('payouts.rejectReason')}
              maxLength={255}
              showCount
            />
          </Form.Item>
        </Form>
      </div>
    </Modal>
  );
}
