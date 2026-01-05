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
} from 'antd';
import { ArrowLeftOutlined, ShopOutlined } from '@ant-design/icons';

import {
  merchantListingApi,
  type MerchantListing,
} from '@/lib/merchant-listing-api';

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

export function EditListingPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { id } = useParams<{ id: string }>();
  const { message } = App.useApp();
  const [form] = Form.useForm();

  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [listing, setListing] = useState<MerchantListing | null>(null);

  useEffect(() => {
    if (id) {
      loadListing();
    }
  }, [id]);

  const loadListing = async () => {
    if (!id) return;
    setLoading(true);
    try {
      const result = await merchantListingApi.getListing(id);
      setListing(result.data);
      form.setFieldsValue({
        price: parseFloat(result.data.price),
        compareAtPrice: result.data.compareAtPrice ? parseFloat(result.data.compareAtPrice) : undefined,
        allocatedQuantity: result.data.allocatedQuantity,
        remark: result.data.remark,
      });
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
      navigate('/channels/listings');
    } finally {
      setLoading(false);
    }
  };

  const handleSubmit = async () => {
    if (!id || !listing) return;

    try {
      const values = await form.validateFields();
      setSubmitting(true);

      await merchantListingApi.updateListing(id, {
        price: values.price.toString(),
        compareAtPrice: values.compareAtPrice ? values.compareAtPrice.toString() : undefined,
        allocatedQuantity: listing.allocationMode === 'dedicated' ? values.allocatedQuantity : undefined,
        remark: values.remark,
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

  const maxAllocatedQuantity = listing.merchantInventory.quantityAvailable -
    listing.merchantInventory.quantityAllocated +
    (listing.allocatedQuantity || 0);

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
          {t('listingManagement.editListing')}
        </h1>
      </div>

      <Card title={t('listingManagement.listingInfo')}>
        <Descriptions column={2} bordered size="small">
          <Descriptions.Item label={t('listingManagement.product')} span={2}>
            <Space>
              {listing.product.imageUrl ? (
                <Avatar src={listing.product.imageUrl} size={40} shape="square" />
              ) : (
                <Avatar size={40} shape="square">
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
                <Avatar src={listing.salesChannel.logoUrl} size={24} shape="square" />
              ) : (
                <Avatar icon={<ShopOutlined />} size={24} shape="square" />
              )}
              {listing.salesChannel.name}
            </Space>
          </Descriptions.Item>
          <Descriptions.Item label={t('listingManagement.status')}>
            <Tag color={statusColorMap[listing.status]}>
              {t(`listingManagement.status${listing.status.charAt(0).toUpperCase() + listing.status.slice(1)}`)}
            </Tag>
          </Descriptions.Item>
          <Descriptions.Item label={t('listingManagement.fulfillmentType')}>
            <Tag color={fulfillmentColorMap[listing.fulfillmentType]}>
              {listing.fulfillmentType === 'consignment'
                ? t('merchantChannels.fulfillmentConsignment')
                : t('merchantChannels.fulfillmentSelfFulfillment')}
            </Tag>
            <span className="text-xs text-gray-400 ml-2">
              ({t('listingManagement.cannotEditFulfillmentType')})
            </span>
          </Descriptions.Item>
          <Descriptions.Item label={t('listingManagement.pricingModel')}>
            <Tag color={pricingColorMap[listing.pricingModel]}>
              {listing.pricingModel === 'self_pricing'
                ? t('listingManagement.selfPricing')
                : t('listingManagement.platformManaged')}
            </Tag>
            <span className="text-xs text-gray-400 ml-2">
              ({t('listingManagement.cannotEditPricingModel')})
            </span>
          </Descriptions.Item>
          <Descriptions.Item label={t('listingManagement.allocationMode')}>
            <Tag>
              {listing.allocationMode === 'shared'
                ? t('listingManagement.allocationModeShared')
                : t('listingManagement.allocationModeDedicated')}
            </Tag>
          </Descriptions.Item>
          <Descriptions.Item label={t('listingManagement.warehouse')}>
            {listing.merchantInventory.warehouse.name} ({listing.merchantInventory.warehouse.code})
          </Descriptions.Item>
          <Descriptions.Item label={t('listingManagement.inventoryQuantity')}>
            {t('listingManagement.available')}: {listing.merchantInventory.quantityAvailable} /
            {t('listingManagement.allocated')}: {listing.merchantInventory.quantityAllocated}
          </Descriptions.Item>
          <Descriptions.Item label={t('listingManagement.soldQuantity')}>
            {listing.soldQuantity}
          </Descriptions.Item>
        </Descriptions>
      </Card>

      <Card title={t('listingManagement.editableFields')}>
        <Form
          form={form}
          layout="vertical"
          style={{ maxWidth: 500 }}
        >
          <Form.Item
            name="price"
            label={t('listingManagement.price')}
            rules={[
              { required: true, message: t('listingManagement.priceRequired') },
              { type: 'number', min: 0.01, message: t('listingManagement.priceInvalid') },
            ]}
          >
            <InputNumber
              prefix="¥"
              precision={2}
              min={0.01}
              style={{ width: '100%' }}
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
              prefix="¥"
              precision={2}
              min={0.01}
              style={{ width: '100%' }}
            />
          </Form.Item>

          {listing.allocationMode === 'dedicated' && (
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
              />
            </Form.Item>
          )}

          <Form.Item
            name="remark"
            label={t('listingManagement.remark')}
          >
            <Input.TextArea rows={3} maxLength={500} showCount />
          </Form.Item>

          <Form.Item>
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
    </div>
  );
}
