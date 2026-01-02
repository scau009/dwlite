import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ProTable, type ActionType, type ProColumns } from '@ant-design/pro-components';
import { Button, Avatar, Tag, Modal, Table } from 'antd';
import { ShoppingOutlined } from '@ant-design/icons';

import {
  inboundApi,
  type InboundProduct,
  type InboundProductSku,
  type ProductDiscoveryParams,
} from '@/lib/inbound-api';
import { CreateOrderModal } from './components/create-order-modal';

interface BrandOption {
  label: string;
  value: string;
}

export function OpportunitiesListPage() {
  const { t } = useTranslation();
  const actionRef = useRef<ActionType>(null);

  const [brands, setBrands] = useState<BrandOption[]>([]);
  const [createModalOpen, setCreateModalOpen] = useState(false);
  const [selectedProducts, setSelectedProducts] = useState<InboundProduct[]>([]);
  const [selectedRowKeys, setSelectedRowKeys] = useState<React.Key[]>([]);
  const [skuModalOpen, setSkuModalOpen] = useState(false);
  const [viewingProduct, setViewingProduct] = useState<InboundProduct | null>(null);

  const loadBrands = async () => {
    try {
      const response = await fetch('/api/admin/brands?limit=100');
      if (response.ok) {
        const data = await response.json();
        setBrands(
          (data.data || []).map((b: { id: string; name: string }) => ({
            label: b.name,
            value: b.id,
          }))
        );
      }
    } catch (error) {
      console.error('Failed to load brands:', error);
    }
  };

  // Load brands on mount
  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect
    loadBrands();
  }, []);

  // Single product order
  const handleCreateOrder = (product: InboundProduct) => {
    setSelectedProducts([product]);
    setCreateModalOpen(true);
  };

  // Batch order from selected products
  const handleBatchCreateOrder = () => {
    setCreateModalOpen(true);
  };

  const handleOrderCreated = () => {
    setCreateModalOpen(false);
    setSelectedProducts([]);
    setSelectedRowKeys([]);
    actionRef.current?.reload();
  };

  const handleViewSkus = (product: InboundProduct) => {
    setViewingProduct(product);
    setSkuModalOpen(true);
  };

  const skuColumns = [
    {
      title: t('opportunities.sku'),
      key: 'sku',
      render: (_: unknown, record: InboundProductSku) => (
        <span>
          {record.sizeUnit && record.sizeValue
            ? `${record.sizeUnit} ${record.sizeValue}`
            : record.skuName || '-'}
        </span>
      ),
    },
    {
      title: t('opportunities.price'),
      dataIndex: 'price',
      key: 'price',
      width: 100,
      align: 'right' as const,
      render: (price: string) => (
        <span className="text-orange-600">¥{parseFloat(price).toFixed(2)}</span>
      ),
    },
  ];

  const columns: ProColumns<InboundProduct>[] = [
    {
      title: t('opportunities.image'),
      dataIndex: 'primaryImageUrl',
      width: 80,
      search: false,
      render: (_, record) =>
        record.primaryImageUrl ? (
          <Avatar src={record.primaryImageUrl} shape="square" size={48} />
        ) : (
          <Avatar shape="square" size={48} icon={<ShoppingOutlined />} />
        ),
    },
    {
      title: t('opportunities.productName'),
      dataIndex: 'name',
      width: 200,
      ellipsis: true,
      fieldProps: {
        placeholder: t('opportunities.searchPlaceholder'),
      },
    },
    {
      title: t('opportunities.styleNumber'),
      dataIndex: 'styleNumber',
      width: 120,
      search: false,
      render: (_, record) => (
        <code className="text-xs bg-gray-100 px-2 py-1 rounded">
          {record.styleNumber}
        </code>
      ),
    },
    {
      title: t('opportunities.color'),
      dataIndex: 'color',
      width: 100,
      search: false,
      render: (_, record) => record.color || '-',
    },
    {
      title: t('opportunities.brand'),
      dataIndex: 'brandId',
      width: 120,
      valueType: 'select',
      fieldProps: {
        options: brands,
        placeholder: t('opportunities.selectBrand'),
        showSearch: true,
        filterOption: (input: string, option: BrandOption) =>
          (option?.label ?? '').toLowerCase().includes(input.toLowerCase()),
      },
      render: (_, record) => record.brandName || '-',
    },
    {
      title: t('opportunities.skuCount'),
      dataIndex: 'skuCount',
      width: 100,
      search: false,
      render: (_, record) => {
        const activeSkus = record.skus.filter((s) => s.isActive);
        return (
          <Tag
            color="blue"
            className="cursor-pointer"
            onClick={() => handleViewSkus(record)}
          >
            {t('opportunities.skuCountValue', { count: activeSkus.length })}
          </Tag>
        );
      },
    },
    {
      title: t('opportunities.priceRange'),
      dataIndex: 'priceRange',
      width: 150,
      search: false,
      render: (_, record) => {
        const activeSkus = record.skus.filter((s) => s.isActive);
        if (activeSkus.length === 0) return '-';

        const prices = activeSkus.map((s) => parseFloat(s.price));
        const min = Math.min(...prices);
        const max = Math.max(...prices);

        if (min === max) {
          return <span className="text-orange-600 font-medium">¥{min.toFixed(2)}</span>;
        }
        return (
          <span className="text-orange-600 font-medium">
            ¥{min.toFixed(2)} - ¥{max.toFixed(2)}
          </span>
        );
      },
    },
    {
      title: t('common.actions'),
      valueType: 'option',
      width: 100,
      fixed: 'right',
      render: (_, record) => (
        <Button
          type="primary"
          size="small"
          onClick={() => handleCreateOrder(record)}
        >
          {t('opportunities.createOrder')}
        </Button>
      ),
    },
  ];

  return (
    <div className="space-y-4">
      <ProTable<InboundProduct>
        actionRef={actionRef}
        columns={columns}
        rowKey="id"
        rowSelection={{
          selectedRowKeys,
          onChange: (keys, rows) => {
            setSelectedRowKeys(keys);
            setSelectedProducts(rows);
          },
        }}
        tableAlertOptionRender={() => (
          <Button
            type="primary"
            onClick={handleBatchCreateOrder}
          >
            {t('opportunities.batchCreateOrder')}
          </Button>
        )}
        request={async (params) => {
          try {
            const queryParams: ProductDiscoveryParams = {
              page: params.current,
              limit: params.pageSize,
              search: params.name,
              brandId: params.brandId,
            };

            const result = await inboundApi.searchProductsForDiscovery(queryParams);
            return {
              data: result.data,
              success: true,
              total: result.meta.total,
            };
          } catch (error) {
            console.error('Failed to fetch products:', error);
            return {
              data: [],
              success: false,
              total: 0,
            };
          }
        }}
        search={{
          labelWidth: 'auto',
          defaultCollapsed: false,
          span: 6,
        }}
        options={{
          density: true,
          fullScreen: true,
          reload: true,
        }}
        pagination={{
          defaultPageSize: 20,
          showSizeChanger: true,
        }}
      />

      {/* SKU Detail Modal */}
      <Modal
        title={t('opportunities.skuList')}
        open={skuModalOpen}
        onCancel={() => setSkuModalOpen(false)}
        footer={null}
        width={400}
      >
        {viewingProduct && (
          <div>
            {/* Product Info */}
            <div className="flex gap-3 mb-3">
              {viewingProduct.primaryImageUrl ? (
                <Avatar src={viewingProduct.primaryImageUrl} shape="square" size={56} />
              ) : (
                <Avatar shape="square" size={56} icon={<ShoppingOutlined />} />
              )}
              <div className="flex-1 min-w-0">
                <div className="font-medium text-sm truncate">{viewingProduct.name}</div>
                <div className="text-xs text-gray-500 mt-1">
                  <span>{viewingProduct.styleNumber}</span>
                  {viewingProduct.color && <span className="ml-2">{viewingProduct.color}</span>}
                </div>
                {viewingProduct.brandName && (
                  <div className="text-xs text-gray-400 mt-0.5">{viewingProduct.brandName}</div>
                )}
              </div>
            </div>
            {/* SKU Table */}
            <Table
              columns={skuColumns}
              dataSource={viewingProduct.skus.filter((s) => s.isActive)}
              rowKey="id"
              pagination={false}
              size="small"
              scroll={{ y: 300 }}
            />
          </div>
        )}
      </Modal>

      {/* Create Order Modal */}
      <CreateOrderModal
        open={createModalOpen}
        products={selectedProducts}
        onClose={() => {
          setCreateModalOpen(false);
          setSelectedProducts([]);
          setSelectedRowKeys([]);
        }}
        onSuccess={handleOrderCreated}
      />
    </div>
  );
}
