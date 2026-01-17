import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal, InputNumber, App } from 'antd';

import { inboundApi, type InboundOrderItem } from '@/lib/inbound-api';
import { getCurrencySymbol } from '@/lib/merchant-listing-api';

interface BatchUpdateCostModalProps {
  open: boolean;
  items: InboundOrderItem[];
  onClose: () => void;
  onSuccess: () => void;
}

export function BatchUpdateCostModal({
  open,
  items,
  onClose,
  onSuccess,
}: BatchUpdateCostModalProps) {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [loading, setLoading] = useState(false);
  const [cost, setCost] = useState<number | null>(null);

  const handleSubmit = async () => {
    if (cost === null || cost < 0) {
      message.warning(t('inventory.costMin'));
      return;
    }

    setLoading(true);
    try {
      // Use batch API to update all items in a single request
      const itemIds = items.map(item => item.id);
      await inboundApi.batchUpdateItemCost(itemIds, cost.toString());
      message.success(t('inventory.batchCostUpdated', { count: items.length }));
      onSuccess();
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setLoading(false);
    }
  };

  const handleClose = () => {
    setCost(null);
    onClose();
  };

  return (
    <Modal
      title={t('inventory.batchUpdateCost')}
      open={open}
      onCancel={handleClose}
      onOk={handleSubmit}
      confirmLoading={loading}
      destroyOnClose
      width={400}
    >
      <div className="py-4">
        <p className="text-gray-500 mb-4">
          {t('inventory.batchUpdateCostDesc', { count: items.length })}
        </p>
        <div className="flex items-center gap-2">
          <span>{t('inventory.unitCost')}:</span>
          <InputNumber
            min={0}
            precision={2}
            value={cost}
            onChange={setCost}
            style={{ width: 150 }}
            prefix={getCurrencySymbol(items[0]?.currency || 'CNY')}
            placeholder="0.00"
            autoFocus
          />
        </div>
      </div>
    </Modal>
  );
}
