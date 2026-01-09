import { useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal, Form, InputNumber, Select, Input, App, Alert } from 'antd';

import { merchantPayoutApi, type PayoutSummary } from '@/lib/merchant-payout-api';

interface CreatePayoutModalProps {
  open: boolean;
  summary: PayoutSummary | null;
  onCancel: () => void;
  onSuccess: () => void;
}

export function CreatePayoutModal({
  open,
  summary,
  onCancel,
  onSuccess,
}: CreatePayoutModalProps) {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [form] = Form.useForm();

  const availableBalance = parseFloat(summary?.availableBalance || '0');
  const bankAccounts = summary?.bankAccounts || [];

  useEffect(() => {
    if (open) {
      form.resetFields();
      // Set default bank account if available
      const defaultAccount = bankAccounts.find((a) => a.isDefault);
      if (defaultAccount) {
        form.setFieldValue('bankAccountId', defaultAccount.id);
      } else if (bankAccounts.length > 0) {
        form.setFieldValue('bankAccountId', bankAccounts[0].id);
      }
    }
  }, [open, bankAccounts, form]);

  const handleSubmit = async () => {
    try {
      const values = await form.validateFields();

      if (parseFloat(values.amount) > availableBalance) {
        message.error(t('merchantPayouts.amountExceedsBalance'));
        return;
      }

      await merchantPayoutApi.createPayout({
        bankAccountId: values.bankAccountId,
        amount: values.amount.toString(),
        remark: values.remark || undefined,
      });

      message.success(t('merchantPayouts.createSuccess'));
      onSuccess();
    } catch (error) {
      const err = error as { error?: string };
      if (err.error) {
        message.error(err.error);
      }
    }
  };

  return (
    <Modal
      title={t('merchantPayouts.requestPayout')}
      open={open}
      onCancel={onCancel}
      onOk={handleSubmit}
      okText={t('common.confirm')}
      cancelText={t('common.cancel')}
      width={500}
      destroyOnClose
    >
      <div className="mb-4">
        <Alert
          message={`${t('merchantPayouts.availableBalance')}: ¥${availableBalance.toFixed(2)}`}
          type="info"
          showIcon
        />
      </div>

      <Form form={form} layout="vertical">
        <Form.Item
          name="bankAccountId"
          label={t('merchantPayouts.selectBankAccount')}
          rules={[{ required: true, message: t('merchantPayouts.bankAccountRequired') }]}
        >
          <Select placeholder={t('merchantPayouts.selectBankAccountPlaceholder')}>
            {bankAccounts.map((account) => (
              <Select.Option key={account.id} value={account.id}>
                {account.bankName} - {account.maskedAccountNumber} ({account.accountHolder})
                {account.isDefault && ' ★'}
              </Select.Option>
            ))}
          </Select>
        </Form.Item>

        <Form.Item
          name="amount"
          label={t('merchantPayouts.withdrawAmount')}
          rules={[
            { required: true, message: t('merchantPayouts.amountRequired') },
            {
              validator: (_, value) => {
                if (value && parseFloat(value) > availableBalance) {
                  return Promise.reject(t('merchantPayouts.amountExceedsBalance'));
                }
                return Promise.resolve();
              },
            },
          ]}
        >
          <InputNumber
            style={{ width: '100%' }}
            min={0.01}
            max={availableBalance}
            precision={2}
            prefix="¥"
            placeholder={t('merchantPayouts.withdrawAmountPlaceholder')}
          />
        </Form.Item>

        <Form.Item name="remark" label={t('merchantPayouts.remark')}>
          <Input.TextArea
            rows={3}
            placeholder={t('merchantPayouts.remarkPlaceholder')}
            maxLength={500}
          />
        </Form.Item>
      </Form>
    </Modal>
  );
}
