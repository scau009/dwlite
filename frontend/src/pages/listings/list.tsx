import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';
import { ProTable, type ActionType, type ProColumns } from '@ant-design/pro-components';
import { Button, Tag, Space, App, Avatar, Image } from 'antd';
import { PlusOutlined } from '@ant-design/icons';

import {
  merchantListingApi,
  getCurrencySymbol,
  type MerchantListing,
  type ListingStatus,
  type FulfillmentType,
  type AllocationMode,
} from '@/lib/merchant-listing-api';

const statusColorMap: Record<ListingStatus, string> = {
  draft: 'default',
  active: 'success',
  paused: 'warning',
  sold_out: 'error',
};

const fulfillmentColorMap: Record<FulfillmentType, string> = {
  consignment: 'blue',
  self_fulfillment: 'purple',
};

const allocationColorMap: Record<AllocationMode, string> = {
  shared: 'cyan',
  dedicated: 'orange',
};

export function ListingsListPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const actionRef = useRef<ActionType>(null);
  const { message, modal } = App.useApp();

  const [actionLoading, setActionLoading] = useState<string | null>(null);
  const [channelOptions, setChannelOptions] = useState<Record<string, { text: string }>>({});

  useEffect(() => {
    merchantListingApi.getAvailableChannels().then((res) => {
      const options: Record<string, { text: string }> = {};
      res.data.forEach((ch) => {
        options[ch.salesChannel.id] = { text: ch.salesChannel.name };
      });
      setChannelOptions(options);
    });
  }, []);

  const handleActivate = async (listing: MerchantListing) => {
    setActionLoading(listing.id);
    try {
      await merchantListingApi.activateListing(listing.id);
      message.success(t('listingManagement.activated'));
      actionRef.current?.reload();
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setActionLoading(null);
    }
  };

  const handlePause = async (listing: MerchantListing) => {
    setActionLoading(listing.id);
    try {
      await merchantListingApi.pauseListing(listing.id);
      message.success(t('listingManagement.paused'));
      actionRef.current?.reload();
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setActionLoading(null);
    }
  };

  const handleDelete = async (listing: MerchantListing) => {
    modal.confirm({
      title: t('listingManagement.confirmDelete'),
      content: t('listingManagement.confirmDeleteDesc'),
      okText: t('common.confirm'),
      cancelText: t('common.cancel'),
      okButtonProps: { danger: true },
      onOk: async () => {
        setActionLoading(listing.id);
        try {
          await merchantListingApi.deleteListing(listing.id);
          message.success(t('listingManagement.deleted'));
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

  const columns: ProColumns<MerchantListing>[] = [
    {
      title: t('listingManagement.product'),
      dataIndex: 'product',
      width: 240,
      search: {
        transform: (value) => ({ search: value }),
      },
      render: (_, record) => (
        <div className="flex items-center gap-2">
          <div className="flex-shrink-0">
            {record.product.imageUrl ? (
              <Image
                src={record.product.imageUrl}
                alt={record.product.name}
                width={56}
                height={56}
                className="object-contain rounded bg-gray-50"
                preview={false}
              />
            ) : (
              <Avatar size={56} shape="square">
                {record.product.name.charAt(0)}
              </Avatar>
            )}
          </div>
          <div className="flex-1 min-w-0">
            <div className="font-medium truncate" title={record.product.name}>
              {record.product.name}
            </div>
            <div className="text-xs text-gray-500 truncate">{record.product.styleNumber}</div>
          </div>
        </div>
      ),
    },
    {
      title: t('listingManagement.size'),
      dataIndex: ['productSku', 'sizeValue'],
      width: 80,
      search: false,
      render: (_, record) => (
        <span>{record.productSku.sizeUnit} {record.productSku.sizeValue}</span>
      ),
    },
    {
      title: t('listingManagement.channel'),
      dataIndex: 'channelId',
      width: 120,
      valueType: 'select',
      valueEnum: channelOptions,
      search: {
        transform: (value) => ({ channelId: value }),
      },
      render: (_, record) => (
        <span className="text-sm">{record.salesChannel.name}</span>
      ),
    },
    {
      title: t('listingManagement.fulfillmentType'),
      dataIndex: 'fulfillmentType',
      width: 100,
      valueType: 'select',
      valueEnum: {
        consignment: { text: t('merchantChannels.fulfillmentConsignment') },
        self_fulfillment: { text: t('merchantChannels.fulfillmentSelfFulfillment') },
      },
      render: (_, record) => (
        <Tag color={fulfillmentColorMap[record.fulfillmentType]}>
          {record.fulfillmentType === 'consignment'
            ? t('merchantChannels.fulfillmentConsignment')
            : t('merchantChannels.fulfillmentSelfFulfillment')}
        </Tag>
      ),
    },
    {
      title: t('listingManagement.allocationMode'),
      dataIndex: 'allocationMode',
      width: 100,
      valueType: 'select',
      valueEnum: {
        shared: { text: t('listingManagement.allocationModeShared') },
        dedicated: { text: t('listingManagement.allocationModeDedicated') },
      },
      render: (_, record) => (
        <Tag color={allocationColorMap[record.allocationMode]}>
          {record.allocationMode === 'shared'
            ? t('listingManagement.allocationModeShared')
            : t('listingManagement.allocationModeDedicated')}
        </Tag>
      ),
    },
    {
      title: t('listingManagement.pricingModel'),
      dataIndex: 'pricingModel',
      hideInTable: true,
      valueType: 'select',
      valueEnum: {
        self_pricing: { text: t('listingManagement.selfPricing') },
        platform_managed: { text: t('listingManagement.platformManaged') },
      },
    },
    {
      title: t('listingManagement.price'),
      dataIndex: 'price',
      width: 120,
      search: false,
      render: (_, record) => (
        <span className="font-medium">
          {getCurrencySymbol(record.salesChannel.currency)}{record.price}
        </span>
      ),
    },
    {
      title: t('listingManagement.availableQuantity'),
      dataIndex: 'availableQuantity',
      width: 100,
      search: false,
      render: (_, record) => {
        const qty = record.availableQuantity;
        return (
          <span className={qty === 0 ? 'text-red-500' : ''}>
            {qty}
          </span>
        );
      },
    },
    {
      title: t('listingManagement.status'),
      dataIndex: 'status',
      width: 100,
      valueType: 'select',
      valueEnum: {
        draft: { text: t('listingManagement.statusDraft') },
        active: { text: t('listingManagement.statusActive') },
        paused: { text: t('listingManagement.statusPaused') },
        sold_out: { text: t('listingManagement.statusSoldOut') },
      },
      render: (_, record) => (
        <Tag color={statusColorMap[record.status]}>
          {t(`listingManagement.status${record.status.charAt(0).toUpperCase() + record.status.slice(1)}`)}
        </Tag>
      ),
    },
    {
      title: t('common.updatedAt'),
      dataIndex: 'updatedAt',
      width: 160,
      search: false,
      render: (_, record) => new Date(record.updatedAt).toLocaleString(),
    },
    {
      title: t('common.actions'),
      valueType: 'option',
      width: 200,
      fixed: 'right',
      render: (_, record) => {
        const isLoading = actionLoading === record.id;

        return (
          <Space size="small">
            {(record.status === 'draft' || record.status === 'paused') && (
              <Button
                type="link"
                size="small"
                loading={isLoading}
                onClick={() => handleActivate(record)}
              >
                {t('listingManagement.activate')}
              </Button>
            )}
            {record.status === 'active' && (
              <Button
                type="link"
                size="small"
                loading={isLoading}
                onClick={() => handlePause(record)}
              >
                {t('listingManagement.pause')}
              </Button>
            )}
            <Button
              type="link"
              size="small"
              onClick={() => navigate(`/channels/listings/${record.id}/edit`)}
            >
              {t('common.edit')}
            </Button>
            {record.status === 'draft' && (
              <Button
                type="link"
                size="small"
                danger
                onClick={() => handleDelete(record)}
              >
                {t('common.delete')}
              </Button>
            )}
          </Space>
        );
      },
    },
  ];

  return (
    <div className="space-y-4">
      <ProTable<MerchantListing>
        actionRef={actionRef}
        columns={columns}
        rowKey="id"
        scroll={{ x: 1180 }}
        request={async (params) => {
          try {
            const result = await merchantListingApi.getListings({
              page: params.current,
              limit: params.pageSize,
              search: params.search,
              status: params.status,
              channelId: params.channelId,
              fulfillmentType: params.fulfillmentType,
              pricingModel: params.pricingModel,
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
        toolBarRender={() => [
          <Button
            key="create"
            type="primary"
            icon={<PlusOutlined />}
            onClick={() => navigate('/channels/listings/create')}
          >
            {t('listingManagement.createListing')}
          </Button>,
        ]}
        search={{
          labelWidth: 'auto',
          defaultCollapsed: false,
        }}
        options={{
          density: true,
          reload: true,
        }}
        pagination={{
          defaultPageSize: 20,
          showSizeChanger: true,
        }}
      />
    </div>
  );
}
