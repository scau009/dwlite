import { useState, useEffect, useRef } from 'react';
import { useParams, useNavigate } from 'react-router';
import { useTranslation } from 'react-i18next';
import {
  Card,
  Button,
  Tag,
  Space,
  App,
  Select,
  Spin,
  Empty,
  Descriptions,
} from 'antd';
import {
  ArrowLeftOutlined,
  EditOutlined,
  DeleteOutlined,
  PlusOutlined,
  ThunderboltOutlined,
} from '@ant-design/icons';
import {
  productApi,
  type ProductDetail,
  type ProductStatus,
} from '@/lib/product-api';
import { ProductImages } from './components/product-images';
import { ProductSkus, type ProductSkusRef } from './components/product-skus';
import { ProductBasicInfoModal } from './components/product-basic-info-modal';

// Status color mapping
const statusColors: Record<ProductStatus, string> = {
  draft: 'default',
  active: 'success',
  inactive: 'warning',
};

export function ProductDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { t } = useTranslation();
  const { modal, message } = App.useApp();

  const [product, setProduct] = useState<ProductDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [editModalOpen, setEditModalOpen] = useState(false);
  const skusRef = useRef<ProductSkusRef>(null);

  const loadProduct = async () => {
    if (!id) return;
    setLoading(true);
    try {
      const data = await productApi.getProduct(id);
      setProduct(data);
    } catch {
      message.error(t('common.error'));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadProduct();
  }, [id]);

  const handleDelete = () => {
    modal.confirm({
      title: t('products.confirmDelete'),
      content: t('products.confirmDeleteDesc', { name: product?.name }),
      okButtonProps: { danger: true },
      onOk: async () => {
        try {
          await productApi.deleteProduct(id!);
          message.success(t('products.deleted'));
          navigate('/products');
        } catch {
          message.error(t('common.error'));
        }
      },
    });
  };

  const handleStatusChange = async (status: ProductStatus) => {
    try {
      await productApi.updateProductStatus(id!, status);
      message.success(t('products.statusUpdated'));
      loadProduct();
    } catch {
      message.error(t('common.error'));
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Spin size="large" />
      </div>
    );
  }

  if (!product) {
    return (
      <Card>
        <Empty description={t('common.noData')}>
          <Button type="primary" onClick={() => navigate('/products')}>
            {t('common.back')}
          </Button>
        </Empty>
      </Card>
    );
  }

  const statusLabel = {
    draft: t('products.statusDraft'),
    active: t('products.statusActive'),
    inactive: t('products.statusInactive'),
  }[product.status];

  return (
    <div className="flex flex-col gap-4">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-4">
          <Button icon={<ArrowLeftOutlined />} onClick={() => navigate('/products')}>
            {t('common.back')}
          </Button>
          <h1 className="text-xl font-semibold m-0">{product.name}</h1>
          <Tag color={statusColors[product.status]}>{statusLabel}</Tag>
        </div>
        <Space>
          <Select
            value={product.status}
            onChange={handleStatusChange}
            style={{ width: 120 }}
            options={[
              { label: t('products.statusDraft'), value: 'draft' },
              { label: t('products.statusActive'), value: 'active' },
              { label: t('products.statusInactive'), value: 'inactive' },
            ]}
          />
          <Button danger icon={<DeleteOutlined />} onClick={handleDelete}>
            {t('common.delete')}
          </Button>
        </Space>
      </div>

      {/* Images Section - Editable */}
      <Card title={`${t('products.images')} (${product.images.length})`}>
        <ProductImages
          productId={id!}
          images={product.images}
          onUpdate={loadProduct}
        />
      </Card>

      {/* Basic Info - View with Edit Button */}
      <Card
        title={t('detail.basicInfo')}
        extra={
          <Button icon={<EditOutlined />} onClick={() => setEditModalOpen(true)}>
            {t('common.edit')}
          </Button>
        }
      >
        <Descriptions column={{ xs: 1, sm: 2, md: 3, lg: 4 }} size="small">
          <Descriptions.Item label={t('products.styleNumber')}>
            <span className="font-mono">{product.styleNumber}</span>
          </Descriptions.Item>
          <Descriptions.Item label={t('products.season')}>{product.season}</Descriptions.Item>
          <Descriptions.Item label={t('products.color')}>{product.color || '-'}</Descriptions.Item>
          <Descriptions.Item label={t('products.brand')}>
            {product.brandName || '-'}
          </Descriptions.Item>
          <Descriptions.Item label={t('products.category')}>
            {product.categoryName || '-'}
          </Descriptions.Item>
          <Descriptions.Item label={t('products.skuCount')}>{product.skuCount}</Descriptions.Item>
          <Descriptions.Item label={t('products.priceRange')}>
            {product.priceRange.min !== null
              ? product.priceRange.min === product.priceRange.max
                ? `¥${product.priceRange.min}`
                : `¥${product.priceRange.min} - ¥${product.priceRange.max}`
              : t('products.noPrice')}
          </Descriptions.Item>
          <Descriptions.Item label={t('common.createdAt')}>
            {new Date(product.createdAt).toLocaleDateString()}
          </Descriptions.Item>
        </Descriptions>
        {product.tags.length > 0 && (
          <div className="mt-3 pt-3 border-t">
            <span className="text-gray-500 mr-2">{t('products.tags')}:</span>
            <Space wrap size="small">
              {product.tags.map((tag) => (
                <Tag key={tag.id}>{tag.name}</Tag>
              ))}
            </Space>
          </div>
        )}
        {product.description && (
          <div className="mt-3 pt-3 border-t">
            <span className="text-gray-500 mr-2">{t('detail.description')}:</span>
            <span>{product.description}</span>
          </div>
        )}
      </Card>

      {/* SKU Management - Editable */}
      <Card
        title={`${t('products.skuManagement')} (${product.skus.length})`}
        extra={
          <Space>
            <Button
              icon={<ThunderboltOutlined />}
              onClick={() => skusRef.current?.openQuickAddModal()}
            >
              {t('products.quickAddSize')}
            </Button>
            <Button
              type="primary"
              icon={<PlusOutlined />}
              onClick={() => skusRef.current?.openAddModal()}
            >
              {t('products.addSku')}
            </Button>
          </Space>
        }
      >
        <ProductSkus
          ref={skusRef}
          productId={id!}
          skus={product.skus}
          onUpdate={loadProduct}
        />
      </Card>

      {/* Edit Basic Info Modal */}
      <ProductBasicInfoModal
        open={editModalOpen}
        product={product}
        onClose={() => setEditModalOpen(false)}
        onSuccess={() => {
          setEditModalOpen(false);
          loadProduct();
        }}
      />
    </div>
  );
}
