import { useParams, useNavigate } from 'react-router';
import { useTranslation } from 'react-i18next';
import {
  ProForm,
  ProFormText,
  ProFormSelect,
  ProFormDigit,
  ProFormTextArea,
  ProFormGroup,
} from '@ant-design/pro-components';
import { Card, App } from 'antd';
import { ArrowLeftOutlined } from '@ant-design/icons';

interface ProductFormData {
  name: string;
  sku: string;
  category: string;
  brand: string;
  price: number;
  originalPrice: number;
  stock: number;
  weight: number;
  dimensions: string;
  description: string;
  status: string;
}

// Mock data for editing
const mockProduct: ProductFormData = {
  name: 'Premium Sneakers',
  sku: 'SKU-001',
  category: 'Footwear',
  brand: 'Nike',
  price: 299,
  originalPrice: 399,
  stock: 150,
  weight: 0.8,
  dimensions: '30 x 20 x 12',
  description: 'High-quality premium sneakers with exceptional comfort and style.',
  status: 'on_sale',
};

export function ProductFormPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { t } = useTranslation();
  const { message } = App.useApp();
  const isEdit = Boolean(id);

  const handleSubmit = async (values: ProductFormData) => {
    console.log('Form values:', values);
    // Simulate API call
    await new Promise((resolve) => setTimeout(resolve, 1000));
    message.success(t('common.success'));
    navigate('/products');
    return true;
  };

  return (
    <div className="max-w-3xl">
      {/* Header */}
      <div className="mb-6">
        <a
          onClick={() => navigate('/products')}
          className="flex items-center gap-1 text-gray-500 hover:text-gray-700 cursor-pointer mb-2"
        >
          <ArrowLeftOutlined />
          <span>{t('common.back')}</span>
        </a>
        <h1 className="text-xl font-semibold">
          {isEdit ? t('common.edit') + ' ' + t('nav.products') : t('products.addProduct')}
        </h1>
      </div>

      <ProForm<ProductFormData>
        layout="vertical"
        onFinish={handleSubmit}
        initialValues={isEdit ? mockProduct : { status: 'off_sale' }}
        submitter={{
          searchConfig: {
            submitText: t('common.save'),
            resetText: t('common.cancel'),
          },
          onReset: () => navigate('/products'),
        }}
      >
        {/* Basic Information */}
        <Card title={t('detail.basicInfo')} className="mb-4">
          <ProFormGroup>
            <ProFormText
              name="name"
              label={t('products.productName')}
              placeholder={t('products.productName')}
              rules={[{ required: true, message: t('validation.required', { field: t('products.productName') }) }]}
              width="md"
            />
            <ProFormText
              name="sku"
              label={t('products.sku')}
              placeholder={t('products.sku')}
              rules={[{ required: true, message: t('validation.required', { field: t('products.sku') }) }]}
              width="md"
            />
          </ProFormGroup>

          <ProFormGroup>
            <ProFormSelect
              name="category"
              label={t('products.category')}
              placeholder={t('common.all')}
              rules={[{ required: true, message: t('validation.required', { field: t('products.category') }) }]}
              options={[
                { label: 'Footwear', value: 'Footwear' },
                { label: 'Apparel', value: 'Apparel' },
                { label: 'Accessories', value: 'Accessories' },
              ]}
              width="md"
            />
            <ProFormText
              name="brand"
              label={t('detail.brand')}
              placeholder={t('detail.brand')}
              width="md"
            />
          </ProFormGroup>

          <ProFormTextArea
            name="description"
            label={t('detail.description')}
            placeholder={t('detail.description')}
            fieldProps={{ rows: 4 }}
          />
        </Card>

        {/* Pricing */}
        <Card title={t('nav.pricing')} className="mb-4">
          <ProFormGroup>
            <ProFormDigit
              name="price"
              label={t('products.price')}
              placeholder="0.00"
              rules={[{ required: true, message: t('validation.required', { field: t('products.price') }) }]}
              min={0}
              fieldProps={{ precision: 2, prefix: '$' }}
              width="sm"
            />
            <ProFormDigit
              name="originalPrice"
              label={t('form.originalPrice')}
              placeholder="0.00"
              min={0}
              fieldProps={{ precision: 2, prefix: '$' }}
              width="sm"
            />
          </ProFormGroup>
        </Card>

        {/* Inventory */}
        <Card title={t('nav.inventory')} className="mb-4">
          <ProFormGroup>
            <ProFormDigit
              name="stock"
              label={t('products.stock')}
              placeholder="0"
              min={0}
              fieldProps={{ precision: 0 }}
              width="sm"
            />
            <ProFormDigit
              name="weight"
              label={t('detail.weight') + ' (kg)'}
              placeholder="0.0"
              min={0}
              fieldProps={{ precision: 1 }}
              width="sm"
            />
          </ProFormGroup>
          <ProFormText
            name="dimensions"
            label={t('detail.dimensions') + ' (cm)'}
            placeholder="L x W x H"
            width="md"
          />
        </Card>

        {/* Status */}
        <Card title={t('products.status')} className="mb-4">
          <ProFormSelect
            name="status"
            label={t('products.status')}
            options={[
              { label: t('products.onSale'), value: 'on_sale' },
              { label: t('products.offSale'), value: 'off_sale' },
            ]}
            width="sm"
          />
        </Card>
      </ProForm>
    </div>
  );
}
