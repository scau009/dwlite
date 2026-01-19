import { useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';
import { Tag, Button } from 'antd';
import { EyeOutlined } from '@ant-design/icons';
import type { ActionType, ProColumns } from '@ant-design/pro-components';
import { ProTable } from '@ant-design/pro-components';

import {
  settlementApi,
  type Settlement,
  type SettlementStatus,
  SETTLEMENT_STATUS_LABELS,
  SETTLEMENT_STATUS_COLORS,
} from '@/lib/settlement-api';
import { getCurrencySymbol } from '@/lib/merchant-listing-api';

export function SettlementsListPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const actionRef = useRef<ActionType>(null);

  const statusValueEnum: Record<SettlementStatus, { text: string; status: string }> = {
    pending: { text: t(SETTLEMENT_STATUS_LABELS.pending), status: 'Processing' },
    settled: { text: t(SETTLEMENT_STATUS_LABELS.settled), status: 'Success' },
    cancelled: { text: t(SETTLEMENT_STATUS_LABELS.cancelled), status: 'Error' },
  };

  const columns: ProColumns<Settlement>[] = [
    {
      title: t('settlements.settlementNo'),
      dataIndex: 'settlementNo',
      width: 180,
      copyable: true,
    },
    {
      title: t('settlements.merchant'),
      dataIndex: 'merchantName',
      width: 150,
      search: false,
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
      align: 'right',
      render: (_, record) => `${getCurrencySymbol(record.currency)}${parseFloat(record.grossAmount).toFixed(2)}`,
    },
    {
      title: t('settlements.commissionRate'),
      dataIndex: 'commissionRate',
      width: 100,
      search: false,
      align: 'right',
      render: (_, record) => `${record.commissionRate}%`,
    },
    {
      title: t('settlements.commissionAmount'),
      dataIndex: 'commissionAmount',
      width: 100,
      search: false,
      align: 'right',
      render: (_, record) => `${getCurrencySymbol(record.currency)}${parseFloat(record.commissionAmount).toFixed(2)}`,
    },
    {
      title: t('settlements.netAmount'),
      dataIndex: 'netAmount',
      width: 120,
      search: false,
      align: 'right',
      render: (_, record) => (
        <span className="text-green-600 font-medium">
          {getCurrencySymbol(record.currency)}{parseFloat(record.netAmount).toFixed(2)}
        </span>
      ),
    },
    {
      title: t('settlements.status'),
      dataIndex: 'status',
      width: 100,
      valueType: 'select',
      valueEnum: statusValueEnum,
      render: (_, record) => (
        <Tag color={SETTLEMENT_STATUS_COLORS[record.status]}>
          {t(SETTLEMENT_STATUS_LABELS[record.status])}
        </Tag>
      ),
    },
    {
      title: t('settlements.scheduledSettleAt'),
      dataIndex: 'scheduledSettleAt',
      width: 160,
      valueType: 'dateRange',
      search: {
        transform: (value) => ({
          scheduledSettleAtFrom: value?.[0],
          scheduledSettleAtTo: value?.[1],
        }),
      },
      render: (_, record) => new Date(record.scheduledSettleAt).toLocaleString(),
    },
    {
      title: t('settlements.settledAt'),
      dataIndex: 'settledAt',
      width: 160,
      search: false,
      render: (_, record) =>
        record.settledAt ? new Date(record.settledAt).toLocaleString() : '-',
    },
    {
      title: t('common.createdAt'),
      dataIndex: 'createdAt',
      width: 160,
      search: false,
      render: (_, record) => new Date(record.createdAt).toLocaleString(),
    },
    {
      title: t('common.actions'),
      valueType: 'option',
      width: 80,
      fixed: 'right',
      render: (_, record) => [
        <Button
          key="view"
          type="link"
          size="small"
          icon={<EyeOutlined />}
          onClick={() => navigate(`/settlements/detail/${record.id}`)}
        >
          {t('common.view')}
        </Button>,
      ],
    },
  ];

  return (
    <ProTable<Settlement>
      headerTitle={t('settlements.title')}
      actionRef={actionRef}
      rowKey="id"
      columns={columns}
      request={async (params) => {
        const result = await settlementApi.getSettlements({
          page: params.current,
          limit: params.pageSize,
          settlementNo: params.settlementNo,
          status: params.status,
          scheduledSettleAtFrom: params.scheduledSettleAtFrom,
          scheduledSettleAtTo: params.scheduledSettleAtTo,
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
      scroll={{ x: 1800 }}
    />
  );
}
