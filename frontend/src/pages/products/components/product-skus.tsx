import { useState, forwardRef, useImperativeHandle } from 'react';
import { useTranslation } from 'react-i18next';
import { Table, Button, Tag, Switch, App, Space, Empty } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import type { TableRowSelection } from 'antd/es/table/interface';
import { productApi, CURRENCIES, type ProductSku, type Currency } from '@/lib/product-api';
import { SkuFormModal } from './sku-form-modal';
import { QuickAddSizeModal } from './quick-add-size-modal';
import { BatchEditSkuModal } from './batch-edit-sku-modal';

interface ProductSkusProps {
  productId: string;
  skus: ProductSku[];
  onUpdate: () => void;
  disabled?: boolean;
}

export interface ProductSkusRef {
  openAddModal: () => void;
  openQuickAddModal: () => void;
}

export const ProductSkus = forwardRef<ProductSkusRef, ProductSkusProps>(function ProductSkus(
  { productId, skus, onUpdate, disabled },
  ref
) {
  const { t } = useTranslation();
  const { message, modal } = App.useApp();
  const [formModalOpen, setFormModalOpen] = useState(false);
  const [quickAddModalOpen, setQuickAddModalOpen] = useState(false);
  const [batchEditModalOpen, setBatchEditModalOpen] = useState(false);
  const [editingSku, setEditingSku] = useState<ProductSku | null>(null);
  const [statusLoading, setStatusLoading] = useState<string | null>(null);
  const [selectedRowKeys, setSelectedRowKeys] = useState<React.Key[]>([]);
  const [batchDeleteLoading, setBatchDeleteLoading] = useState(false);

  const handleAdd = () => {
    setEditingSku(null);
    setFormModalOpen(true);
  };

  const handleQuickAdd = () => {
    setQuickAddModalOpen(true);
  };

  useImperativeHandle(ref, () => ({
    openAddModal: handleAdd,
    openQuickAddModal: handleQuickAdd,
  }));

  const handleEdit = (sku: ProductSku) => {
    setEditingSku(sku);
    setFormModalOpen(true);
  };

  const handleDelete = async (sku: ProductSku) => {
    modal.confirm({
      title: t('products.confirmDeleteSku'),
      content: t('products.confirmDeleteSkuDesc', { code: sku.specDescription || sku.id }),
      okText: t('common.confirm'),
      cancelText: t('common.cancel'),
      okButtonProps: { danger: true },
      onOk: async () => {
        try {
          await productApi.deleteSku(productId, sku.id);
          message.success(t('products.skuDeleted'));
          onUpdate();
        } catch (error) {
          const err = error as { error?: string };
          message.error(err.error || t('common.error'));
        }
      },
    });
  };

  const handleStatusChange = async (sku: ProductSku, isActive: boolean) => {
    setStatusLoading(sku.id);
    try {
      await productApi.updateSkuStatus(productId, sku.id, isActive);
      message.success(isActive ? t('products.skuActivated') : t('products.skuDeactivated'));
      onUpdate();
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setStatusLoading(null);
    }
  };

  const handleBatchDelete = () => {
    modal.confirm({
      title: t('products.confirmBatchDelete'),
      content: t('products.confirmBatchDeleteDesc', { count: selectedRowKeys.length }),
      okText: t('common.confirm'),
      cancelText: t('common.cancel'),
      okButtonProps: { danger: true },
      onOk: async () => {
        setBatchDeleteLoading(true);
        try {
          const result = await productApi.batchDeleteSkus(productId, selectedRowKeys as string[]);
          message.success(t('products.batchDeleted', { count: result.deletedCount }));
          setSelectedRowKeys([]);
          onUpdate();
        } catch (error) {
          const err = error as { error?: string };
          message.error(err.error || t('common.error'));
        } finally {
          setBatchDeleteLoading(false);
        }
      },
    });
  };

  const handleBatchEdit = () => {
    setBatchEditModalOpen(true);
  };

  const rowSelection: TableRowSelection<ProductSku> = {
    selectedRowKeys,
    onChange: (keys: React.Key[]) => setSelectedRowKeys(keys),
  };

  const getCurrencySymbol = (currency: string) => {
    const found = CURRENCIES.find((c) => c.value === currency);
    return found ? found.symbol : '$';
  };

  const columns: ColumnsType<ProductSku> = [
    {
      title: t('products.size'),
      key: 'size',
      width: 120,
      render: (_, record) => {
        if (record.sizeUnit && record.sizeValue) {
          return `${record.sizeUnit} ${record.sizeValue}`;
        }
        return '-';
      },
    },
    {
      title: t('products.price'),
      dataIndex: 'price',
      key: 'price',
      width: 120,
      render: (price: string, record) => {
        const symbol = getCurrencySymbol(record.currency);
        return `${symbol}${parseFloat(price).toFixed(2)}`;
      },
    },
    {
      title: t('products.originalPrice'),
      dataIndex: 'originalPrice',
      key: 'originalPrice',
      width: 120,
      render: (price: string | null, record) => {
        if (!price) return '-';
        const symbol = getCurrencySymbol(record.currency);
        return `${symbol}${parseFloat(price).toFixed(2)}`;
      },
    },
    {
      title: t('products.barcode'),
      dataIndex: 'barcode',
      key: 'barcode',
      width: 150,
      render: (barcode: string | null) => barcode || '-',
    },
    {
      title: t('products.status'),
      dataIndex: 'isActive',
      key: 'isActive',
      width: 100,
      render: (isActive: boolean, record) => (
        disabled ? (
          <Tag color={isActive ? 'success' : 'default'}>
            {isActive ? t('products.active') : t('products.inactive')}
          </Tag>
        ) : (
          <Switch
            checked={isActive}
            loading={statusLoading === record.id}
            onChange={(checked) => handleStatusChange(record, checked)}
            size="small"
          />
        )
      ),
    },
    {
      title: t('common.actions'),
      key: 'actions',
      width: 120,
      render: (_, record) => (
        disabled ? null : (
          <Space size="small">
            <Button
              type="link"
              size="small"
              onClick={() => handleEdit(record)}
            >
              {t('common.edit')}
            </Button>
            <Button
              type="link"
              size="small"
              danger
              onClick={() => handleDelete(record)}
            >
              {t('common.delete')}
            </Button>
          </Space>
        )
      ),
    },
  ];

  return (
    <div>
      {!disabled && selectedRowKeys.length > 0 && (
        <div style={{ marginBottom: 16, display: 'flex', alignItems: 'center', gap: 8 }}>
          <span>{t('products.selectedCount', { count: selectedRowKeys.length })}</span>
          <Button
            size="small"
            onClick={handleBatchEdit}
          >
            {t('products.batchEdit')}
          </Button>
          <Button
            size="small"
            danger
            loading={batchDeleteLoading}
            onClick={handleBatchDelete}
          >
            {t('products.batchDelete')}
          </Button>
        </div>
      )}

      {skus.length > 0 ? (
        <Table
          columns={columns}
          dataSource={skus}
          rowKey="id"
          pagination={false}
          size="small"
          rowSelection={disabled ? undefined : rowSelection}
        />
      ) : (
        <Empty description={t('products.noSkus')} />
      )}

      <SkuFormModal
        open={formModalOpen}
        productId={productId}
        sku={editingSku}
        existingCurrency={skus.length > 0 ? (skus[0].currency as Currency) : undefined}
        onClose={() => {
          setFormModalOpen(false);
          setEditingSku(null);
        }}
        onSuccess={() => {
          setFormModalOpen(false);
          setEditingSku(null);
          onUpdate();
        }}
      />

      <QuickAddSizeModal
        open={quickAddModalOpen}
        productId={productId}
        existingCurrency={skus.length > 0 ? (skus[0].currency as Currency) : undefined}
        onClose={() => setQuickAddModalOpen(false)}
        onSuccess={() => {
          setQuickAddModalOpen(false);
          onUpdate();
        }}
      />

      <BatchEditSkuModal
        open={batchEditModalOpen}
        productId={productId}
        selectedCount={selectedRowKeys.length}
        skuIds={selectedRowKeys as string[]}
        currency={skus.length > 0 ? (skus[0].currency as Currency) : undefined}
        onClose={() => setBatchEditModalOpen(false)}
        onSuccess={() => {
          setBatchEditModalOpen(false);
          setSelectedRowKeys([]);
          onUpdate();
        }}
      />
    </div>
  );
});
