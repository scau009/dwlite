import { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ProTable, type ActionType, type ProColumns } from '@ant-design/pro-components';
import { Button, Tag, App, Space, Tooltip, Badge, Card, Statistic } from 'antd';
import { CheckOutlined, PauseOutlined, PlayCircleOutlined, EyeOutlined } from '@ant-design/icons';

import { channelApi, type MerchantChannel } from '@/lib/channel-api';
import { SuspendModal } from './components/suspend-modal';
import { MerchantChannelDetailModal } from './components/detail-modal';

const statusColorMap: Record<string, string> = {
  pending: 'processing',
  active: 'success',
  suspended: 'warning',
  disabled: 'default',
};

export function MerchantChannelsListPage() {
  const { t } = useTranslation();
  const actionRef = useRef<ActionType>(null);
  const { message, modal } = App.useApp();

  const [pendingCount, setPendingCount] = useState(0);
  const [actionLoading, setActionLoading] = useState<string | null>(null);
  const [suspendModalOpen, setSuspendModalOpen] = useState(false);
  const [suspendingChannel, setSuspendingChannel] = useState<MerchantChannel | null>(null);
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
          loadPendingCount();
        } catch (error) {
          const err = error as { error?: string };
          message.error(err.error || t('common.error'));
        } finally {
          setActionLoading(null);
        }
      },
    });
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

  const loadPendingCount = async () => {
    try {
      const result = await channelApi.getPendingCount();
      setPendingCount(result.count);
    } catch (error) {
      console.error('Failed to load pending count:', error);
    }
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
      width: 150,
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
      width: 100,
      valueType: 'select',
      valueEnum: {
        pending: { text: t('merchantChannels.statusPending'), status: 'Processing' },
        active: { text: t('merchantChannels.statusActive'), status: 'Success' },
        suspended: { text: t('merchantChannels.statusSuspended'), status: 'Warning' },
        disabled: { text: t('merchantChannels.statusDisabled'), status: 'Default' },
      },
      render: (_, record) => (
        <Tag color={statusColorMap[record.status]}>
          {t(`merchantChannels.status${record.status.charAt(0).toUpperCase() + record.status.slice(1)}`)}
        </Tag>
      ),
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
      width: 150,
      fixed: 'right',
      render: (_, record) => {
        const isLoading = actionLoading === record.id;
        const actions = [];

        actions.push(
          <Tooltip key="view" title={t('common.view')}>
            <Button
              type="text"
              size="small"
              icon={<EyeOutlined />}
              onClick={() => handleViewDetail(record)}
            />
          </Tooltip>
        );

        if (record.status === 'pending') {
          actions.push(
            <Tooltip key="approve" title={t('merchantChannels.approve')}>
              <Button
                type="text"
                size="small"
                icon={<CheckOutlined />}
                loading={isLoading}
                onClick={() => handleApprove(record)}
                style={{ color: '#52c41a' }}
              />
            </Tooltip>
          );
        }

        if (record.status === 'active') {
          actions.push(
            <Tooltip key="suspend" title={t('merchantChannels.suspend')}>
              <Button
                type="text"
                size="small"
                icon={<PauseOutlined />}
                loading={isLoading}
                onClick={() => handleSuspend(record)}
                style={{ color: '#faad14' }}
              />
            </Tooltip>
          );
        }

        if (record.status === 'suspended' || record.status === 'disabled') {
          actions.push(
            <Tooltip key="enable" title={t('merchantChannels.enable')}>
              <Button
                type="text"
                size="small"
                icon={<PlayCircleOutlined />}
                loading={isLoading}
                onClick={() => handleEnable(record)}
                style={{ color: '#1890ff' }}
              />
            </Tooltip>
          );
        }

        return <Space size="small">{actions}</Space>;
      },
    },
  ];

  return (
    <div className="space-y-4">
      <div className="mb-4">
        <h1 className="text-xl font-semibold">{t('merchantChannels.title')}</h1>
        <p className="text-gray-500">{t('merchantChannels.description')}</p>
      </div>

      <Card className="mb-4">
        <Space size="large">
          <Statistic
            title={t('merchantChannels.pendingApprovals')}
            value={pendingCount}
            prefix={<Badge status="processing" />}
          />
        </Space>
      </Card>

      <ProTable<MerchantChannel>
        actionRef={actionRef}
        columns={columns}
        rowKey="id"
        request={async (params) => {
          try {
            const result = await channelApi.getMerchantChannels({
              page: params.current,
              limit: params.pageSize,
              status: params.status,
            });
            loadPendingCount();
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
