import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal, Form, Input, Avatar, Space, Descriptions, App, Radio, Select } from 'antd';
import { ShopOutlined } from '@ant-design/icons';

import {
  merchantChannelApi,
  type AvailableSalesChannel,
  type FulfillmentType
} from '@/lib/merchant-channel-api';
import { warehouseApi, type Warehouse } from '@/lib/warehouse-api';

const { TextArea } = Input;

interface Props {
  open: boolean;
  channel: AvailableSalesChannel | null;
  onClose: () => void;
  onSuccess: () => void;
}

export function ApplyChannelModal({ open, channel, onClose, onSuccess }: Props) {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);
  const [warehouses, setWarehouses] = useState<Warehouse[]>([]);
  const [fulfillmentType, setFulfillmentType] = useState<FulfillmentType>('consignment');

  useEffect(() => {
    if (open) {
      // Load active warehouses
      warehouseApi.getWarehouses({ status: 'active', limit: 100 })
        .then(response => setWarehouses(response.data))
        .catch(() => setWarehouses([]));

      // Reset form and state
      form.resetFields();
      setFulfillmentType('consignment');
    }
  }, [open, form]);

  const handleSubmit = async () => {
    if (!channel) return;

    try {
      const values = await form.validateFields();
      setLoading(true);

      await merchantChannelApi.applyChannel({
        salesChannelId: channel.id,
        fulfillmentType: values.fulfillmentType,
        pricingModel: values.pricingModel,
        defaultWarehouseId: values.defaultWarehouseId,
        remark: values.remark,
      });
      message.success(t('myChannels.applicationSubmitted'));
      form.resetFields();
      onSuccess();
    } catch (error) {
      const err = error as { error?: string };
      if (err.error) {
        message.error(err.error);
      }
    } finally {
      setLoading(false);
    }
  };

  const handleCancel = () => {
    form.resetFields();
    onClose();
  };

  if (!channel) return null;

  return (
    <Modal
      title={t('myChannels.applyForChannel')}
      open={open}
      onOk={handleSubmit}
      onCancel={handleCancel}
      confirmLoading={loading}
      okText={t('myChannels.submitApplication')}
      cancelText={t('common.cancel')}
      width={560}
    >
      <div className="mb-6">
        <Descriptions column={1} bordered size="small">
          <Descriptions.Item label={t('channels.name')}>
            <Space>
              {channel.logoUrl ? (
                <Avatar src={channel.logoUrl} size={24} shape="square" />
              ) : (
                <Avatar icon={<ShopOutlined />} size={24} shape="square" />
              )}
              <span>{channel.name}</span>
              <code className="text-xs bg-gray-100 px-1 rounded">
                {channel.code}
              </code>
            </Space>
          </Descriptions.Item>
          {channel.description && (
            <Descriptions.Item label={t('channels.description')}>
              {channel.description}
            </Descriptions.Item>
          )}
        </Descriptions>
      </div>

      <Form
        form={form}
        layout="vertical"
        initialValues={{
          fulfillmentType: 'consignment',
          pricingModel: 'self_pricing',
        }}
      >
        <Form.Item
          name="fulfillmentType"
          label={t('merchantChannels.fulfillmentType')}
          rules={[{ required: true, message: t('merchantChannels.fulfillmentTypeRequired') }]}
        >
          <Radio.Group onChange={(e) => setFulfillmentType(e.target.value)}>
            <Radio.Button value="consignment">
              {t('merchantChannels.fulfillmentConsignment')}
              <div className="text-xs text-gray-500">
                {t('merchantChannels.fulfillmentConsignmentDesc')}
              </div>
            </Radio.Button>
            <Radio.Button value="self_fulfillment">
              {t('merchantChannels.fulfillmentSelfFulfillment')}
              <div className="text-xs text-gray-500">
                {t('merchantChannels.fulfillmentSelfFulfillmentDesc')}
              </div>
            </Radio.Button>
          </Radio.Group>
        </Form.Item>

        {fulfillmentType === 'consignment' && (
          <Form.Item
            name="defaultWarehouseId"
            label={t('merchantChannels.defaultWarehouse')}
            rules={[{ required: true, message: t('merchantChannels.warehouseRequired') }]}
          >
            <Select
              placeholder={t('merchantChannels.selectWarehouse')}
              showSearch
              optionFilterProp="label"
              options={warehouses.map(w => ({
                label: `${w.name} (${w.code})`,
                value: w.id,
              }))}
            />
          </Form.Item>
        )}

        <Form.Item
          name="pricingModel"
          label={t('merchantChannels.pricingModel')}
        >
          <Radio.Group>
            <Radio.Button value="self_pricing">
              {t('merchantChannels.pricingSelf')}
            </Radio.Button>
            <Radio.Button value="platform_managed">
              {t('merchantChannels.pricingPlatformManaged')}
            </Radio.Button>
          </Radio.Group>
        </Form.Item>

        <Form.Item
          name="remark"
          label={t('myChannels.applicationRemark')}
          extra={t('myChannels.applicationRemarkHint')}
        >
          <TextArea
            rows={3}
            maxLength={255}
            showCount
            placeholder={t('myChannels.applicationRemarkPlaceholder')}
          />
        </Form.Item>
      </Form>
    </Modal>
  );
}
