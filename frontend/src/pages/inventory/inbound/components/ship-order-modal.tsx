import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal, Form, Input, InputNumber, DatePicker, App, Select, Divider } from 'antd';

import { inboundApi } from '@/lib/inbound-api';
import { commonApi, type Carrier } from '@/lib/common-api';

interface ShipOrderModalProps {
  open: boolean;
  orderId: string;
  onClose: () => void;
  onSuccess: () => void;
}

export function ShipOrderModal({
  open,
  orderId,
  onClose,
  onSuccess,
}: ShipOrderModalProps) {
  const { t } = useTranslation();
  const [form] = Form.useForm();
  const { message } = App.useApp();
  const [loading, setLoading] = useState(false);
  const [carriers, setCarriers] = useState<Carrier[]>([]);
  const [carriersLoading, setCarriersLoading] = useState(false);

  useEffect(() => {
    if (open) {
      loadCarriers();
    }
  }, [open]);

  const loadCarriers = async () => {
    try {
      setCarriersLoading(true);
      const data = await commonApi.getCarrierOptions();
      setCarriers(data);
    } catch {
      // Fallback to empty array if API fails
      setCarriers([]);
    } finally {
      setCarriersLoading(false);
    }
  };

  const handleSubmit = async () => {
    try {
      const values = await form.validateFields();
      setLoading(true);

      const carrier = carriers.find((c) => c.code === values.carrierCode);

      await inboundApi.shipInboundOrder(orderId, {
        carrierCode: values.carrierCode,
        carrierName: carrier?.name || values.carrierName,
        trackingNumber: values.trackingNumber,
        senderName: values.senderName,
        senderPhone: values.senderPhone,
        senderAddress: values.senderAddress,
        boxCount: values.boxCount,
        totalWeight: values.totalWeight ? String(values.totalWeight) : undefined,
        estimatedArrivalDate: values.estimatedArrivalDate
          ? values.estimatedArrivalDate.format('YYYY-MM-DD')
          : undefined,
      });

      message.success(t('inventory.orderShipped'));
      form.resetFields();
      onSuccess();
    } catch (error) {
      if (error && typeof error === 'object' && 'errorFields' in error) {
        return;
      }
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setLoading(false);
    }
  };

  const handleClose = () => {
    form.resetFields();
    onClose();
  };

  const carrierCode = Form.useWatch('carrierCode', form);

  return (
    <Modal
      title={t('inventory.shipOrder')}
      open={open}
      onCancel={handleClose}
      onOk={handleSubmit}
      confirmLoading={loading}
      destroyOnClose
      width={600}
    >
      <Form form={form} layout="vertical" className="mt-4">
        {/* Logistics Info */}
        <div className="mb-2 font-medium text-gray-600">{t('inventory.logisticsInfo')}</div>

        <div className="grid grid-cols-2 gap-4">
          <Form.Item
            name="carrierCode"
            label={t('inventory.carrier')}
            rules={[{ required: true, message: t('inventory.carrierRequired') }]}
          >
            <Select
              placeholder={t('inventory.selectCarrier')}
              loading={carriersLoading}
              options={carriers.map((c) => ({
                value: c.code,
                label: c.name,
              }))}
            />
          </Form.Item>

          {carrierCode === 'OTHER' && (
            <Form.Item
              name="carrierName"
              label={t('inventory.carrierName')}
              rules={[{ required: true, message: t('inventory.carrierNameRequired') }]}
            >
              <Input placeholder={t('inventory.enterCarrierName')} />
            </Form.Item>
          )}

          <Form.Item
            name="trackingNumber"
            label={t('inventory.trackingNumber')}
            rules={[{ required: true, message: t('inventory.trackingNumberRequired') }]}
          >
            <Input placeholder={t('inventory.enterTrackingNumber')} />
          </Form.Item>
        </div>

        <div className="grid grid-cols-3 gap-4">
          <Form.Item
            name="boxCount"
            label={t('inventory.boxCount')}
            rules={[
              { required: true, message: t('inventory.boxCountRequired') },
              { type: 'number', min: 1, message: t('inventory.boxCountMin') },
            ]}
          >
            <InputNumber min={1} precision={0} style={{ width: '100%' }} />
          </Form.Item>

          <Form.Item name="totalWeight" label={t('inventory.totalWeight')}>
            <InputNumber min={0} precision={2} addonAfter="kg" style={{ width: '100%' }} />
          </Form.Item>

          <Form.Item name="estimatedArrivalDate" label={t('inventory.estimatedArrivalDate')}>
            <DatePicker style={{ width: '100%' }} />
          </Form.Item>
        </div>

        <Divider />

        {/* Sender Info */}
        <div className="mb-2 font-medium text-gray-600">{t('inventory.senderInfo')}</div>

        <div className="grid grid-cols-2 gap-4">
          <Form.Item
            name="senderName"
            label={t('inventory.senderName')}
            rules={[{ required: true, message: t('inventory.senderNameRequired') }]}
          >
            <Input placeholder={t('inventory.enterSenderName')} />
          </Form.Item>

          <Form.Item
            name="senderPhone"
            label={t('inventory.senderPhone')}
            rules={[{ required: true, message: t('inventory.senderPhoneRequired') }]}
          >
            <Input placeholder={t('inventory.enterSenderPhone')} />
          </Form.Item>
        </div>

        <Form.Item
          name="senderAddress"
          label={t('inventory.senderAddress')}
          rules={[{ required: true, message: t('inventory.senderAddressRequired') }]}
        >
          <Input.TextArea
            rows={2}
            placeholder={t('inventory.enterSenderAddress')}
            maxLength={500}
            showCount
          />
        </Form.Item>
      </Form>
    </Modal>
  );
}
