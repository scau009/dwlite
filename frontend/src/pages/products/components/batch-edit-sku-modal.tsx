import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal, Form, Input, InputNumber, Switch, Checkbox, App, Alert } from 'antd';
import { productApi, CURRENCIES, type Currency } from '@/lib/product-api';

interface BatchEditSkuModalProps {
  open: boolean;
  productId: string;
  selectedCount: number;
  skuIds: string[];
  currency?: Currency;
  onClose: () => void;
  onSuccess: () => void;
}

interface FormValues {
  updatePrice: boolean;
  price: number;
  updateOriginalPrice: boolean;
  originalPrice: number;
  updateStatus: boolean;
  isActive: boolean;
  updateBarcode: boolean;
  barcode: string;
}

export function BatchEditSkuModal({
  open,
  productId,
  selectedCount,
  skuIds,
  currency = 'USD',
  onClose,
  onSuccess,
}: BatchEditSkuModalProps) {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [form] = Form.useForm<FormValues>();
  const [loading, setLoading] = useState(false);

  const currencySymbol = CURRENCIES.find((c) => c.value === currency)?.symbol || '$';

  const updatePrice = Form.useWatch('updatePrice', form);
  const updateOriginalPrice = Form.useWatch('updateOriginalPrice', form);
  const updateStatus = Form.useWatch('updateStatus', form);
  const updateBarcode = Form.useWatch('updateBarcode', form);

  const handleSubmit = async () => {
    try {
      const values = await form.validateFields();

      // Check if at least one field is selected for update
      if (!values.updatePrice && !values.updateOriginalPrice && !values.updateStatus && !values.updateBarcode) {
        message.warning(t('products.batchEditNoField'));
        return;
      }

      setLoading(true);

      const data: {
        skuIds: string[];
        price?: string;
        originalPrice?: string;
        isActive?: boolean;
        barcode?: string;
      } = { skuIds };

      if (values.updatePrice) {
        data.price = String(values.price);
      }
      if (values.updateOriginalPrice) {
        data.originalPrice = String(values.originalPrice);
      }
      if (values.updateStatus) {
        data.isActive = values.isActive;
      }
      if (values.updateBarcode) {
        data.barcode = values.barcode || '';
      }

      const result = await productApi.batchUpdateSkus(productId, data);
      message.success(t('products.batchUpdated', { count: result.updatedCount }));
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
      title={t('products.batchEdit')}
      open={open}
      onCancel={handleClose}
      onOk={handleSubmit}
      confirmLoading={loading}
      okText={t('common.confirm')}
      cancelText={t('common.cancel')}
      destroyOnClose
      width={500}
    >
      <div style={{ marginBottom: 16 }}>
        <Alert
          message={t('products.batchEditHint', { count: selectedCount })}
          type="info"
          showIcon
        />
      </div>

      <Form
        form={form}
        layout="vertical"
        initialValues={{
          updatePrice: false,
          updateOriginalPrice: false,
          updateStatus: false,
          isActive: true,
          updateBarcode: false,
          barcode: '',
        }}
      >
        <Form.Item name="updatePrice" valuePropName="checked" style={{ marginBottom: 8 }}>
          <Checkbox>{t('products.updatePrice')}</Checkbox>
        </Form.Item>
        <Form.Item
          name="price"
          rules={[
            {
              required: updatePrice,
              message: t('products.priceRequired'),
            },
          ]}
          style={{ marginBottom: 16, marginLeft: 24 }}
        >
          <InputNumber
            min={0}
            precision={2}
            prefix={currencySymbol}
            style={{ width: '100%' }}
            disabled={!updatePrice}
            placeholder={t('products.pricePlaceholder')}
          />
        </Form.Item>

        <Form.Item name="updateOriginalPrice" valuePropName="checked" style={{ marginBottom: 8 }}>
          <Checkbox>{t('products.updateOriginalPrice')}</Checkbox>
        </Form.Item>
        <Form.Item
          name="originalPrice"
          style={{ marginBottom: 16, marginLeft: 24 }}
        >
          <InputNumber
            min={0}
            precision={2}
            prefix={currencySymbol}
            style={{ width: '100%' }}
            disabled={!updateOriginalPrice}
            placeholder={t('products.originalPricePlaceholder')}
          />
        </Form.Item>

        <Form.Item name="updateStatus" valuePropName="checked" style={{ marginBottom: 8 }}>
          <Checkbox>{t('products.updateStatus')}</Checkbox>
        </Form.Item>
        <Form.Item
          name="isActive"
          valuePropName="checked"
          style={{ marginBottom: 16, marginLeft: 24 }}
        >
          <Switch
            checkedChildren={t('products.active')}
            unCheckedChildren={t('products.inactive')}
            disabled={!updateStatus}
          />
        </Form.Item>

        <Form.Item name="updateBarcode" valuePropName="checked" style={{ marginBottom: 8 }}>
          <Checkbox>{t('products.updateBarcode')}</Checkbox>
        </Form.Item>
        <Form.Item
          name="barcode"
          style={{ marginBottom: 0, marginLeft: 24 }}
        >
          <Input
            placeholder="UPC / EAN"
            maxLength={50}
            disabled={!updateBarcode}
          />
        </Form.Item>
      </Form>
    </Modal>
  );
}
