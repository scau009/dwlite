import { useState, useEffect, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router';
import { useTranslation } from 'react-i18next';
import { Card, Descriptions, Tag, Table, Button, Spin, Empty, App } from 'antd';
import { ArrowLeftOutlined } from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';

import {
  settlementApi,
  type SettlementDetail,
  type SettlementItem,
  SETTLEMENT_STATUS_LABELS,
  SETTLEMENT_STATUS_COLORS,
} from '@/lib/settlement-api';

export function SettlementDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { t } = useTranslation();
  const { message } = App.useApp();

  const [settlement, setSettlement] = useState<SettlementDetail | null>(null);
  const [loading, setLoading] = useState(true);

  const loadSettlement = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    try {
      const data = await settlementApi.getSettlement(id);
      setSettlement(data);
    } catch {
      message.error(t('common.error'));
    } finally {
      setLoading(false);
    }
  }, [id, message, t]);

  useEffect(() => {
    loadSettlement();
  }, [loadSettlement]);

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Spin size="large" />
      </div>
    );
  }

  if (!settlement) {
    return <Empty description={t('common.noData')} />;
  }

  const itemColumns: ColumnsType<SettlementItem> = [
    {
      title: t('settlements.skuCode'),
      dataIndex: 'skuCode',
      width: 150,
      render: (value) => value || '-',
    },
    {
      title: t('settlements.productName'),
      dataIndex: 'productName',
      ellipsis: true,
      render: (value) => value || '-',
    },
    {
      title: t('settlements.quantity'),
      dataIndex: 'quantity',
      width: 80,
      align: 'center',
    },
    {
      title: t('settlements.unitPrice'),
      dataIndex: 'unitPrice',
      width: 100,
      align: 'right',
      render: (value) => `¥${parseFloat(value).toFixed(2)}`,
    },
    {
      title: t('settlements.grossAmount'),
      dataIndex: 'grossAmount',
      width: 120,
      align: 'right',
      render: (value) => `¥${parseFloat(value).toFixed(2)}`,
    },
    {
      title: t('settlements.commissionRate'),
      dataIndex: 'commissionRate',
      width: 100,
      align: 'right',
      render: (value) => `${value}%`,
    },
    {
      title: t('settlements.commissionAmount'),
      dataIndex: 'commissionAmount',
      width: 100,
      align: 'right',
      render: (value) => `¥${parseFloat(value).toFixed(2)}`,
    },
    {
      title: t('settlements.netAmount'),
      dataIndex: 'netAmount',
      width: 120,
      align: 'right',
      render: (value) => (
        <span className="text-green-600 font-medium">
          ¥{parseFloat(value).toFixed(2)}
        </span>
      ),
    },
  ];

  return (
    <div className="flex flex-col gap-4">
      {/* Header */}
      <div className="flex items-center justify-between">
        <Button
          icon={<ArrowLeftOutlined />}
          onClick={() => navigate('/settlements/list')}
        >
          {t('common.back')}
        </Button>
      </div>

      {/* Basic Info Card */}
      <Card title={t('settlements.basicInfo')}>
        <Descriptions column={{ xs: 1, sm: 2, md: 3 }}>
          <Descriptions.Item label={t('settlements.settlementNo')}>
            {settlement.settlementNo}
          </Descriptions.Item>
          <Descriptions.Item label={t('settlements.status')}>
            <Tag color={SETTLEMENT_STATUS_COLORS[settlement.status]}>
              {t(SETTLEMENT_STATUS_LABELS[settlement.status])}
            </Tag>
          </Descriptions.Item>
          <Descriptions.Item label={t('settlements.merchant')}>
            {settlement.merchantName}
          </Descriptions.Item>
          <Descriptions.Item label={t('settlements.orderNo')}>
            {settlement.orderNo}
          </Descriptions.Item>
          <Descriptions.Item label={t('settlements.fulfillmentNo')}>
            {settlement.fulfillmentNo}
          </Descriptions.Item>
          <Descriptions.Item label={t('settlements.currency')}>
            {settlement.currency}
          </Descriptions.Item>
        </Descriptions>
      </Card>

      {/* Amount Info Card */}
      <Card title={t('settlements.amountInfo')}>
        <Descriptions column={{ xs: 1, sm: 2, md: 4 }}>
          <Descriptions.Item label={t('settlements.grossAmount')}>
            <span className="text-lg">¥{parseFloat(settlement.grossAmount).toFixed(2)}</span>
          </Descriptions.Item>
          <Descriptions.Item label={t('settlements.commissionRate')}>
            {settlement.commissionRate}%
          </Descriptions.Item>
          <Descriptions.Item label={t('settlements.commissionAmount')}>
            <span className="text-red-500">-¥{parseFloat(settlement.commissionAmount).toFixed(2)}</span>
          </Descriptions.Item>
          <Descriptions.Item label={t('settlements.netAmount')}>
            <span className="text-lg text-green-600 font-medium">
              ¥{parseFloat(settlement.netAmount).toFixed(2)}
            </span>
          </Descriptions.Item>
        </Descriptions>
      </Card>

      {/* Time Info Card */}
      <Card title={t('settlements.timeInfo')}>
        <Descriptions column={{ xs: 1, sm: 2, md: 3 }}>
          <Descriptions.Item label={t('settlements.settlementDays')}>
            T+{settlement.settlementDays}
          </Descriptions.Item>
          <Descriptions.Item label={t('settlements.scheduledSettleAt')}>
            {new Date(settlement.scheduledSettleAt).toLocaleString()}
          </Descriptions.Item>
          <Descriptions.Item label={t('settlements.settledAt')}>
            {settlement.settledAt
              ? new Date(settlement.settledAt).toLocaleString()
              : '-'}
          </Descriptions.Item>
          <Descriptions.Item label={t('common.createdAt')}>
            {new Date(settlement.createdAt).toLocaleString()}
          </Descriptions.Item>
          {settlement.cancelledAt && (
            <>
              <Descriptions.Item label={t('settlements.cancelledAt')}>
                {new Date(settlement.cancelledAt).toLocaleString()}
              </Descriptions.Item>
              <Descriptions.Item label={t('settlements.cancelReason')}>
                {settlement.cancelReason || '-'}
              </Descriptions.Item>
            </>
          )}
        </Descriptions>
      </Card>

      {/* Settlement Items */}
      <Card title={t('settlements.itemList')}>
        <Table
          columns={itemColumns}
          dataSource={settlement.items}
          rowKey="id"
          pagination={false}
          scroll={{ x: 900 }}
          size="small"
        />
      </Card>
    </div>
  );
}
