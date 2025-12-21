import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal, Form, Input, InputNumber, Select, App } from 'antd';
import { productApi, SIZE_UNITS, type ProductSku, type SizeUnit } from '@/lib/product-api';

interface SkuFormModalProps {
  open: boolean;
  productId: string;
  sku: ProductSku | null;
  onClose: () => void;
  onSuccess: () => void;
}

interface FormValues {
  sizeUnit?: SizeUnit;
  sizeValue?: string;
  price: number;  // 参考价
  originalPrice?: number;  // 发售价
}

export function SkuFormModal({ open, productId, sku, onClose, onSuccess }: SkuFormModalProps) {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [form] = Form.useForm<FormValues>();
  const [loading, setLoading] = useState(false);

  const isEdit = !!sku;

  useEffect(() => {
    if (open) {
      if (sku) {
        form.setFieldsValue({
          sizeUnit: sku.sizeUnit || undefined,
          sizeValue: sku.sizeValue || undefined,
          price: parseFloat(sku.price),
          originalPrice: sku.originalPrice ? parseFloat(sku.originalPrice) : undefined,
        });
      } else {
        form.resetFields();
      }
    }
  }, [open, sku, form]);

  const handleSubmit = async () => {
    try {
      const values = await form.validateFields();
      setLoading(true);

      const data = {
        ...values,
        price: String(values.price),
        originalPrice: values.originalPrice ? String(values.originalPrice) : undefined,
      };

      if (isEdit) {
        await productApi.updateSku(productId, sku!.id, data);
        message.success(t('products.skuUpdated'));
      } else {
        await productApi.createSku(productId, data);
        message.success(t('products.skuCreated'));
      }

      onSuccess();
    } catch (error) {
      if (error && typeof error === 'object' && 'errorFields' in error) return;
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setLoading(false);
    }
  };

  return (
    <Modal
      title={isEdit ? t('products.editSku') : t('products.addSku')}
      open={open}
      onCancel={onClose}
      onOk={handleSubmit}
      confirmLoading={loading}
      okText={t('common.save')}
      cancelText={t('common.cancel')}
      destroyOnClose
      width={600}
    >
      <Form form={form} layout="vertical" className="mt-4">
        <div className="grid grid-cols-2 gap-4">
          <Form.Item name="sizeUnit" label={t('products.sizeUnit')}>
            <Select
              placeholder={t('products.selectSizeUnit')}
              options={SIZE_UNITS.map((u) => ({ label: u.label, value: u.value }))}
              allowClear
            />
          </Form.Item>
          <Form.Item name="sizeValue" label={t('products.sizeValue')}>
            <Input placeholder="38, 39, 40, S, M, L..." />
          </Form.Item>
        </div>

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
