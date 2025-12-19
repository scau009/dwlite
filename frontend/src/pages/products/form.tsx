import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router';
import { useTranslation } from 'react-i18next';
import {
  ProForm,
  ProFormText,
  ProFormSelect,
  ProFormTextArea,
  ProFormGroup,
} from '@ant-design/pro-components';
import { Card, App, Button, Spin } from 'antd';
import { ArrowLeftOutlined } from '@ant-design/icons';
import {
  productApi,
  type ProductDetail,
  type ProductStatus,
  type CreateProductParams,
  type UpdateProductParams,
} from '@/lib/product-api';
import { brandApi } from '@/lib/brand-api';
import { categoryApi } from '@/lib/category-api';

interface ProductFormData {
  name: string;
  styleNumber: string;
  season: string;
  color?: string;
  brandId?: string;
  categoryId?: string;
  description?: string;
  status: ProductStatus;
}

export function ProductFormPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { t } = useTranslation();
  const { message } = App.useApp();
  const isEdit = Boolean(id);

  const [loading, setLoading] = useState(false);
  const [initialLoading, setInitialLoading] = useState(isEdit);
  const [initialValues, setInitialValues] = useState<ProductFormData | undefined>(undefined);

  // Options for selects
  const [brands, setBrands] = useState<Array<{ value: string; label: string }>>([]);
  const [categories, setCategories] = useState<Array<{ value: string; label: string }>>([]);

  // Load options
  useEffect(() => {
    brandApi.getBrands({ limit: 100 }).then((r) =>
      setBrands(r.data.map((b) => ({ value: b.id, label: b.name })))
    );
    categoryApi.getCategories({ limit: 100 }).then((r) =>
      setCategories(r.data.map((c) => ({ value: c.id, label: c.name })))
    );
  }, []);

  // Load product for edit
  useEffect(() => {
    if (isEdit && id) {
      setInitialLoading(true);
      productApi
        .getProduct(id)
        .then((product: ProductDetail) => {
          setInitialValues({
            name: product.name,
            styleNumber: product.styleNumber,
            season: product.season,
            color: product.color || undefined,
            brandId: product.brandId || undefined,
            categoryId: product.categoryId || undefined,
            description: product.description || undefined,
            status: product.status,
          });
        })
        .catch(() => {
          message.error(t('common.error'));
          navigate('/products');
        })
        .finally(() => {
          setInitialLoading(false);
        });
    }
  }, [isEdit, id, navigate, message, t]);

  const handleSubmit = async (values: ProductFormData) => {
    setLoading(true);
    try {
      if (isEdit && id) {
        const updateData: UpdateProductParams = {
          name: values.name,
          styleNumber: values.styleNumber,
          season: values.season,
          color: values.color,
          brandId: values.brandId,
          categoryId: values.categoryId,
          description: values.description,
          status: values.status,
        };
        await productApi.updateProduct(id, updateData);
        message.success(t('products.updated'));
      } else {
        const createData: CreateProductParams = {
          name: values.name,
          styleNumber: values.styleNumber,
          season: values.season,
          color: values.color,
          brandId: values.brandId,
          categoryId: values.categoryId,
          description: values.description,
          status: values.status,
        };
        await productApi.createProduct(createData);
        message.success(t('products.created'));
      }
      navigate('/products');
      return true;
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
      return false;
    } finally {
      setLoading(false);
    }
  };

  if (initialLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Spin size="large" />
      </div>
    );
  }

  return (
    <div className="max-w-3xl">
      {/* Header */}
      <div className="mb-6">
        <Button
          type="text"
          icon={<ArrowLeftOutlined />}
          onClick={() => navigate('/products')}
          className="mb-2 -ml-2"
        >
          {t('common.back')}
        </Button>
        <h1 className="text-xl font-semibold">
          {isEdit ? `${t('common.edit')} ${t('nav.products')}` : t('products.addProduct')}
        </h1>
      </div>

      <ProForm<ProductFormData>
        layout="vertical"
        onFinish={handleSubmit}
        initialValues={initialValues || { status: 'draft' }}
        submitter={{
          searchConfig: {
            submitText: t('common.save'),
            resetText: t('common.cancel'),
          },
          submitButtonProps: { loading },
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
              rules={[
                { required: true, message: t('validation.required', { field: t('products.productName') }) },
              ]}
              width="md"
            />
            <ProFormText
              name="styleNumber"
              label={t('products.styleNumber')}
              placeholder="DR-2024SS-001"
              rules={[
                { required: true, message: t('validation.required', { field: t('products.styleNumber') }) },
              ]}
              width="md"
            />
          </ProFormGroup>

          <ProFormGroup>
            <ProFormText
              name="season"
              label={t('products.season')}
              placeholder="2024SS"
              rules={[
                { required: true, message: t('validation.required', { field: t('products.season') }) },
              ]}
              width="sm"
            />
            <ProFormText
              name="color"
              label={t('products.color')}
              placeholder={t('products.color')}
              width="sm"
            />
          </ProFormGroup>

          <ProFormGroup>
            <ProFormSelect
              name="brandId"
              label={t('products.brand')}
              placeholder={t('products.selectBrand')}
              options={brands}
              width="md"
              showSearch
              allowClear
            />
            <ProFormSelect
              name="categoryId"
              label={t('products.category')}
              placeholder={t('products.selectCategory')}
              options={categories}
              width="md"
              showSearch
              allowClear
            />
          </ProFormGroup>

          <ProFormTextArea
            name="description"
            label={t('detail.description')}
            placeholder={t('detail.description')}
            fieldProps={{ rows: 4 }}
          />
        </Card>

        {/* Status */}
        <Card title={t('products.status')} className="mb-4">
          <ProFormSelect
            name="status"
            label={t('products.status')}
            options={[
              { label: t('products.statusDraft'), value: 'draft' },
              { label: t('products.statusActive'), value: 'active' },
              { label: t('products.statusInactive'), value: 'inactive' },
              { label: t('products.statusDiscontinued'), value: 'discontinued' },
            ]}
            width="sm"
          />
        </Card>
      </ProForm>
    </div>
  );
}
