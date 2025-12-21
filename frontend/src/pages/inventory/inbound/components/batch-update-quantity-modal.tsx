import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal, InputNumber, App } from 'antd';

import { inboundApi, type InboundOrderItem } from '@/lib/inbound-api';

interface BatchUpdateQuantityModalProps {
  open: boolean;
  items: InboundOrderItem[];
  onClose: () => void;
  onSuccess: () => void;
}

export function BatchUpdateQuantityModal({
  open,
  items,
  onClose,
  onSuccess,
}: BatchUpdateQuantityModalProps) {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [loading, setLoading] = useState(false);
  const [quantity, setQuantity] = useState<number | null>(1);

  const handleSubmit = async () => {
    if (!quantity || quantity < 1) {
      message.warning(t('inventory.quantityMin'));
      return;
    }

    setLoading(true);
    try {
      // Update all selected items
      await Promise.all(
        items.map(item =>
          inboundApi.updateInboundOrderItem(item.id, {
            expectedQuantity: quantity,
          })
        )
      );
      message.success(t('inventory.batchUpdateSuccess', { count: items.length }));
      onSuccess();
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setLoading(false);
    }
  };

  const handleClose = () => {
    setQuantity(1);
    onClose();
  };

  return (
    <Modal
      title={t('inventory.batchUpdateQuantity')}
      open={open}
      onCancel={handleClose}
      onOk={handleSubmit}
      confirmLoading={loading}
      destroyOnClose
      width={400}
    >
      <div className="py-4">
        <p className="text-gray-500 mb-4">
          {t('inventory.batchUpdateQuantityDesc', { count: items.length })}
        </p>
        <div className="flex items-center gap-2">
          <span>{t('inventory.expectedQuantity')}:</span>
          <InputNumber
            min={1}
            precision={0}
            value={quantity}
            onChange={setQuantity}
            style={{ width: 150 }}
            autoFocus
          />
        </div>
      </div>
    </Modal>
  );
}
