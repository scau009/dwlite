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
  Checkbox,
  Space,
} from 'antd';
import { QueryFilter, ProFormText, ProFormSelect } from '@ant-design/pro-components';
import { PlusOutlined, ShoppingOutlined, EditOutlined, CloseOutlined, CheckOutlined } from '@ant-design/icons';
import {
  productApi,
  type Product,
  type ProductListParams,
  type ProductStatus,
} from '@/lib/product-api';
import { brandApi } from '@/lib/brand-api';
import { categoryApi } from '@/lib/category-api';
import { ProductCreateModal } from './components/product-create-modal';
import { BatchUpdateStatusModal } from './components/batch-update-status-modal';

// Product Card Component
interface ProductCardProps {
  product: Product;
  selectable?: boolean;
  selected?: boolean;
  onSelect?: (id: string, selected: boolean) => void;
}

function ProductCard({ product, selectable, selected, onSelect }: ProductCardProps) {
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

  const handleClick = () => {
    if (selectable) {
      onSelect?.(product.id, !selected);
    } else {
      navigate(`/products/detail/${product.id}`);
    }
  };

  const handleCheckboxClick = (e: React.MouseEvent) => {
    e.stopPropagation();
    onSelect?.(product.id, !selected);
  };

  return (
    <Card
      hoverable
      onClick={handleClick}
      styles={{ body: { padding: 12 } }}
      className={`h-full flex flex-col [&>.ant-card-cover]:shrink-0 [&>.ant-card-body]:flex-1 ${selected ? 'border-blue-500 border-2' : ''}`}
      cover={
        <div className="aspect-[4/3] bg-gray-100 dark:bg-gray-800 overflow-hidden relative">
          {product.primaryImageUrl ? (
            <div className="absolute inset-0 flex items-center justify-center p-2">
              <Image
                src={product.primaryImageUrl}
                alt={product.name}
                preview={false}
                style={{ maxWidth: '100%', maxHeight: '100%', objectFit: 'contain' }}
              />
            </div>
          ) : (
            <div className="w-full h-full flex items-center justify-center text-gray-300 text-5xl">
              <ShoppingOutlined />
            </div>
          )}
          {/* Selection checkbox */}
          {selectable && (
            <div
              className="absolute top-2 left-2 z-10"
              onClick={handleCheckboxClick}
            >
              <Checkbox checked={selected} className="[&_.ant-checkbox-inner]:w-5 [&_.ant-checkbox-inner]:h-5" />
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
        {/* Title - fixed height for 2 lines with ellipsis */}
        <div className="font-medium text-sm leading-tight line-clamp-2 h-[2.5rem] overflow-hidden" title={product.name}>
          {product.name}
        </div>
        {/* Style number */}
        <div className="text-xs text-gray-500 dark:text-gray-300 font-mono truncate mt-1">{product.styleNumber}</div>
        {/* Price */}
        <div className="font-semibold text-sm text-gray-900 dark:text-gray-100 mt-1">{priceDisplay()}</div>
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

  // Selection mode states
  const [selectionMode, setSelectionMode] = useState(false);
  const [selectedProductIds, setSelectedProductIds] = useState<string[]>([]);
  const [batchStatusModalOpen, setBatchStatusModalOpen] = useState(false);

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

  // Selection handlers
  const handleProductSelect = (id: string, selected: boolean) => {
    if (selected) {
      setSelectedProductIds((prev) => [...prev, id]);
    } else {
      setSelectedProductIds((prev) => prev.filter((pid) => pid !== id));
    }
  };

  const handleSelectAll = () => {
    const allIds = products.map((p) => p.id);
    setSelectedProductIds(allIds);
  };

  const handleDeselectAll = () => {
    setSelectedProductIds([]);
  };

  const enterSelectionMode = () => {
    setSelectionMode(true);
    setSelectedProductIds([]);
  };

  const exitSelectionMode = () => {
    setSelectionMode(false);
    setSelectedProductIds([]);
  };

  const handleBatchStatusSuccess = () => {
    setBatchStatusModalOpen(false);
    exitSelectionMode();
    loadProducts();
  };

  return (
    <div className="flex flex-col gap-4">
      <div className="flex justify-between items-center">
        {/* Batch operation bar */}
        {selectionMode ? (
          <div className="flex items-center gap-4">
            <Space>
              <span className="text-gray-600">
                {t('products.selectedCount', { count: selectedProductIds.length })}
              </span>
              {selectedProductIds.length < products.length ? (
                <Button size="small" icon={<CheckOutlined />} onClick={handleSelectAll}>
                  {t('products.selectAll')}
                </Button>
              ) : (
                <Button size="small" onClick={handleDeselectAll}>
                  {t('products.deselectAll')}
                </Button>
              )}
            </Space>
          </div>
        ) : (
          <div />
        )}

        <Space>
          {selectionMode ? (
            <>
              <Button
                type="primary"
                icon={<EditOutlined />}
                disabled={selectedProductIds.length === 0}
                onClick={() => setBatchStatusModalOpen(true)}
              >
                {t('products.batchUpdateStatus')}
              </Button>
              <Button icon={<CloseOutlined />} onClick={exitSelectionMode}>
                {t('common.cancel')}
              </Button>
            </>
          ) : (
            <>
              <Button icon={<CheckOutlined />} onClick={enterSelectionMode}>
                {t('products.batchOperation')}
              </Button>
              <Button type="primary" icon={<PlusOutlined />} onClick={() => setCreateModalOpen(true)}>
                {t('products.addProduct')}
              </Button>
            </>
          )}
        </Space>
      </div>

      {/* Search & Filters */}
      <Card>
        <QueryFilter
          style={{ padding: 0 }}
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
                    <ProductCard
                      product={product}
                      selectable={selectionMode}
                      selected={selectedProductIds.includes(product.id)}
                      onSelect={handleProductSelect}
                    />
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

      {/* Batch Update Status Modal */}
      <BatchUpdateStatusModal
        open={batchStatusModalOpen}
        selectedCount={selectedProductIds.length}
        selectedProductIds={selectedProductIds}
        onCancel={() => setBatchStatusModalOpen(false)}
        onSuccess={handleBatchStatusSuccess}
      />
    </div>
  );
}
