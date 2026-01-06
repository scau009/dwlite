import { useState, useEffect } from 'react';
import { useNavigate, useParams } from 'react-router';
import { useTranslation } from 'react-i18next';
import {
  Card,
  Button,
  Space,
  App,
  Form,
  InputNumber,
  Input,
  Spin,
  Descriptions,
  Tag,
  Avatar,
  Table,
  Typography,
  Collapse,
  Switch,
  Popover,
  Radio,
  Row,
  Col,
} from 'antd';
import type { ColumnsType } from 'antd/es/table';
import { ArrowLeftOutlined, ShopOutlined, InfoCircleOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';

import {
  merchantListingApi,
  getCurrencySymbol,
  type MerchantListing,
  type ListingOperationLog,
  type OperationType,
  type PricingDefaults,
  type AllocationDefaults,
  type RuleDetail,
  type AllocationMode,
} from '@/lib/merchant-listing-api';

const { Text } = Typography;

const statusColorMap: Record<string, string> = {
  draft: 'default',
  active: 'success',
  paused: 'warning',
  sold_out: 'error',
};

const fulfillmentColorMap: Record<string, string> = {
  consignment: 'blue',
  self_fulfillment: 'purple',
};

const pricingColorMap: Record<string, string> = {
  self_pricing: 'cyan',
  platform_managed: 'orange',
};

const operationColorMap: Record<OperationType, string> = {
  create: 'green',
  update_price: 'blue',
  update_compare_price: 'blue',
  update_allocation: 'orange',
  update_remark: 'default',
  activate: 'success',
  pause: 'warning',
  delete: 'error',
};

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
      <div key={rule.id} className="mb-2 p-2 bg-gray-50 dark:bg-gray-800 rounded text-xs">
        <div className="font-medium">{index + 1}. {rule.name}</div>
        <div className="text-gray-500 mt-1">
          <span className="font-mono">{rule.expression}</span>
        </div>
        <div className="mt-1 flex justify-between text-gray-400">
          <span>{t('listingManagement.inputValue')}: {type === 'pricing' ? currencySymbol : ''}{rule.inputValue}</span>
          <span>{t('listingManagement.outputValue')}: {type === 'pricing' ? currencySymbol : ''}{rule.outputValue}</span>
        </div>
      </div>
    ))}
    <div className="mt-2 pt-2 border-t border-gray-200 dark:border-gray-700">
      {type === 'pricing' && baseCost && (
        <div className="text-xs text-gray-500 mb-1">
          {t('listingManagement.baseCost')}: {currencySymbol}{baseCost}
        </div>
      )}
      <div className="font-medium text-sm">
        {type === 'pricing'
          ? `${t('listingManagement.calculatedPrice')}: ${currencySymbol}${finalValue}`
          : `${t('listingManagement.calculatedQuantity')}: ${finalValue}`}
      </div>
    </div>
  </div>
);

export function EditListingPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { id } = useParams<{ id: string }>();
  const { message } = App.useApp();
  const [form] = Form.useForm();

  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [listing, setListing] = useState<MerchantListing | null>(null);
  const [logs, setLogs] = useState<ListingOperationLog[]>([]);
  const [logsLoading, setLogsLoading] = useState(false);

  // Rule defaults and switches
  const [pricingDefaults, setPricingDefaults] = useState<PricingDefaults | null>(null);
  const [allocationDefaults, setAllocationDefaults] = useState<AllocationDefaults | null>(null);
  const [applyPriceRule, setApplyPriceRule] = useState(false);
  const [applyStockRule, setApplyStockRule] = useState(false);

  // Allocation mode state (editable)
  const [allocationMode, setAllocationMode] = useState<AllocationMode>('shared');

  useEffect(() => {
    if (id) {
      loadListing();
      loadLogs();
    }
  }, [id]);

  const loadListing = async () => {
    if (!id) return;
    setLoading(true);
    try {
      const result = await merchantListingApi.getListing(id);
      setListing(result.data);
      setAllocationMode(result.data.allocationMode);
      form.setFieldsValue({
        price: parseFloat(result.data.price),
        compareAtPrice: result.data.compareAtPrice ? parseFloat(result.data.compareAtPrice) : undefined,
        allocationMode: result.data.allocationMode,
        allocatedQuantity: result.data.allocatedQuantity,
        remark: result.data.remark,
      });

      // Fetch rule defaults for this listing
      try {
        const defaults = await merchantListingApi.calculateDefaults({
          merchantSalesChannelId: result.data.merchantSalesChannel.id,
          inventoryIds: [result.data.merchantInventory.id],
        });

        if (defaults.data.length > 0) {
          const inventoryDefaults = defaults.data[0];
          setPricingDefaults(inventoryDefaults.pricing);
          setAllocationDefaults(inventoryDefaults.allocation);

          // Set switch states based on whether listing has rule expressions
          setApplyPriceRule(!!result.data.priceRuleExpression);
          setApplyStockRule(!!result.data.stockRuleExpression);
        }
      } catch {
        // Silent fail for defaults - not critical
      }
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
      navigate('/channels/listings');
    } finally {
      setLoading(false);
    }
  };

  const loadLogs = async () => {
    if (!id) return;
    setLogsLoading(true);
    try {
      const result = await merchantListingApi.getListingLogs(id, 20);
      setLogs(result.data);
    } catch {
      // Silent fail
    } finally {
      setLogsLoading(false);
    }
  };

  const handleSubmit = async () => {
    if (!id || !listing) return;

    try {
      const values = await form.validateFields();
      setSubmitting(true);

      // Build rule expressions based on switch states
      const priceRuleExpression = applyPriceRule && pricingDefaults?.hasRules
        ? pricingDefaults.rules.map(r => r.expression).join('\n')
        : undefined;

      const stockRuleExpression = applyStockRule && allocationDefaults?.hasRules
        ? allocationDefaults.rules.map(r => r.expression).join('\n')
        : undefined;

      await merchantListingApi.updateListing(id, {
        price: values.price.toString(),
        compareAtPrice: values.compareAtPrice ? values.compareAtPrice.toString() : undefined,
        allocationMode: values.allocationMode,
        allocatedQuantity: values.allocationMode === 'dedicated' ? values.allocatedQuantity : undefined,
        remark: values.remark,
        priceRuleExpression,
        stockRuleExpression,
      });

      message.success(t('listingManagement.updated'));
      navigate('/channels/listings');
    } catch (error) {
      const err = error as { error?: string; errorFields?: unknown };
      if (!err.errorFields) {
        message.error(err.error || t('common.error'));
      }
    } finally {
      setSubmitting(false);
    }
  };

  const getOperationLabel = (operation: OperationType): string => {
    const labels: Record<OperationType, string> = {
      create: t('listingManagement.operationCreate'),
      update_price: t('listingManagement.operationUpdatePrice'),
      update_compare_price: t('listingManagement.operationUpdateComparePrice'),
      update_allocation: t('listingManagement.operationUpdateAllocation'),
      update_remark: t('listingManagement.operationUpdateRemark'),
      activate: t('listingManagement.operationActivate'),
      pause: t('listingManagement.operationPause'),
      delete: t('listingManagement.operationDelete'),
    };
    return labels[operation] || operation;
  };

  const formatChangeValue = (value: unknown): string => {
    if (value === null || value === undefined) return '-';
    if (typeof value === 'object') return JSON.stringify(value);
    return String(value);
  };

  const logColumns: ColumnsType<ListingOperationLog> = [
    {
      title: t('listingManagement.operationTime'),
      dataIndex: 'createdAt',
      width: 170,
      render: (val) => dayjs(val).format('YYYY-MM-DD HH:mm:ss'),
    },
    {
      title: t('listingManagement.operationType'),
      dataIndex: 'operation',
      width: 120,
      render: (val: OperationType) => (
        <Tag color={operationColorMap[val]}>{getOperationLabel(val)}</Tag>
      ),
    },
    {
      title: t('listingManagement.operator'),
      dataIndex: 'operatorEmail',
      width: 180,
      ellipsis: true,
    },
    {
      title: t('listingManagement.changeDetails'),
      dataIndex: 'changes',
      render: (_, record) => {
        if (!record.changes || (!record.changes.before && !record.changes.after)) {
          return <Text type="secondary">-</Text>;
        }

        return (
          <Collapse
            ghost
            size="small"
            items={[
              {
                key: 'changes',
                label: t('listingManagement.changeDetails'),
                children: (
                  <Descriptions size="small" column={1} bordered>
                    {record.changes.before &&
                      Object.entries(record.changes.before).map(([key, value]) => (
                        <Descriptions.Item
                          key={`before-${key}`}
                          label={
                            <span>
                              <Text type="secondary">{t('listingManagement.beforeChange')}</Text>
                              <Text className="ml-1">{key}</Text>
                            </span>
                          }
                        >
                          <Text delete type="secondary">
                            {formatChangeValue(value)}
                          </Text>
                        </Descriptions.Item>
                      ))}
                    {record.changes.after &&
                      Object.entries(record.changes.after).map(([key, value]) => (
                        <Descriptions.Item
                          key={`after-${key}`}
                          label={
                            <span>
                              <Text type="success">{t('listingManagement.afterChange')}</Text>
                              <Text className="ml-1">{key}</Text>
                            </span>
                          }
                        >
                          <Text strong>{formatChangeValue(value)}</Text>
                        </Descriptions.Item>
                      ))}
                  </Descriptions>
                ),
              },
            ]}
          />
        );
      },
    },
  ];

  if (loading) {
    return (
      <div className="flex justify-center items-center h-64">
        <Spin size="large" />
      </div>
    );
  }

  if (!listing) {
    return null;
  }

  // Calculate max allocatable quantity based on current mode
  // If currently shared (switching to dedicated), max is shareableQuantity
  // If already dedicated, max includes current allocation
  const maxAllocatedQuantity = listing.allocationMode === 'dedicated'
    ? listing.merchantInventory.quantityAvailable - listing.merchantInventory.quantityAllocated + (listing.allocatedQuantity || 0)
    : listing.merchantInventory.quantityAvailable - listing.merchantInventory.quantityAllocated;

  return (
    <div>
      {/* Header */}
      <div className="flex items-center gap-3 mb-3">
        <Button
          size="small"
          icon={<ArrowLeftOutlined />}
          onClick={() => navigate('/channels/listings')}
        >
          {t('common.back')}
        </Button>
        <span className="text-base font-medium">
          {t('listingManagement.editListing')}
        </span>
      </div>

      {/* Basic Info Card */}
      <Card title={t('listingManagement.listingInfo')} style={{ marginBottom: 16 }}>
        <Descriptions column={{ xs: 1, sm: 2, md: 3 }} >
          <Descriptions.Item label={t('listingManagement.product')} span={3}>
            <Space>
              {listing.product.imageUrl ? (
                <Avatar src={listing.product.imageUrl} size={48} shape="square" />
              ) : (
                <Avatar size={48} shape="square">
                  {listing.product.name.charAt(0)}
                </Avatar>
              )}
              <div>
                <div className="font-medium">{listing.product.name}</div>
                <div className="text-xs text-gray-500">
                  {listing.product.styleNumber} / {listing.productSku.sizeValue}
                  {listing.productSku.sizeUnit}
                </div>
              </div>
            </Space>
          </Descriptions.Item>
          <Descriptions.Item label={t('listingManagement.channel')}>
            <Space>
              {listing.salesChannel.logoUrl ? (
                <Avatar src={listing.salesChannel.logoUrl} size={20} shape="square" />
              ) : (
                <Avatar icon={<ShopOutlined />} size={20} shape="square" />
              )}
              {listing.salesChannel.name}
            </Space>
          </Descriptions.Item>
          <Descriptions.Item label={t('listingManagement.status')}>
            <Tag color={statusColorMap[listing.status]}>
              {t(`listingManagement.status${listing.status.charAt(0).toUpperCase() + listing.status.slice(1)}`)}
            </Tag>
          </Descriptions.Item>
          <Descriptions.Item label={t('listingManagement.warehouse')}>
            {listing.merchantInventory.warehouse.name}
          </Descriptions.Item>
          <Descriptions.Item label={t('listingManagement.fulfillmentType')}>
            <Tag color={fulfillmentColorMap[listing.fulfillmentType]}>
              {listing.fulfillmentType === 'consignment'
                ? t('merchantChannels.fulfillmentConsignment')
                : t('merchantChannels.fulfillmentSelfFulfillment')}
            </Tag>
          </Descriptions.Item>
          <Descriptions.Item label={t('listingManagement.pricingModel')}>
            <Tag color={pricingColorMap[listing.pricingModel]}>
              {listing.pricingModel === 'self_pricing'
                ? t('listingManagement.selfPricing')
                : t('listingManagement.platformManaged')}
            </Tag>
          </Descriptions.Item>
          <Descriptions.Item label={t('listingManagement.available')}>
            <span className="text-green-600 font-medium">
              {listing.merchantInventory.quantityAvailable}
            </span>
          </Descriptions.Item>
          <Descriptions.Item label={t('listingManagement.allocated')}>
            <span className="text-blue-600 font-medium">
              {listing.merchantInventory.quantityAllocated}
            </span>
          </Descriptions.Item>
          <Descriptions.Item label={t('listingManagement.soldQuantity')}>
            {listing.soldQuantity}
          </Descriptions.Item>
        </Descriptions>
      </Card>

      {/* Edit Form Card */}
      <Card title={t('listingManagement.editableFields')} style={{ marginBottom: 16 }}>
        <Form form={form} layout="vertical">
          <Row gutter={24}>
            {/* Left Column - Stock/Allocation */}
            <Col xs={24} md={12}>
              <div className="font-medium text-gray-500 mb-3">{t('listingManagement.stockConfig')}</div>

              <Form.Item
                name="allocationMode"
                label={t('listingManagement.allocationMode')}
              >
                <Radio.Group
                  onChange={(e) => {
                    const newMode = e.target.value as AllocationMode;
                    setAllocationMode(newMode);
                    if (newMode === 'shared') {
                      form.setFieldValue('allocatedQuantity', undefined);
                      setApplyStockRule(false);
                    }
                  }}
                >
                  <Radio value="shared">{t('listingManagement.allocationModeShared')}</Radio>
                  <Radio value="dedicated">{t('listingManagement.allocationModeDedicated')}</Radio>
                </Radio.Group>
              </Form.Item>

              {allocationMode === 'dedicated' && (
                <>
                  <Form.Item label={t('listingManagement.applyStockRule')}>
                    <Space>
                      {allocationDefaults?.hasRules ? (
                        <>
                          <Switch
                            checked={applyStockRule}
                            onChange={(checked) => {
                              setApplyStockRule(checked);
                              if (checked && allocationDefaults.calculatedQuantity) {
                                form.setFieldValue('allocatedQuantity', allocationDefaults.calculatedQuantity);
                              }
                            }}
                          />
                          <Popover
                            content={
                              <RuleDetailsContent
                                rules={allocationDefaults.rules}
                                finalValue={allocationDefaults.calculatedQuantity}
                                currencySymbol=""
                                type="allocation"
                                t={t}
                              />
                            }
                            title={t('listingManagement.stockRuleDetails')}
                            trigger="click"
                          >
                            <InfoCircleOutlined className="text-blue-500 cursor-pointer" />
                          </Popover>
                          {applyStockRule && (
                            <Text type="secondary">
                              {t('listingManagement.calculatedQuantity')}: {allocationDefaults.calculatedQuantity}
                            </Text>
                          )}
                        </>
                      ) : (
                        <Text type="secondary">-</Text>
                      )}
                    </Space>
                  </Form.Item>

                  <Form.Item
                    name="allocatedQuantity"
                    label={t('listingManagement.allocatedQuantity')}
                    extra={`${t('listingManagement.maxAllocatable')}: ${maxAllocatedQuantity}`}
                    rules={[
                      { required: true, message: t('listingManagement.allocatedQuantityRequired') },
                      {
                        type: 'number',
                        max: maxAllocatedQuantity,
                        message: t('listingManagement.allocatedQuantityExceeded'),
                      },
                    ]}
                  >
                    <InputNumber
                      min={1}
                      max={maxAllocatedQuantity}
                      style={{ width: '100%' }}
                      disabled={applyStockRule}
                    />
                  </Form.Item>
                </>
              )}
            </Col>

            {/* Right Column - Price */}
            <Col xs={24} md={12}>
              <div className="font-medium text-gray-500 mb-3">{t('listingManagement.priceConfig')}</div>

              <Form.Item label={t('listingManagement.applyPriceRule')}>
                <Space>
                  {pricingDefaults?.hasRules ? (
                    <>
                      <Switch
                        checked={applyPriceRule}
                        onChange={(checked) => {
                          setApplyPriceRule(checked);
                          if (checked && pricingDefaults.calculatedPrice) {
                            form.setFieldValue('price', parseFloat(pricingDefaults.calculatedPrice));
                          }
                        }}
                      />
                      <Popover
                        content={
                          <RuleDetailsContent
                            rules={pricingDefaults.rules}
                            baseCost={pricingDefaults.baseCost}
                            finalValue={pricingDefaults.calculatedPrice}
                            currencySymbol={getCurrencySymbol(listing.salesChannel.currency)}
                            type="pricing"
                            t={t}
                          />
                        }
                        title={t('listingManagement.priceRuleDetails')}
                        trigger="click"
                      >
                        <InfoCircleOutlined className="text-blue-500 cursor-pointer" />
                      </Popover>
                      {applyPriceRule && (
                        <Text type="secondary">
                          {t('listingManagement.calculatedPrice')}: {getCurrencySymbol(listing.salesChannel.currency)}{pricingDefaults.calculatedPrice}
                        </Text>
                      )}
                    </>
                  ) : (
                    <Text type="secondary">-</Text>
                  )}
                </Space>
              </Form.Item>

              <Form.Item
                name="price"
                label={t('listingManagement.price')}
                rules={[
                  { required: true, message: t('listingManagement.priceRequired') },
                  { type: 'number', min: 0.01, message: t('listingManagement.priceInvalid') },
                ]}
              >
                <InputNumber
                  prefix={getCurrencySymbol(listing.salesChannel.currency)}
                  precision={2}
                  min={0.01}
                  style={{ width: '100%' }}
                  disabled={applyPriceRule}
                />
              </Form.Item>

              <Form.Item
                name="compareAtPrice"
                label={t('listingManagement.compareAtPrice')}
                rules={[
                  { type: 'number', min: 0.01, message: t('listingManagement.priceInvalid') },
                ]}
              >
                <InputNumber
                  prefix={getCurrencySymbol(listing.salesChannel.currency)}
                  precision={2}
                  min={0.01}
                  style={{ width: '100%' }}
                />
              </Form.Item>
            </Col>
          </Row>

          {/* Bottom - Remark and Buttons */}
          <Form.Item
            name="remark"
            label={t('listingManagement.remark')}
          >
            <Input.TextArea rows={3} maxLength={500} showCount />
          </Form.Item>

          <Form.Item className="mb-0">
            <Space>
              <Button type="primary" loading={submitting} onClick={handleSubmit}>
                {t('common.save')}
              </Button>
              <Button onClick={() => navigate('/channels/listings')}>
                {t('common.cancel')}
              </Button>
            </Space>
          </Form.Item>
        </Form>
      </Card>

      {/* Operation Logs Card */}
      <Card title={t('listingManagement.operationLogs')} >
        <Table
          columns={logColumns}
          dataSource={logs}
          rowKey="id"
          loading={logsLoading}
          pagination={false}
          
          scroll={{ x: 700 }}
        />
      </Card>
    </div>
  );
}
