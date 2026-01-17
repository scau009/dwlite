import { useState, useEffect, useMemo, useCallback } from 'react';
import { useNavigate } from 'react-router';
import { useTranslation } from 'react-i18next';
import {
  Modal,
  Steps,
  Select,
  Table,
  InputNumber,
  Button,
  Space,
  App,
  Empty,
  Image,
} from 'antd';
import { ShoppingOutlined } from '@ant-design/icons';

import {
  inboundApi,
  type InboundProduct,
  type InboundProductSku,
  type WarehouseGroup,
} from '@/lib/inbound-api';
import { getCurrencySymbol } from '@/lib/merchant-listing-api';

interface CreateOrderModalProps {
  open: boolean;
  products: InboundProduct[];
  onClose: () => void;
  onSuccess: () => void;
}

interface SkuSelection {
  productId: string;
  productName: string;
  productStyleNumber: string;
  productImage: string | null;
  sku: InboundProductSku;
  quantity: number;
}

export function CreateOrderModal({
  open,
  products,
  onClose,
  onSuccess,
}: CreateOrderModalProps) {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { message } = App.useApp();

  const [currentStep, setCurrentStep] = useState(0);
  const [warehouseGroups, setWarehouseGroups] = useState<WarehouseGroup[]>([]);
  const [warehouseLoading, setWarehouseLoading] = useState(false);
  const [selectedWarehouse, setSelectedWarehouse] = useState<string | null>(null);
  const [skuSelections, setSkuSelections] = useState<SkuSelection[]>([]);
  const [submitting, setSubmitting] = useState(false);
  const [batchQuantity, setBatchQuantity] = useState<number>(1);

  const loadWarehouses = useCallback(async () => {
    setWarehouseLoading(true);
    try {
      const data = await inboundApi.getAvailableWarehouses();
      setWarehouseGroups(data);
    } catch (error) {
      console.error('Failed to load warehouses:', error);
      message.error(t('common.error'));
    } finally {
      setWarehouseLoading(false);
    }
  }, [message, t]);

  const initSkuSelections = useCallback(() => {
    const selections: SkuSelection[] = [];
    products.forEach((product) => {
      const activeSkus = product.skus.filter((s) => s.isActive);
      activeSkus.forEach((sku) => {
        selections.push({
          productId: product.id,
          productName: product.name,
          productStyleNumber: product.styleNumber,
          productImage: product.primaryImageUrl,
          sku,
          quantity: 1,
        });
      });
    });
    setSkuSelections(selections);
  }, [products]);

  // Load warehouses when modal opens
  useEffect(() => {
    if (open) {
      setCurrentStep(0);
      setSelectedWarehouse(null);
      setSkuSelections([]);
      loadWarehouses();
      initSkuSelections();
    }
  }, [open, loadWarehouses, initSkuSelections]);

  const handleQuantityChange = (skuId: string, quantity: number | null) => {
    setSkuSelections((prev) =>
      prev.map((s) =>
        s.sku.id === skuId ? { ...s, quantity: quantity || 0 } : s
      )
    );
  };

  const handleRemoveSku = (skuId: string) => {
    setSkuSelections((prev) => prev.filter((s) => s.sku.id !== skuId));
  };

  const handleBatchFill = () => {
    setSkuSelections((prev) =>
      prev.map((s) => ({ ...s, quantity: batchQuantity }))
    );
  };

  const handleClearAll = () => {
    setSkuSelections((prev) =>
      prev.map((s) => ({ ...s, quantity: 0 }))
    );
  };

  const handleSubmit = async () => {
    if (!selectedWarehouse) {
      message.warning(t('opportunities.warehouseRequired'));
      return;
    }

    const validSelections = skuSelections.filter((s) => s.quantity > 0);
    if (validSelections.length === 0) {
      message.warning(t('opportunities.noSkusSelected'));
      return;
    }

    setSubmitting(true);
    try {
      // Create inbound order
      const orderResult = await inboundApi.createInboundOrder({
        warehouseId: selectedWarehouse,
      });

      const orderId = orderResult.data.id;

      // Add items to order
      for (const selection of validSelections) {
        await inboundApi.addInboundOrderItem(orderId, {
          productSkuId: selection.sku.id,
          expectedQuantity: selection.quantity,
        });
      }

      message.success(t('opportunities.orderCreated'));
      onSuccess();

      // Navigate to order detail
      navigate(`/inventory/inbound/detail/${orderId}`);
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setSubmitting(false);
    }
  };

  const columns = [
    {
      title: t('opportunities.product'),
      key: 'product',
      width: 200,
      render: (_: unknown, record: SkuSelection) => (
        <div className="flex items-center gap-3">
          {record.productImage ? (
            <div className="w-14 h-14 flex items-center justify-center bg-gray-100 rounded flex-shrink-0">
              <Image
                src={record.productImage}
                alt={record.productName}
                preview={false}
                style={{ maxWidth: 56, maxHeight: 56, objectFit: 'contain' }}
              />
            </div>
          ) : (
            <div className="w-14 h-14 flex items-center justify-center bg-gray-100 rounded text-gray-400 flex-shrink-0">
              <ShoppingOutlined style={{ fontSize: 24 }} />
            </div>
          )}
          <code className="text-xs bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">
            {record.productStyleNumber}
          </code>
        </div>
      ),
    },
    {
      title: t('opportunities.size'),
      key: 'size',
      width: 100,
      render: (_: unknown, record: SkuSelection) => (
        <span className="text-sm">
          {record.sku.sizeValue
            ? `${record.sku.sizeUnit ? `${record.sku.sizeUnit} ` : ''}${record.sku.sizeValue}`
            : '-'}
        </span>
      ),
    },
    {
      title: t('opportunities.price'),
      dataIndex: ['sku', 'price'],
      key: 'price',
      width: 100,
      render: (_: unknown, record: SkuSelection) => (
        <span className="text-orange-600">
          {getCurrencySymbol(record.sku.currency)}{parseFloat(record.sku.price).toFixed(2)}
        </span>
      ),
    },
    {
      title: t('opportunities.quantity'),
      key: 'quantity',
      width: 120,
      render: (_: unknown, record: SkuSelection) => (
        <InputNumber
          min={0}
          max={9999}
          value={record.quantity}
          onChange={(val) => handleQuantityChange(record.sku.id, val)}
          size="small"
        />
      ),
    },
    {
      title: t('common.actions'),
      key: 'actions',
      width: 80,
      render: (_: unknown, record: SkuSelection) => (
        <Button
          type="text"
          danger
          size="small"
          onClick={() => handleRemoveSku(record.sku.id)}
        >
          {t('common.delete')}
        </Button>
      ),
    },
  ];

  const validSelectionsCount = skuSelections.filter((s) => s.quantity > 0).length;
  const totalQuantity = skuSelections.reduce((sum, s) => sum + s.quantity, 0);

  // Transform warehouse groups to flat options
  const warehouseOptions = useMemo(() => {
    const options: { label: string; value: string }[] = [];
    warehouseGroups.forEach((group) => {
      group.warehouses.forEach((warehouse) => {
        const location = warehouse.city || warehouse.province || '';
        options.push({
          label: `${warehouse.name} (${warehouse.code})${location ? ` - ${location}` : ''}`,
          value: warehouse.id,
        });
      });
    });
    return options;
  }, [warehouseGroups]);

  return (
    <Modal
      title={t('opportunities.createOrderTitle')}
      open={open}
      onCancel={onClose}
      width={800}
      footer={null}
      destroyOnHidden
    >
      <Steps
        current={currentStep}
        className="mb-6"
        items={[
          { title: t('opportunities.stepWarehouse') },
          { title: t('opportunities.stepProducts') },
        ]}
      />

      {currentStep === 0 && (
        <div className="py-4">
          <div className="mb-4">
            <label className="block text-sm font-medium mb-2">
              {t('opportunities.selectWarehouse')}
            </label>
            <Select
              className="w-full"
              placeholder={t('opportunities.selectWarehousePlaceholder')}
              loading={warehouseLoading}
              value={selectedWarehouse}
              onChange={setSelectedWarehouse}
              options={warehouseOptions}
              showSearch
              filterOption={(input, option) =>
                (option?.label ?? '').toLowerCase().includes(input.toLowerCase())
              }
            />
          </div>
          <div className="flex justify-end mt-6">
            <Space>
              <Button onClick={onClose}>{t('common.cancel')}</Button>
              <Button
                type="primary"
                disabled={!selectedWarehouse}
                onClick={() => setCurrentStep(1)}
              >
                {t('common.next')}
              </Button>
            </Space>
          </div>
        </div>
      )}

      {currentStep === 1 && (
        <div className="py-4">
          <div className="mb-4 text-sm text-gray-500">
            {t('opportunities.setQuantities')}
          </div>

          {skuSelections.length === 0 ? (
            <Empty description={t('opportunities.noSkusAvailable')} />
          ) : (
            <>
              {/* Batch fill toolbar */}
              <div className="mb-3 p-3 bg-gray-50 rounded flex items-center gap-3">
                <span className="text-sm text-gray-600">{t('opportunities.batchFill')}:</span>
                <InputNumber
                  min={0}
                  max={9999}
                  value={batchQuantity}
                  onChange={(val) => setBatchQuantity(val || 0)}
                  size="small"
                  style={{ width: 100 }}
                />
                <Button size="small" type="primary" onClick={handleBatchFill}>
                  {t('opportunities.fillAll')}
                </Button>
                <Button size="small" onClick={handleClearAll}>
                  {t('opportunities.clearAll')}
                </Button>
              </div>

              <Table
                columns={columns}
                dataSource={skuSelections}
                rowKey={(record) => record.sku.id}
                pagination={false}
                size="small"
                scroll={{ y: 400 }}
              />

              <div className="mt-4 p-3 bg-gray-50 rounded flex justify-between items-center">
                <span className="text-sm text-gray-600">
                  {t('opportunities.summary', {
                    skuCount: validSelectionsCount,
                    totalQuantity,
                  })}
                </span>
              </div>
            </>
          )}

          <div className="flex justify-end mt-6">
            <Space>
              <Button onClick={() => setCurrentStep(0)}>{t('common.back')}</Button>
              <Button
                type="primary"
                loading={submitting}
                disabled={validSelectionsCount === 0}
                onClick={handleSubmit}
              >
                {t('opportunities.createOrder')}
              </Button>
            </Space>
          </div>
        </div>
      )}
    </Modal>
  );
}
