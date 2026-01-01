import { useState, useEffect } from 'react';
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
  Avatar,
  Empty,
} from 'antd';
import { ShoppingOutlined } from '@ant-design/icons';

import {
  inboundApi,
  type InboundProduct,
  type InboundProductSku,
  type AvailableWarehouse,
} from '@/lib/inbound-api';

interface CreateOrderModalProps {
  open: boolean;
  products: InboundProduct[];
  onClose: () => void;
  onSuccess: () => void;
}

interface SkuSelection {
  productId: string;
  productName: string;
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
  const [warehouses, setWarehouses] = useState<AvailableWarehouse[]>([]);
  const [warehouseLoading, setWarehouseLoading] = useState(false);
  const [selectedWarehouse, setSelectedWarehouse] = useState<string | null>(null);
  const [skuSelections, setSkuSelections] = useState<SkuSelection[]>([]);
  const [submitting, setSubmitting] = useState(false);
  const [batchQuantity, setBatchQuantity] = useState<number>(1);

  // Load warehouses when modal opens
  useEffect(() => {
    if (open) {
      setCurrentStep(0);
      setSelectedWarehouse(null);
      setSkuSelections([]);
      loadWarehouses();
      initSkuSelections();
    }
  }, [open, products]);

  const loadWarehouses = async () => {
    setWarehouseLoading(true);
    try {
      const data = await inboundApi.getAvailableWarehouses();
      setWarehouses(data);
    } catch (error) {
      console.error('Failed to load warehouses:', error);
      message.error(t('common.error'));
    } finally {
      setWarehouseLoading(false);
    }
  };

  const initSkuSelections = () => {
    const selections: SkuSelection[] = [];
    products.forEach((product) => {
      const activeSkus = product.skus.filter((s) => s.isActive);
      activeSkus.forEach((sku) => {
        selections.push({
          productId: product.id,
          productName: product.name,
          productImage: product.primaryImageUrl,
          sku,
          quantity: 1,
        });
      });
    });
    setSkuSelections(selections);
  };

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
        <div className="flex items-center gap-2">
          {record.productImage ? (
            <Avatar src={record.productImage} shape="square" size={40} />
          ) : (
            <Avatar shape="square" size={40} icon={<ShoppingOutlined />} />
          )}
          <div className="min-w-0">
            <div className="text-sm font-medium truncate">{record.productName}</div>
          </div>
        </div>
      ),
    },
    {
      title: t('opportunities.sku'),
      key: 'sku',
      width: 150,
      render: (_: unknown, record: SkuSelection) => (
        <div>
          <div className="text-sm">{record.sku.skuName || '-'}</div>
          {record.sku.sizeValue && (
            <div className="text-xs text-gray-500">
              {record.sku.sizeUnit} {record.sku.sizeValue}
            </div>
          )}
        </div>
      ),
    },
    {
      title: t('opportunities.price'),
      dataIndex: ['sku', 'price'],
      key: 'price',
      width: 100,
      render: (price: string) => (
        <span className="text-orange-600">¥{parseFloat(price).toFixed(2)}</span>
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
              options={warehouses.map((w) => ({
                label: `${w.name} (${w.code})`,
                value: w.id,
              }))}
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
