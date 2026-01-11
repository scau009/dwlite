import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ProTable, type ActionType, type ProColumns } from '@ant-design/pro-components';
import { Button, Image } from 'antd';
import { ShoppingOutlined } from '@ant-design/icons';

import {
  inboundApi,
  type InboundProduct,
  type ProductDiscoveryParams,
} from '@/lib/inbound-api';
import { getCurrencySymbol } from '@/lib/merchant-listing-api';
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

  const columns: ProColumns<InboundProduct>[] = [
    {
      title: t('opportunities.image'),
      dataIndex: 'primaryImageUrl',
      width: 64,
      search: false,
      render: (_, record) =>
        record.primaryImageUrl ? (
          <div className="w-12 h-12 flex items-center justify-center bg-gray-100 rounded">
            <Image
              src={record.primaryImageUrl}
              alt={record.name}
              preview={false}
              style={{ maxWidth: 48, maxHeight: 48, objectFit: 'contain' }}
            />
          </div>
        ) : (
          <div className="w-12 h-12 flex items-center justify-center bg-gray-100 rounded text-gray-400">
            <ShoppingOutlined style={{ fontSize: 20 }} />
          </div>
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
        const currencySymbol = getCurrencySymbol(activeSkus[0].currency);

        if (min === max) {
          return <span className="text-orange-600 font-medium">{currencySymbol}{min.toFixed(2)}</span>;
        }
        return (
          <span className="text-orange-600 font-medium">
            {currencySymbol}{min.toFixed(2)} - {currencySymbol}{max.toFixed(2)}
          </span>
        );
      },
    },
    {
      title: t('common.actions'),
      valueType: 'option',
      width: 80,
      fixed: 'right',
      render: (_, record) => (
        <Button type="link" size="small" onClick={() => handleCreateOrder(record)}>
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
