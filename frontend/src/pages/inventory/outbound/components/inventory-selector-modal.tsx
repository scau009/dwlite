import { useState, useMemo, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import {
  Modal,
  Table,
  InputNumber,
  Image,
  App,
  Tag,
  Input,
  Tabs,
  Empty,
  Spin,
} from 'antd';
import { SearchOutlined } from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';

import {
  merchantInventoryApi,
  type MerchantInventoryItem,
} from '@/lib/inbound-api';
import { outboundApi } from '@/lib/outbound-api';

interface InventorySelectorModalProps {
  open: boolean;
  orderId: string;
  warehouseId: string;
  existingSkuCodes: string[]; // Already added SKU codes
  onClose: () => void;
  onSuccess: () => void;
}

type StockTab = 'normal' | 'damaged';

export function InventorySelectorModal({
  open,
  orderId,
  warehouseId,
  existingSkuCodes,
  onClose,
  onSuccess,
}: InventorySelectorModalProps) {
  const { t } = useTranslation();
  const { message } = App.useApp();

  const [loading, setLoading] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [inventoryItems, setInventoryItems] = useState<MerchantInventoryItem[]>([]);
  const [searchKeyword, setSearchKeyword] = useState('');
  const [activeTab, setActiveTab] = useState<StockTab>('normal');
  const [selectedRowKeys, setSelectedRowKeys] = useState<React.Key[]>([]);
  const [quantities, setQuantities] = useState<Record<string, number>>({});

  // Load inventory when modal opens
  useEffect(() => {
    if (open && warehouseId) {
      loadInventory();
    }
  }, [open, warehouseId]);

  // Reset state when modal closes
  useEffect(() => {
    if (!open) {
      setSearchKeyword('');
      setActiveTab('normal');
      setSelectedRowKeys([]);
      setQuantities({});
    }
  }, [open]);

  const loadInventory = async () => {
    setLoading(true);
    try {
      const response = await merchantInventoryApi.getInventoryList({
        warehouseId,
        hasStock: true,
        limit: 200,
      });
      setInventoryItems(response.data || []);
    } catch (error) {
      console.error('Failed to load inventory:', error);
      message.error(t('common.error'));
    } finally {
      setLoading(false);
    }
  };

  // Filter inventory based on tab and search
  const filteredInventory = useMemo(() => {
    let items = inventoryItems;

    // Filter by stock type
    if (activeTab === 'normal') {
      items = items.filter(item => item.quantityAvailable > 0);
    } else {
      items = items.filter(item => item.quantityDamaged > 0);
    }

    // Filter out existing items
    items = items.filter(item => {
      const skuCode = `${item.product?.styleNumber}-${item.sku.sizeValue}`;
      return !existingSkuCodes.includes(skuCode);
    });

    // Filter by search keyword
    if (searchKeyword.trim()) {
      const keyword = searchKeyword.toLowerCase().trim();
      items = items.filter(item => {
        const productName = item.product?.name?.toLowerCase() || '';
        const styleNumber = item.product?.styleNumber?.toLowerCase() || '';
        const skuName = item.sku?.skuName?.toLowerCase() || '';
        const colorName = item.product?.color?.toLowerCase() || '';
        return (
          productName.includes(keyword) ||
          styleNumber.includes(keyword) ||
          skuName.includes(keyword) ||
          colorName.includes(keyword)
        );
      });
    }

    return items;
  }, [inventoryItems, activeTab, searchKeyword, existingSkuCodes]);

  // Get max quantity based on stock type
  const getMaxQuantity = (item: MerchantInventoryItem): number => {
    return activeTab === 'normal' ? item.quantityAvailable : item.quantityDamaged;
  };

  // Update quantity for an item
  const handleQuantityChange = (itemId: string, quantity: number | null) => {
    setQuantities(prev => ({
      ...prev,
      [itemId]: quantity || 0,
    }));
  };

  // Get selected items with valid quantities
  const selectedItems = useMemo(() => {
    return filteredInventory.filter(
      item => selectedRowKeys.includes(item.id) && (quantities[item.id] || 0) > 0
    );
  }, [filteredInventory, selectedRowKeys, quantities]);

  // Handle tab change - reset selection
  const handleTabChange = (tab: string) => {
    setActiveTab(tab as StockTab);
    setSelectedRowKeys([]);
    setQuantities({});
  };

  // Submit selected items
  const handleSubmit = async () => {
    if (selectedItems.length === 0) {
      message.warning(t('outbound.pleaseSelectItem'));
      return;
    }

    setSubmitting(true);
    try {
      // Add items one by one
      for (const item of selectedItems) {
        await outboundApi.addOutboundItem(orderId, {
          inventoryId: item.id,
          quantity: quantities[item.id],
          stockType: activeTab, // 'normal' or 'damaged'
        });
      }
      message.success(t('outbound.itemsAdded'));
      onSuccess();
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setSubmitting(false);
    }
  };

  const columns: ColumnsType<MerchantInventoryItem> = [
    {
      title: t('inventory.productImage'),
      dataIndex: ['product', 'primaryImage'],
      width: 80,
      render: (url: string | null) =>
        url ? (
          <Image
            src={url}
            width={60}
            height={60}
            style={{ objectFit: 'cover' }}
            preview={false}
          />
        ) : (
          <div className="w-[60px] h-[60px] bg-gray-100 flex items-center justify-center text-gray-400 text-xs">
            {t('common.noImages')}
          </div>
        ),
    },
    {
      title: t('inventory.productName'),
      dataIndex: ['product', 'name'],
      width: 160,
      ellipsis: true,
      render: (name: string | null, record) => (
        <div>
          <div className="font-medium truncate">{name || '-'}</div>
          <div className="text-xs text-gray-500">{record.product?.styleNumber || '-'}</div>
        </div>
      ),
    },
    {
      title: t('inventory.skuInfo'),
      dataIndex: 'sku',
      width: 120,
      render: (_, record) => (
        <div>
          <div>{record.sku?.skuName || '-'}</div>
          {record.product?.color && (
            <div className="text-xs text-gray-500">{record.product.color}</div>
          )}
        </div>
      ),
    },
    {
      title: activeTab === 'normal' ? t('outbound.availableStock') : t('outbound.damagedStock'),
      dataIndex: activeTab === 'normal' ? 'quantityAvailable' : 'quantityDamaged',
      width: 100,
      align: 'center',
      render: (qty: number) => (
        <Tag color={activeTab === 'normal' ? 'green' : 'orange'}>{qty}</Tag>
      ),
    },
    {
      title: t('outbound.outboundQuantity'),
      dataIndex: 'id',
      width: 120,
      render: (id: string, record) => {
        const maxQty = getMaxQuantity(record);
        const isSelected = selectedRowKeys.includes(id);
        return (
          <InputNumber
            min={1}
            max={maxQty}
            precision={0}
            value={quantities[id] || undefined}
            onChange={(value) => handleQuantityChange(id, value)}
            placeholder={`${t('common.max')} ${maxQty}`}
            style={{ width: '100%' }}
            disabled={!isSelected}
          />
        );
      },
    },
  ];

  const rowSelection = {
    selectedRowKeys,
    onChange: (keys: React.Key[]) => setSelectedRowKeys(keys),
  };

  // Count items by type
  const normalCount = inventoryItems.filter(item =>
    item.quantityAvailable > 0 &&
    !existingSkuCodes.includes(`${item.product?.styleNumber}-${item.sku.sizeValue}`)
  ).length;
  const damagedCount = inventoryItems.filter(item =>
    item.quantityDamaged > 0 &&
    !existingSkuCodes.includes(`${item.product?.styleNumber}-${item.sku.sizeValue}`)
  ).length;

  const tabItems = [
    {
      key: 'normal',
      label: (
        <span>
          {t('outbound.normalStock')} <Tag color="green">{normalCount}</Tag>
        </span>
      ),
    },
    {
      key: 'damaged',
      label: (
        <span>
          {t('outbound.damagedStock')} <Tag color="orange">{damagedCount}</Tag>
        </span>
      ),
    },
  ];

  return (
    <Modal
      title={t('outbound.addItem')}
      open={open}
      onCancel={onClose}
      onOk={handleSubmit}
      confirmLoading={submitting}
      okText={t('outbound.addToOrder')}
      okButtonProps={{ disabled: selectedItems.length === 0 }}
      width={800}
      destroyOnClose
    >
      <div className="flex flex-col gap-4">
        {/* Search */}
        <Input
          placeholder={t('outbound.searchInventory')}
          prefix={<SearchOutlined />}
          allowClear
          value={searchKeyword}
          onChange={e => setSearchKeyword(e.target.value)}
        />

        {/* Stock type tabs */}
        <Tabs
          activeKey={activeTab}
          onChange={handleTabChange}
          items={tabItems}
          size="small"
        />

        {/* Inventory table */}
        {loading ? (
          <div className="flex justify-center py-8">
            <Spin />
          </div>
        ) : filteredInventory.length === 0 ? (
          <Empty description={t('outbound.noInventoryAvailable')} />
        ) : (
          <>
            {/* Selection summary */}
            <div className="flex justify-between items-center text-sm">
              <span className="text-gray-500">
                {t('common.total')}: {filteredInventory.length} {t('common.items')}
              </span>
              {selectedItems.length > 0 && (
                <span className="text-blue-600">
                  {t('outbound.selectedItems', { count: selectedItems.length })}
                  {' - '}
                  {t('outbound.totalQuantityValue', {
                    quantity: selectedItems.reduce((sum, item) => sum + (quantities[item.id] || 0), 0),
                  })}
                </span>
              )}
            </div>

            <Table
              columns={columns}
              dataSource={filteredInventory}
              rowKey="id"
              rowSelection={rowSelection}
              pagination={filteredInventory.length > 10 ? { pageSize: 10 } : false}
              scroll={{ y: 400 }}
              size="small"
            />
          </>
        )}
      </div>
    </Modal>
  );
}
