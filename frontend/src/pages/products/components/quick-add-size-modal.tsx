import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal, Form, Select, InputNumber, App, Alert } from 'antd';
import { productApi, type SizeUnit } from '@/lib/product-api';

// Quick add only supports US, EU, UK (not CM)
const QUICK_SIZE_UNITS: { value: SizeUnit; label: string }[] = [
  { value: 'US', label: 'US (美码)' },
  { value: 'EU', label: 'EU (欧码)' },
  { value: 'UK', label: 'UK (英码)' },
];

interface QuickAddSizeModalProps {
  open: boolean;
  productId: string;
  onClose: () => void;
  onSuccess: () => void;
}

interface FormValues {
  sizeUnit: SizeUnit;
  price: number;
  originalPrice?: number;
}

export function QuickAddSizeModal({
  open,
  productId,
  onClose,
  onSuccess,
}: QuickAddSizeModalProps) {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [form] = Form.useForm<FormValues>();
  const [loading, setLoading] = useState(false);

  const handleSubmit = async () => {
    try {
      const values = await form.validateFields();
      setLoading(true);

      const result = await productApi.batchCreateSkus(productId, {
        sizeUnit: values.sizeUnit,
        price: String(values.price),
        originalPrice: values.originalPrice ? String(values.originalPrice) : undefined,
      });

      // Show success message with details
      if (result.skippedCount > 0) {
        message.success(
          t('products.quickAddPartialSuccess', {
            created: result.createdCount,
            skipped: result.skippedCount,
          })
        );
      } else {
        message.success(
          t('products.quickAddSuccess', { count: result.createdCount })
        );
      }

      form.resetFields();
      onSuccess();
    } catch (error) {
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

  return (
    <Modal
      title={t('products.quickAddSize')}
      open={open}
      onCancel={handleClose}
      onOk={handleSubmit}
      confirmLoading={loading}
      okText={t('common.confirm')}
      cancelText={t('common.cancel')}
      destroyOnClose
      width={500}
    >
      <div className="mb-4">
        <Alert
            message={t('products.quickAddHint')}
            type="info"
            showIcon
        />
      </div>

      <Form form={form} layout="vertical" className="mt-4">
        <Form.Item
          name="sizeUnit"
          label={t('products.sizeUnit')}
          rules={[{ required: true, message: t('products.sizeUnitRequired') }]}
        >
          <Select
            placeholder={t('products.selectSizeUnit')}
            options={QUICK_SIZE_UNITS.map((u) => ({
              label: u.label,
              value: u.value,
            }))}
          />
        </Form.Item>

        <div className="grid grid-cols-2 gap-4">
          <Form.Item
            name="price"
            label={t('products.price')}
            rules={[{ required: true, message: t('products.priceRequired') }]}
          >
            <InputNumber min={0} precision={2} prefix="¥" style={{ width: '100%' }} />
          </Form.Item>

          <Form.Item name="originalPrice" label={t('products.originalPrice')}>
            <InputNumber min={0} precision={2} prefix="¥" style={{ width: '100%' }} />
          </Form.Item>
        </div>
      </Form>
    </Modal>
  );
}
