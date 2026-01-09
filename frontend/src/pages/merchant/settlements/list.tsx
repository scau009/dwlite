import { useRef, useState, useEffect } from 'react';
import { useNavigate } from 'react-router';
import { useTranslation } from 'react-i18next';
import { Button, Tag, Card, Row, Col, Statistic } from 'antd';
import { EyeOutlined } from '@ant-design/icons';
import { ProTable, type ActionType, type ProColumns } from '@ant-design/pro-components';

import {
  merchantSettlementApi,
  type Settlement,
  type SettlementStatus,
  SETTLEMENT_STATUS_LABELS,
  SETTLEMENT_STATUS_COLORS,
} from '@/lib/merchant-settlement-api';

export function MerchantSettlementsListPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const actionRef = useRef<ActionType>(null);

  const [summary, setSummary] = useState({ pendingAmount: '0.00', settledAmount: '0.00' });

  useEffect(() => {
    const loadSummary = async () => {
      try {
        const data = await merchantSettlementApi.getSettlementSummary();
        setSummary(data);
      } catch {
        // ignore
      }
    };
    loadSummary();
  }, []);

  const columns: ProColumns<Settlement>[] = [
    {
      title: t('settlements.settlementNo'),
      dataIndex: 'settlementNo',
      width: 180,
      copyable: true,
    },
    {
      title: t('settlements.orderNo'),
      dataIndex: 'orderNo',
      width: 180,
      search: false,
    },
    {
      title: t('settlements.fulfillmentNo'),
      dataIndex: 'fulfillmentNo',
      width: 180,
      search: false,
    },
    {
      title: t('settlements.grossAmount'),
      dataIndex: 'grossAmount',
      width: 120,
      search: false,
      render: (_, record) => `¥${parseFloat(record.grossAmount).toFixed(2)}`,
    },
    {
      title: t('settlements.commissionRate'),
      dataIndex: 'commissionRate',
      width: 100,
      search: false,
      render: (_, record) => `${record.commissionRate}%`,
    },
    {
      title: t('settlements.commissionAmount'),
      dataIndex: 'commissionAmount',
      width: 120,
      search: false,
      render: (_, record) => (
        <span className="text-red-500">-¥{parseFloat(record.commissionAmount).toFixed(2)}</span>
      ),
    },
    {
      title: t('settlements.netAmount'),
      dataIndex: 'netAmount',
      width: 120,
      search: false,
      render: (_, record) => (
        <span className="text-green-600 font-medium">¥{parseFloat(record.netAmount).toFixed(2)}</span>
      ),
    },
    {
      title: t('settlements.status'),
      dataIndex: 'status',
      width: 100,
      valueType: 'select',
      valueEnum: {
        pending: { text: t('settlements.statusPending'), status: 'Processing' },
        settled: { text: t('settlements.statusSettled'), status: 'Success' },
        cancelled: { text: t('settlements.statusCancelled'), status: 'Error' },
      },
      render: (_, record) => (
        <Tag color={SETTLEMENT_STATUS_COLORS[record.status]}>
          {t(SETTLEMENT_STATUS_LABELS[record.status])}
        </Tag>
      ),
    },
    {
      title: t('settlements.scheduledSettleAt'),
      dataIndex: 'scheduledSettleAt',
      valueType: 'dateTime',
      width: 180,
      search: false,
    },
    {
      title: t('common.actions'),
      valueType: 'option',
      width: 100,
      fixed: 'right',
      render: (_, record) => (
        <Button
          type="link"
          size="small"
          icon={<EyeOutlined />}
          onClick={() => navigate(`/merchant/settlements/${record.id}`)}
        >
          {t('common.detail')}
        </Button>
      ),
    },
  ];

  return (
    <div className="flex flex-col gap-4">
      {/* Summary Cards */}
      <Row gutter={16}>
        <Col xs={24} sm={12}>
          <Card>
            <Statistic
              title={t('merchantSettlements.pendingAmount')}
              value={parseFloat(summary.pendingAmount)}
              precision={2}
              prefix="¥"
              valueStyle={{ color: '#1890ff' }}
            />
          </Card>
        </Col>
        <Col xs={24} sm={12}>
          <Card>
            <Statistic
              title={t('merchantSettlements.settledAmount')}
              value={parseFloat(summary.settledAmount)}
              precision={2}
              prefix="¥"
              valueStyle={{ color: '#52c41a' }}
            />
          </Card>
        </Col>
      </Row>

      {/* Table */}
      <ProTable<Settlement>
        headerTitle={t('merchantSettlements.title')}
        actionRef={actionRef}
        rowKey="id"
        columns={columns}
        request={async (params) => {
          const result = await merchantSettlementApi.getSettlements({
            page: params.current,
            limit: params.pageSize,
            settlementNo: params.settlementNo,
            status: params.status as SettlementStatus | undefined,
          });
          return {
            data: result.data,
            total: result.total,
            success: true,
          };
        }}
        pagination={{
          defaultPageSize: 20,
          showSizeChanger: true,
        }}
        scroll={{ x: 1500 }}
      />
    </div>
  );
}
