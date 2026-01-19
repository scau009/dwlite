import { useState } from 'react';
import { Modal, Radio, Space, Alert, message } from 'antd';
import { useTranslation } from 'react-i18next';
import { productApi, type ProductStatus } from '@/lib/product-api';

interface BatchUpdateStatusModalProps {
  open: boolean;
  selectedCount: number;
  selectedProductIds: string[];
  onCancel: () => void;
  onSuccess: () => void;
}

export function BatchUpdateStatusModal({
  open,
  selectedCount,
  selectedProductIds,
  onCancel,
  onSuccess,
}: BatchUpdateStatusModalProps) {
  const { t } = useTranslation();
  const [status, setStatus] = useState<ProductStatus>('active');
  const [loading, setLoading] = useState(false);

  const handleOk = async () => {
    if (selectedProductIds.length === 0) return;

    setLoading(true);
    try {
      const result = await productApi.batchUpdateProductStatus(selectedProductIds, status);
      message.success(t('products.batchStatusUpdated', { count: result.updatedCount }));
      onSuccess();
    } catch {
      message.error(t('common.operationFailed'));
    } finally {
      setLoading(false);
    }
  };

  const handleCancel = () => {
    setStatus('active');
    onCancel();
  };

  return (
    <Modal
      title={t('products.batchUpdateStatus')}
      open={open}
      onOk={handleOk}
      onCancel={handleCancel}
      confirmLoading={loading}
      okText={t('common.confirm')}
      cancelText={t('common.cancel')}
    >
      <Alert
        message={t('products.batchStatusHint', { count: selectedCount })}
        type="info"
        showIcon
        className="mb-4"
      />

      <div className="py-2">
        <div className="mb-2 text-gray-600">{t('products.batchSelectStatus')}</div>
        <Radio.Group value={status} onChange={(e) => setStatus(e.target.value)}>
          <Space direction="vertical">
            <Radio value="draft">{t('products.statusDraft')}</Radio>
            <Radio value="active">{t('products.statusActive')}</Radio>
            <Radio value="inactive">{t('products.statusInactive')}</Radio>
          </Space>
        </Radio.Group>
      </div>
    </Modal>
  );
}
