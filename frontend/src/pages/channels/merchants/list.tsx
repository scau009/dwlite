import { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ProTable, type ActionType, type ProColumns } from '@ant-design/pro-components';
import { Button, App, Space, Tag } from 'antd';

import { channelApi, type MerchantChannel } from '@/lib/channel-api';
import { SuspendModal } from './components/suspend-modal';
import { RejectModal } from './components/reject-modal';
import { MerchantChannelDetailModal } from './components/detail-modal';

export function MerchantChannelsListPage() {
  const { t } = useTranslation();
  const actionRef = useRef<ActionType>(null);
  const { message, modal } = App.useApp();

  const [actionLoading, setActionLoading] = useState<string | null>(null);

  const [suspendModalOpen, setSuspendModalOpen] = useState(false);
  const [suspendingChannel, setSuspendingChannel] = useState<MerchantChannel | null>(null);

  const [rejectModalOpen, setRejectModalOpen] = useState(false);
  const [rejectingChannel, setRejectingChannel] = useState<MerchantChannel | null>(null);

  const [detailModalOpen, setDetailModalOpen] = useState(false);
  const [viewingChannel, setViewingChannel] = useState<MerchantChannel | null>(null);

  const handleApprove = async (mc: MerchantChannel) => {
    modal.confirm({
      title: t('merchantChannels.confirmApprove'),
      content: t('merchantChannels.confirmApproveDesc', {
        merchant: mc.merchant.name,
        channel: mc.salesChannel.name,
      }),
      okText: t('common.confirm'),
      cancelText: t('common.cancel'),
      onOk: async () => {
        setActionLoading(mc.id);
        try {
          await channelApi.approveChannel(mc.id);
          message.success(t('merchantChannels.approved'));
          actionRef.current?.reload();
        } catch (error) {
          const err = error as { error?: string };
          message.error(err.error || t('common.error'));
        } finally {
          setActionLoading(null);
        }
      },
    });
  };

  const handleReject = (mc: MerchantChannel) => {
    setRejectingChannel(mc);
    setRejectModalOpen(true);
  };

  const handleSuspend = (mc: MerchantChannel) => {
    setSuspendingChannel(mc);
    setSuspendModalOpen(true);
  };

  const handleEnable = async (mc: MerchantChannel) => {
    modal.confirm({
      title: t('merchantChannels.confirmEnable'),
      content: t('merchantChannels.confirmEnableDesc', {
        merchant: mc.merchant.name,
        channel: mc.salesChannel.name,
      }),
      okText: t('common.confirm'),
      cancelText: t('common.cancel'),
      onOk: async () => {
        setActionLoading(mc.id);
        try {
          await channelApi.enableChannel(mc.id);
          message.success(t('merchantChannels.enabled'));
          actionRef.current?.reload();
        } catch (error) {
          const err = error as { error?: string };
          message.error(err.error || t('common.error'));
        } finally {
          setActionLoading(null);
        }
      },
    });
  };

  const handleViewDetail = (mc: MerchantChannel) => {
    setViewingChannel(mc);
    setDetailModalOpen(true);
  };

  const getStatusColor = (status: string) => {
    const colors: Record<string, string> = {
      pending: 'warning',
      active: 'success',
      rejected: 'error',
      suspended: 'default',
      disabled: 'default',
    };
    return colors[status] || 'default';
  };

  const columns: ProColumns<MerchantChannel>[] = [
    {
      title: t('merchantChannels.merchant'),
      dataIndex: ['merchant', 'name'],
      width: 180,
      ellipsis: true,
      search: false,
    },
    {
      title: t('merchantChannels.channel'),
      dataIndex: ['salesChannel', 'name'],
      width: 180,
      search: false,
      render: (_, record) => (
        <Space size="small">
          {record.salesChannel.logoUrl && (
            <img
              src={record.salesChannel.logoUrl}
              alt={record.salesChannel.name}
              className="w-5 h-5 rounded"
            />
          )}
          <span>{record.salesChannel.name}</span>
          <code className="text-xs bg-gray-100 px-1 rounded">{record.salesChannel.code}</code>
        </Space>
      ),
    },
    {
      title: t('merchantChannels.status'),
      dataIndex: 'status',
      width: 120,
      valueType: 'select',
      valueEnum: {
        pending: { text: t('merchantChannels.statusPending') },
        active: { text: t('merchantChannels.statusActive') },
        rejected: { text: t('merchantChannels.statusRejected') },
        suspended: { text: t('merchantChannels.statusSuspended') },
        disabled: { text: t('merchantChannels.statusDisabled') },
      },
      render: (_, record) => (
        <Tag color={getStatusColor(record.status)}>
          {t(`merchantChannels.status${record.status.charAt(0).toUpperCase() + record.status.slice(1)}`)}
        </Tag>
      ),
    },
    {
      title: t('merchantChannels.pricingModel'),
      dataIndex: 'pricingModel',
      width: 120,
      valueType: 'select',
      valueEnum: {
        self_pricing: { text: t('merchantChannels.pricingSelf') },
        platform_managed: { text: t('merchantChannels.pricingPlatformManaged') },
      },
    },
    {
      title: t('merchantChannels.fulfillmentType'),
      dataIndex: 'fulfillmentType',
      width: 120,
      valueType: 'select',
      valueEnum: {
        consignment: { text: t('merchantChannels.fulfillmentConsignment') },
        self_fulfillment: { text: t('merchantChannels.fulfillmentSelfFulfillment') },
      },
    },
    {
      title: t('merchantChannels.remark'),
      dataIndex: 'remark',
      width: 200,
      search: false,
      ellipsis: true,
      render: (_, record) => record.remark || '-',
    },
    {
      title: t('merchantChannels.approvedAt'),
      dataIndex: 'approvedAt',
      width: 160,
      search: false,
      render: (_, record) => record.approvedAt ? new Date(record.approvedAt).toLocaleString() : '-',
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
      width: 220,
      fixed: 'right',
      render: (_, record) => {
        const isLoading = actionLoading === record.id;
        const actions = [];

        actions.push(
          <Button
            key="view"
            type="link"
            size="small"
            onClick={() => handleViewDetail(record)}
          >
            {t('common.view')}
          </Button>
        );

        if (record.status === 'pending') {
          actions.push(
            <Button
              key="approve"
              type="link"
              size="small"
              loading={isLoading}
              onClick={() => handleApprove(record)}
              style={{ color: '#52c41a' }}
            >
              {t('merchantChannels.approve')}
            </Button>,
            <Button
              key="reject"
              type="link"
              size="small"
              danger
              loading={isLoading}
              onClick={() => handleReject(record)}
            >
              {t('merchantChannels.reject')}
            </Button>
          );
        }

        if (record.status === 'active') {
          actions.push(
            <Button
              key="suspend"
              type="link"
              size="small"
              loading={isLoading}
              onClick={() => handleSuspend(record)}
              style={{ color: '#faad14' }}
            >
              {t('merchantChannels.suspend')}
            </Button>
          );
        }

        if (record.status === 'suspended' || record.status === 'disabled') {
          actions.push(
            <Button
              key="enable"
              type="link"
              size="small"
              loading={isLoading}
              onClick={() => handleEnable(record)}
              style={{ color: '#1890ff' }}
            >
              {t('merchantChannels.enable')}
            </Button>
          );
        }

        return <Space size="small">{actions}</Space>;
      },
    },
  ];

  return (
    <div className="space-y-4">
      <ProTable<MerchantChannel>
        actionRef={actionRef}
        columns={columns}
        rowKey="id"
        scroll={{ x: 1200 }}
        request={async (params) => {
          try {
            const result = await channelApi.getMerchantChannels({
              page: params.current,
              limit: params.pageSize,
              status: params.status,
            });
            return {
              data: result.data,
              success: true,
              total: result.total,
            };
          } catch (error) {
            console.error('Failed to fetch merchant channels:', error);
            return {
              data: [],
              success: false,
              total: 0,
            };
          }
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
      />

      <RejectModal
        open={rejectModalOpen}
        merchantChannel={rejectingChannel}
        onClose={() => {
          setRejectModalOpen(false);
          setRejectingChannel(null);
        }}
        onSuccess={() => {
          setRejectModalOpen(false);
          setRejectingChannel(null);
          actionRef.current?.reload();
        }}
      />

      <SuspendModal
        open={suspendModalOpen}
        merchantChannel={suspendingChannel}
        onClose={() => {
          setSuspendModalOpen(false);
          setSuspendingChannel(null);
        }}
        onSuccess={() => {
          setSuspendModalOpen(false);
          setSuspendingChannel(null);
          actionRef.current?.reload();
        }}
      />

      <MerchantChannelDetailModal
        open={detailModalOpen}
        merchantChannel={viewingChannel}
        onClose={() => {
          setDetailModalOpen(false);
          setViewingChannel(null);
        }}
      />
    </div>
  );
}
