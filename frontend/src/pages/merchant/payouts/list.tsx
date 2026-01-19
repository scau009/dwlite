import { useRef, useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { Button, Tag, Card, Row, Col, Statistic, App } from 'antd';
import { PlusOutlined, EyeOutlined } from '@ant-design/icons';
import { ProTable, type ActionType, type ProColumns } from '@ant-design/pro-components';

import {
  merchantPayoutApi,
  type Payout,
  type PayoutStatus,
  type PayoutSummary,
  PAYOUT_STATUS_LABELS,
  PAYOUT_STATUS_COLORS,
} from '@/lib/merchant-payout-api';
import { CreatePayoutModal } from './components/create-payout-modal';
import { PayoutDetailModal } from './components/payout-detail-modal';

export function MerchantPayoutsListPage() {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const actionRef = useRef<ActionType>(null);

  const [summary, setSummary] = useState<PayoutSummary | null>(null);
  const [createModalOpen, setCreateModalOpen] = useState(false);
  const [detailModalOpen, setDetailModalOpen] = useState(false);
  const [selectedPayoutId, setSelectedPayoutId] = useState<string | null>(null);

  const loadSummary = async () => {
    try {
      const data = await merchantPayoutApi.getPayoutSummary();
      setSummary(data);
    } catch {
      // ignore
    }
  };

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- Initial data load
    void loadSummary();
  }, []);

  const handleCreate = () => {
    if (!summary || summary.bankAccounts.length === 0) {
      message.warning(t('merchantPayouts.noActiveBankAccount'));
      return;
    }
    setCreateModalOpen(true);
  };

  const handleViewDetail = (id: string) => {
    setSelectedPayoutId(id);
    setDetailModalOpen(true);
  };

  const handleCreateSuccess = () => {
    setCreateModalOpen(false);
    loadSummary();
    actionRef.current?.reload();
  };

  const columns: ProColumns<Payout>[] = [
    {
      title: t('payouts.payoutNo'),
      dataIndex: 'payoutNo',
      width: 180,
      copyable: true,
    },
    {
      title: t('payouts.amount'),
      dataIndex: 'amount',
      width: 120,
      search: false,
      render: (_, record) => `¥${parseFloat(record.amount).toFixed(2)}`,
    },
    {
      title: t('payouts.fee'),
      dataIndex: 'fee',
      width: 100,
      search: false,
      render: (_, record) => (
        <span className="text-red-500">-¥{parseFloat(record.fee).toFixed(2)}</span>
      ),
    },
    {
      title: t('payouts.actualAmount'),
      dataIndex: 'actualAmount',
      width: 120,
      search: false,
      render: (_, record) => (
        <span className="text-green-600 font-medium">¥{parseFloat(record.actualAmount).toFixed(2)}</span>
      ),
    },
    {
      title: t('payouts.status'),
      dataIndex: 'status',
      width: 100,
      valueType: 'select',
      valueEnum: {
        pending: { text: t('merchantPayouts.statusPending'), status: 'Processing' },
        approved: { text: t('merchantPayouts.statusApproved'), status: 'Default' },
        processing: { text: t('merchantPayouts.statusProcessing'), status: 'Warning' },
        completed: { text: t('merchantPayouts.statusCompleted'), status: 'Success' },
        rejected: { text: t('merchantPayouts.statusRejected'), status: 'Error' },
        failed: { text: t('merchantPayouts.statusFailed'), status: 'Default' },
      },
      render: (_, record) => (
        <Tag color={PAYOUT_STATUS_COLORS[record.status]}>
          {t(PAYOUT_STATUS_LABELS[record.status])}
        </Tag>
      ),
    },
    {
      title: t('payouts.bankName'),
      dataIndex: 'bankName',
      width: 120,
      search: false,
    },
    {
      title: t('payouts.accountNumber'),
      dataIndex: 'maskedAccountNumber',
      width: 150,
      search: false,
    },
    {
      title: t('common.createdAt'),
      dataIndex: 'createdAt',
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
          onClick={() => handleViewDetail(record.id)}
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
              title={t('merchantPayouts.availableBalance')}
              value={parseFloat(summary?.availableBalance || '0')}
              precision={2}
              prefix="¥"
              valueStyle={{ color: '#52c41a' }}
            />
          </Card>
        </Col>
        <Col xs={24} sm={12}>
          <Card>
            <Statistic
              title={t('merchantPayouts.processingAmount')}
              value={parseFloat(summary?.processingAmount || '0')}
              precision={2}
              prefix="¥"
              valueStyle={{ color: '#faad14' }}
            />
          </Card>
        </Col>
      </Row>

      {/* Table */}
      <ProTable<Payout>
        headerTitle={t('merchantPayouts.title')}
        actionRef={actionRef}
        rowKey="id"
        columns={columns}
        request={async (params) => {
          const result = await merchantPayoutApi.getPayouts({
            page: params.current,
            limit: params.pageSize,
            payoutNo: params.payoutNo,
            status: params.status as PayoutStatus | undefined,
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
        toolBarRender={() => [
          <Button key="create" type="primary" icon={<PlusOutlined />} onClick={handleCreate}>
            {t('merchantPayouts.requestPayout')}
          </Button>,
        ]}
        scroll={{ x: 1200 }}
      />

      {/* Create Payout Modal */}
      <CreatePayoutModal
        open={createModalOpen}
        summary={summary}
        onCancel={() => setCreateModalOpen(false)}
        onSuccess={handleCreateSuccess}
      />

      {/* Payout Detail Modal */}
      <PayoutDetailModal
        open={detailModalOpen}
        payoutId={selectedPayoutId}
        onClose={() => setDetailModalOpen(false)}
      />
    </div>
  );
}
