import { useState, useEffect, useCallback } from 'react';
import { useNavigate } from 'react-router';
import { useTranslation } from 'react-i18next';
import {
  Card,
  Row,
  Col,
  Button,
  Tag,
  Pagination,
  App,
  Empty,
  Spin,
  Image,
} from 'antd';
import { QueryFilter, ProFormText, ProFormSelect } from '@ant-design/pro-components';
import { PlusOutlined, ShoppingOutlined } from '@ant-design/icons';
import {
  productApi,
  type Product,
  type ProductListParams,
  type ProductStatus,
} from '@/lib/product-api';
import { brandApi } from '@/lib/brand-api';
import { categoryApi } from '@/lib/category-api';
import { ProductCreateModal } from './components/product-create-modal';

// Product Card Component
interface ProductCardProps {
  product: Product;
}

function ProductCard({ product }: ProductCardProps) {
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
    };
    return statusMap[product.status];
  };

  // Status overlay styles (only for draft and inactive)
  const statusOverlayColors: Record<string, string> = {
    draft: 'bg-gray-800/50',
    inactive: 'bg-amber-600/50',
  };

  const showStatusOverlay = product.status === 'draft' || product.status === 'inactive';

  return (
    <Card
      hoverable
      onClick={() => navigate(`/products/detail/${product.id}`)}
      styles={{ body: { padding: 12 } }}
      className="h-full flex flex-col [&>.ant-card-cover]:shrink-0 [&>.ant-card-body]:flex-1"
      cover={
        <div className="aspect-square bg-gray-100 overflow-hidden relative">
          {product.primaryImageUrl ? (
            <Image
              src={product.primaryImageUrl}
              alt={product.name}
              preview={false}
              className="object-cover w-full h-full"
            />
          ) : (
            <div className="w-full h-full flex items-center justify-center text-gray-300 text-5xl">
              <ShoppingOutlined />
            </div>
          )}
          {/* Status overlay - only show for draft and inactive */}
          {showStatusOverlay && (
            <div className={`absolute bottom-0 left-0 right-0 px-2 py-1 text-white text-xs text-center ${statusOverlayColors[product.status]}`}>
              {statusLabel()}
            </div>
          )}
        </div>
      }
    >
      <div className="flex flex-col h-full">
        {/* Title - fixed height for 2 lines */}
        <div className="font-medium text-sm leading-tight line-clamp-2 h-[2.5rem]" title={product.name}>
          {product.name}
        </div>
        {/* Style number */}
        <div className="text-xs text-gray-400 font-mono truncate mt-1">{product.styleNumber}</div>
        {/* Price */}
        <div className="font-semibold text-sm text-gray-900 mt-1">{priceDisplay()}</div>
        {/* Tags - push to bottom */}
        <div className="mt-auto pt-1.5">
          {product.tags && product.tags.length > 0 && (
            <div className="flex flex-wrap gap-1">
              {product.tags.slice(0, 2).map((tag) => (
                <Tag key={tag.id} className="text-xs leading-none" style={{ margin: 0, padding: '1px 4px' }}>
                  {tag.name}
                </Tag>
              ))}
              {product.tags.length > 2 && (
                <Tag className="text-xs leading-none" style={{ margin: 0, padding: '1px 4px' }}>+{product.tags.length - 2}</Tag>
              )}
            </div>
          )}
        </div>
      </div>
    </Card>
  );
}

// Main List Page
export function ProductsListPage() {
  const { t } = useTranslation();
  const { message } = App.useApp();

  const [products, setProducts] = useState<Product[]>([]);
  const [loading, setLoading] = useState(false);
  const [total, setTotal] = useState(0);
  const [params, setParams] = useState<ProductListParams>({ page: 1, limit: 12 });
  const [createModalOpen, setCreateModalOpen] = useState(false);

  // Filter options
  const [brands, setBrands] = useState<Array<{ id: string; name: string }>>([]);
  const [categories, setCategories] = useState<Array<{ id: string; name: string }>>([]);

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

  const handleFilter = (values: Record<string, string | undefined>) => {
    setParams({
      page: 1,
      limit: params.limit,
      search: values.search || undefined,
      brandId: values.brandId || undefined,
      categoryId: values.categoryId || undefined,
      status: values.status as ProductStatus | undefined,
      season: values.season || undefined,
    });
  };

  const handleReset = () => {
    setParams({ page: 1, limit: params.limit });
  };

  return (
    <div className="flex flex-col gap-4">
      <div className="flex justify-end">
        <Button type="primary" icon={<PlusOutlined />} onClick={() => setCreateModalOpen(true)}>
          {t('products.addProduct')}
        </Button>
      </div>

      {/* Search & Filters */}
      <Card >
        <QueryFilter style={{padding:0}}
          labelWidth="auto"
          onFinish={handleFilter}
          onReset={handleReset}
          defaultCollapsed={false}
          split
        >
          <ProFormText
            name="search"
            label={t('products.productName')}
            placeholder={t('products.searchPlaceholder')}
          />
          <ProFormSelect
            name="brandId"
            label={t('products.brand')}
            placeholder={t('products.selectBrand')}
            options={brands.map((b) => ({ label: b.name, value: b.id }))}
            showSearch
            allowClear
          />
          <ProFormSelect
            name="categoryId"
            label={t('products.category')}
            placeholder={t('products.selectCategory')}
            options={categories.map((c) => ({ label: c.name, value: c.id }))}
            showSearch
            allowClear
          />
          <ProFormSelect
            name="status"
            label={t('products.status')}
            placeholder={t('products.selectStatus')}
            options={[
              { label: t('products.statusDraft'), value: 'draft' },
              { label: t('products.statusActive'), value: 'active' },
              { label: t('products.statusInactive'), value: 'inactive' },
            ]}
            allowClear
          />
          <ProFormText
            name="season"
            label={t('products.season')}
            placeholder={t('products.season')}
          />
        </QueryFilter>
      </Card>

      {/* Product Grid */}
      <Card>
          <Spin spinning={loading}>
            {products.length > 0 ? (
              <>
                <Row gutter={[16, 16]}>
                  {products.map((product) => (
                    <Col key={product.id} xs={24} sm={12} md={8} lg={6} xl={4}>
                      <ProductCard product={product} />
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
                    showTotal={(total) => t('products.totalCount', { count: total })}
                    onChange={(page, pageSize) => setParams((p) => ({ ...p, page, limit: pageSize }))}
                  />
                </div>
              </>
            ) : (
              <Empty description={t('common.noData')}>
                <Button type="primary" onClick={() => setCreateModalOpen(true)}>
                  {t('products.addProduct')}
                </Button>
              </Empty>
            )}
        </Spin>
      </Card>

      {/* Create Product Modal */}
      <ProductCreateModal
        open={createModalOpen}
        onClose={() => setCreateModalOpen(false)}
      />
    </div>
  );
}
