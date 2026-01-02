import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal, Form, InputNumber, App, Descriptions, Image } from 'antd';

import { inboundApi, type InboundOrderItem } from '@/lib/inbound-api';

interface InboundOrderItemModalProps {
  open: boolean;
  orderId: string;
  item: InboundOrderItem | null;
  onClose: () => void;
  onSuccess: () => void;
}

export function InboundOrderItemModal({
  open,
  item,
  onClose,
  onSuccess,
}: InboundOrderItemModalProps) {
  const { t } = useTranslation();
  const [form] = Form.useForm();
  const { message } = App.useApp();
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (open && item) {
      form.setFieldsValue({
        expectedQuantity: item.expectedQuantity,
      });
    }
  }, [open, item, form]);

  const handleSubmit = async () => {
    if (!item) return;

    try {
      const values = await form.validateFields();
      setLoading(true);

      await inboundApi.updateInboundOrderItem(item.id, {
        expectedQuantity: values.expectedQuantity,
      });
      message.success(t('inventory.itemUpdated'));
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

  if (!item) return null;

  return (
    <Modal
      title={t('inventory.editItem')}
      open={open}
      onCancel={handleClose}
      onOk={handleSubmit}
      confirmLoading={loading}
      destroyOnClose
      width={500}
    >
      <div className="flex flex-col gap-4 mt-4">
        {/* Product info display */}
        <div className="flex gap-4 p-3 bg-gray-50 rounded-lg">
          {item.productImage ? (
            <Image
              src={item.productImage}
              width={80}
              height={80}
              style={{ objectFit: 'cover' }}
              preview={false}
            />
          ) : (
            <div className="w-[80px] h-[80px] bg-gray-200 flex items-center justify-center text-gray-400">
              N/A
            </div>
          )}
          <Descriptions column={1} size="small" className="flex-1">
            <Descriptions.Item label={t('inventory.productName')}>
              {item.productName || '-'}
            </Descriptions.Item>
            <Descriptions.Item label={t('inventory.styleNumber')}>
              {item.styleNumber || '-'}
            </Descriptions.Item>
            <Descriptions.Item label={t('inventory.skuName')}>
              {item.productSku.skuName || '-'}
            </Descriptions.Item>
            {item.productSku.colorName && (
              <Descriptions.Item label={t('inventory.colorName')}>
                {item.productSku.colorName}
              </Descriptions.Item>
            )}
          </Descriptions>
        </div>

        {/* Quantity input */}
        <Form form={form} layout="vertical">
          <Form.Item
            name="expectedQuantity"
            label={t('inventory.expectedQuantity')}
            rules={[
              { required: true, message: t('inventory.quantityRequired') },
              { type: 'number', min: 1, message: t('inventory.quantityMin') },
            ]}
          >
            <InputNumber min={1} precision={0} style={{ width: '100%' }} />
          </Form.Item>
        </Form>
      </div>
    </Modal>
  );
}
