import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal, Form, Input, DatePicker, App, Select, Spin } from 'antd';
import dayjs from 'dayjs';

import { inboundApi, type InboundOrder, type AvailableWarehouse } from '@/lib/inbound-api';

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
  const [warehouses, setWarehouses] = useState<AvailableWarehouse[]>([]);
  const [warehousesLoading, setWarehousesLoading] = useState(false);

  const isEdit = !!order;

  // Fetch available warehouses when modal opens
  useEffect(() => {
    if (open) {
      setWarehousesLoading(true);
      inboundApi
        .getAvailableWarehouses()
        .then((data) => {
          setWarehouses(data);
        })
        .catch(() => {
          message.error(t('common.error'));
        })
        .finally(() => {
          setWarehousesLoading(false);
        });
    }
  }, [open, message, t]);

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

  return (
    <Modal
      title={isEdit ? t('inventory.editOrder') : t('inventory.createOrder')}
      open={open}
      onCancel={onClose}
      onOk={handleSubmit}
      confirmLoading={loading}
      destroyOnClose
      width={500}
    >
      <Spin spinning={warehousesLoading}>
        <Form form={form} layout="vertical" className="mt-4">
          <Form.Item
            name="warehouseId"
            label={t('inventory.warehouse')}
            rules={[{ required: true, message: t('inventory.warehouseRequired') }]}
          >
            <Select
              placeholder={t('inventory.warehouseRequired')}
              loading={warehousesLoading}
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
