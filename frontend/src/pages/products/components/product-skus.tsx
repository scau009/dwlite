import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Table, Button, Tag, Switch, App, Popconfirm, Space, Tooltip, Empty } from 'antd';
import { PlusOutlined, EditOutlined, DeleteOutlined } from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';
import { productApi, type ProductSku } from '@/lib/product-api';
import { SkuFormModal } from './sku-form-modal';

interface ProductSkusProps {
  productId: string;
  skus: ProductSku[];
  onUpdate: () => void;
  disabled?: boolean;
}

export function ProductSkus({ productId, skus, onUpdate, disabled }: ProductSkusProps) {
  const { t } = useTranslation();
  const { message, modal } = App.useApp();
  const [formModalOpen, setFormModalOpen] = useState(false);
  const [editingSku, setEditingSku] = useState<ProductSku | null>(null);
  const [statusLoading, setStatusLoading] = useState<string | null>(null);

  const handleAdd = () => {
    setEditingSku(null);
    setFormModalOpen(true);
  };

  const handleEdit = (sku: ProductSku) => {
    setEditingSku(sku);
    setFormModalOpen(true);
  };

  const handleDelete = async (sku: ProductSku) => {
    modal.confirm({
      title: t('products.confirmDeleteSku'),
      content: t('products.confirmDeleteSkuDesc', { code: sku.skuCode }),
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

  const columns: ColumnsType<ProductSku> = [
    {
      title: t('products.skuCode'),
      dataIndex: 'skuCode',
      key: 'skuCode',
      width: 180,
      render: (code: string) => (
        <code className="text-xs bg-gray-100 px-2 py-1 rounded">{code}</code>
      ),
    },
    {
      title: t('products.spec'),
      key: 'spec',
      width: 120,
      render: (_, record) => (
        record.specDescription || '-'
      ),
    },
    {
      title: t('products.price'),
      dataIndex: 'price',
      key: 'price',
      width: 100,
      render: (price: string) => `¥${parseFloat(price).toFixed(2)}`,
    },
    {
      title: t('products.originalPrice'),
      dataIndex: 'originalPrice',
      key: 'originalPrice',
      width: 100,
      render: (price: string | null) => price ? `¥${parseFloat(price).toFixed(2)}` : '-',
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
      width: 100,
      render: (_, record) => (
        disabled ? null : (
          <Space size="small">
            <Tooltip title={t('common.edit')}>
              <Button
                type="text"
                size="small"
                icon={<EditOutlined />}
                onClick={() => handleEdit(record)}
              />
            </Tooltip>
            <Tooltip title={t('common.delete')}>
              <Button
                type="text"
                size="small"
                danger
                icon={<DeleteOutlined />}
                onClick={() => handleDelete(record)}
              />
            </Tooltip>
          </Space>
        )
      ),
    },
  ];

  return (
    <div>
      {!disabled && (
        <div className="mb-4">
          <Button
            type="primary"
            icon={<PlusOutlined />}
            onClick={handleAdd}
          >
            {t('products.addSku')}
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
        />
      ) : (
        <Empty description={t('products.noSkus')} />
      )}

      <SkuFormModal
        open={formModalOpen}
        productId={productId}
        sku={editingSku}
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
    </div>
  );
}
