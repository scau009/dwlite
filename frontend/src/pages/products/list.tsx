import { useState, useEffect, useCallback } from 'react';
import { useNavigate } from 'react-router';
import { useTranslation } from 'react-i18next';
import {
  Card,
  Row,
  Col,
  Input,
  Select,
  Button,
  Tag,
  Pagination,
  Dropdown,
  App,
  Empty,
  Spin,
  Image,
} from 'antd';
import {
  PlusOutlined,
  FilterOutlined,
  EllipsisOutlined,
  EditOutlined,
  EyeOutlined,
  DeleteOutlined,
  ShoppingOutlined,
} from '@ant-design/icons';
import {
  productApi,
  type Product,
  type ProductListParams,
  type ProductStatus,
} from '@/lib/product-api';
import { brandApi } from '@/lib/brand-api';
import { categoryApi } from '@/lib/category-api';

// Status color mapping
const statusColors: Record<ProductStatus, string> = {
  draft: 'default',
  active: 'success',
  inactive: 'warning',
  discontinued: 'error',
};

// Product Card Component
interface ProductCardProps {
  product: Product;
  onDelete: (product: Product) => void;
}

function ProductCard({ product, onDelete }: ProductCardProps) {
  const { t } = useTranslation();
  const navigate = useNavigate();

  const priceDisplay = () => {
    const { min, max } = product.priceRange;
    if (min === null || max === null) return t('products.noPrice');
    if (min === max) return `¥${min.toFixed(2)}`;
    return `¥${min.toFixed(2)} - ¥${max.toFixed(2)}`;
  };

  const statusLabel = () => {
    const statusMap: Record<ProductStatus, string> = {
      draft: t('products.statusDraft'),
      active: t('products.statusActive'),
      inactive: t('products.statusInactive'),
      discontinued: t('products.statusDiscontinued'),
    };
    return statusMap[product.status];
  };

  const menuItems = [
    { key: 'view', icon: <EyeOutlined />, label: t('common.view') },
    { key: 'edit', icon: <EditOutlined />, label: t('common.edit') },
    { type: 'divider' as const },
    { key: 'delete', icon: <DeleteOutlined />, label: t('common.delete'), danger: true },
  ];

  const handleMenuClick = ({ key }: { key: string }) => {
    switch (key) {
      case 'view':
        navigate(`/products/${product.id}`);
        break;
      case 'edit':
        navigate(`/products/${product.id}/edit`);
        break;
      case 'delete':
        onDelete(product);
        break;
    }
  };

  return (
    <Card
      hoverable
      cover={
        <div
          className="aspect-square bg-gray-100 flex items-center justify-center overflow-hidden cursor-pointer"
          onClick={() => navigate(`/products/${product.id}`)}
        >
          {product.primaryImageUrl ? (
            <Image
              src={product.primaryImageUrl}
              alt={product.name}
              preview={false}
              className="object-cover w-full h-full"
            />
          ) : (
            <div className="text-gray-300 text-5xl">
              <ShoppingOutlined />
            </div>
          )}
        </div>
      }
      actions={[
        <EyeOutlined key="view" onClick={() => navigate(`/products/${product.id}`)} />,
        <EditOutlined key="edit" onClick={() => navigate(`/products/${product.id}/edit`)} />,
        <Dropdown
          key="more"
          menu={{ items: menuItems, onClick: handleMenuClick }}
          trigger={['click']}
        >
          <EllipsisOutlined />
        </Dropdown>,
      ]}
    >
      <Card.Meta
        title={
          <div className="flex items-start justify-between gap-2">
            <span className="truncate flex-1" title={product.name}>
              {product.name}
            </span>
            <Tag color={statusColors[product.status]} className="shrink-0">
              {statusLabel()}
            </Tag>
          </div>
        }
        description={
          <div className="space-y-1">
            <div className="text-xs text-gray-500">{product.styleNumber}</div>
            <div className="font-semibold text-base text-gray-900">{priceDisplay()}</div>
            <div className="flex items-center justify-between text-xs text-gray-500">
              <span>{product.brandName || '-'}</span>
              <span>
                {product.skuCount} {t('products.sku')}
              </span>
            </div>
          </div>
        }
      />
    </Card>
  );
}

// Main List Page
export function ProductsListPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { message, modal } = App.useApp();

  const [products, setProducts] = useState<Product[]>([]);
  const [loading, setLoading] = useState(false);
  const [total, setTotal] = useState(0);
  const [params, setParams] = useState<ProductListParams>({ page: 1, limit: 12 });

  // Filter options
  const [brands, setBrands] = useState<Array<{ id: string; name: string }>>([]);
  const [categories, setCategories] = useState<Array<{ id: string; name: string }>>([]);

  // Search state
  const [searchValue, setSearchValue] = useState('');
  const [showFilters, setShowFilters] = useState(false);

  const loadProducts = useCallback(async () => {
    setLoading(true);
    try {
      const result = await productApi.getProducts(params);
      setProducts(result.data);
      setTotal(result.total);
    } catch {
      message.error(t('common.error'));
    } finally {
      setLoading(false);
    }
  }, [params, message, t]);

  useEffect(() => {
    loadProducts();
  }, [loadProducts]);

  // Load filter options
  useEffect(() => {
    brandApi.getBrands({ limit: 100 }).then((r) => setBrands(r.data));
    categoryApi.getCategories({ limit: 100 }).then((r) => setCategories(r.data));
  }, []);

  const handleSearch = () => {
    setParams((p) => ({ ...p, page: 1, search: searchValue || undefined }));
  };

  const handleDelete = (product: Product) => {
    modal.confirm({
      title: t('products.confirmDelete'),
      content: t('products.confirmDeleteDesc', { name: product.name }),
      okButtonProps: { danger: true },
      onOk: async () => {
        try {
          await productApi.deleteProduct(product.id);
          message.success(t('products.deleted'));
          loadProducts();
        } catch {
          message.error(t('common.error'));
        }
      },
    });
  };

  const handleFilterChange = (key: string, value: string | undefined) => {
    setParams((p) => ({ ...p, page: 1, [key]: value }));
  };

  return (
    <div className="flex flex-col gap-4">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-xl font-semibold">{t('products.title')}</h1>
          <p className="text-gray-500">{t('products.description')}</p>
        </div>
        <Button type="primary" icon={<PlusOutlined />} onClick={() => navigate('/products/new')}>
          {t('products.addProduct')}
        </Button>
      </div>

      {/* Search & Filters */}
      <Card>
        <div className="flex items-center gap-4">
          <Input.Search
            placeholder={t('products.searchPlaceholder')}
            value={searchValue}
            onChange={(e) => setSearchValue(e.target.value)}
            onSearch={handleSearch}
            style={{ width: 300 }}
            allowClear
            onClear={() => setParams((p) => ({ ...p, page: 1, search: undefined }))}
          />
          <Button
            icon={<FilterOutlined />}
            onClick={() => setShowFilters(!showFilters)}
            type={showFilters ? 'primary' : 'default'}
          >
            {t('common.moreFilters')}
          </Button>
          <div className="flex-1" />
          <span className="text-gray-500">
            {t('common.showing', {
              from: (params.page! - 1) * params.limit! + 1,
              to: Math.min(params.page! * params.limit!, total),
              total,
            })}
          </span>
        </div>

        {showFilters && (
          <div className="mt-4 grid grid-cols-4 gap-4">
            <Select
              placeholder={t('products.selectBrand')}
              allowClear
              options={brands.map((b) => ({ label: b.name, value: b.id }))}
              onChange={(v) => handleFilterChange('brandId', v)}
              value={params.brandId}
            />
            <Select
              placeholder={t('products.selectCategory')}
              allowClear
              options={categories.map((c) => ({ label: c.name, value: c.id }))}
              onChange={(v) => handleFilterChange('categoryId', v)}
              value={params.categoryId}
            />
            <Select
              placeholder={t('products.selectStatus')}
              allowClear
              options={[
                { label: t('products.statusDraft'), value: 'draft' },
                { label: t('products.statusActive'), value: 'active' },
                { label: t('products.statusInactive'), value: 'inactive' },
                { label: t('products.statusDiscontinued'), value: 'discontinued' },
              ]}
              onChange={(v) => handleFilterChange('status', v)}
              value={params.status}
            />
            <Input
              placeholder={t('products.season')}
              onChange={(e) => handleFilterChange('season', e.target.value || undefined)}
              value={params.season}
              allowClear
            />
          </div>
        )}
      </Card>

      {/* Product Grid */}
      <Card>
          <Spin spinning={loading}>
            {products.length > 0 ? (
              <>
                <Row gutter={[16, 16]}>
                  {products.map((product) => (
                    <Col key={product.id} xs={24} sm={12} md={8} lg={6} xl={4}>
                      <ProductCard product={product} onDelete={handleDelete} />
                    </Col>
                  ))}
                </Row>
                <div className="flex justify-center mt-6">
                  <Pagination
                    current={params.page}
                    pageSize={params.limit}
                    total={total}
                    showSizeChanger
                    pageSizeOptions={[12, 24, 48, 96]}
                    showTotal={(total) => `${total} ${t('products.title').toLowerCase()}`}
                    onChange={(page, pageSize) => setParams((p) => ({ ...p, page, limit: pageSize }))}
                  />
                </div>
              </>
            ) : (
              <Empty description={t('common.noData')}>
                <Button type="primary" onClick={() => navigate('/products/new')}>
                  {t('products.addProduct')}
                </Button>
              </Empty>
            )}
        </Spin>
      </Card>
    </div>
  );
}
