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

interface SkuSelectorModalProps {
  open: boolean;
  product: InboundProduct;
  orderId: string;
  onClose: () => void;
  onSuccess: () => void;
}

interface SkuRow extends InboundProductSku {
  quantity: number;
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

  // SKU data with quantity
  const [skuData, setSkuData] = useState<SkuRow[]>(() =>
    product.skus
      .filter(sku => sku.isActive)
      .map(sku => ({ ...sku, quantity: 0 }))
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

  // Batch fill quantity
  const handleBatchFill = (quantity: number) => {
    setSkuData(prev =>
      prev.map(sku =>
        selectedRowKeys.includes(sku.id) ? { ...sku, quantity } : sku
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

    setLoading(true);
    try {
      // Add items one by one (backend will handle duplicate SKU)
      for (const sku of selectedSkus) {
        await inboundApi.addInboundOrderItem(orderId, {
          productSkuId: sku.id,
          expectedQuantity: sku.quantity,
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
    setSkuData(
      product.skus
        .filter(sku => sku.isActive)
        .map(sku => ({ ...sku, quantity: 0 }))
    );
    onClose();
  };

  const columns: ColumnsType<SkuRow> = [
    {
      title: t('inventory.size'),
      dataIndex: 'sizeValue',
      width: 120,
      render: (value: string | null, record) => (
        <span className="font-medium">{record.skuName || value || '-'}</span>
      ),
    },
    {
      title: t('inventory.price'),
      dataIndex: 'price',
      width: 100,
      render: (price: string) => (price ? `¥${price}` : '-'),
    },
    {
      title: t('inventory.quantity'),
      dataIndex: 'quantity',
      width: 120,
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
  ];

  const rowSelection = {
    selectedRowKeys,
    onChange: (keys: React.Key[]) => setSelectedRowKeys(keys),
  };

  // Batch fill popover content
  const batchFillContent = (
    <div className="flex flex-col gap-2 p-1">
      <div className="text-sm text-gray-500 mb-1">{t('inventory.quickFill')}</div>
      <div className="flex gap-2 flex-wrap">
        {[1, 2, 3, 5, 10, 20, 50].map(num => (
          <Button
            key={num}
            size="small"
            onClick={() => handleBatchFill(num)}
          >
            {num}
          </Button>
        ))}
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
      width={600}
      destroyOnClose
    >
      <div className="flex flex-col gap-4">
        {/* Product info */}
        <div className="flex gap-4 p-3 bg-gray-50 rounded-lg">
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