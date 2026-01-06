import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal, Form, Input, InputNumber, Select, App } from 'antd';
import { productApi, SIZE_UNITS, CURRENCIES, type ProductSku, type SizeUnit, type Currency } from '@/lib/product-api';

interface SkuFormModalProps {
  open: boolean;
  productId: string;
  sku: ProductSku | null;
  existingCurrency?: Currency; // Currency from existing SKUs
  onClose: () => void;
  onSuccess: () => void;
}

interface FormValues {
  sizeUnit?: SizeUnit;
  sizeValue?: string;
  price: number;  // 参考价
  originalPrice?: number;  // 发售价
  currency: Currency;  // 币种
  barcode?: string;  // 条码 (UPC/EAN)
}

export function SkuFormModal({ open, productId, sku, existingCurrency, onClose, onSuccess }: SkuFormModalProps) {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [form] = Form.useForm<FormValues>();
  const [loading, setLoading] = useState(false);

  const isEdit = !!sku;
  // Currency is locked if editing or if there's an existing currency from other SKUs
  const currencyLocked = isEdit || !!existingCurrency;
  const defaultCurrency = existingCurrency || 'USD';

  const selectedCurrency = Form.useWatch('currency', form) || defaultCurrency;
  const currencySymbol = CURRENCIES.find((c) => c.value === selectedCurrency)?.symbol || '$';

  useEffect(() => {
    if (open) {
      if (sku) {
        form.setFieldsValue({
          sizeUnit: sku.sizeUnit || undefined,
          sizeValue: sku.sizeValue || undefined,
          price: parseFloat(sku.price),
          originalPrice: sku.originalPrice ? parseFloat(sku.originalPrice) : undefined,
          currency: sku.currency || 'USD',
          barcode: sku.barcode || undefined,
        });
      } else {
        form.resetFields();
        form.setFieldsValue({ currency: defaultCurrency });
      }
    }
  }, [open, sku, form, defaultCurrency]);

  const handleSubmit = async () => {
    try {
      const values = await form.validateFields();
      setLoading(true);

      const data = {
        ...values,
        price: String(values.price),
        originalPrice: values.originalPrice ? String(values.originalPrice) : undefined,
        currency: values.currency,
        barcode: values.barcode || undefined,
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
            <InputNumber min={0} precision={2} prefix={currencySymbol} style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item name="originalPrice" label={t('products.originalPrice')}>
            <InputNumber min={0} precision={2} prefix={currencySymbol} style={{ width: '100%' }} />
          </Form.Item>
        </div>

        <div className="grid grid-cols-2 gap-4">
          <Form.Item
            name="currency"
            label={t('products.currency')}
            tooltip={currencyLocked ? t('products.currencyLocked') : undefined}
            rules={[{ required: true, message: t('products.currencyRequired') }]}
          >
            <Select
              options={CURRENCIES.map((c) => ({ label: c.label, value: c.value }))}
              disabled={currencyLocked}
            />
          </Form.Item>
          <Form.Item name="barcode" label={t('products.barcode')}>
            <Input placeholder="UPC / EAN" maxLength={50} />
          </Form.Item>
        </div>
      </Form>
    </Modal>
  );
}
