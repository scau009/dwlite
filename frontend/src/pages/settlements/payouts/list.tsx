import { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';
import { Tag, Button, Space, App } from 'antd';
import { EyeOutlined, CheckOutlined, CloseOutlined } from '@ant-design/icons';
import type { ActionType, ProColumns } from '@ant-design/pro-components';
import { ProTable } from '@ant-design/pro-components';

import {
  settlementApi,
  type Payout,
  type PayoutStatus,
  PAYOUT_STATUS_LABELS,
  PAYOUT_STATUS_COLORS,
} from '@/lib/settlement-api';
import { ApproveModal } from './components/approve-modal';
import { RejectModal } from './components/reject-modal';

export function PayoutsListPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { message } = App.useApp();
  const actionRef = useRef<ActionType>(null);

  const [selectedPayout, setSelectedPayout] = useState<Payout | null>(null);
  const [approveModalOpen, setApproveModalOpen] = useState(false);
  const [rejectModalOpen, setRejectModalOpen] = useState(false);

  const statusValueEnum: Record<PayoutStatus, { text: string; status: string }> = {
    pending: { text: t(PAYOUT_STATUS_LABELS.pending), status: 'Processing' },
    approved: { text: t(PAYOUT_STATUS_LABELS.approved), status: 'Default' },
    processing: { text: t(PAYOUT_STATUS_LABELS.processing), status: 'Warning' },
    completed: { text: t(PAYOUT_STATUS_LABELS.completed), status: 'Success' },
    rejected: { text: t(PAYOUT_STATUS_LABELS.rejected), status: 'Error' },
    failed: { text: t(PAYOUT_STATUS_LABELS.failed), status: 'Default' },
  };

  const handleApprove = (payout: Payout) => {
    setSelectedPayout(payout);
    setApproveModalOpen(true);
  };

  const handleReject = (payout: Payout) => {
    setSelectedPayout(payout);
    setRejectModalOpen(true);
  };

  const handleApproveSuccess = () => {
    setApproveModalOpen(false);
    setSelectedPayout(null);
    message.success(t('payouts.approveSuccess'));
    actionRef.current?.reload();
  };

  const handleRejectSuccess = () => {
    setRejectModalOpen(false);
    setSelectedPayout(null);
    message.success(t('payouts.rejectSuccess'));
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
      title: t('payouts.merchant'),
      dataIndex: 'merchantName',
      width: 150,
      search: false,
    },
    {
      title: t('payouts.amount'),
      dataIndex: 'amount',
      width: 120,
      search: false,
      align: 'right',
      render: (_, record) => `¥${parseFloat(record.amount).toFixed(2)}`,
    },
    {
      title: t('payouts.fee'),
      dataIndex: 'fee',
      width: 100,
      search: false,
      align: 'right',
      render: (_, record) => `¥${parseFloat(record.fee).toFixed(2)}`,
    },
    {
      title: t('payouts.actualAmount'),
      dataIndex: 'actualAmount',
      width: 120,
      search: false,
      align: 'right',
      render: (_, record) => (
        <span className="text-green-600 font-medium">
          ¥{parseFloat(record.actualAmount).toFixed(2)}
        </span>
      ),
    },
    {
      title: t('payouts.status'),
      dataIndex: 'status',
      width: 100,
      valueType: 'select',
      valueEnum: statusValueEnum,
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
      width: 160,
      search: false,
    },
    {
      title: t('payouts.accountHolder'),
      dataIndex: 'accountHolder',
      width: 100,
      search: false,
    },
    {
      title: t('common.createdAt'),
      dataIndex: 'createdAt',
      width: 160,
      valueType: 'dateRange',
      search: {
        transform: (value) => ({
          createdAtFrom: value?.[0],
          createdAtTo: value?.[1],
        }),
      },
      render: (_, record) => new Date(record.createdAt).toLocaleString(),
    },
    {
      title: t('common.actions'),
      valueType: 'option',
      width: 180,
      fixed: 'right',
      render: (_, record) => (
        <Space>
          {record.status === 'pending' && (
            <>
              <Button
                type="link"
                size="small"
                icon={<CheckOutlined />}
                onClick={() => handleApprove(record)}
                className="text-green-600"
              >
                {t('payouts.approve')}
              </Button>
              <Button
                type="link"
                size="small"
                icon={<CloseOutlined />}
                onClick={() => handleReject(record)}
                danger
              >
                {t('payouts.reject')}
              </Button>
            </>
          )}
          <Button
            type="link"
            size="small"
            icon={<EyeOutlined />}
            onClick={() => navigate(`/settlements/payouts/${record.id}`)}
          >
            {t('common.view')}
          </Button>
        </Space>
      ),
    },
  ];

  return (
    <>
      <ProTable<Payout>
        headerTitle={t('payouts.title')}
        actionRef={actionRef}
        rowKey="id"
        columns={columns}
        request={async (params) => {
          const result = await settlementApi.getPayouts({
            page: params.current,
            limit: params.pageSize,
            payoutNo: params.payoutNo,
            status: params.status,
            createdAtFrom: params.createdAtFrom,
            createdAtTo: params.createdAtTo,
          });
          return {
            data: result.data,
            success: true,
            total: result.total,
          };
        }}
        search={{
          labelWidth: 'auto',
          defaultCollapsed: false,
        }}
        options={{
          density: true,
          fullScreen: true,
          reload: true,
        }}
        pagination={{
          defaultPageSize: 20,
          showSizeChanger: true,
        }}
        scroll={{ x: 1600 }}
      />

      <ApproveModal
        open={approveModalOpen}
        payout={selectedPayout}
        onClose={() => {
          setApproveModalOpen(false);
          setSelectedPayout(null);
        }}
        onSuccess={handleApproveSuccess}
      />

      <RejectModal
        open={rejectModalOpen}
        payout={selectedPayout}
        onClose={() => {
          setRejectModalOpen(false);
          setSelectedPayout(null);
        }}
        onSuccess={handleRejectSuccess}
      />
    </>
  );
}
