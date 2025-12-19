import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router';
import { useTranslation } from 'react-i18next';
import {
  Card,
  Button,
  Tabs,
  Tag,
  Space,
  Table,
  Statistic,
  Row,
  Col,
  App,
  Descriptions,
  Image,
  Switch,
  Popconfirm,
  Select,
  Spin,
  Empty,
} from 'antd';
import {
  ArrowLeftOutlined,
  EditOutlined,
  DeleteOutlined,
  PlusOutlined,
  ShoppingOutlined,
} from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';
import {
  productApi,
  type ProductDetail,
  type ProductSku,
  type ProductStatus,
} from '@/lib/product-api';
import { SkuFormModal } from './components/sku-form-modal';

// Status color mapping
const statusColors: Record<ProductStatus, string> = {
  draft: 'default',
  active: 'success',
  inactive: 'warning',
  discontinued: 'error',
};

export function ProductDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { t } = useTranslation();
  const { modal, message } = App.useApp();

  const [product, setProduct] = useState<ProductDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [skuModalOpen, setSkuModalOpen] = useState(false);
  const [editingSku, setEditingSku] = useState<ProductSku | null>(null);

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

  const handleSkuStatusChange = async (sku: ProductSku, isActive: boolean) => {
    try {
      await productApi.updateSkuStatus(id!, sku.id, isActive);
      message.success(t('products.skuStatusUpdated'));
      loadProduct();
    } catch {
      message.error(t('common.error'));
    }
  };

  const handleDeleteSku = (sku: ProductSku) => {
    modal.confirm({
      title: t('products.confirmDeleteSku'),
      okButtonProps: { danger: true },
      onOk: async () => {
        try {
          await productApi.deleteSku(id!, sku.id);
          message.success(t('products.skuDeleted'));
          loadProduct();
        } catch {
          message.error(t('common.error'));
        }
      },
    });
  };

  const priceDisplay = () => {
    if (!product) return '-';
    const { min, max } = product.priceRange;
    if (min === null) return t('products.noPrice');
    if (min === max) return `¥${min.toFixed(2)}`;
    return `¥${min.toFixed(2)} - ¥${max.toFixed(2)}`;
  };

  // SKU table columns
  const skuColumns: ColumnsType<ProductSku> = [
    {
      title: t('products.skuCode'),
      dataIndex: 'skuCode',
      key: 'skuCode',
      width: 180,
    },
    {
      title: t('products.spec'),
      dataIndex: 'specDescription',
      key: 'spec',
      render: (text: string) => text || '-',
    },
    {
      title: t('products.price'),
      dataIndex: 'price',
      key: 'price',
      width: 100,
      render: (v: string) => `¥${v}`,
    },
    {
      title: t('products.originalPrice'),
      dataIndex: 'originalPrice',
      key: 'originalPrice',
      width: 100,
      render: (v: string | null) => (v ? `¥${v}` : '-'),
    },
    {
      title: t('products.status'),
      dataIndex: 'isActive',
      key: 'status',
      width: 100,
      render: (isActive: boolean, record: ProductSku) => (
        <Popconfirm
          title={isActive ? t('products.confirmDeactivateSku') : t('products.confirmActivateSku')}
          onConfirm={() => handleSkuStatusChange(record, !isActive)}
        >
          <Switch checked={isActive} size="small" />
        </Popconfirm>
      ),
    },
    {
      title: t('common.actions'),
      key: 'actions',
      width: 150,
      render: (_: unknown, record: ProductSku) => (
        <Space>
          <Button
            size="small"
            onClick={() => {
              setEditingSku(record);
              setSkuModalOpen(true);
            }}
          >
            {t('common.edit')}
          </Button>
          <Button size="small" danger onClick={() => handleDeleteSku(record)}>
            {t('common.delete')}
          </Button>
        </Space>
      ),
    },
  ];

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

  const tabItems = [
    {
      key: 'basic',
      label: t('detail.basicInfo'),
      children: (
        <Descriptions column={2} bordered>
          <Descriptions.Item label={t('products.productName')}>{product.name}</Descriptions.Item>
          <Descriptions.Item label={t('products.styleNumber')}>
            {product.styleNumber}
          </Descriptions.Item>
          <Descriptions.Item label={t('products.season')}>{product.season}</Descriptions.Item>
          <Descriptions.Item label={t('products.color')}>{product.color || '-'}</Descriptions.Item>
          <Descriptions.Item label={t('products.brand')}>
            {product.brandName || '-'}
          </Descriptions.Item>
          <Descriptions.Item label={t('products.category')}>
            {product.categoryName || '-'}
          </Descriptions.Item>
          <Descriptions.Item label={t('detail.description')} span={2}>
            {product.description || '-'}
          </Descriptions.Item>
          {product.tags.length > 0 && (
            <Descriptions.Item label="Tags" span={2}>
              <Space wrap>
                {product.tags.map((tag) => (
                  <Tag key={tag.id}>{tag.name}</Tag>
                ))}
              </Space>
            </Descriptions.Item>
          )}
        </Descriptions>
      ),
    },
    {
      key: 'skus',
      label: `${t('products.skuList')} (${product.skus.length})`,
      children: (
        <div>
          <div className="mb-4 flex justify-end">
            <Button
              type="primary"
              icon={<PlusOutlined />}
              onClick={() => {
                setEditingSku(null);
                setSkuModalOpen(true);
              }}
            >
              {t('products.addSku')}
            </Button>
          </div>
          <Table
            dataSource={product.skus}
            columns={skuColumns}
            rowKey="id"
            pagination={false}
            size="small"
          />
        </div>
      ),
    },
    {
      key: 'images',
      label: `${t('products.images')} (${product.images.length})`,
      children:
        product.images.length > 0 ? (
          <Image.PreviewGroup>
            <div className="grid grid-cols-6 gap-4">
              {product.images.map((img) => (
                <div key={img.id} className="relative">
                  <Image
                    src={img.url}
                    className="rounded aspect-square object-cover"
                    width="100%"
                  />
                  {img.isPrimary && (
                    <Tag className="absolute top-2 left-2" color="blue">
                      {t('products.primaryImage')}
                    </Tag>
                  )}
                </div>
              ))}
            </div>
          </Image.PreviewGroup>
        ) : (
          <Empty description={t('common.noData')} />
        ),
    },
  ];

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex items-center justify-between">
        <Button icon={<ArrowLeftOutlined />} onClick={() => navigate('/products')}>
          {t('common.back')}
        </Button>
        <Space>
          <Button icon={<EditOutlined />} onClick={() => navigate(`/products/${id}/edit`)}>
            {t('common.edit')}
          </Button>
          <Button danger icon={<DeleteOutlined />} onClick={handleDelete}>
            {t('common.delete')}
          </Button>
        </Space>
      </div>

      {/* Product Header Card */}
      <Card>
        <div className="flex gap-6">
          <div className="w-48 h-48 bg-gray-100 rounded overflow-hidden flex-shrink-0">
            {product.primaryImageUrl ? (
              <Image
                src={product.primaryImageUrl}
                className="object-cover w-full h-full"
                width="100%"
                height="100%"
              />
            ) : (
              <div className="flex items-center justify-center h-full text-gray-300 text-5xl">
                <ShoppingOutlined />
              </div>
            )}
          </div>
          <div className="flex-1">
            <div className="flex items-start justify-between mb-4">
              <div>
                <h1 className="text-2xl font-bold">{product.name}</h1>
                <p className="text-gray-500">{product.styleNumber}</p>
              </div>
              <Select
                value={product.status}
                onChange={handleStatusChange}
                style={{ width: 150 }}
                options={[
                  { label: t('products.statusDraft'), value: 'draft' },
                  { label: t('products.statusActive'), value: 'active' },
                  { label: t('products.statusInactive'), value: 'inactive' },
                  { label: t('products.statusDiscontinued'), value: 'discontinued' },
                ]}
              />
            </div>
            <Row gutter={24}>
              <Col span={6}>
                <Statistic title={t('products.priceRange')} value={priceDisplay()} />
              </Col>
              <Col span={6}>
                <Statistic title={t('products.skuCount')} value={product.skuCount} />
              </Col>
              <Col span={6}>
                <Statistic title={t('products.brand')} value={product.brandName || '-'} />
              </Col>
              <Col span={6}>
                <Statistic title={t('products.season')} value={product.season} />
              </Col>
            </Row>
          </div>
        </div>
      </Card>

      {/* Tabs */}
      <Card>
        <Tabs items={tabItems} />
      </Card>

      {/* SKU Form Modal */}
      <SkuFormModal
        open={skuModalOpen}
        productId={id!}
        sku={editingSku}
        onClose={() => {
          setSkuModalOpen(false);
          setEditingSku(null);
        }}
        onSuccess={() => {
          setSkuModalOpen(false);
          setEditingSku(null);
          loadProduct();
        }}
      />
    </div>
  );
}
