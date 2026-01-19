import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal, App } from 'antd';

import { settlementApi, type Payout } from '@/lib/settlement-api';

interface ApproveModalProps {
  open: boolean;
  payout: Payout | null;
  onClose: () => void;
  onSuccess: () => void;
}

export function ApproveModal({ open, payout, onClose, onSuccess }: ApproveModalProps) {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [loading, setLoading] = useState(false);

  const handleSubmit = async () => {
    if (!payout) return;

    setLoading(true);
    try {
      await settlementApi.approvePayout(payout.id);
      onSuccess();
    } catch (error: unknown) {
      const errorMessage = error instanceof Error ? error.message : t('common.error');
      message.error(errorMessage);
    } finally {
      setLoading(false);
    }
  };

  if (!payout) return null;

  return (
    <Modal
      title={t('payouts.approve')}
      open={open}
      onOk={handleSubmit}
      onCancel={onClose}
      confirmLoading={loading}
      okText={t('common.confirm')}
      cancelText={t('common.cancel')}
    >
      <div className="py-4">
        <p className="mb-4">{t('payouts.approveConfirm')}</p>
        <div className="bg-gray-50 rounded p-4 space-y-2">
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
          <div className="flex justify-between">
            <span className="text-gray-500">{t('payouts.bankName')}:</span>
            <span>{payout.bankName}</span>
          </div>
          <div className="flex justify-between">
            <span className="text-gray-500">{t('payouts.accountNumber')}:</span>
            <span>{payout.maskedAccountNumber}</span>
          </div>
        </div>
      </div>
    </Modal>
  );
}
