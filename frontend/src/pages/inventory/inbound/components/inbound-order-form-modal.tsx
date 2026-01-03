import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';
import { Modal, Form, Input, DatePicker, App, Select, Spin, Alert, Empty, Space } from 'antd';
import dayjs from 'dayjs';

import { inboundApi, type InboundOrder } from '@/lib/inbound-api';
import {
  merchantChannelApi,
  type MyMerchantChannel,
  type ChannelWarehouse,
} from '@/lib/merchant-channel-api';

interface InboundOrderFormModalProps {
  open: boolean;
  order: InboundOrder | null;
  onClose: () => void;
  onSuccess: () => void;
}

export function InboundOrderFormModal({
  open,
  order,
  onClose,
  onSuccess,
}: InboundOrderFormModalProps) {
  const { t } = useTranslation();
  const [form] = Form.useForm();
  const { message } = App.useApp();
  const [loading, setLoading] = useState(false);

  // Sales channels state
  const [channels, setChannels] = useState<MyMerchantChannel[]>([]);
  const [channelsLoading, setChannelsLoading] = useState(false);

  // Warehouses state
  const [warehouses, setWarehouses] = useState<ChannelWarehouse[]>([]);
  const [warehousesLoading, setWarehousesLoading] = useState(false);

  // Watch selected channel
  const selectedChannelId = Form.useWatch('salesChannelId', form);

  const isEdit = !!order;

  // Fetch active sales channels when modal opens
  useEffect(() => {
    if (open) {
      setChannelsLoading(true);
      merchantChannelApi
        .getMyChannels({ status: 'active', limit: 100 })
        .then((response) => {
          setChannels(response.data);
        })
        .catch(() => {
          message.error(t('common.error'));
        })
        .finally(() => {
          setChannelsLoading(false);
        });
    }
  }, [open, message, t]);

  // Fetch warehouses when selected channel changes
  useEffect(() => {
    if (selectedChannelId) {
      setWarehousesLoading(true);
      setWarehouses([]);
      form.setFieldValue('warehouseId', undefined);

      merchantChannelApi
        .getChannelWarehouses(selectedChannelId)
        .then((response) => {
          setWarehouses(response.data);
        })
        .catch(() => {
          message.error(t('common.error'));
        })
        .finally(() => {
          setWarehousesLoading(false);
        });
    } else {
      setWarehouses([]);
    }
  }, [selectedChannelId, form, message, t]);

  useEffect(() => {
    if (open) {
      if (order) {
        form.setFieldsValue({
          warehouseId: order.warehouse.id,
          expectedArrivalDate: order.expectedArrivalDate
            ? dayjs(order.expectedArrivalDate)
            : null,
          merchantNotes: '',
        });
      } else {
        form.resetFields();
      }
    }
  }, [open, order, form]);

  const handleSubmit = async () => {
    // Check if there are no warehouses available
    if (!isEdit && warehouses.length === 0) {
      message.error(t('inventory.noWarehousesAvailable'));
      return;
    }

    try {
      const values = await form.validateFields();
      setLoading(true);

      const data = {
        warehouseId: values.warehouseId,
        expectedArrivalDate: values.expectedArrivalDate
          ? values.expectedArrivalDate.format('YYYY-MM-DD')
          : undefined,
        merchantNotes: values.merchantNotes || undefined,
      };

      if (isEdit) {
        await inboundApi.updateInboundOrder(order!.id, data);
        message.success(t('inventory.orderUpdated'));
      } else {
        await inboundApi.createInboundOrder(data);
        message.success(t('inventory.orderCreated'));
      }

      onSuccess();
    } catch (error) {
      if (error && typeof error === 'object' && 'errorFields' in error) {
        // Form validation error
        return;
      }
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setLoading(false);
    }
  };

  // Check if form can be submitted
  const canSubmit = isEdit || (channels.length > 0 && (selectedChannelId ? warehouses.length > 0 : true));

  return (
    <Modal
      title={isEdit ? t('inventory.editOrder') : t('inventory.createOrder')}
      open={open}
      onCancel={onClose}
      onOk={handleSubmit}
      confirmLoading={loading}
      okButtonProps={{ disabled: !canSubmit }}
      destroyOnHidden
      width={500}
    >
      <Spin spinning={channelsLoading}>
        {/* No active channels alert */}
        {!channelsLoading && channels.length === 0 && !isEdit && (
          <Alert
            type="warning"
            showIcon
            className="mb-4"
            message={t('inventory.noActiveChannels')}
            description={
              <Space direction="vertical" size={4}>
                <span>{t('inventory.noActiveChannelsDesc')}</span>
                <Link to="/channels/available" onClick={onClose}>
                  {t('inventory.goToApplyChannels')}
                </Link>
              </Space>
            }
          />
        )}

        <Form form={form} layout="vertical" className="mt-4">
          {/* Sales Channel Selector - only show for new orders */}
          {!isEdit && (
            <Form.Item
              name="salesChannelId"
              label={t('inventory.salesChannel')}
              rules={[{ required: true, message: t('inventory.salesChannelRequired') }]}
            >
              <Select
                placeholder={t('inventory.selectSalesChannel')}
                loading={channelsLoading}
                disabled={channels.length === 0}
                showSearch
                optionFilterProp="label"
                options={channels.map((c) => ({
                  value: c.id,
                  label: c.salesChannel.name,
                }))}
              />
            </Form.Item>
          )}

          {/* Warehouse Selector */}
          <Form.Item
            name="warehouseId"
            label={t('inventory.warehouse')}
            rules={[{ required: true, message: t('inventory.warehouseRequired') }]}
          >
            <Select
              placeholder={
                !selectedChannelId && !isEdit
                  ? t('inventory.selectChannelFirst')
                  : t('inventory.warehouseRequired')
              }
              loading={warehousesLoading}
              disabled={!isEdit && (!selectedChannelId || warehouses.length === 0)}
              notFoundContent={
                selectedChannelId && !warehousesLoading && warehouses.length === 0 ? (
                  <Empty
                    image={Empty.PRESENTED_IMAGE_SIMPLE}
                    description={t('inventory.noWarehousesForChannel')}
                  />
                ) : undefined
              }
              options={warehouses.map((w) => ({
                value: w.id,
                label: w.name,
                title: w.fullAddress || `${w.province || ''}${w.city || ''}`,
              }))}
              optionRender={(option) => {
                const warehouse = warehouses.find((w) => w.id === option.value);
                return (
                  <div>
                    <div>{option.label}</div>
                    {warehouse?.fullAddress && (
                      <div className="text-xs text-gray-400">{warehouse.fullAddress}</div>
                    )}
                  </div>
                );
              }}
            />
          </Form.Item>

          {/* Show warning if no warehouses for selected channel */}
          {selectedChannelId && !warehousesLoading && warehouses.length === 0 && (
            <Alert
              type="error"
              showIcon
              className="mb-4"
              message={t('inventory.noWarehousesForChannelError')}
              description={t('inventory.noWarehousesForChannelDesc')}
            />
          )}

          <Form.Item
            name="expectedArrivalDate"
            label={t('inventory.expectedArrivalDate')}
          >
            <DatePicker className="w-full" />
          </Form.Item>

          <Form.Item name="merchantNotes" label={t('inventory.merchantNotes')}>
            <Input.TextArea
              rows={3}
              placeholder={t('inventory.merchantNotes')}
              maxLength={500}
              showCount
            />
          </Form.Item>
        </Form>
      </Spin>
    </Modal>
  );
}
