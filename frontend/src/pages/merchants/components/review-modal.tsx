import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal, Form, Input, App, Button } from 'antd';
import { CheckOutlined, CloseOutlined } from '@ant-design/icons';

import { merchantApi, type Merchant } from '@/lib/merchant-api';

interface ReviewModalProps {
  open: boolean;
  merchant: Merchant | null;
  onClose: () => void;
  onSuccess: () => void;
}

export function ReviewModal({ open, merchant, onClose, onSuccess }: ReviewModalProps) {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const [loading, setLoading] = useState<'approve' | 'reject' | null>(null);

  const handleApprove = async () => {
    if (!merchant) return;

    try {
      setLoading('approve');
      await merchantApi.approveMerchant(merchant.id);
      message.success(t('merchants.approveSuccess'));
      onSuccess();
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setLoading(null);
    }
  };

  const handleReject = async () => {
    if (!merchant) return;

    const reason = form.getFieldValue('reason');
    if (!reason || reason.trim() === '') {
      message.error(t('merchants.rejectReasonRequired'));
      return;
    }

    try {
      setLoading('reject');
      await merchantApi.rejectMerchant(merchant.id, reason.trim());
      message.success(t('merchants.rejectSuccess'));
      form.resetFields();
      onSuccess();
    } catch (error) {
      const err = error as { error?: string };
      if (err.error) {
        message.error(err.error);
      }
    } finally {
      setLoading(null);
    }
  };

  const handleCancel = () => {
    form.resetFields();
    onClose();
  };

  return (
    <Modal
      title={t('merchants.review')}
      open={open}
      onCancel={handleCancel}
      footer={null}
      destroyOnClose
    >
      <div className="mb-4 p-3 bg-gray-50 rounded">
        <p className="text-sm text-gray-600">
          {t('merchants.merchantName')}: <span className="font-medium">{merchant?.name}</span>
        </p>
        <p className="text-sm text-gray-600">
          {t('merchants.email')}: <span className="font-medium">{merchant?.email}</span>
        </p>
        <p className="text-sm text-gray-600">
          {t('merchants.contactName')}: <span className="font-medium">{merchant?.contactName || '-'}</span>
        </p>
        <p className="text-sm text-gray-600">
          {t('merchants.contactPhone')}: <span className="font-medium">{merchant?.contactPhone || '-'}</span>
        </p>
      </div>

      <Form form={form} layout="vertical">
        <Form.Item
          name="reason"
          label={t('merchants.rejectReason')}
        >
          <Input.TextArea
            placeholder={t('merchants.enterRejectReason')}
            rows={3}
            maxLength={255}
            showCount
          />
        </Form.Item>
      </Form>

      <div className="flex justify-end gap-2 mt-4">
        <Button onClick={handleCancel}>{t('common.cancel')}</Button>
        <Button
          danger
          icon={<CloseOutlined />}
          loading={loading === 'reject'}
          disabled={loading === 'approve'}
          onClick={handleReject}
        >
          {t('merchants.reject')}
        </Button>
        <Button
          type="primary"
          icon={<CheckOutlined />}
          loading={loading === 'approve'}
          disabled={loading === 'reject'}
          onClick={handleApprove}
        >
          {t('merchants.approve')}
        </Button>
      </div>
    </Modal>
  );
}
