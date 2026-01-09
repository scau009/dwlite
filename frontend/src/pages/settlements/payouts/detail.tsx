import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router';
import { useTranslation } from 'react-i18next';
import { Card, Descriptions, Tag, Button, Spin, Empty, App, Timeline } from 'antd';
import { ArrowLeftOutlined, CheckCircleOutlined, ClockCircleOutlined, CloseCircleOutlined } from '@ant-design/icons';

import {
  settlementApi,
  type PayoutDetail,
  PAYOUT_STATUS_LABELS,
  PAYOUT_STATUS_COLORS,
} from '@/lib/settlement-api';

export function PayoutDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { t } = useTranslation();
  const { message } = App.useApp();

  const [payout, setPayout] = useState<PayoutDetail | null>(null);
  const [loading, setLoading] = useState(true);

  const loadPayout = async () => {
    if (!id) return;
    setLoading(true);
    try {
      const data = await settlementApi.getPayout(id);
      setPayout(data);
    } catch {
      message.error(t('common.error'));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadPayout();
  }, [id]);

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Spin size="large" />
      </div>
    );
  }

  if (!payout) {
    return <Empty description={t('common.noData')} />;
  }

  const getTimelineItems = () => {
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
            <div className="font-medium">{t('payouts.statusApproved')}</div>
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
            <div className="font-medium">{t('payouts.statusRejected')}</div>
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
        dot: <ClockCircleOutlined />,
        children: (
          <div>
            <div className="font-medium">{t('payouts.statusProcessing')}</div>
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
            <div className="font-medium">{t('payouts.statusCompleted')}</div>
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
            <div className="font-medium">{t('payouts.statusFailed')}</div>
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
    <div className="flex flex-col gap-4">
      {/* Header */}
      <div className="flex items-center justify-between">
        <Button
          icon={<ArrowLeftOutlined />}
          onClick={() => navigate('/settlements/payouts')}
        >
          {t('common.back')}
        </Button>
      </div>

      {/* Basic Info Card */}
      <Card title={t('payouts.basicInfo')}>
        <Descriptions column={{ xs: 1, sm: 2, md: 3 }}>
          <Descriptions.Item label={t('payouts.payoutNo')}>
            {payout.payoutNo}
          </Descriptions.Item>
          <Descriptions.Item label={t('payouts.status')}>
            <Tag color={PAYOUT_STATUS_COLORS[payout.status]}>
              {t(PAYOUT_STATUS_LABELS[payout.status])}
            </Tag>
          </Descriptions.Item>
          <Descriptions.Item label={t('payouts.merchant')}>
            {payout.merchantName}
          </Descriptions.Item>
          <Descriptions.Item label={t('payouts.currency')}>
            {payout.currency}
          </Descriptions.Item>
        </Descriptions>
      </Card>

      {/* Amount Info Card */}
      <Card title={t('payouts.amountInfo')}>
        <Descriptions column={{ xs: 1, sm: 2, md: 3 }}>
          <Descriptions.Item label={t('payouts.amount')}>
            <span className="text-lg">¥{parseFloat(payout.amount).toFixed(2)}</span>
          </Descriptions.Item>
          <Descriptions.Item label={t('payouts.fee')}>
            <span className="text-red-500">-¥{parseFloat(payout.fee).toFixed(2)}</span>
          </Descriptions.Item>
          <Descriptions.Item label={t('payouts.actualAmount')}>
            <span className="text-lg text-green-600 font-medium">
              ¥{parseFloat(payout.actualAmount).toFixed(2)}
            </span>
          </Descriptions.Item>
        </Descriptions>
      </Card>

      {/* Bank Info Card */}
      <Card title={t('payouts.bankInfo')}>
        <Descriptions column={{ xs: 1, sm: 2, md: 3 }}>
          <Descriptions.Item label={t('payouts.bankName')}>
            {payout.bankName}
          </Descriptions.Item>
          <Descriptions.Item label={t('payouts.accountNumber')}>
            {payout.maskedAccountNumber}
          </Descriptions.Item>
          <Descriptions.Item label={t('payouts.accountHolder')}>
            {payout.accountHolder}
          </Descriptions.Item>
        </Descriptions>
      </Card>

      {/* Timeline Card */}
      <Card title={t('payouts.timeline')}>
        <Timeline items={getTimelineItems()} />
      </Card>

      {/* Review Info Card (if rejected) */}
      {(payout.rejectReason || payout.failReason || payout.reviewedBy) && (
        <Card title={t('payouts.reviewInfo')}>
          <Descriptions column={{ xs: 1, sm: 2 }}>
            {payout.reviewedBy && (
              <Descriptions.Item label={t('payouts.reviewedBy')}>
                {payout.reviewedBy}
              </Descriptions.Item>
            )}
            {payout.rejectReason && (
              <Descriptions.Item label={t('payouts.rejectReasonLabel')}>
                <span className="text-red-500">{payout.rejectReason}</span>
              </Descriptions.Item>
            )}
            {payout.failReason && (
              <Descriptions.Item label={t('payouts.failReason')}>
                <span className="text-red-500">{payout.failReason}</span>
              </Descriptions.Item>
            )}
            {payout.externalTransactionId && (
              <Descriptions.Item label="External TX ID">
                {payout.externalTransactionId}
              </Descriptions.Item>
            )}
          </Descriptions>
        </Card>
      )}
    </div>
  );
}
