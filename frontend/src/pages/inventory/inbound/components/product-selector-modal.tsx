import { useState, useCallback, useRef, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal, Input, Table, Button, Image, App } from 'antd';
import { SearchOutlined } from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';

import { inboundApi, type InboundProduct } from '@/lib/inbound-api';
import { SkuSelectorModal } from './sku-selector-modal';

interface ProductSelectorModalProps {
  open: boolean;
  orderId: string;
  onClose: () => void;
  onSuccess: () => void;
}

export function ProductSelectorModal({
  open,
  orderId,
  onClose,
  onSuccess,
}: ProductSelectorModalProps) {
  const { t } = useTranslation();
  const { message } = App.useApp();

  const [searchValue, setSearchValue] = useState('');
  const [products, setProducts] = useState<InboundProduct[]>([]);
  const [loading, setLoading] = useState(false);
  const searchTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // SKU selector state
  const [skuModalOpen, setSkuModalOpen] = useState(false);
  const [selectedProduct, setSelectedProduct] = useState<InboundProduct | null>(null);

  // Load default products when modal opens
  const loadProducts = useCallback(async (search: string = '') => {
    setLoading(true);
    try {
      const result = await inboundApi.searchProducts(search, 10);
      setProducts(result);
    } catch (error) {
      console.error('Failed to load products:', error);
      message.error(t('common.error'));
    } finally {
      setLoading(false);
    }
  }, [message, t]);

  // Load default products when modal opens
  useEffect(() => {
    if (open) {
      loadProducts();
    }
  }, [open, loadProducts]);

  // Search products with debounce
  const handleSearch = useCallback(async (value: string) => {
    if (searchTimerRef.current) {
      clearTimeout(searchTimerRef.current);
    }

    searchTimerRef.current = setTimeout(async () => {
      loadProducts(value);
    }, 300);
  }, [loadProducts]);

  const handleSearchChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = e.target.value;
    setSearchValue(value);
    handleSearch(value);
  };

  const handleSelectSize = (product: InboundProduct) => {
    setSelectedProduct(product);
    setSkuModalOpen(true);
  };

  const handleSkuModalClose = () => {
    setSkuModalOpen(false);
    setSelectedProduct(null);
  };

  const handleSkuModalSuccess = () => {
    setSkuModalOpen(false);
    setSelectedProduct(null);
    onSuccess();
  };

  const handleClose = () => {
    setSearchValue('');
    setProducts([]);
    onClose();
  };

  const columns: ColumnsType<InboundProduct> = [
    {
      title: t('inventory.productImage'),
      dataIndex: 'primaryImageUrl',
      width: 80,
      render: (url: string | null) =>
        url ? (
          <Image src={url} width={60} height={60} style={{ objectFit: 'cover' }} />
        ) : (
          <div className="w-[60px] h-[60px] bg-gray-100 flex items-center justify-center text-gray-400 text-xs">
            N/A
          </div>
        ),
    },
    {
      title: t('inventory.styleNumber'),
      dataIndex: 'styleNumber',
      width: 160,
      render: (text: string, record) => (
        <div>
          <div className="font-medium">{text}</div>
          <div className="text-xs text-gray-500">{record.name}</div>
        </div>
      ),
    },
    {
      title: t('inventory.color'),
      dataIndex: 'color',
      width: 100,
      render: (text: string | null) => text || '-',
    },
    {
      title: t('inventory.referencePrice'),
      dataIndex: 'skus',
      width: 100,
      render: (skus: InboundProduct['skus']) => {
        if (!skus || skus.length === 0) return '-';
        const prices = skus.map(s => parseFloat(s.price)).filter(p => p > 0);
        if (prices.length === 0) return '-';
        const minPrice = Math.min(...prices);
        const maxPrice = Math.max(...prices);
        if (minPrice === maxPrice) {
          return `¥${minPrice}`;
        }
        return `¥${minPrice} - ¥${maxPrice}`;
      },
    },
    {
      title: t('inventory.skuCount'),
      dataIndex: 'skus',
      width: 80,
      align: 'center',
      render: (skus: InboundProduct['skus']) => skus?.length || 0,
    },
    {
      title: t('common.actions'),
      key: 'actions',
      width: 120,
      fixed: 'right',
      render: (_, record) => (
        <Button type="primary" size="small" onClick={() => handleSelectSize(record)}>
          {t('inventory.selectSize')}
        </Button>
      ),
    },
  ];

  return (
    <>
      <Modal
        title={t('inventory.selectProduct')}
        open={open}
        onCancel={handleClose}
        footer={null}
        width={900}
        destroyOnClose
      >
        <div className="flex flex-col gap-4">
          {/* Search input */}
          <Input
            placeholder={t('inventory.searchByStyleNumber')}
            prefix={<SearchOutlined />}
            value={searchValue}
            onChange={handleSearchChange}
            allowClear
            size="large"
          />

          {/* Products table */}
          <Table
            columns={columns}
            dataSource={products}
            rowKey="id"
            loading={loading}
            pagination={false}
            scroll={{ x: 700, y: 400 }}
            size="small"
          />
        </div>
      </Modal>

      {/* SKU Selector Modal */}
      {selectedProduct && (
        <SkuSelectorModal
          open={skuModalOpen}
          product={selectedProduct}
          orderId={orderId}
          onClose={handleSkuModalClose}
          onSuccess={handleSkuModalSuccess}
        />
      )}
    </>
  );
}
