import { useState, useEffect, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal, Descriptions, Tag, Spin, Timeline } from 'antd';
import {
  CheckCircleOutlined,
  CloseCircleOutlined,
  SyncOutlined,
} from '@ant-design/icons';

import {
  merchantPayoutApi,
  type PayoutDetail,
  PAYOUT_STATUS_LABELS,
  PAYOUT_STATUS_COLORS,
} from '@/lib/merchant-payout-api';

interface PayoutDetailModalProps {
  open: boolean;
  payoutId: string | null;
  onClose: () => void;
}

export function PayoutDetailModal({ open, payoutId, onClose }: PayoutDetailModalProps) {
  const { t } = useTranslation();
  const [payout, setPayout] = useState<PayoutDetail | null>(null);
  const [loading, setLoading] = useState(false);

  const loadPayout = useCallback(async () => {
    if (!payoutId) return;
    setLoading(true);
    try {
      const data = await merchantPayoutApi.getPayout(payoutId);
      setPayout(data);
    } catch {
      // Error handled by API client
    } finally {
      setLoading(false);
    }
  }, [payoutId]);

  useEffect(() => {
    if (open && payoutId) {
      loadPayout();
    }
  }, [open, payoutId, loadPayout]);

  const getTimelineItems = () => {
    if (!payout) return [];

    const items = [
      {
        color: 'green',
        dot: <CheckCircleOutlined />,
        children: (
          <div>
            <div className="font-medium">{t('common.createdAt')}</div>
            <div className="text-gray-500 text-sm">
              {new Date(payout.createdAt).toLocaleString()}
            </div>
          </div>
        ),
      },
    ];

    if (payout.approvedAt) {
      items.push({
        color: 'green',
        dot: <CheckCircleOutlined />,
        children: (
          <div>
            <div className="font-medium">{t('merchantPayouts.statusApproved')}</div>
            <div className="text-gray-500 text-sm">
              {new Date(payout.approvedAt).toLocaleString()}
            </div>
          </div>
        ),
      });
    }

    if (payout.rejectedAt) {
      items.push({
        color: 'red',
        dot: <CloseCircleOutlined />,
        children: (
          <div>
            <div className="font-medium">{t('merchantPayouts.statusRejected')}</div>
            <div className="text-gray-500 text-sm">
              {new Date(payout.rejectedAt).toLocaleString()}
            </div>
            {payout.rejectReason && (
              <div className="text-red-500 text-sm mt-1">
                {t('payouts.rejectReasonLabel')}: {payout.rejectReason}
              </div>
            )}
          </div>
        ),
      });
    }

    if (payout.processingAt) {
      items.push({
        color: 'blue',
        dot: <SyncOutlined spin />,
        children: (
          <div>
            <div className="font-medium">{t('merchantPayouts.statusProcessing')}</div>
            <div className="text-gray-500 text-sm">
              {new Date(payout.processingAt).toLocaleString()}
            </div>
          </div>
        ),
      });
    }

    if (payout.completedAt) {
      items.push({
        color: 'green',
        dot: <CheckCircleOutlined />,
        children: (
          <div>
            <div className="font-medium">{t('merchantPayouts.statusCompleted')}</div>
            <div className="text-gray-500 text-sm">
              {new Date(payout.completedAt).toLocaleString()}
            </div>
          </div>
        ),
      });
    }

    if (payout.failedAt) {
      items.push({
        color: 'red',
        dot: <CloseCircleOutlined />,
        children: (
          <div>
            <div className="font-medium">{t('merchantPayouts.statusFailed')}</div>
            <div className="text-gray-500 text-sm">
              {new Date(payout.failedAt).toLocaleString()}
            </div>
            {payout.failReason && (
              <div className="text-red-500 text-sm mt-1">
                {t('payouts.failReason')}: {payout.failReason}
              </div>
            )}
          </div>
        ),
      });
    }

    return items;
  };

  return (
    <Modal
      title={t('merchantPayouts.payoutDetail')}
      open={open}
      onCancel={onClose}
      footer={null}
      width={600}
    >
      {loading ? (
        <div className="flex items-center justify-center py-8">
          <Spin size="large" />
        </div>
      ) : payout ? (
        <div className="flex flex-col gap-4">
          {/* Basic Info */}
          <Descriptions column={2} bordered size="small">
            <Descriptions.Item label={t('payouts.payoutNo')} span={2}>
              {payout.payoutNo}
            </Descriptions.Item>
            <Descriptions.Item label={t('payouts.status')}>
              <Tag color={PAYOUT_STATUS_COLORS[payout.status]}>
                {t(PAYOUT_STATUS_LABELS[payout.status])}
              </Tag>
            </Descriptions.Item>
            <Descriptions.Item label={t('payouts.currency')}>
              {payout.currency}
            </Descriptions.Item>
          </Descriptions>

          {/* Amount Info */}
          <Descriptions column={3} bordered size="small">
            <Descriptions.Item label={t('payouts.amount')}>
              ¥{parseFloat(payout.amount).toFixed(2)}
            </Descriptions.Item>
            <Descriptions.Item label={t('payouts.fee')}>
              <span className="text-red-500">-¥{parseFloat(payout.fee).toFixed(2)}</span>
            </Descriptions.Item>
            <Descriptions.Item label={t('payouts.actualAmount')}>
              <span className="text-green-600 font-medium">
                ¥{parseFloat(payout.actualAmount).toFixed(2)}
              </span>
            </Descriptions.Item>
          </Descriptions>

          {/* Bank Info */}
          <Descriptions column={2} bordered size="small">
            <Descriptions.Item label={t('payouts.bankName')}>
              {payout.bankName}
            </Descriptions.Item>
            <Descriptions.Item label={t('payouts.accountNumber')}>
              {payout.maskedAccountNumber}
            </Descriptions.Item>
            <Descriptions.Item label={t('payouts.accountHolder')} span={2}>
              {payout.accountHolder}
            </Descriptions.Item>
          </Descriptions>

          {/* Timeline */}
          <div className="mt-2">
            <h4 className="text-sm font-medium mb-2">{t('payouts.timeline')}</h4>
            <Timeline items={getTimelineItems()} />
          </div>

          {/* Remark */}
          {payout.remark && (
            <Descriptions column={1} bordered size="small">
              <Descriptions.Item label={t('merchantPayouts.remark')}>
                {payout.remark}
              </Descriptions.Item>
            </Descriptions>
          )}
        </div>
      ) : null}
    </Modal>
  );
}
