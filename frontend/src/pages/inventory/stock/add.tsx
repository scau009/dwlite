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

    // If input is empty, just clear the list
    if (!value.trim()) {
      setProducts([]);
      return;
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

  // Handle change SKU - show same product's sizes for quick switch
  const handleChangeSku = () => {
    if (selectedSku) {
      const styleNumber = selectedSku.product.styleNumber;
      setSearchValue(styleNumber);
      setSelectedSku(null);
      form.setFieldValue('productSkuId', undefined);
      // Directly search with the style number
      searchProducts(styleNumber);
    }
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
      title: t('merchantStock.productImage'),
      key: 'image',
      width: 80,
      render: (_, record) =>
        record.primaryImageUrl ? (
          <div className="w-14 h-14 flex items-center justify-center bg-gray-100 rounded">
            <Image
              src={record.primaryImageUrl}
              style={{ maxWidth: 56, maxHeight: 56, objectFit: 'contain' }}
              preview={false}
            />
          </div>
        ) : (
          <div className="w-14 h-14 bg-gray-100 flex items-center justify-center text-gray-400 text-xs rounded">
            N/A
          </div>
        ),
    },
    {
      title: t('merchantStock.styleNumber'),
      dataIndex: 'styleNumber',
      key: 'styleNumber',
      width: 120,
      render: (text: string) => (
        <code className="text-xs bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">{text}</code>
      ),
    },
    {
      title: t('merchantStock.availableSizes'),
      key: 'sizes',
      render: (_, record) => (
        <Space wrap size={[8, 8]}>
          {record.skus
            .filter(sku => sku.isActive)
            .map(sku => (
              <Button
                key={sku.id}
                size="middle"
                type="default"
                onClick={() => handleSelectSku(record, sku)}
              >
                {sku.sizeUnit && sku.sizeValue
                  ? `${sku.sizeUnit} ${sku.sizeValue}`
                  : sku.skuName || '-'}
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
              <div className="flex items-center gap-4 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                {selectedSku.product.primaryImageUrl ? (
                  <div className="w-16 h-16 flex-shrink-0 flex items-center justify-center bg-gray-100 rounded">
                    <Image
                      src={selectedSku.product.primaryImageUrl}
                      style={{ maxWidth: 64, maxHeight: 64, objectFit: 'contain' }}
                      preview={false}
                    />
                  </div>
                ) : (
                  <div className="w-16 h-16 flex-shrink-0 bg-gray-200 flex items-center justify-center text-gray-400 text-xs rounded">
                    N/A
                  </div>
                )}
                <div className="flex-1 space-y-1">
                  <code className="text-sm bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">
                    {selectedSku.product.styleNumber}
                  </code>
                  <div className="text-sm text-gray-600">
                    <span className="mr-4">
                      {t('merchantStock.skuName')}: {selectedSku.sku.sizeUnit && selectedSku.sku.sizeValue
                        ? `${selectedSku.sku.sizeUnit} ${selectedSku.sku.sizeValue}`
                        : selectedSku.sku.skuName || '-'}
                    </span>
                    <span className="text-orange-600">
                      {getCurrencySymbol(selectedSku.sku.currency)}{parseFloat(selectedSku.sku.price).toFixed(2)}
                    </span>
                  </div>
                </div>
                <Button size="small" onClick={handleChangeSku}>
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
