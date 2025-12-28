import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router';
import { useTranslation } from 'react-i18next';
import {
  Modal,
  Select,
  Button,
  Space,
  App,
  Form,
  Input,
} from 'antd';

import { outboundApi } from '@/lib/outbound-api';
import {
  merchantInventoryApi,
  type InventoryWarehouse,
} from '@/lib/inbound-api';

interface CreateOutboundModalProps {
  open: boolean;
  onClose: () => void;
  onSuccess: () => void;
}

interface ReceiverFormValues {
  warehouseId: string;
  receiverName: string;
  receiverPhone: string;
  receiverAddress: string;
  receiverPostalCode?: string;
  remark?: string;
}

export function CreateOutboundModal({
  open,
  onClose,
  onSuccess,
}: CreateOutboundModalProps) {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { message } = App.useApp();
  const [form] = Form.useForm<ReceiverFormValues>();

  const [warehouses, setWarehouses] = useState<InventoryWarehouse[]>([]);
  const [warehouseLoading, setWarehouseLoading] = useState(false);
  const [submitting, setSubmitting] = useState(false);

  // Load warehouses when modal opens
  useEffect(() => {
    if (open) {
      form.resetFields();
      loadWarehouses();
    }
  }, [open]);

  const loadWarehouses = async () => {
    setWarehouseLoading(true);
    try {
      const response = await merchantInventoryApi.getWarehouses();
      setWarehouses(response.data || []);
    } catch (error) {
      console.error('Failed to load warehouses:', error);
      message.error(t('common.error'));
    } finally {
      setWarehouseLoading(false);
    }
  };

  const handleSubmit = async () => {
    try {
      const values = await form.validateFields();
      setSubmitting(true);

      const result = await outboundApi.createOutboundOrder({
        warehouseId: values.warehouseId,
        receiverName: values.receiverName,
        receiverPhone: values.receiverPhone,
        receiverAddress: values.receiverAddress,
        receiverPostalCode: values.receiverPostalCode,
        remark: values.remark,
      });

      message.success(t('outbound.createSuccess'));
      onSuccess();

      // Navigate to order detail to add items
      navigate(`/inventory/outbound/detail/${result.data.id}`);
    } catch (error) {
      if (error && typeof error === 'object' && 'errorFields' in error) {
        // Form validation error
        return;
      }
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <Modal
      title={t('outbound.createOutbound')}
      open={open}
      onCancel={onClose}
      width={600}
      footer={null}
      destroyOnClose
    >
      <div className="py-4">
        <div className="mb-4 text-sm text-gray-500">
          {t('outbound.createDraftDescription')}
        </div>

        <Form form={form} layout="vertical">
          <Form.Item
            name="warehouseId"
            label={t('outbound.selectWarehouse')}
            rules={[{ required: true, message: t('outbound.selectWarehouseRequired') }]}
          >
            <Select
              placeholder={t('outbound.selectWarehousePlaceholder')}
              loading={warehouseLoading}
              options={warehouses.map((w) => ({
                label: `${w.name} (${w.code})`,
                value: w.id,
              }))}
            />
          </Form.Item>

          <div className="text-base font-medium mb-4 mt-6">{t('outbound.receiverInfo')}</div>

          <div className="grid grid-cols-2 gap-4">
            <Form.Item
              name="receiverName"
              label={t('outbound.receiverName')}
              rules={[{ required: true, message: t('outbound.receiverNameRequired') }]}
            >
              <Input placeholder={t('outbound.enterReceiverName')} />
            </Form.Item>

            <Form.Item
              name="receiverPhone"
              label={t('outbound.receiverPhone')}
              rules={[{ required: true, message: t('outbound.receiverPhoneRequired') }]}
            >
              <Input placeholder={t('outbound.enterReceiverPhone')} />
            </Form.Item>
          </div>

          <Form.Item
            name="receiverAddress"
            label={t('outbound.receiverAddress')}
            rules={[{ required: true, message: t('outbound.receiverAddressRequired') }]}
          >
            <Input.TextArea rows={2} placeholder={t('outbound.enterReceiverAddress')} />
          </Form.Item>

          <div className="grid grid-cols-2 gap-4">
            <Form.Item
              name="receiverPostalCode"
              label={t('outbound.receiverPostalCode')}
            >
              <Input placeholder={t('outbound.enterReceiverPostalCode')} />
            </Form.Item>
          </div>

          <Form.Item
            name="remark"
            label={t('outbound.remark')}
          >
            <Input.TextArea rows={2} placeholder={t('outbound.enterRemark')} />
          </Form.Item>
        </Form>

        <div className="flex justify-end mt-6">
          <Space>
            <Button onClick={onClose}>{t('common.cancel')}</Button>
            <Button
              type="primary"
              loading={submitting}
              onClick={handleSubmit}
            >
              {t('outbound.createDraft')}
            </Button>
          </Space>
        </div>
      </div>
    </Modal>
  );
}
