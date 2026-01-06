import { useState, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import {
  Modal,
  Table,
  InputNumber,
  Button,
  Space,
  Image,
  App,
  Popover,
  Descriptions,
} from 'antd';
import { ThunderboltOutlined } from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';

import { inboundApi, type InboundProduct, type InboundProductSku } from '@/lib/inbound-api';
import { getCurrencySymbol } from '@/lib/merchant-listing-api';

interface SkuSelectorModalProps {
  open: boolean;
  product: InboundProduct;
  orderId: string;
  onClose: () => void;
  onSuccess: () => void;
}

interface SkuRow extends InboundProductSku {
  quantity: number;
  unitCost?: number;
}

export function SkuSelectorModal({
  open,
  product,
  orderId,
  onClose,
  onSuccess,
}: SkuSelectorModalProps) {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [loading, setLoading] = useState(false);
  const [selectedRowKeys, setSelectedRowKeys] = useState<React.Key[]>([]);

  // Custom batch fill values
  const [customQuantity, setCustomQuantity] = useState<number | null>(null);
  const [customCost, setCustomCost] = useState<number | null>(null);

  // SKU data with quantity and unitCost
  const [skuData, setSkuData] = useState<SkuRow[]>(() =>
    product.skus
      .filter(sku => sku.isActive)
      .map(sku => ({ ...sku, quantity: 0, unitCost: undefined }))
  );

  // Get selected SKUs with quantity > 0
  const selectedSkus = useMemo(() => {
    return skuData.filter(sku => selectedRowKeys.includes(sku.id) && sku.quantity > 0);
  }, [skuData, selectedRowKeys]);

  // Update quantity for a SKU
  const handleQuantityChange = (skuId: string, quantity: number | null) => {
    setSkuData(prev =>
      prev.map(sku =>
        sku.id === skuId ? { ...sku, quantity: quantity || 0 } : sku
      )
    );
  };

  // Update unit cost for a SKU
  const handleUnitCostChange = (skuId: string, cost: number | null) => {
    setSkuData(prev =>
      prev.map(sku =>
        sku.id === skuId ? { ...sku, unitCost: cost ?? undefined } : sku
      )
    );
  };

  // Batch fill quantity
  const handleBatchFill = (quantity: number) => {
    setSkuData(prev =>
      prev.map(sku =>
        selectedRowKeys.includes(sku.id) ? { ...sku, quantity } : sku
      )
    );
  };

  // Batch fill unit cost
  const handleBatchFillCost = (cost: number) => {
    setSkuData(prev =>
      prev.map(sku =>
        selectedRowKeys.includes(sku.id) ? { ...sku, unitCost: cost } : sku
      )
    );
  };

  // Select all
  const handleSelectAll = () => {
    setSelectedRowKeys(skuData.map(sku => sku.id));
  };

  // Submit selected SKUs
  const handleSubmit = async () => {
    if (selectedSkus.length === 0) {
      message.warning(t('inventory.pleaseSelectSku'));
      return;
    }

    // Check if all selected SKUs have unitCost filled
    const missingCost = selectedSkus.some(sku => sku.unitCost === undefined || sku.unitCost === null);
    if (missingCost) {
      message.warning(t('inventory.unitCostRequired'));
      return;
    }

    setLoading(true);
    try {
      // Add items one by one (backend will handle duplicate SKU)
      for (const sku of selectedSkus) {
        await inboundApi.addInboundOrderItem(orderId, {
          productSkuId: sku.id,
          expectedQuantity: sku.quantity,
          unitCost: sku.unitCost!.toString(),
        });
      }
      message.success(t('inventory.itemsAdded'));
      onSuccess();
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setLoading(false);
    }
  };

  const handleClose = () => {
    setSelectedRowKeys([]);
    setCustomQuantity(null);
    setCustomCost(null);
    setSkuData(
      product.skus
        .filter(sku => sku.isActive)
        .map(sku => ({ ...sku, quantity: 0, unitCost: undefined }))
    );
    onClose();
  };

  const columns: ColumnsType<SkuRow> = [
    {
      title: t('inventory.size'),
      dataIndex: 'sizeValue',
      width: 100,
      render: (value: string | null, record) => (
        <span className="font-medium">{record.skuName || value || '-'}</span>
      ),
    },
    {
      title: t('inventory.price'),
      dataIndex: 'price',
      width: 80,
      render: (price: string, record) => (price ? `${getCurrencySymbol(record.currency)}${price}` : '-'),
    },
    {
      title: t('inventory.quantity'),
      dataIndex: 'quantity',
      width: 100,
      render: (_, record) => (
        <InputNumber
          min={0}
          precision={0}
          value={record.quantity}
          onChange={(value) => handleQuantityChange(record.id, value)}
          style={{ width: '100%' }}
          disabled={!selectedRowKeys.includes(record.id)}
        />
      ),
    },
    {
      title: (
        <span>
          {t('inventory.unitCost')} ({getCurrencySymbol(product.skus[0]?.currency || 'CNY')})
          <span className="text-red-500 ml-0.5">*</span>
        </span>
      ),
      dataIndex: 'unitCost',
      width: 130,
      render: (_, record) => {
        const isSelected = selectedRowKeys.includes(record.id);
        const isEmpty = record.unitCost === undefined || record.unitCost === null;
        return (
          <InputNumber
            min={0}
            precision={2}
            value={record.unitCost}
            onChange={(value) => handleUnitCostChange(record.id, value)}
            style={{ width: '100%' }}
            disabled={!isSelected}
            prefix={getCurrencySymbol(record.currency)}
            placeholder="0.00"
            status={isSelected && isEmpty ? 'error' : undefined}
          />
        );
      },
    },
  ];

  const rowSelection = {
    selectedRowKeys,
    onChange: (keys: React.Key[]) => setSelectedRowKeys(keys),
  };

  // Batch fill popover content
  const batchFillContent = (
    <div className="flex flex-col gap-3 p-1" style={{ width: 280 }}>
      {/* Quantity batch fill */}
      <div>
        <div className="text-sm text-gray-500 dark:text-gray-400 mb-2">{t('inventory.batchFillQuantity')}</div>
        <div className="flex gap-2 flex-wrap mb-2">
          {[1, 2, 3, 5, 10, 20].map(num => (
            <Button
              key={num}
              size="small"
              onClick={() => handleBatchFill(num)}
            >
              {num}
            </Button>
          ))}
        </div>
        <Space.Compact style={{ width: '100%' }}>
          <InputNumber
            size="small"
            min={1}
            precision={0}
            value={customQuantity}
            onChange={setCustomQuantity}
            placeholder={t('inventory.customQuantity')}
            style={{ flex: 1 }}
          />
          <Button
            size="small"
            type="primary"
            disabled={!customQuantity || customQuantity <= 0}
            onClick={() => customQuantity && handleBatchFill(customQuantity)}
          >
            {t('common.apply')}
          </Button>
        </Space.Compact>
      </div>

      {/* Cost batch fill */}
      <div>
        <div className="text-sm text-gray-500 dark:text-gray-400 mb-2">{t('inventory.batchFillCost')}</div>
        <Space.Compact style={{ width: '100%' }}>
          <InputNumber
            size="small"
            min={0}
            precision={2}
            value={customCost}
            onChange={setCustomCost}
            placeholder={t('inventory.customCost')}
            prefix={getCurrencySymbol(product.skus[0]?.currency || 'CNY')}
            style={{ flex: 1 }}
          />
          <Button
            size="small"
            type="primary"
            disabled={customCost === null || customCost === undefined || customCost < 0}
            onClick={() => customCost !== null && customCost !== undefined && handleBatchFillCost(customCost)}
          >
            {t('common.apply')}
          </Button>
        </Space.Compact>
      </div>
    </div>
  );

  return (
    <Modal
      title={t('inventory.selectSize')}
      open={open}
      onCancel={handleClose}
      onOk={handleSubmit}
      confirmLoading={loading}
      okText={t('inventory.addToOrder')}
      okButtonProps={{ disabled: selectedSkus.length === 0 }}
      width={680}
      destroyOnClose
    >
      <div className="flex flex-col gap-4">
        {/* Product info */}
        <div className="flex gap-4 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
          {product.primaryImageUrl ? (
            <Image
              src={product.primaryImageUrl}
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
              {product.name}
            </Descriptions.Item>
            <Descriptions.Item label={t('inventory.styleNumber')}>
              {product.styleNumber}
            </Descriptions.Item>
            {product.color && (
              <Descriptions.Item label={t('inventory.color')}>
                {product.color}
              </Descriptions.Item>
            )}
          </Descriptions>
        </div>

        {/* Toolbar */}
        <div className="flex justify-between items-center">
          <Space>
            <Button size="small" onClick={handleSelectAll}>
              {t('common.selectAll')}
            </Button>
            <Popover content={batchFillContent} trigger="click" placement="bottomLeft">
              <Button size="small" icon={<ThunderboltOutlined />}>
                {t('inventory.batchFill')}
              </Button>
            </Popover>
          </Space>
          <span className="text-gray-500 text-sm">
            {t('inventory.selectedCount', { count: selectedSkus.length })}
            {selectedSkus.length > 0 && (
              <span className="ml-2">
                ({t('inventory.totalQuantity')}: {selectedSkus.reduce((sum, s) => sum + s.quantity, 0)})
              </span>
            )}
          </span>
        </div>

        {/* SKU table */}
        <Table
          columns={columns}
          dataSource={skuData}
          rowKey="id"
          rowSelection={rowSelection}
          pagination={false}
          scroll={{ y: 300 }}
          size="small"
        />
      </div>
    </Modal>
  );
}