import { useState, useEffect, useCallback, useRef } from 'react';
import { useNavigate } from 'react-router';
import { useTranslation } from 'react-i18next';
import {
  Card,
  Form,
  Select,
  InputNumber,
  Input,
  Button,
  Space,
  App,
  Table,
  Image,
  Empty,
} from 'antd';
import { ArrowLeftOutlined, SearchOutlined } from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';

import {
  merchantInventoryApi,
  type MerchantWarehouse,
} from '@/lib/inbound-api';
import { inboundApi, type InboundProduct, type InboundProductSku } from '@/lib/inbound-api';
import { getCurrencySymbol } from '@/lib/merchant-listing-api';

interface SelectedSku {
  product: InboundProduct;
  sku: InboundProductSku;
}

export function AddInventoryPage() {
  const navigate = useNavigate();
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [form] = Form.useForm();

  const [warehouses, setWarehouses] = useState<MerchantWarehouse[]>([]);
  const [warehousesLoading, setWarehousesLoading] = useState(true);

  // Product search
  const [searchValue, setSearchValue] = useState('');
  const [products, setProducts] = useState<InboundProduct[]>([]);
  const [productsLoading, setProductsLoading] = useState(false);
  const searchTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Selected SKU
  const [selectedSku, setSelectedSku] = useState<SelectedSku | null>(null);

  const [submitting, setSubmitting] = useState(false);

  // Load merchant warehouses
  useEffect(() => {
    const loadWarehouses = async () => {
      setWarehousesLoading(true);
      try {
        const result = await merchantInventoryApi.getMerchantWarehouses();
        setWarehouses(result.data);
        if (result.data.length === 1) {
          form.setFieldValue('warehouseId', result.data[0].id);
        }
      } catch {
        message.error(t('common.error'));
      } finally {
        setWarehousesLoading(false);
      }
    };
    loadWarehouses();
  }, [form, message, t]);

  // Search products
  const searchProducts = useCallback(async (search: string) => {
    setProductsLoading(true);
    try {
      const result = await inboundApi.searchProducts(search, 10);
      setProducts(result);
    } catch {
      console.error('Failed to search products');
    } finally {
      setProductsLoading(false);
    }
  }, []);

  // Handle search input change with debounce
  const handleSearchChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = e.target.value;
    setSearchValue(value);

    if (searchTimerRef.current) {
      clearTimeout(searchTimerRef.current);
    }

    searchTimerRef.current = setTimeout(() => {
      searchProducts(value);
    }, 300);
  };

  // Handle SKU selection
  const handleSelectSku = (product: InboundProduct, sku: InboundProductSku) => {
    setSelectedSku({ product, sku });
    form.setFieldValue('productSkuId', sku.id);
    // Reset search
    setSearchValue('');
    setProducts([]);
  };

  // Handle form submit
  const handleSubmit = async () => {
    try {
      const values = await form.validateFields();
      setSubmitting(true);

      await merchantInventoryApi.createInventory({
        warehouseId: values.warehouseId,
        productSkuId: values.productSkuId,
        quantity: values.quantity,
        unitCost: values.unitCost?.toString(),
        costCurrency: values.costCurrency || 'CNY',
        notes: values.notes,
      });

      message.success(t('merchantStock.inventoryCreated'));
      navigate('/inventory/stock');
    } catch (error) {
      if (error && typeof error === 'object' && 'errorFields' in error) {
        return;
      }
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setSubmitting(false);
    }
  };

  // Product columns for search results
  const productColumns: ColumnsType<InboundProduct> = [
    {
      title: t('merchantStock.product'),
      key: 'product',
      width: 250,
      render: (_, record) => (
        <div className="flex gap-3">
          {record.primaryImageUrl ? (
            <Image
              src={record.primaryImageUrl}
              width={50}
              height={50}
              style={{ objectFit: 'cover' }}
              preview={false}
            />
          ) : (
            <div className="w-[50px] h-[50px] bg-gray-100 flex items-center justify-center text-gray-400 text-xs">
              N/A
            </div>
          )}
          <div className="flex-1 min-w-0">
            <div className="font-medium text-sm truncate">{record.name}</div>
            <div className="text-xs text-gray-500">{record.styleNumber}</div>
          </div>
        </div>
      ),
    },
    {
      title: t('merchantStock.availableSizes'),
      key: 'sizes',
      render: (_, record) => (
        <Space wrap size={[4, 4]}>
          {record.skus
            .filter(sku => sku.isActive)
            .map(sku => (
              <Button
                key={sku.id}
                size="small"
                type="default"
                onClick={() => handleSelectSku(record, sku)}
              >
                {sku.skuName || sku.sizeValue || '-'}
              </Button>
            ))}
        </Space>
      ),
    },
  ];

  // No warehouse warning
  if (!warehousesLoading && warehouses.length === 0) {
    return (
      <div className="space-y-4">
        <Button icon={<ArrowLeftOutlined />} onClick={() => navigate('/inventory/stock')}>
          {t('common.back')}
        </Button>
        <Empty description={t('merchantStock.noMerchantWarehouse')}>
          <Button type="primary" onClick={() => navigate('/inventory/warehouses')}>
            {t('merchantStock.createWarehouse')}
          </Button>
        </Empty>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex items-center justify-between">
        <Button icon={<ArrowLeftOutlined />} onClick={() => navigate('/inventory/stock')}>
          {t('common.back')}
        </Button>
      </div>

      <Card title={t('merchantStock.addInventory')} loading={warehousesLoading}>
        <Form form={form} layout="vertical" className="max-w-2xl" initialValues={{ costCurrency: 'CNY' }}>
          {/* Warehouse Selection */}
          <Form.Item
            name="warehouseId"
            label={t('merchantStock.warehouse')}
            rules={[{ required: true, message: t('validation.required') }]}
          >
            <Select
              placeholder={t('merchantStock.selectWarehouse')}
              options={warehouses.map(w => ({
                value: w.id,
                label: w.shortName || w.name,
              }))}
            />
          </Form.Item>

          {/* Hidden field for productSkuId */}
          <Form.Item
            name="productSkuId"
            hidden
            rules={[{ required: true, message: t('merchantStock.pleaseSelectSku') }]}
          >
            <Input />
          </Form.Item>

          {/* Selected SKU Display */}
          {selectedSku && (
            <Form.Item label={t('merchantStock.selectedSku')}>
              <div className="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                {selectedSku.product.primaryImageUrl ? (
                  <Image
                    src={selectedSku.product.primaryImageUrl}
                    width={60}
                    height={60}
                    style={{ objectFit: 'cover' }}
                    preview={false}
                  />
                ) : (
                  <div className="w-[60px] h-[60px] bg-gray-200 flex items-center justify-center text-gray-400 text-xs">
                    N/A
                  </div>
                )}
                <div className="flex-1">
                  <div className="font-medium">{selectedSku.product.name}</div>
                  <div className="text-sm text-gray-500">
                    {selectedSku.product.styleNumber} - {selectedSku.sku.skuName || selectedSku.sku.sizeValue}
                  </div>
                </div>
                <Button size="small" onClick={() => setSelectedSku(null)}>
                  {t('common.change')}
                </Button>
              </div>
            </Form.Item>
          )}

          {/* SKU Search (only show when no SKU selected) */}
          {!selectedSku && (
            <Form.Item label={t('merchantStock.searchSku')}>
              <div className="space-y-3">
                <Input
                  placeholder={t('merchantStock.searchByStyleOrSku')}
                  prefix={<SearchOutlined />}
                  value={searchValue}
                  onChange={handleSearchChange}
                  allowClear
                />
                {(searchValue || products.length > 0) && (
                  <Table
                    columns={productColumns}
                    dataSource={products}
                    rowKey="id"
                    loading={productsLoading}
                    pagination={false}
                    size="small"
                    locale={{ emptyText: searchValue ? t('merchantStock.noProductsFound') : t('merchantStock.typeToSearch') }}
                  />
                )}
              </div>
            </Form.Item>
          )}

          {/* Quantity */}
          <Form.Item
            name="quantity"
            label={t('merchantStock.quantity')}
            rules={[
              { required: true, message: t('validation.required') },
              { type: 'number', min: 1, message: t('validation.minValue', { min: 1 }) },
            ]}
          >
            <InputNumber
              min={1}
              precision={0}
              style={{ width: '100%' }}
              placeholder={t('merchantStock.enterQuantity')}
            />
          </Form.Item>

          {/* Unit Cost (optional) */}
          <Space.Compact style={{ width: '100%' }}>
            <Form.Item
              name="unitCost"
              label={t('merchantStock.unitCost')}
              style={{ flex: 1 }}
            >
              <InputNumber
                min={0}
                precision={2}
                style={{ width: '100%' }}
                placeholder={t('merchantStock.optional')}
              />
            </Form.Item>
            <Form.Item
              name="costCurrency"
              label=" "
              style={{ width: 100 }}
            >
              <Select>
                <Select.Option value="CNY">{getCurrencySymbol('CNY')} CNY</Select.Option>
                <Select.Option value="USD">{getCurrencySymbol('USD')} USD</Select.Option>
                <Select.Option value="EUR">{getCurrencySymbol('EUR')} EUR</Select.Option>
              </Select>
            </Form.Item>
          </Space.Compact>

          {/* Notes */}
          <Form.Item name="notes" label={t('merchantStock.notes')}>
            <Input.TextArea rows={3} placeholder={t('merchantStock.optional')} maxLength={500} />
          </Form.Item>

          {/* Submit */}
          <Form.Item>
            <Space>
              <Button type="primary" onClick={handleSubmit} loading={submitting}>
                {t('merchantStock.createInventory')}
              </Button>
              <Button onClick={() => navigate('/inventory/stock')}>{t('common.cancel')}</Button>
            </Space>
          </Form.Item>
        </Form>
      </Card>
    </div>
  );
}
