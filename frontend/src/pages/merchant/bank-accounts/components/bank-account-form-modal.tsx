import { useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal, Form, Input, Select, App } from 'antd';

import {
  merchantBankAccountApi,
  type BankAccount,
  type CreateBankAccountRequest,
  type UpdateBankAccountRequest,
} from '@/lib/merchant-bank-account-api';

interface BankAccountFormModalProps {
  open: boolean;
  account: BankAccount | null;
  onCancel: () => void;
  onSuccess: () => void;
}

export function BankAccountFormModal({
  open,
  account,
  onCancel,
  onSuccess,
}: BankAccountFormModalProps) {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [form] = Form.useForm();

  const isEditing = !!account;

  useEffect(() => {
    if (open) {
      if (account) {
        // Load full account details for editing
        merchantBankAccountApi.getBankAccount(account.id).then((data) => {
          form.setFieldsValue({
            bankName: data.bankName,
            bankCode: data.bankCode || '',
            branchName: data.branchName || '',
            accountNumber: data.accountNumber,
            accountHolder: data.accountHolder,
            accountType: data.accountType,
            currency: data.currency,
          });
        });
      } else {
        form.resetFields();
        form.setFieldsValue({
          currency: 'CNY',
          accountType: 'corporate',
        });
      }
    }
  }, [open, account, form]);

  const handleSubmit = async () => {
    try {
      const values = await form.validateFields();

      const data: CreateBankAccountRequest | UpdateBankAccountRequest = {
        bankName: values.bankName,
        bankCode: values.bankCode || undefined,
        branchName: values.branchName || undefined,
        accountNumber: values.accountNumber,
        accountHolder: values.accountHolder,
        accountType: values.accountType,
        currency: values.currency,
      };

      if (isEditing) {
        await merchantBankAccountApi.updateBankAccount(account.id, data);
        message.success(t('bankAccounts.updated'));
      } else {
        await merchantBankAccountApi.createBankAccount(data);
        message.success(t('bankAccounts.created'));
      }

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
      title={isEditing ? t('bankAccounts.editAccount') : t('bankAccounts.addAccount')}
      open={open}
      onCancel={onCancel}
      onOk={handleSubmit}
      okText={t('common.save')}
      cancelText={t('common.cancel')}
      width={500}
      destroyOnClose
    >
      <Form form={form} layout="vertical" className="mt-4">
        <Form.Item
          name="bankName"
          label={t('bankAccounts.bankName')}
          rules={[{ required: true, message: t('bankAccounts.bankNameRequired') }]}
        >
          <Input placeholder={t('bankAccounts.bankNamePlaceholder')} />
        </Form.Item>

        <Form.Item name="bankCode" label={t('bankAccounts.bankCode')}>
          <Input placeholder={t('bankAccounts.bankCodePlaceholder')} />
        </Form.Item>

        <Form.Item name="branchName" label={t('bankAccounts.branchName')}>
          <Input placeholder={t('bankAccounts.branchNamePlaceholder')} />
        </Form.Item>

        <Form.Item
          name="accountNumber"
          label={t('bankAccounts.accountNumber')}
          rules={[{ required: true, message: t('bankAccounts.accountNumberRequired') }]}
        >
          <Input placeholder={t('bankAccounts.accountNumberPlaceholder')} />
        </Form.Item>

        <Form.Item
          name="accountHolder"
          label={t('bankAccounts.accountHolder')}
          rules={[{ required: true, message: t('bankAccounts.accountHolderRequired') }]}
        >
          <Input placeholder={t('bankAccounts.accountHolderPlaceholder')} />
        </Form.Item>

        <Form.Item
          name="accountType"
          label={t('bankAccounts.accountType')}
          rules={[{ required: true, message: t('bankAccounts.accountTypeRequired') }]}
        >
          <Select>
            <Select.Option value="corporate">{t('bankAccounts.typeCorporate')}</Select.Option>
            <Select.Option value="personal">{t('bankAccounts.typePersonal')}</Select.Option>
          </Select>
        </Form.Item>

        <Form.Item name="currency" label={t('bankAccounts.currency')}>
          <Select>
            <Select.Option value="CNY">CNY</Select.Option>
            <Select.Option value="USD">USD</Select.Option>
            <Select.Option value="EUR">EUR</Select.Option>
          </Select>
        </Form.Item>
      </Form>
    </Modal>
  );
}
