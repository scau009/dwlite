import { useState, useEffect, useRef } from 'react';
import { useNavigate } from 'react-router';
import { useTranslation } from 'react-i18next';
import {
  Card,
  Steps,
  Button,
  Space,
  App,
  Select,
  InputNumber,
  Avatar,
  Empty,
  Spin,
  Tag,
  Table,
  Alert,
  Modal,
  List,
  Typography,
  Result,
  Popover,
  Tooltip,
} from 'antd';
import { ArrowLeftOutlined, ShopOutlined, CheckCircleOutlined, CloseCircleOutlined, InfoCircleOutlined, WarningOutlined } from '@ant-design/icons';
import { ProTable, type ActionType, type ProColumns } from '@ant-design/pro-components';

import {
  merchantListingApi,
  getCurrencySymbol,
  type AvailableInventory,
  type AvailableChannel,
  type FulfillmentType,
  type PricingModel,
  type AllocationMode,
  type BatchListingItemRequest,
  type BatchCreateListingResult,
  type PricingDefaults,
  type AllocationDefaults,
  type RuleDetail,
} from '@/lib/merchant-listing-api';

interface ListingConfig extends AvailableInventory {
  fulfillmentType: FulfillmentType;
  pricingModel: PricingModel;
  allocationMode: AllocationMode;
  allocatedQuantity?: number;
  price?: number;
  compareAtPrice?: number;
  pricingDefaults?: PricingDefaults;
  allocationDefaults?: AllocationDefaults;
}

const { Text } = Typography;

// Rule Details Popover Content
const RuleDetailsContent = ({
  rules,
  baseCost,
  finalValue,
  currencySymbol,
  type,
  t,
}: {
  rules: RuleDetail[];
  baseCost?: string;
  finalValue: string | number;
  currencySymbol: string;
  type: 'pricing' | 'allocation';
  t: (key: string) => string;
}) => (
  <div className="max-w-xs">
    <div className="font-medium mb-2">{t('listingManagement.appliedRules')}</div>
    {rules.map((rule, index) => (
      <div key={rule.id} className="mb-2 p-2 bg-gray-50 rounded text-xs">
        <div className="font-medium">{index + 1}. {rule.name}</div>
        <div className="text-gray-500 mt-1">
          <span className="font-mono">{rule.expression}</span>
        </div>
        <div className="mt-1">
          {type === 'pricing' ? (
            <span>
              {currencySymbol}{rule.inputValue.toFixed(2)} → {currencySymbol}{rule.outputValue.toFixed(2)}
            </span>
          ) : (
            <span>{rule.inputValue} → {rule.outputValue}</span>
          )}
        </div>
      </div>
    ))}
    <div className="border-t pt-2 mt-2 text-xs">
      {type === 'pricing' && baseCost && (
        <div className="text-gray-500">
          {t('listingManagement.baseCost')}: {currencySymbol}{baseCost}
        </div>
      )}
      <div className="font-medium">
        {type === 'pricing'
          ? `${t('listingManagement.calculatedPrice')}: ${currencySymbol}${finalValue}`
          : `${t('listingManagement.calculatedQuantity')}: ${finalValue}`}
      </div>
    </div>
  </div>
);

export function CreateListingPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { message } = App.useApp();
  const actionRef = useRef<ActionType>(null);

  const [currentStep, setCurrentStep] = useState(0);
  const [submitting, setSubmitting] = useState(false);

  // Batch result state
  const [batchResult, setBatchResult] = useState<BatchCreateListingResult | null>(null);
  const [resultModalOpen, setResultModalOpen] = useState(false);

  // Step 1: Channel selection
  const [channels, setChannels] = useState<AvailableChannel[]>([]);
  const [channelsLoading, setChannelsLoading] = useState(false);
  const [selectedChannel, setSelectedChannel] = useState<AvailableChannel | null>(null);

  // Step 2: Inventory selection (multi-select)
  const [selectedInventories, setSelectedInventories] = useState<AvailableInventory[]>([]);
  const [selectedRowKeys, setSelectedRowKeys] = useState<React.Key[]>([]);

  // Step 3: Configuration
  const [listingConfigs, setListingConfigs] = useState<ListingConfig[]>([]);
  const [loadingDefaults, setLoadingDefaults] = useState(false);

  // Load channels on mount
  useEffect(() => {
    loadChannels();
  }, []);

  const loadChannels = async () => {
    setChannelsLoading(true);
    try {
      const result = await merchantListingApi.getAvailableChannels();
      setChannels(result.data);
    } catch (error) {
      console.error('Failed to load channels:', error);
      message.error(t('common.error'));
    } finally {
      setChannelsLoading(false);
    }
  };

  const handleChannelSelect = (channel: AvailableChannel) => {
    setSelectedChannel(channel);
    // Reset selections when channel changes
    setSelectedInventories([]);
    setSelectedRowKeys([]);
    setListingConfigs([]);
  };

  const handleNext = async () => {
    if (currentStep === 0 && !selectedChannel) {
      message.warning(t('listingManagement.selectChannelRequired'));
      return;
    }
    if (currentStep === 1 && selectedInventories.length === 0) {
      message.warning(t('listingManagement.selectInventoryRequired'));
      return;
    }

    // When moving from step 2 to step 3, initialize configs with rule defaults
    if (currentStep === 1 && selectedChannel) {
      setLoadingDefaults(true);
      try {
        // Fetch rule-based defaults
        const defaultsResult = await merchantListingApi.calculateDefaults({
          merchantSalesChannelId: selectedChannel.id,
          inventoryIds: selectedInventories.map((inv) => inv.id),
        });

        // Create a map for quick lookup
        const defaultsMap = new Map(
          defaultsResult.data.map((d) => [d.inventoryId, d])
        );

        const defaultFulfillmentType = selectedChannel.approvedFulfillmentTypes[0] || 'consignment';
        const configs: ListingConfig[] = selectedInventories.map((inv) => {
          const defaults = defaultsMap.get(inv.id);
          return {
            ...inv,
            fulfillmentType: defaultFulfillmentType,
            pricingModel: 'self_pricing',
            allocationMode: defaults?.allocation.hasRules ? 'dedicated' : 'shared',
            allocatedQuantity: defaults?.allocation.hasRules ? defaults.allocation.calculatedQuantity : undefined,
            price: defaults?.pricing.hasRules && defaults.pricing.calculatedPrice
              ? parseFloat(defaults.pricing.calculatedPrice)
              : undefined,
            compareAtPrice: undefined,
            pricingDefaults: defaults?.pricing,
            allocationDefaults: defaults?.allocation,
          };
        });
        setListingConfigs(configs);
      } catch (error) {
        console.error('Failed to load defaults:', error);
        // Fall back to no defaults
        const defaultFulfillmentType = selectedChannel.approvedFulfillmentTypes[0] || 'consignment';
        const configs: ListingConfig[] = selectedInventories.map((inv) => ({
          ...inv,
          fulfillmentType: defaultFulfillmentType,
          pricingModel: 'self_pricing',
          allocationMode: 'shared',
          allocatedQuantity: undefined,
          price: undefined,
          compareAtPrice: undefined,
        }));
        setListingConfigs(configs);
      } finally {
        setLoadingDefaults(false);
      }
    }

    setCurrentStep(currentStep + 1);
  };

  const handleBack = () => {
    setCurrentStep(currentStep - 1);
  };

  const handleConfigChange = (id: string, field: keyof ListingConfig, value: unknown) => {
    setListingConfigs((prev) =>
      prev.map((config) => {
        if (config.id === id) {
          const updated = { ...config, [field]: value };
          // Reset allocatedQuantity when switching to shared mode
          if (field === 'allocationMode' && value === 'shared') {
            updated.allocatedQuantity = undefined;
          }
          return updated;
        }
        return config;
      })
    );
  };

  const validateConfigs = (): boolean => {
    for (const config of listingConfigs) {
      if (!config.price || config.price <= 0) {
        message.error(t('listingManagement.priceRequiredForAll'));
        return false;
      }
      if (config.allocationMode === 'dedicated') {
        if (!config.allocatedQuantity || config.allocatedQuantity <= 0) {
          message.error(t('listingManagement.allocatedQuantityRequiredForDedicated'));
          return false;
        }
        if (config.allocatedQuantity > config.shareableQuantity) {
          message.error(t('listingManagement.allocatedQuantityExceeded'));
          return false;
        }
      }
    }
    return true;
  };

  const handleSubmit = async () => {
    if (!selectedChannel || listingConfigs.length === 0) return;

    if (!validateConfigs()) return;

    try {
      setSubmitting(true);

      const listings: BatchListingItemRequest[] = listingConfigs.map((config) => ({
        merchantInventoryId: config.id,
        fulfillmentType: config.fulfillmentType,
        pricingModel: config.pricingModel,
        allocationMode: config.allocationMode,
        allocatedQuantity: config.allocationMode === 'dedicated' ? config.allocatedQuantity : undefined,
        price: config.price!.toString(),
        compareAtPrice: config.compareAtPrice ? config.compareAtPrice.toString() : undefined,
      }));

      const result = await merchantListingApi.batchCreateListings({
        merchantSalesChannelId: selectedChannel.id,
        listings,
      });

      if (result.failedCount === 0) {
        // All succeeded - show success message and navigate
        message.success(t('listingManagement.batchCreatedSuccess', { count: result.successCount }));
        navigate('/channels/listings');
      } else {
        // Has failures - show detailed result modal
        setBatchResult(result);
        setResultModalOpen(true);
      }
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setSubmitting(false);
    }
  };

  const inventoryColumns: ProColumns<AvailableInventory>[] = [
    {
      title: t('listingManagement.product'),
      dataIndex: 'product',
      width: 280,
      search: {
        transform: (value) => ({ search: value }),
      },
      fieldProps: {
        placeholder: t('listingManagement.searchPlaceholder'),
      },
      render: (_, record) => (
        <Space size="small">
          {record.product.imageUrl ? (
            <Avatar src={record.product.imageUrl} size={40} shape="square" />
          ) : (
            <Avatar size={40} shape="square">
              {record.product.name.charAt(0)}
            </Avatar>
          )}
          <div>
            <div className="font-medium">{record.product.name}</div>
            <div className="text-xs text-gray-500">
              {record.product.styleNumber} / {record.productSku.sizeValue}
              {record.productSku.sizeUnit}
            </div>
          </div>
        </Space>
      ),
    },
    {
      title: t('listingManagement.warehouse'),
      dataIndex: ['warehouse', 'name'],
      width: 160,
      search: false,
      render: (_, record) => (
        <div>
          <div>{record.warehouse.name}</div>
          <div className="text-xs text-gray-500">
            {record.warehouse.code}
            {record.warehouse.category === 'platform' && (
              <Tag color="blue" className="ml-1">{t('listingManagement.platformWarehouse')}</Tag>
            )}
          </div>
        </div>
      ),
    },
    {
      title: t('listingManagement.availableQuantity'),
      dataIndex: 'quantityAvailable',
      width: 100,
      search: false,
    },
    {
      title: t('listingManagement.shareableQuantity'),
      dataIndex: 'shareableQuantity',
      width: 100,
      search: false,
      render: (_, record) => (
        <span className={record.shareableQuantity === 0 ? 'text-red-500' : 'text-green-600'}>
          {record.shareableQuantity}
        </span>
      ),
    },
  ];

  const currencySymbol = selectedChannel ? getCurrencySymbol(selectedChannel.salesChannel.currency) : '¥';
  const availableFulfillmentTypes = selectedChannel?.approvedFulfillmentTypes || [];

  const configColumns = [
    {
      title: t('listingManagement.product'),
      dataIndex: 'product',
      width: 200,
      render: (_: unknown, record: ListingConfig) => (
        <div>
          <div className="font-medium text-sm">{record.product.name}</div>
          <div className="text-xs text-gray-500">
            {record.product.styleNumber} / {record.productSku.sizeValue}{record.productSku.sizeUnit}
          </div>
        </div>
      ),
    },
    {
      title: t('listingManagement.warehouse'),
      dataIndex: 'warehouse',
      width: 120,
      render: (_: unknown, record: ListingConfig) => (
        <div className="text-sm">{record.warehouse.name}</div>
      ),
    },
    {
      title: t('listingManagement.shareableQuantity'),
      dataIndex: 'shareableQuantity',
      width: 80,
      render: (_: unknown, record: ListingConfig) => (
        <span className="text-green-600">{record.shareableQuantity}</span>
      ),
    },
    {
      title: t('listingManagement.fulfillmentType'),
      dataIndex: 'fulfillmentType',
      width: 120,
      render: (_: unknown, record: ListingConfig) => (
        <Select
          size="small"
          value={record.fulfillmentType}
          onChange={(value) => handleConfigChange(record.id, 'fulfillmentType', value)}
          style={{ width: '100%' }}
          options={availableFulfillmentTypes.map((type) => ({
            value: type,
            label: type === 'consignment'
              ? t('merchantChannels.fulfillmentConsignment')
              : t('merchantChannels.fulfillmentSelfFulfillment'),
          }))}
        />
      ),
    },
    {
      title: t('listingManagement.pricingModel'),
      dataIndex: 'pricingModel',
      width: 120,
      render: (_: unknown, record: ListingConfig) => (
        <Select
          size="small"
          value={record.pricingModel}
          onChange={(value) => handleConfigChange(record.id, 'pricingModel', value)}
          style={{ width: '100%' }}
          options={[
            { value: 'self_pricing', label: t('listingManagement.selfPricing') },
            { value: 'platform_managed', label: t('listingManagement.platformManaged') },
          ]}
        />
      ),
    },
    {
      title: t('listingManagement.allocationMode'),
      dataIndex: 'allocationMode',
      width: 100,
      render: (_: unknown, record: ListingConfig) => (
        <Select
          size="small"
          value={record.allocationMode}
          onChange={(value) => handleConfigChange(record.id, 'allocationMode', value)}
          style={{ width: '100%' }}
          options={[
            { value: 'shared', label: t('listingManagement.allocationModeShared') },
            { value: 'dedicated', label: t('listingManagement.allocationModeDedicated') },
          ]}
        />
      ),
    },
    {
      title: t('listingManagement.allocatedQuantity'),
      dataIndex: 'allocatedQuantity',
      width: 120,
      render: (_: unknown, record: ListingConfig) => (
        record.allocationMode === 'dedicated' ? (
          <Space size={4}>
            <InputNumber
              size="small"
              min={1}
              max={record.shareableQuantity}
              value={record.allocatedQuantity}
              onChange={(value) => handleConfigChange(record.id, 'allocatedQuantity', value)}
              style={{ width: 70 }}
            />
            {record.allocationDefaults?.hasRules && record.allocationDefaults.rules.length > 0 ? (
              <Popover
                content={
                  <RuleDetailsContent
                    rules={record.allocationDefaults.rules}
                    finalValue={record.allocationDefaults.calculatedQuantity}
                    currencySymbol={currencySymbol}
                    type="allocation"
                    t={t}
                  />
                }
                title={null}
                trigger="click"
              >
                <InfoCircleOutlined className="text-blue-500 cursor-pointer" />
              </Popover>
            ) : (
              <Tooltip title={t('listingManagement.noRulesConfigured')}>
                <WarningOutlined className="text-orange-400" />
              </Tooltip>
            )}
          </Space>
        ) : (
          <span className="text-gray-400">-</span>
        )
      ),
    },
    {
      title: `${t('listingManagement.price')} (${currencySymbol})`,
      dataIndex: 'price',
      width: 140,
      render: (_: unknown, record: ListingConfig) => (
        <Space size={4}>
          <InputNumber
            size="small"
            min={0.01}
            precision={2}
            value={record.price}
            onChange={(value) => handleConfigChange(record.id, 'price', value)}
            style={{ width: 90 }}
            prefix={currencySymbol}
          />
          {record.pricingDefaults?.hasRules && record.pricingDefaults.rules.length > 0 ? (
            <Popover
              content={
                <RuleDetailsContent
                  rules={record.pricingDefaults.rules}
                  baseCost={record.pricingDefaults.baseCost}
                  finalValue={record.pricingDefaults.calculatedPrice}
                  currencySymbol={currencySymbol}
                  type="pricing"
                  t={t}
                />
              }
              title={null}
              trigger="click"
            >
              <InfoCircleOutlined className="text-blue-500 cursor-pointer" />
            </Popover>
          ) : (
            <Tooltip title={t('listingManagement.noRulesConfigured')}>
              <WarningOutlined className="text-orange-400" />
            </Tooltip>
          )}
        </Space>
      ),
    },
    {
      title: `${t('listingManagement.compareAtPrice')} (${currencySymbol})`,
      dataIndex: 'compareAtPrice',
      width: 120,
      render: (_: unknown, record: ListingConfig) => (
        <InputNumber
          size="small"
          min={0.01}
          precision={2}
          value={record.compareAtPrice}
          onChange={(value) => handleConfigChange(record.id, 'compareAtPrice', value)}
          style={{ width: '100%' }}
          prefix={currencySymbol}
        />
      ),
    },
  ];

  const renderStepContent = () => {
    switch (currentStep) {
      case 0:
        return (
          <div className="py-4">
            <div className="mb-4 text-gray-500">
              {t('listingManagement.selectChannelDesc')}
            </div>
            {channelsLoading ? (
              <div className="flex justify-center py-8">
                <Spin />
              </div>
            ) : channels.length === 0 ? (
              <Empty description={t('listingManagement.noAvailableChannels')} />
            ) : (
              <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
                {channels.map((channel) => (
                  <Card
                    key={channel.id}
                    hoverable
                    className="cursor-pointer transition-all"
                    style={{
                      border: selectedChannel?.id === channel.id
                        ? '2px solid #1890ff'
                        : '1px solid #d9d9d9',
                    }}
                    onClick={() => handleChannelSelect(channel)}
                  >
                    <div className="flex items-center gap-3">
                      {channel.salesChannel.logoUrl ? (
                        <Avatar src={channel.salesChannel.logoUrl} size={48} shape="square" />
                      ) : (
                        <Avatar icon={<ShopOutlined />} size={48} shape="square" />
                      )}
                      <div>
                        <div className="font-medium">{channel.salesChannel.name}</div>
                        <div className="text-xs text-gray-500 mb-1">
                          {getCurrencySymbol(channel.salesChannel.currency)} {channel.salesChannel.currency}
                        </div>
                        <div className="text-xs text-gray-500">
                          {channel.approvedFulfillmentTypes.map((type) => (
                            <Tag key={type} className="text-xs">
                              {type === 'consignment'
                                ? t('merchantChannels.fulfillmentConsignment')
                                : t('merchantChannels.fulfillmentSelfFulfillment')}
                            </Tag>
                          ))}
                        </div>
                      </div>
                    </div>
                  </Card>
                ))}
              </div>
            )}
          </div>
        );

      case 1:
        return (
          <div className="py-4">
            <div className="mb-4 text-gray-500">
              {t('listingManagement.selectInventoryDescMulti')}
            </div>
            {selectedInventories.length > 0 && (
              <Alert
                type="info"
                className="mb-4"
                message={t('listingManagement.selectedCount', { count: selectedInventories.length })}
              />
            )}
            <ProTable<AvailableInventory>
              actionRef={actionRef}
              columns={inventoryColumns}
              rowKey="id"
              rowSelection={{
                selectedRowKeys,
                onChange: (keys, rows) => {
                  setSelectedRowKeys(keys);
                  setSelectedInventories(rows);
                },
                getCheckboxProps: (record) => ({
                  disabled: record.shareableQuantity === 0,
                }),
              }}
              tableAlertRender={false}
              tableAlertOptionRender={false}
              request={async (params) => {
                if (!selectedChannel) {
                  return { data: [], success: true, total: 0 };
                }
                try {
                  const result = await merchantListingApi.getAvailableInventory({
                    channelId: selectedChannel.id,
                    page: params.current,
                    limit: params.pageSize,
                    search: params.search,
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
              search={{
                labelWidth: 'auto',
                defaultCollapsed: true,
              }}
              pagination={{
                defaultPageSize: 10,
                showSizeChanger: true,
              }}
              options={false}
              toolBarRender={false}
            />
          </div>
        );

      case 2:
        return (
          <div className="py-4">
            <div className="mb-4 p-4 bg-gray-50 rounded-lg">
              <div className="text-sm">
                <strong>{t('listingManagement.channel')}:</strong>{' '}
                {selectedChannel?.salesChannel.name}{' '}
                <Tag color="blue">{selectedChannel?.salesChannel.currency}</Tag>
              </div>
            </div>

            <Table
              dataSource={listingConfigs}
              columns={configColumns}
              rowKey="id"
              pagination={false}
              scroll={{ x: 1300 }}
              size="small"
            />
          </div>
        );

      default:
        return null;
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-4">
        <Button
          icon={<ArrowLeftOutlined />}
          onClick={() => navigate('/channels/listings')}
        >
          {t('common.back')}
        </Button>
        <h1 className="text-xl font-semibold m-0">
          {t('listingManagement.createListing')}
        </h1>
      </div>

      <Card>
        <Steps
          current={currentStep}
          className="mb-6"
          items={[
            { title: t('listingManagement.stepSelectChannel') },
            { title: t('listingManagement.stepSelectInventory') },
            { title: t('listingManagement.stepConfigureListing') },
          ]}
        />

        {renderStepContent()}

        <div className="flex justify-end mt-6 pt-4">
          <Space>
            {currentStep > 0 && (
              <Button onClick={handleBack}>
                {t('common.previous')}
              </Button>
            )}
            {currentStep < 2 ? (
              <Button type="primary" onClick={handleNext} loading={loadingDefaults}>
                {t('common.next')}
              </Button>
            ) : (
              <Button type="primary" loading={submitting} onClick={handleSubmit}>
                {t('listingManagement.createListing')} ({listingConfigs.length})
              </Button>
            )}
          </Space>
        </div>
      </Card>

      {/* Batch Result Modal */}
      <Modal
        title={t('listingManagement.batchResultTitle')}
        open={resultModalOpen}
        onOk={() => navigate('/channels/listings')}
        onCancel={() => setResultModalOpen(false)}
        okText={t('listingManagement.backToList')}
        cancelText={t('listingManagement.continueOperation')}
        width={600}
      >
        {batchResult && (
          <div className="space-y-4">
            <Result
              status={batchResult.successCount > 0 ? 'warning' : 'error'}
              title={
                batchResult.successCount > 0
                  ? t('listingManagement.batchPartialSuccess')
                  : t('listingManagement.batchAllFailed')
              }
              subTitle={t('listingManagement.batchResultSummary', {
                success: batchResult.successCount,
                failed: batchResult.failedCount,
              })}
            />

            {batchResult.results.success.length > 0 && (
              <div>
                <Text strong className="text-green-600">
                  <CheckCircleOutlined className="mr-1" />
                  {t('listingManagement.batchSuccessItems')} ({batchResult.successCount})
                </Text>
                <List
                  size="small"
                  className="mt-2 max-h-32 overflow-auto"
                  dataSource={batchResult.results.success}
                  renderItem={(item) => {
                    const config = listingConfigs.find((c) => c.id === item.inventoryId);
                    return (
                      <List.Item className="py-1">
                        <Text type="success">
                          {config
                            ? `${config.product.name} - ${config.productSku.sizeValue}${config.productSku.sizeUnit}`
                            : item.inventoryId}
                        </Text>
                      </List.Item>
                    );
                  }}
                />
              </div>
            )}

            {batchResult.results.failed.length > 0 && (
              <div>
                <Text strong className="text-red-600">
                  <CloseCircleOutlined className="mr-1" />
                  {t('listingManagement.batchFailedItems')} ({batchResult.failedCount})
                </Text>
                <List
                  size="small"
                  className="mt-2 max-h-48 overflow-auto"
                  dataSource={batchResult.results.failed}
                  renderItem={(item) => {
                    const config = listingConfigs.find((c) => c.id === item.inventoryId);
                    return (
                      <List.Item className="py-1 flex-col items-start">
                        <Text type="danger">
                          {config
                            ? `${config.product.name} - ${config.productSku.sizeValue}${config.productSku.sizeUnit}`
                            : item.inventoryId}
                        </Text>
                        <Text type="secondary" className="text-xs">
                          {item.error}
                        </Text>
                      </List.Item>
                    );
                  }}
                />
              </div>
            )}
          </div>
        )}
      </Modal>
    </div>
  );
}
