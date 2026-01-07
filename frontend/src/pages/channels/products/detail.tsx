import { useState, useEffect, useRef } from 'react';
import { useParams, useNavigate } from 'react-router';
import { useTranslation } from 'react-i18next';
import {
  Card,
  Button,
  Tag,
  Space,
  App,
  Spin,
  Empty,
  Descriptions,
  Alert,
} from 'antd';
import { ProTable, type ActionType, type ProColumns } from '@ant-design/pro-components';
import {
  ArrowLeftOutlined,
  SyncOutlined,
  PlayCircleOutlined,
  PauseCircleOutlined,
  LinkOutlined,
  ExclamationCircleOutlined,
} from '@ant-design/icons';
import {
  channelProductApi,
  type ChannelProductDetail,
  type ChannelProductStatus,
  type ChannelProductSyncStatus,
  type ChannelProductSyncLog,
  type ChannelProductSource,
  type SyncLogStatus,
  type SyncOperation,
  type SyncTriggerSource,
  type AllocationMode,
  type FulfillmentType,
} from '@/lib/channel-product-api';

// Status color mapping
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

const logStatusColorMap: Record<SyncLogStatus, string> = {
  pending: 'default',
  processing: 'processing',
  success: 'success',
  failed: 'error',
  skipped: 'warning',
};

const allocationModeColorMap: Record<AllocationMode, string> = {
  shared: 'blue',
  dedicated: 'purple',
};

const fulfillmentTypeColorMap: Record<FulfillmentType, string> = {
  consignment: 'cyan',
  self_fulfillment: 'orange',
};

export function ChannelProductDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { t } = useTranslation();
  const { message } = App.useApp();
  const actionRef = useRef<ActionType>(null);

  const [product, setProduct] = useState<ChannelProductDetail | null>(null);
  const [sources, setSources] = useState<ChannelProductSource[]>([]);
  const [loading, setLoading] = useState(true);
  const [sourcesLoading, setSourcesLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState(false);

  const loadProduct = async () => {
    if (!id) return;
    setLoading(true);
    try {
      const result = await channelProductApi.getChannelProduct(id);
      setProduct(result.data);
    } catch {
      message.error(t('common.error'));
    } finally {
      setLoading(false);
    }
  };

  const loadSources = async () => {
    if (!id) return;
    setSourcesLoading(true);
    try {
      const result = await channelProductApi.getSources(id);
      setSources(result.data);
    } catch {
      // Silently fail, sources are secondary
    } finally {
      setSourcesLoading(false);
    }
  };

  useEffect(() => {
    loadProduct();
    loadSources();
  }, [id]);

  const handleActivate = async () => {
    if (!id) return;
    setActionLoading(true);
    try {
      await channelProductApi.activateChannelProduct(id);
      message.success(t('channelProducts.activated'));
      loadProduct();
      actionRef.current?.reload();
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setActionLoading(false);
    }
  };

  const handlePause = async () => {
    if (!id) return;
    setActionLoading(true);
    try {
      await channelProductApi.pauseChannelProduct(id);
      message.success(t('channelProducts.paused'));
      loadProduct();
      actionRef.current?.reload();
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setActionLoading(false);
    }
  };

  const handleSync = async () => {
    if (!id) return;
    setActionLoading(true);
    try {
      const result = await channelProductApi.triggerSync(id);
      if (result.correctedSources > 0) {
        message.success(t('channelProducts.syncTriggeredWithCorrection', { count: result.correctedSources }));
      } else {
        message.success(t('channelProducts.syncTriggered'));
      }
      loadProduct();
      actionRef.current?.reload();
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setActionLoading(false);
    }
  };

  const getOperationLabel = (operation: SyncOperation): string => {
    const labels: Record<SyncOperation, string> = {
      aggregate: t('channelProducts.operationAggregate'),
      push_product: t('channelProducts.operationPushProduct'),
      update_stock_price: t('channelProducts.operationUpdateStockPrice'),
      delist: t('channelProducts.operationDelist'),
    };
    return labels[operation] || operation;
  };

  const getTriggerSourceLabel = (source: SyncTriggerSource): string => {
    const labels: Record<SyncTriggerSource, string> = {
      listing_create: t('channelProducts.triggerListingCreate'),
      listing_update: t('channelProducts.triggerListingUpdate'),
      listing_activate: t('channelProducts.triggerListingActivate'),
      listing_pause: t('channelProducts.triggerListingPause'),
      listing_delete: t('channelProducts.triggerListingDelete'),
      inventory_inbound: t('channelProducts.triggerInventoryInbound'),
      inventory_outbound: t('channelProducts.triggerInventoryOutbound'),
      inventory_adjust: t('channelProducts.triggerInventoryAdjust'),
      manual: t('channelProducts.triggerManual'),
      scheduled: t('channelProducts.triggerScheduled'),
      compensation: t('channelProducts.triggerCompensation'),
    };
    return labels[source] || source;
  };

  const getLogStatusLabel = (status: SyncLogStatus): string => {
    const labels: Record<SyncLogStatus, string> = {
      pending: t('channelProducts.logStatusPending'),
      processing: t('channelProducts.logStatusProcessing'),
      success: t('channelProducts.logStatusSuccess'),
      failed: t('channelProducts.logStatusFailed'),
      skipped: t('channelProducts.logStatusSkipped'),
    };
    return labels[status] || status;
  };

  const getAllocationModeLabel = (mode: AllocationMode): string => {
    const labels: Record<AllocationMode, string> = {
      shared: t('channelProducts.allocationShared'),
      dedicated: t('channelProducts.allocationDedicated'),
    };
    return labels[mode] || mode;
  };

  const getFulfillmentTypeLabel = (type: FulfillmentType): string => {
    const labels: Record<FulfillmentType, string> = {
      consignment: t('channelProducts.fulfillmentConsignment'),
      self_fulfillment: t('channelProducts.fulfillmentSelfFulfillment'),
    };
    return labels[type] || type;
  };

  const sourceColumns: ProColumns<ChannelProductSource>[] = [
    {
      title: t('channelProducts.sourceMerchant'),
      dataIndex: ['merchant', 'name'],
      width: 120,
    },
    {
      title: t('channelProducts.sourceWarehouse'),
      dataIndex: ['warehouse', 'name'],
      width: 100,
      render: (_, record) => (
        <span title={record.warehouse.name}>{record.warehouse.shortName || record.warehouse.name}</span>
      ),
    },
    {
      title: t('channelProducts.sourcePrice'),
      dataIndex: ['listing', 'price'],
      width: 100,
      render: (_, record) => <span className="font-medium">${record.listing.price}</span>,
    },
    {
      title: t('channelProducts.sourceAvailable'),
      dataIndex: ['listing', 'availableQuantity'],
      width: 80,
      render: (_, record) => (
        <span className={record.listing.availableQuantity === 0 ? 'text-red-500' : ''}>
          {record.listing.availableQuantity}
        </span>
      ),
    },
    {
      title: t('channelProducts.sourceAllocation'),
      dataIndex: ['listing', 'allocationMode'],
      width: 80,
      render: (_, record) => (
        <Tag color={allocationModeColorMap[record.listing.allocationMode]}>
          {getAllocationModeLabel(record.listing.allocationMode)}
        </Tag>
      ),
    },
    {
      title: t('channelProducts.sourceFulfillment'),
      dataIndex: ['listing', 'fulfillmentType'],
      width: 80,
      render: (_, record) => (
        <Tag color={fulfillmentTypeColorMap[record.listing.fulfillmentType]}>
          {getFulfillmentTypeLabel(record.listing.fulfillmentType)}
        </Tag>
      ),
    },
    {
      title: t('channelProducts.sourcePriority'),
      dataIndex: 'priority',
      width: 70,
      render: (_, record) => record.priority,
    },
    {
      title: t('channelProducts.sourceStatus'),
      dataIndex: 'isActive',
      width: 80,
      render: (_, record) => (
        <Tag color={record.isActive ? 'success' : 'default'}>
          {record.isActive ? t('channelProducts.sourceActive') : t('channelProducts.sourceInactive')}
        </Tag>
      ),
    },
    {
      title: t('channelProducts.sourceSold'),
      dataIndex: 'soldQuantity',
      width: 70,
    },
  ];

  const syncLogColumns: ProColumns<ChannelProductSyncLog>[] = [
    {
      title: t('channelProducts.operation'),
      dataIndex: 'operation',
      width: 120,
      render: (_, record) => getOperationLabel(record.operation),
    },
    {
      title: t('channelProducts.triggerSource'),
      dataIndex: 'triggerSource',
      width: 120,
      render: (_, record) => getTriggerSourceLabel(record.triggerSource),
    },
    {
      title: t('channelProducts.logStatus'),
      dataIndex: 'status',
      width: 100,
      render: (_, record) => (
        <Tag color={logStatusColorMap[record.status]}>
          {getLogStatusLabel(record.status)}
        </Tag>
      ),
    },
    {
      title: t('channelProducts.errorMessage'),
      dataIndex: 'errorMessage',
      width: 200,
      ellipsis: true,
      render: (_, record) =>
        record.errorMessage ? (
          <span className="text-red-500">{record.errorMessage}</span>
        ) : (
          '-'
        ),
    },
    {
      title: t('channelProducts.duration'),
      dataIndex: 'durationMs',
      width: 100,
      render: (_, record) =>
        record.durationMs !== null ? `${record.durationMs}ms` : '-',
    },
    {
      title: t('channelProducts.startedAt'),
      dataIndex: 'startedAt',
      width: 160,
      render: (_, record) => new Date(record.startedAt).toLocaleString(),
    },
  ];

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Spin size="large" />
      </div>
    );
  }

  if (!product) {
    return (
      <Empty
        description={t('channelProducts.notFound')}
        className="mt-16"
      >
        <Button onClick={() => navigate('/channels/products')}>
          {t('common.back')}
        </Button>
      </Empty>
    );
  }

  return (
    <Space direction="vertical" size="large" style={{ display: 'flex' }}>
      {/* Header */}
      <div className="flex items-center justify-between">
        <Button
          icon={<ArrowLeftOutlined />}
          onClick={() => navigate('/channels/products')}
        >
          {t('common.back')}
        </Button>
        <Space>
          {product.status !== 'active' && (
            <Button
              type="primary"
              icon={<PlayCircleOutlined />}
              loading={actionLoading}
              onClick={handleActivate}
            >
              {t('channelProducts.activate')}
            </Button>
          )}
          {product.status === 'active' && (
            <Button
              icon={<PauseCircleOutlined />}
              loading={actionLoading}
              onClick={handlePause}
            >
              {t('channelProducts.pause')}
            </Button>
          )}
          <Button
            icon={<SyncOutlined />}
            loading={actionLoading}
            onClick={handleSync}
          >
            {t('channelProducts.sync')}
          </Button>
        </Space>
      </div>

      {/* Sync Error Alert */}
      {product.syncError && (
        <Alert
          type="error"
          showIcon
          icon={<ExclamationCircleOutlined />}
          message={t('channelProducts.syncFailed')}
          description={product.syncError}
        />
      )}

      {/* Basic Info */}
      <Card title={t('channelProducts.basicInfo')}>
        <Descriptions column={3} bordered size="small">
          <Descriptions.Item label={t('channelProducts.productName')}>
            {product.productSku.productName}
          </Descriptions.Item>
          <Descriptions.Item label={t('channelProducts.skuCode')}>
            {product.productSku.skuCode}
          </Descriptions.Item>
          <Descriptions.Item label={t('channelProducts.salesChannel')}>
            <Tag>{product.salesChannel.name}</Tag>
          </Descriptions.Item>

          <Descriptions.Item label={t('channelProducts.platformPrice')}>
            <span className="font-semibold">${product.platformPrice}</span>
          </Descriptions.Item>
          <Descriptions.Item label={t('channelProducts.stockQuantity')}>
            <span className={product.stockQuantity === 0 ? 'text-red-500' : ''}>
              {product.stockQuantity}
            </span>
          </Descriptions.Item>
          <Descriptions.Item label={t('channelProducts.stockMode')}>
            {product.stockMode}
          </Descriptions.Item>

          <Descriptions.Item label={t('channelProducts.status')}>
            <Tag color={statusColorMap[product.status]}>
              {t(`channelProducts.status${product.status.charAt(0).toUpperCase() + product.status.slice(1)}`)}
            </Tag>
          </Descriptions.Item>
          <Descriptions.Item label={t('channelProducts.syncStatus')}>
            <Tag color={syncStatusColorMap[product.syncStatus]}>
              {t(`channelProducts.sync${product.syncStatus.charAt(0).toUpperCase() + product.syncStatus.slice(1)}`)}
            </Tag>
          </Descriptions.Item>
          <Descriptions.Item label={t('channelProducts.lastSyncedAt')}>
            {product.lastSyncedAt
              ? new Date(product.lastSyncedAt).toLocaleString()
              : '-'}
          </Descriptions.Item>

          <Descriptions.Item label={t('channelProducts.externalId')} span={2}>
            {product.externalId ? (
              product.externalUrl ? (
                <a
                  href={product.externalUrl}
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  {product.externalId} <LinkOutlined />
                </a>
              ) : (
                product.externalId
              )
            ) : (
              '-'
            )}
          </Descriptions.Item>
          <Descriptions.Item label={t('channelProducts.sourcesCount')}>
            {product.activeSourcesCount} / {product.sourcesCount}
          </Descriptions.Item>
        </Descriptions>
      </Card>

      {/* Inventory Sources */}
      <Card title={t('channelProducts.inventorySources')} loading={sourcesLoading}>
        <ProTable<ChannelProductSource>
          columns={sourceColumns}
          dataSource={sources}
          rowKey="id"
          search={false}
          options={false}
          pagination={false}
          size="small"
        />
      </Card>

      {/* Sync Logs */}
      <Card title={t('channelProducts.syncLogs')}>
        <ProTable<ChannelProductSyncLog>
          actionRef={actionRef}
          columns={syncLogColumns}
          rowKey="id"
          request={async (params) => {
            if (!id) return { data: [], success: false, total: 0 };
            try {
              const result = await channelProductApi.getSyncLogs(id, {
                page: params.current,
                limit: params.pageSize,
              });
              return {
                data: result.data,
                success: true,
                total: result.total,
              };
            } catch {
              return { data: [], success: false, total: 0 };
            }
          }}
          search={false}
          options={false}
          pagination={{
            defaultPageSize: 10,
            showSizeChanger: true,
          }}
        />
      </Card>
    </Space>
  );
}
