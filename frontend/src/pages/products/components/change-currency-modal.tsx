import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal, Form, Select, App, Alert } from 'antd';
import { productApi, CURRENCIES, type Currency } from '@/lib/product-api';

interface ChangeCurrencyModalProps {
  open: boolean;
  productId: string;
  currentCurrency: Currency;
  skuCount: number;
  onClose: () => void;
  onSuccess: () => void;
}

export function ChangeCurrencyModal({
  open,
  productId,
  currentCurrency,
  skuCount,
  onClose,
  onSuccess,
}: ChangeCurrencyModalProps) {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [form] = Form.useForm<{ currency: Currency }>();
  const [loading, setLoading] = useState(false);

  const handleSubmit = async () => {
    try {
      const values = await form.validateFields();

      if (values.currency === currentCurrency) {
        message.info(t('products.currencyUnchanged'));
        onClose();
        return;
      }

      setLoading(true);
      const result = await productApi.updateProductCurrency(productId, values.currency);
      message.success(result.message);
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

  return (
    <Modal
      title={t('products.changeCurrency')}
      open={open}
      onCancel={onClose}
      onOk={handleSubmit}
      confirmLoading={loading}
      okText={t('common.confirm')}
      cancelText={t('common.cancel')}
      destroyOnClose
      width={400}
    >
      <Alert
        type="info"
        showIcon
        style={{ marginBottom: 16 }}
        message={t('products.changeCurrencyInfo', { count: skuCount })}
      />

      <Form
        form={form}
        layout="vertical"
        initialValues={{ currency: currentCurrency }}
      >
        <Form.Item
          name="currency"
          label={t('products.currency')}
          rules={[{ required: true, message: t('products.currencyRequired') }]}
        >
          <Select
            options={CURRENCIES.map((c) => ({ label: c.label, value: c.value }))}
          />
        </Form.Item>
      </Form>
    </Modal>
  );
}
