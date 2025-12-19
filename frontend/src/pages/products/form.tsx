import { useState, useEffect, useCallback, useRef } from 'react';
import { useParams, useNavigate } from 'react-router';
import { useTranslation } from 'react-i18next';
import {
  ProForm,
  ProFormText,
  ProFormSelect,
  ProFormTextArea,
  type ProFormInstance,
} from '@ant-design/pro-components';
import { Card, App, Button, Spin, Row, Col, Space } from 'antd';
import { ArrowLeftOutlined } from '@ant-design/icons';
import {
  productApi,
  type ProductDetail,
  type ProductStatus,
  type ProductImage,
  type ProductSku,
  type CreateProductParams,
  type UpdateProductParams,
} from '@/lib/product-api';
import { brandApi } from '@/lib/brand-api';
import { categoryApi } from '@/lib/category-api';
import { ProductImages } from './components/product-images';
import { ProductSkus } from './components/product-skus';

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

  const formRef = useRef<ProFormInstance>();
  const [loading, setLoading] = useState(false);
  const [saveAndContinue, setSaveAndContinue] = useState(false);
  const [initialLoading, setInitialLoading] = useState(isEdit);
  const [initialValues, setInitialValues] = useState<ProductFormData | undefined>(undefined);
  const [images, setImages] = useState<ProductImage[]>([]);
  const [skus, setSkus] = useState<ProductSku[]>([]);

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

  // Load product data
  const loadProduct = useCallback(async () => {
    if (!id) return;

    try {
      const product = await productApi.getProduct(id);
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
      setImages(product.images || []);
      setSkus(product.skus || []);
    } catch {
      message.error(t('common.error'));
      navigate('/products');
    }
  }, [id, message, t, navigate]);

  // Load product for edit
  useEffect(() => {
    if (isEdit && id) {
      setInitialLoading(true);
      loadProduct().finally(() => {
        setInitialLoading(false);
      });
    }
  }, [isEdit, id, loadProduct]);

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
        navigate('/products');
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
        const result = await productApi.createProduct(createData);
        message.success(t('products.created'));

        if (saveAndContinue) {
          // Navigate to edit page to continue adding images and SKUs
          navigate(`/products/${result.product.id}/edit`);
        } else {
          navigate('/products');
        }
      }
      return true;
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
      return false;
    } finally {
      setLoading(false);
      setSaveAndContinue(false);
    }
  };

  const handleSave = () => {
    setSaveAndContinue(false);
    formRef.current?.submit();
  };

  const handleSaveAndContinue = () => {
    setSaveAndContinue(true);
    formRef.current?.submit();
  };

  if (initialLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Spin size="large" />
      </div>
    );
  }

  return (
    <div>
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
        formRef={formRef}
        layout="vertical"
        onFinish={handleSubmit}
        initialValues={initialValues || { status: 'draft' }}
        submitter={false}
      >
        {isEdit ? (
          // Edit mode: Show basic info + images side by side
          <Row gutter={16} style={{ marginBottom: 16 }}>
            <Col xs={24} lg={14}>
              <Card title={t('detail.basicInfo')} style={{ height: '100%' }}>
                <BasicInfoFields
                  t={t}
                  brands={brands}
                  categories={categories}
                />
              </Card>
            </Col>
            <Col xs={24} lg={10}>
              <Card title={t('products.images')} style={{ height: '100%' }}>
                <ProductImages
                  productId={id!}
                  images={images}
                  onUpdate={loadProduct}
                />
              </Card>
            </Col>
          </Row>
        ) : (
          // Create mode: Only show basic info
          <Card title={t('detail.basicInfo')} style={{ marginBottom: 16 }}>
            <BasicInfoFields
              t={t}
              brands={brands}
              categories={categories}
            />
          </Card>
        )}
      </ProForm>

      {/* SKU Management - only show in edit mode */}
      {isEdit && id && (
        <Card title={t('products.skuManagement')} style={{ marginBottom: 16 }}>
          <ProductSkus
            productId={id}
            skus={skus}
            onUpdate={loadProduct}
          />
        </Card>
      )}

      {/* Footer Actions */}
      <div className="mt-4">
        <Space>
          <Button onClick={() => navigate('/products')}>
            {t('common.cancel')}
          </Button>
          {!isEdit && (
            <Button
              loading={loading && saveAndContinue}
              onClick={handleSaveAndContinue}
            >
              {t('products.saveAndContinue')}
            </Button>
          )}
          <Button
            type="primary"
            loading={loading && !saveAndContinue}
            onClick={handleSave}
          >
            {t('common.save')}
          </Button>
        </Space>
      </div>
    </div>
  );
}

// Extracted basic info fields component to avoid duplication
function BasicInfoFields({
  t,
  brands,
  categories,
}: {
  t: (key: string, options?: Record<string, unknown>) => string;
  brands: Array<{ value: string; label: string }>;
  categories: Array<{ value: string; label: string }>;
}) {
  return (
    <>
      <ProFormText
        name="name"
        label={t('products.productName')}
        placeholder={t('products.productName')}
        rules={[
          { required: true, message: t('validation.required', { field: t('products.productName') }) },
        ]}
      />

      <Row gutter={16}>
        <Col span={12}>
          <ProFormText
            name="styleNumber"
            label={t('products.styleNumber')}
            placeholder="DR-2024SS-001"
            rules={[
              { required: true, message: t('validation.required', { field: t('products.styleNumber') }) },
            ]}
          />
        </Col>
        <Col span={12}>
          <ProFormText
            name="season"
            label={t('products.season')}
            placeholder="2024SS"
            rules={[
              { required: true, message: t('validation.required', { field: t('products.season') }) },
            ]}
          />
        </Col>
      </Row>

      <Row gutter={16}>
        <Col span={12}>
          <ProFormSelect
            name="brandId"
            label={t('products.brand')}
            placeholder={t('products.selectBrand')}
            options={brands}
            showSearch
            allowClear
          />
        </Col>
        <Col span={12}>
          <ProFormSelect
            name="categoryId"
            label={t('products.category')}
            placeholder={t('products.selectCategory')}
            options={categories}
            showSearch
            allowClear
          />
        </Col>
      </Row>

      <Row gutter={16}>
        <Col span={12}>
          <ProFormText
            name="color"
            label={t('products.color')}
            placeholder={t('products.color')}
          />
        </Col>
        <Col span={12}>
          <ProFormSelect
            name="status"
            label={t('products.status')}
            options={[
              { label: t('products.statusDraft'), value: 'draft' },
              { label: t('products.statusActive'), value: 'active' },
              { label: t('products.statusInactive'), value: 'inactive' },
              { label: t('products.statusDiscontinued'), value: 'discontinued' },
            ]}
          />
        </Col>
      </Row>

      <ProFormTextArea
        name="description"
        label={t('detail.description')}
        placeholder={t('detail.description')}
        fieldProps={{ rows: 3 }}
      />
    </>
  );
}
