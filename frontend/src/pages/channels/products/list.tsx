import { useRef, useState, useEffect } from 'react';
import { useNavigate } from 'react-router';
import { useTranslation } from 'react-i18next';
import { ProTable, type ActionType, type ProColumns } from '@ant-design/pro-components';
import { Tag, App, Space, Button, Tooltip, Typography, Image, Avatar } from 'antd';
import { SyncOutlined, PlayCircleOutlined, PauseCircleOutlined, LinkOutlined, ExclamationCircleOutlined, ShopOutlined } from '@ant-design/icons';

import {
  channelProductApi,
  type ChannelProduct,
  type ChannelProductStatus,
  type ChannelProductSyncStatus,
} from '@/lib/channel-product-api';
import { channelApi, type SalesChannel } from '@/lib/channel-api';

const { Text } = Typography;

const statusColorMap: Record<ChannelProductStatus, string> = {
  draft: 'default',
  pending: 'processing',
  active: 'success',
  paused: 'warning',
  rejected: 'error',
};

const syncStatusColorMap: Record<ChannelProductSyncStatus, string> = {
  pending: 'processing',
  syncing: 'processing',
  synced: 'success',
  failed: 'error',
};

export function ChannelProductsListPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const actionRef = useRef<ActionType>(null);
  const { message } = App.useApp();

  const [actionLoading, setActionLoading] = useState<string | null>(null);
  const [channels, setChannels] = useState<SalesChannel[]>([]);

  // Load sales channels for filter
  useEffect(() => {
    const loadChannels = async () => {
      try {
        const result = await channelApi.getChannels({ limit: 100 });
        setChannels(result.data);
      } catch (error) {
        console.error('Failed to load channels:', error);
      }
    };
    loadChannels();
  }, []);

  const handleActivate = async (record: ChannelProduct) => {
    setActionLoading(record.id);
    try {
      await channelProductApi.activateChannelProduct(record.id);
      message.success(t('channelProducts.activated'));
      actionRef.current?.reload();
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setActionLoading(null);
    }
  };

  const handlePause = async (record: ChannelProduct) => {
    setActionLoading(record.id);
    try {
      await channelProductApi.pauseChannelProduct(record.id);
      message.success(t('channelProducts.paused'));
      actionRef.current?.reload();
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setActionLoading(null);
    }
  };

  const handleSync = async (record: ChannelProduct) => {
    setActionLoading(record.id);
    try {
      const result = await channelProductApi.triggerSync(record.id);
      if (result.correctedSources > 0) {
        message.success(t('channelProducts.syncTriggeredWithCorrection', { count: result.correctedSources }));
      } else {
        message.success(t('channelProducts.syncTriggered'));
      }
      actionRef.current?.reload();
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setActionLoading(null);
    }
  };

  const columns: ProColumns<ChannelProduct>[] = [
    {
      title: t('channelProducts.product'),
      dataIndex: 'search',
      width: 200,
      fieldProps: {
        placeholder: t('channelProducts.searchPlaceholder'),
      },
      render: (_, record) => (
        <div
          className="flex items-center gap-3 cursor-pointer hover:text-blue-500"
          onClick={() => navigate(`/channels/products/${record.id}`)}
        >
          {record.productSku.imageUrl ? (
            <div className="w-14 h-14 flex items-center justify-center bg-gray-100 rounded flex-shrink-0">
              <Image
                src={record.productSku.imageUrl}
                style={{ maxWidth: 56, maxHeight: 56, objectFit: 'contain' }}
                preview={false}
              />
            </div>
          ) : (
            <Avatar shape="square" size={56} icon={<ShopOutlined />} className="flex-shrink-0" />
          )}
          <code className="text-xs bg-gray-100 px-2 py-1 rounded">
            {record.productSku.styleNumber || record.productSku.skuCode}
          </code>
        </div>
      ),
    },
    {
      title: t('channelProducts.size'),
      dataIndex: 'size',
      width: 100,
      search: false,
      render: (_, record) => (
        <span className="text-sm">
          {record.productSku.sizeUnit && record.productSku.sizeValue
            ? `${record.productSku.sizeUnit} ${record.productSku.sizeValue}`
            : '-'}
        </span>
      ),
    },
    {
      title: t('channelProducts.salesChannel'),
      dataIndex: 'salesChannelId',
      width: 150,
      valueType: 'select',
      fieldProps: {
        options: channels.map((c) => ({ label: c.name, value: c.id })),
        placeholder: t('common.selectPlaceholder'),
      },
      render: (_, record) => (
        <Tag>{record.salesChannel.name}</Tag>
      ),
    },
    {
      title: t('channelProducts.platformPrice'),
      dataIndex: 'platformPrice',
      width: 120,
      search: false,
      render: (_, record) => (
        <Text strong>${record.platformPrice}</Text>
      ),
    },
    {
      title: t('channelProducts.stockQuantity'),
      dataIndex: 'stockQuantity',
      width: 100,
      search: false,
      render: (_, record) => (
        <Text type={record.stockQuantity === 0 ? 'danger' : undefined}>
          {record.stockQuantity}
        </Text>
      ),
    },
    {
      title: t('channelProducts.status'),
      dataIndex: 'status',
      width: 100,
      valueType: 'select',
      valueEnum: {
        draft: { text: t('channelProducts.statusDraft') },
        pending: { text: t('channelProducts.statusPending') },
        active: { text: t('channelProducts.statusActive') },
        paused: { text: t('channelProducts.statusPaused') },
        rejected: { text: t('channelProducts.statusRejected') },
      },
      render: (_, record) => (
        <Tag color={statusColorMap[record.status]}>
          {t(`channelProducts.status${record.status.charAt(0).toUpperCase() + record.status.slice(1)}`)}
        </Tag>
      ),
    },
    {
      title: t('channelProducts.syncStatus'),
      dataIndex: 'syncStatus',
      width: 100,
      valueType: 'select',
      valueEnum: {
        pending: { text: t('channelProducts.syncPending') },
        synced: { text: t('channelProducts.syncSynced') },
        failed: { text: t('channelProducts.syncFailed') },
      },
      render: (_, record) => (
        <Tooltip title={record.syncError || undefined}>
          <Space size={4}>
            <Tag color={syncStatusColorMap[record.syncStatus]}>
              {t(`channelProducts.sync${record.syncStatus.charAt(0).toUpperCase() + record.syncStatus.slice(1)}`)}
            </Tag>
            {record.syncStatus === 'failed' && record.syncError && (
              <ExclamationCircleOutlined className="text-red-500" />
            )}
          </Space>
        </Tooltip>
      ),
    },
    {
      title: t('channelProducts.externalId'),
      dataIndex: 'externalId',
      width: 150,
      search: false,
      ellipsis: true,
      render: (_, record) => (
        record.externalId ? (
          record.externalUrl ? (
            <a href={record.externalUrl} target="_blank" rel="noopener noreferrer">
              {record.externalId} <LinkOutlined />
            </a>
          ) : (
            <Text copyable>{record.externalId}</Text>
          )
        ) : (
          <Text type="secondary">-</Text>
        )
      ),
    },
    {
      title: t('channelProducts.lastSyncedAt'),
      dataIndex: 'lastSyncedAt',
      width: 160,
      search: false,
      render: (_, record) => (
        record.lastSyncedAt ? new Date(record.lastSyncedAt).toLocaleString() : '-'
      ),
    },
    {
      title: t('common.actions'),
      valueType: 'option',
      width: 180,
      fixed: 'right',
      render: (_, record) => {
        const isLoading = actionLoading === record.id;
        return (
          <Space size="small">
            {record.status !== 'active' && (
              <Tooltip title={t('channelProducts.activate')}>
                <Button
                  type="link"
                  size="small"
                  icon={<PlayCircleOutlined />}
                  loading={isLoading}
                  onClick={() => handleActivate(record)}
                >
                  {t('channelProducts.activate')}
                </Button>
              </Tooltip>
            )}
            {record.status === 'active' && (
              <Tooltip title={t('channelProducts.pause')}>
                <Button
                  type="link"
                  size="small"
                  icon={<PauseCircleOutlined />}
                  loading={isLoading}
                  onClick={() => handlePause(record)}
                >
                  {t('channelProducts.pause')}
                </Button>
              </Tooltip>
            )}
            <Tooltip title={t('channelProducts.triggerSync')}>
              <Button
                type="link"
                size="small"
                icon={<SyncOutlined />}
                loading={isLoading}
                onClick={() => handleSync(record)}
              >
                {t('channelProducts.sync')}
              </Button>
            </Tooltip>
          </Space>
        );
      },
    },
  ];

  return (
    <div className="space-y-4">
      <ProTable<ChannelProduct>
        actionRef={actionRef}
        columns={columns}
        rowKey="id"
        request={async (params) => {
          try {
            const result = await channelProductApi.getChannelProducts({
              page: params.current,
              limit: params.pageSize,
              salesChannelId: params.salesChannelId,
              status: params.status,
              syncStatus: params.syncStatus,
              search: params.search,
            });
            return {
              data: result.data,
              success: true,
              total: result.total,
            };
          } catch (error) {
            console.error('Failed to fetch channel products:', error);
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
          span: 6,
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
        scroll={{ x: 1400 }}
      />
    </div>
  );
}
