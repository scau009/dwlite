import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal, Form, Input, InputNumber, Switch, App } from 'antd';
import { productApi, type ProductSku } from '@/lib/product-api';

interface SkuFormModalProps {
  open: boolean;
  productId: string;
  sku: ProductSku | null;
  onClose: () => void;
  onSuccess: () => void;
}

interface FormValues {
  skuCode: string;
  colorCode?: string;
  sizeUnit?: string;
  sizeValue?: string;
  price: number;
  originalPrice?: number;
  costPrice?: number;
  isActive: boolean;
  sortOrder: number;
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
          skuCode: sku.skuCode,
          colorCode: sku.colorCode || undefined,
          sizeUnit: sku.sizeUnit || undefined,
          sizeValue: sku.sizeValue || undefined,
          price: parseFloat(sku.price),
          originalPrice: sku.originalPrice ? parseFloat(sku.originalPrice) : undefined,
          costPrice: sku.costPrice ? parseFloat(sku.costPrice) : undefined,
          isActive: sku.isActive,
          sortOrder: sku.sortOrder,
        });
      } else {
        form.resetFields();
        form.setFieldsValue({ isActive: true, sortOrder: 0 });
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
        costPrice: values.costPrice ? String(values.costPrice) : undefined,
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
        <Form.Item
          name="skuCode"
          label={t('products.skuCode')}
          rules={[{ required: true, message: t('products.skuCodeRequired') }]}
        >
          <Input placeholder="DR-2024SS-001-S-RED" disabled={isEdit} />
        </Form.Item>

        <div className="grid grid-cols-3 gap-4">
          <Form.Item name="colorCode" label={t('products.colorCode')}>
            <Input placeholder="RED" />
          </Form.Item>
          <Form.Item name="sizeUnit" label={t('products.sizeUnit')}>
            <Input placeholder="EU" />
          </Form.Item>
          <Form.Item name="sizeValue" label={t('products.sizeValue')}>
            <Input placeholder="38" />
          </Form.Item>
        </div>

        <div className="grid grid-cols-3 gap-4">
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
          <Form.Item name="costPrice" label={t('products.costPrice')}>
            <InputNumber min={0} precision={2} prefix="¥" style={{ width: '100%' }} />
          </Form.Item>
        </div>

        <div className="grid grid-cols-2 gap-4">
          <Form.Item name="sortOrder" label={t('products.sortOrder')}>
            <InputNumber min={0} style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item name="isActive" label={t('products.status')} valuePropName="checked">
            <Switch
              checkedChildren={t('products.active')}
              unCheckedChildren={t('products.inactive')}
            />
          </Form.Item>
        </div>
      </Form>
    </Modal>
  );
}
