import { useEffect, useState, useRef, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal, Form, InputNumber, App, Select, Spin, Empty } from 'antd';
import { SearchOutlined } from '@ant-design/icons';

import { inboundApi, type InboundOrderItem } from '@/lib/inbound-api';
import { productApi, type ProductSku, type ProductDetail } from '@/lib/product-api';

interface InboundOrderItemModalProps {
  open: boolean;
  orderId: string;
  item: InboundOrderItem | null;
  onClose: () => void;
  onSuccess: () => void;
}

interface SkuOption {
  value: string;
  label: string;
  sku: ProductSku & { productName: string; productImage: string | null };
}

export function InboundOrderItemModal({
  open,
  orderId,
  item,
  onClose,
  onSuccess,
}: InboundOrderItemModalProps) {
  const { t } = useTranslation();
  const [form] = Form.useForm();
  const { message } = App.useApp();
  const [loading, setLoading] = useState(false);
  const [searching, setSearching] = useState(false);
  const [skuOptions, setSkuOptions] = useState<SkuOption[]>([]);
  const searchTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const isEdit = !!item;

  useEffect(() => {
    if (open) {
      if (item) {
        form.setFieldsValue({
          productSkuId: item.productSku.id,
          expectedQuantity: item.expectedQuantity,
          unitCost: item.unitCost ? parseFloat(item.unitCost) : undefined,
        });
        // Set initial SKU option for display
        if (item.productSku.id) {
          setSkuOptions([
            {
              value: item.productSku.id,
              label: `${item.productName || ''} - ${item.productSku.colorName || ''} ${item.productSku.skuName || ''}`,
              sku: {
                id: item.productSku.id,
                sizeUnit: null,
                sizeValue: item.productSku.skuName || '',
                specInfo: null,
                specDescription: '',
                price: '0',
                originalPrice: null,
                isActive: true,
                sortOrder: 0,
                createdAt: '',
                updatedAt: '',
                productName: item.productName || '',
                productImage: item.productImage,
              },
            },
          ]);
        }
      } else {
        form.resetFields();
        setSkuOptions([]);
      }
    }
  }, [open, item, form]);

  // Cleanup timer on unmount
  useEffect(() => {
    return () => {
      if (searchTimerRef.current) {
        clearTimeout(searchTimerRef.current);
      }
    };
  }, []);

  // Search SKUs with debounce
  const handleSearch = useCallback(async (value: string) => {
    // Clear previous timer
    if (searchTimerRef.current) {
      clearTimeout(searchTimerRef.current);
    }

    if (!value || value.length < 2) {
      setSkuOptions([]);
      return;
    }

    // Debounce search
    searchTimerRef.current = setTimeout(async () => {
      setSearching(true);
      try {
        // First get product list
        const result = await productApi.getProducts({ search: value, limit: 10 });
        const options: SkuOption[] = [];

        // For each product, fetch details to get SKUs
        for (const product of result.data) {
          try {
            const detail: ProductDetail = await productApi.getProduct(product.id);
            for (const sku of detail.skus) {
              if (sku.isActive) {
                options.push({
                  value: sku.id,
                  label: `${detail.name} - ${sku.specDescription || sku.sizeValue || 'N/A'}`,
                  sku: {
                    ...sku,
                    productName: detail.name,
                    productImage: detail.images[0]?.url || null,
                  },
                });
              }
            }
          } catch (e) {
            console.error('Failed to fetch product details:', e);
          }
        }

        setSkuOptions(options);
      } catch (error) {
        console.error('Failed to search SKUs:', error);
      } finally {
        setSearching(false);
      }
    }, 300);
  }, []);

  const handleSubmit = async () => {
    try {
      const values = await form.validateFields();
      setLoading(true);

      if (isEdit) {
        await inboundApi.updateInboundOrderItem(item!.id, {
          expectedQuantity: values.expectedQuantity,
          unitCost: values.unitCost ? String(values.unitCost) : undefined,
        });
        message.success(t('inventory.itemUpdated'));
      } else {
        await inboundApi.addInboundOrderItem(orderId, {
          productSkuId: values.productSkuId,
          expectedQuantity: values.expectedQuantity,
          unitCost: values.unitCost ? String(values.unitCost) : undefined,
        });
        message.success(t('inventory.itemAdded'));
      }

      onSuccess();
    } catch (error) {
      if (error && typeof error === 'object' && 'errorFields' in error) {
        return;
      }
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setLoading(false);
    }
  };

  const handleClose = () => {
    form.resetFields();
    setSkuOptions([]);
    onClose();
  };

  return (
    <Modal
      title={isEdit ? t('inventory.editItem') : t('inventory.addItem')}
      open={open}
      onCancel={handleClose}
      onOk={handleSubmit}
      confirmLoading={loading}
      destroyOnClose
      width={500}
    >
      <Form form={form} layout="vertical" className="mt-4">
        <Form.Item
          name="productSkuId"
          label={t('inventory.selectSku')}
          rules={[{ required: true, message: t('inventory.skuRequired') }]}
        >
          <Select
            showSearch
            placeholder={t('inventory.searchSku')}
            filterOption={false}
            onSearch={handleSearch}
            disabled={isEdit}
            loading={searching}
            suffixIcon={searching ? <Spin size="small" /> : <SearchOutlined />}
            notFoundContent={
              searching ? (
                <Spin size="small" />
              ) : skuOptions.length === 0 ? (
                <Empty description={t('inventory.searchSkuHint')} image={Empty.PRESENTED_IMAGE_SIMPLE} />
              ) : null
            }
            options={skuOptions}
          />
        </Form.Item>

        <div className="grid grid-cols-2 gap-4">
          <Form.Item
            name="expectedQuantity"
            label={t('inventory.expectedQuantity')}
            rules={[
              { required: true, message: t('inventory.quantityRequired') },
              { type: 'number', min: 1, message: t('inventory.quantityMin') },
            ]}
          >
            <InputNumber min={1} precision={0} style={{ width: '100%' }} />
          </Form.Item>

          <Form.Item name="unitCost" label={t('inventory.unitCost')}>
            <InputNumber min={0} precision={2} prefix="¥" style={{ width: '100%' }} />
          </Form.Item>
        </div>
      </Form>
    </Modal>
  );
}
