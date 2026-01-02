import { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ProTable, type ActionType, type ProColumns } from '@ant-design/pro-components';
import { Button, Tag, App, Space, Tooltip, Avatar } from 'antd';
import {
  StopOutlined,
  PlayCircleOutlined,
  CloseCircleOutlined,
  ShopOutlined,
} from '@ant-design/icons';

import {
  merchantChannelApi,
  type MyMerchantChannel,
} from '@/lib/merchant-channel-api';

const statusColorMap: Record<string, string> = {
  pending: 'processing',
  active: 'success',
  suspended: 'warning',
  disabled: 'default',
};

interface Props {
  actionRef?: React.MutableRefObject<ActionType | null>;
}

export function MyChannelsTab({ actionRef: externalRef }: Props) {
  const { t } = useTranslation();
  const internalRef = useRef<ActionType>(null);
  const actionRef = externalRef || internalRef;
  const { message, modal } = App.useApp();

  const [actionLoading, setActionLoading] = useState<string | null>(null);

  const handleCancelApplication = async (mc: MyMerchantChannel) => {
    modal.confirm({
      title: t('myChannels.confirmCancel'),
      content: t('myChannels.confirmCancelDesc', { name: mc.salesChannel.name }),
      okText: t('common.confirm'),
      cancelText: t('common.cancel'),
      okButtonProps: { danger: true },
      onOk: async () => {
        setActionLoading(mc.id);
        try {
          await merchantChannelApi.cancelOrDisable(mc.id);
          message.success(t('myChannels.applicationCancelled'));
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

  const handleDisable = async (mc: MyMerchantChannel) => {
    modal.confirm({
      title: t('myChannels.confirmDisable'),
      content: t('myChannels.confirmDisableDesc', { name: mc.salesChannel.name }),
      okText: t('common.confirm'),
      cancelText: t('common.cancel'),
      okButtonProps: { danger: true },
      onOk: async () => {
        setActionLoading(mc.id);
        try {
          await merchantChannelApi.cancelOrDisable(mc.id);
          message.success(t('myChannels.channelDisabled'));
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

  const handleEnable = async (mc: MyMerchantChannel) => {
    setActionLoading(mc.id);
    try {
      await merchantChannelApi.enableChannel(mc.id);
      message.success(t('myChannels.channelEnabled'));
      actionRef.current?.reload();
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setActionLoading(null);
    }
  };

  const columns: ProColumns<MyMerchantChannel>[] = [
    {
      title: t('myChannels.channel'),
      dataIndex: ['salesChannel', 'name'],
      width: 200,
      search: false,
      render: (_, record) => (
        <Space size="small">
          {record.salesChannel.logoUrl ? (
            <Avatar src={record.salesChannel.logoUrl} size={32} shape="square" />
          ) : (
            <Avatar icon={<ShopOutlined />} size={32} shape="square" />
          )}
          <div>
            <div>{record.salesChannel.name}</div>
            <code className="text-xs text-gray-400">
              {record.salesChannel.code}
            </code>
          </div>
        </Space>
      ),
    },
    {
      title: t('myChannels.status'),
      dataIndex: 'status',
      width: 120,
      valueType: 'select',
      valueEnum: {
        pending: { text: t('myChannels.statusPending'), status: 'Processing' },
        active: { text: t('myChannels.statusActive'), status: 'Success' },
        suspended: { text: t('myChannels.statusSuspended'), status: 'Warning' },
        disabled: { text: t('myChannels.statusDisabled'), status: 'Default' },
      },
      render: (_, record) => (
        <Tag color={statusColorMap[record.status]}>
          {t(`myChannels.status${record.status?.charAt(0).toUpperCase() + record.status?.slice(1)}`)}
        </Tag>
      ),
    },
    {
      title: t('merchantChannels.fulfillmentType'),
      dataIndex: 'fulfillmentType',
      width: 140,
      search: false,
      render: (_, record) => {
        if (!record.fulfillmentType) return '-';
        return (
          <div>
            <div>
              {record.fulfillmentType === 'consignment'
                ? t('merchantChannels.fulfillmentConsignment')
                : t('merchantChannels.fulfillmentSelfFulfillment')}
            </div>
            <div className="text-xs text-gray-400">
              {record.fulfillmentType === 'consignment'
                ? t('merchantChannels.fulfillmentConsignmentDesc')
                : t('merchantChannels.fulfillmentSelfFulfillmentDesc')}
            </div>
          </div>
        );
      },
    },
    {
      title: t('merchantChannels.pricingModel'),
      dataIndex: 'pricingModel',
      width: 120,
      search: false,
      render: (_, record) => {
        if (!record.pricingModel) return '-';
        return (
          <Tag color={record.pricingModel === 'self_pricing' ? 'blue' : 'green'}>
            {record.pricingModel === 'self_pricing'
              ? t('merchantChannels.pricingSelf')
              : t('merchantChannels.pricingPlatformManaged')}
          </Tag>
        );
      },
    },
    {
      title: t('myChannels.remark'),
      dataIndex: 'remark',
      width: 200,
      search: false,
      ellipsis: true,
      render: (_, record) => record.remark || '-',
    },
    {
      title: t('myChannels.appliedAt'),
      dataIndex: 'createdAt',
      width: 160,
      search: false,
      render: (_, record) => new Date(record.createdAt).toLocaleString(),
    },
    {
      title: t('myChannels.approvedAt'),
      dataIndex: 'approvedAt',
      width: 160,
      search: false,
      render: (_, record) =>
        record.approvedAt ? new Date(record.approvedAt).toLocaleString() : '-',
    },
    {
      title: t('common.actions'),
      valueType: 'option',
      width: 120,
      fixed: 'right',
      render: (_, record) => {
        const isLoading = actionLoading === record.id;
        const actions = [];

        if (record.status === 'pending') {
          actions.push(
            <Tooltip key="cancel" title={t('myChannels.cancelApplication')}>
              <Button
                type="text"
                size="small"
                danger
                icon={<CloseCircleOutlined />}
                loading={isLoading}
                onClick={() => handleCancelApplication(record)}
              />
            </Tooltip>
          );
        }

        if (record.status === 'active') {
          actions.push(
            <Tooltip key="disable" title={t('myChannels.disable')}>
              <Button
                type="text"
                size="small"
                danger
                icon={<StopOutlined />}
                loading={isLoading}
                onClick={() => handleDisable(record)}
              />
            </Tooltip>
          );
        }

        if (record.status === 'disabled') {
          actions.push(
            <Tooltip key="enable" title={t('myChannels.enable')}>
              <Button
                type="text"
                size="small"
                icon={<PlayCircleOutlined />}
                loading={isLoading}
                onClick={() => handleEnable(record)}
                style={{ color: '#52c41a' }}
              />
            </Tooltip>
          );
        }

        if (record.status === 'suspended') {
          // 被管理员暂停，无法操作
          actions.push(
            <Tag key="suspended-info" color="warning">
              {t('myChannels.suspendedByAdmin')}
            </Tag>
          );
        }

        return <Space size="small">{actions}</Space>;
      },
    },
  ];

  return (
    <ProTable<MyMerchantChannel>
      actionRef={actionRef}
      columns={columns}
      rowKey="id"
      scroll={{ x: 1400 }}
      request={async (params) => {
        try {
          const result = await merchantChannelApi.getMyChannels({
            page: params.current,
            limit: params.pageSize,
            status: params.status,
          });
          return {
            data: result.data,
            success: true,
            total: result.total,
          };
        } catch {
          return {
            data: [],
            success: false,
            total: 0,
          };
        }
      }}
      search={{
        labelWidth: 'auto',
        defaultCollapsed: true,
      }}
      options={{
        density: true,
        reload: true,
      }}
      pagination={{
        defaultPageSize: 10,
        showSizeChanger: true,
      }}
    />
  );
}
