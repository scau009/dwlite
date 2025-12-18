import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal, Form, InputNumber, Input, App } from 'antd';

import { merchantApi, type Merchant } from '@/lib/merchant-api';

interface ChargeModalProps {
  open: boolean;
  merchant: Merchant | null;
  onClose: () => void;
  onSuccess: () => void;
}

export function ChargeModal({ open, merchant, onClose, onSuccess }: ChargeModalProps) {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);

  const handleSubmit = async () => {
    if (!merchant) return;

    try {
      const values = await form.validateFields();
      setLoading(true);

      await merchantApi.chargeDeposit(merchant.id, values.amount, values.remark);
      message.success(t('merchants.chargeSuccess'));
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

  return (
    <Modal
      title={t('merchants.chargeDeposit')}
      open={open}
      onOk={handleSubmit}
      onCancel={handleCancel}
      confirmLoading={loading}
      destroyOnClose
    >
      <div className="mb-4 p-3 bg-gray-50 rounded">
        <p className="text-sm text-gray-600">
          {t('merchants.merchantName')}: <span className="font-medium">{merchant?.name}</span>
        </p>
        <p className="text-sm text-gray-600">
          {t('merchants.currentBalance')}: <span className="font-medium text-green-600">{merchant?.depositBalance}</span>
        </p>
      </div>

      <Form form={form} layout="vertical">
        <Form.Item
          name="amount"
          label={t('merchants.chargeAmount')}
          rules={[
            { required: true, message: t('validation.required') },
            { type: 'number', min: 0.01, message: t('merchants.amountMustBePositive') },
          ]}
        >
          <InputNumber
            style={{ width: '100%' }}
            placeholder={t('merchants.enterAmount')}
            precision={2}
            min={0.01}
            addonBefore="$"
          />
        </Form.Item>

        <Form.Item name="remark" label={t('merchants.remark')}>
          <Input.TextArea
            placeholder={t('merchants.enterRemark')}
            rows={3}
            maxLength={200}
            showCount
          />
        </Form.Item>
      </Form>
    </Modal>
  );
}
