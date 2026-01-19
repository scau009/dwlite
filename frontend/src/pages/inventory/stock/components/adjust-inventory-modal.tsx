import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import {
  Modal,
  Form,
  InputNumber,
  Input,
  Radio,
  Descriptions,
  App,
} from 'antd';

import {
  merchantInventoryApi,
  type MerchantInventoryItem,
  type AdjustmentType,
} from '@/lib/inbound-api';
import { getCurrencySymbol } from '@/lib/merchant-listing-api';

interface AdjustInventoryModalProps {
  open: boolean;
  inventory: MerchantInventoryItem | null;
  onClose: () => void;
  onSuccess: () => void;
}

export function AdjustInventoryModal({
  open,
  inventory,
  onClose,
  onSuccess,
}: AdjustInventoryModalProps) {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);
  const [adjustmentType, setAdjustmentType] = useState<AdjustmentType>('set');
  const [previewValue, setPreviewValue] = useState<number | null>(null);

  // Reset form when modal opens/closes or inventory changes
  useEffect(() => {
    if (open && inventory) {
      form.resetFields();
      form.setFieldsValue({
        adjustmentType: 'set',
        quantity: inventory.quantityAvailable,
        unitCost: inventory.averageCost ? parseFloat(inventory.averageCost) : undefined,
      });
      setAdjustmentType('set');
      setPreviewValue(inventory.quantityAvailable);
    }
  }, [open, inventory, form]);

  // Calculate preview value
  const handleQuantityChange = (value: number | null) => {
    if (!inventory || value === null) {
      setPreviewValue(null);
      return;
    }

    switch (adjustmentType) {
      case 'set':
        setPreviewValue(Math.max(0, value));
        break;
      case 'increase':
        setPreviewValue(inventory.quantityAvailable + Math.max(0, value));
        break;
      case 'decrease':
        setPreviewValue(Math.max(0, inventory.quantityAvailable - Math.max(0, value)));
        break;
    }
  };

  // Handle adjustment type change
  const handleAdjustmentTypeChange = (type: AdjustmentType) => {
    setAdjustmentType(type);
    const currentQuantity = form.getFieldValue('quantity');

    if (!inventory) return;

    // Recalculate preview
    switch (type) {
      case 'set':
        setPreviewValue(Math.max(0, currentQuantity || 0));
        break;
      case 'increase':
        setPreviewValue(inventory.quantityAvailable + Math.max(0, currentQuantity || 0));
        break;
      case 'decrease':
        setPreviewValue(Math.max(0, inventory.quantityAvailable - Math.max(0, currentQuantity || 0)));
        break;
    }
  };

  const handleSubmit = async () => {
    if (!inventory) return;

    try {
      const values = await form.validateFields();
      setLoading(true);

      // Validate decrease doesn't go negative
      if (values.adjustmentType === 'decrease' && values.quantity > inventory.quantityAvailable) {
        message.error(t('merchantStock.cannotDecreaseMoreThanAvailable'));
        return;
      }

      await merchantInventoryApi.adjustInventory(inventory.id, {
        adjustmentType: values.adjustmentType,
        quantity: values.quantity,
        unitCost: values.unitCost?.toString(),
        notes: values.notes,
      });

      message.success(t('merchantStock.inventoryAdjusted'));
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

  if (!inventory) return null;

  return (
    <Modal
      title={t('merchantStock.adjustInventory')}
      open={open}
      onCancel={onClose}
      onOk={handleSubmit}
      confirmLoading={loading}
      destroyOnClose
      width={500}
    >
      {/* Current inventory info */}
      <div className="mb-6 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
        <Descriptions column={1} size="small">
          <Descriptions.Item label={t('merchantStock.sku')}>
            {inventory.product?.styleNumber} - {inventory.sku?.skuName || inventory.sku?.sizeValue || '-'}
          </Descriptions.Item>
          <Descriptions.Item label={t('merchantStock.warehouse')}>
            {inventory.warehouse?.name}
          </Descriptions.Item>
          <Descriptions.Item label={t('merchantStock.currentStock')}>
            <span className="font-medium text-lg">{inventory.quantityAvailable}</span>
          </Descriptions.Item>
          {inventory.averageCost && (
            <Descriptions.Item label={t('merchantStock.currentCost')}>
              {getCurrencySymbol(inventory.currency)}{inventory.averageCost}
            </Descriptions.Item>
          )}
        </Descriptions>
      </div>

      <Form form={form} layout="vertical">
        {/* Adjustment Type */}
        <Form.Item
          name="adjustmentType"
          label={t('merchantStock.adjustmentType')}
          rules={[{ required: true, message: t('validation.required') }]}
        >
          <Radio.Group
            onChange={(e) => handleAdjustmentTypeChange(e.target.value)}
            optionType="button"
            buttonStyle="solid"
          >
            <Radio.Button value="set">{t('merchantStock.adjustSet')}</Radio.Button>
            <Radio.Button value="increase">{t('merchantStock.adjustIncrease')}</Radio.Button>
            <Radio.Button value="decrease">{t('merchantStock.adjustDecrease')}</Radio.Button>
          </Radio.Group>
        </Form.Item>

        {/* Quantity */}
        <Form.Item
          name="quantity"
          label={t('merchantStock.quantity')}
          rules={[
            { required: true, message: t('validation.required') },
            { type: 'number', min: 0, message: t('validation.minValue', { min: 0 }) },
          ]}
          extra={
            previewValue !== null && previewValue !== inventory.quantityAvailable ? (
              <span className="text-blue-500">
                {t('merchantStock.adjustPreview')}: {inventory.quantityAvailable} → {previewValue}
              </span>
            ) : null
          }
        >
          <InputNumber
            min={0}
            precision={0}
            style={{ width: '100%' }}
            onChange={handleQuantityChange}
          />
        </Form.Item>

        {/* Unit Cost (optional) */}
        <Form.Item
          name="unitCost"
          label={t('merchantStock.unitCost')}
          extra={t('merchantStock.unitCostHint')}
        >
          <InputNumber
            min={0}
            precision={2}
            style={{ width: '100%' }}
            prefix={getCurrencySymbol(inventory.currency)}
            placeholder={inventory.averageCost ? `${t('merchantStock.current')}: ${inventory.averageCost}` : t('merchantStock.optional')}
          />
        </Form.Item>

        {/* Notes */}
        <Form.Item name="notes" label={t('merchantStock.notes')}>
          <Input.TextArea rows={2} placeholder={t('merchantStock.optional')} maxLength={500} />
        </Form.Item>
      </Form>
    </Modal>
  );
}
