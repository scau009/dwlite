import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router';
import { useTranslation } from 'react-i18next';
import { Modal, Form, Input, Select, App, Row, Col, Spin } from 'antd';
import {
  productApi,
  type ProductStatus,
  type CreateProductParams,
} from '@/lib/product-api';
import { brandApi } from '@/lib/brand-api';
import { categoryApi } from '@/lib/category-api';

interface ProductCreateModalProps {
  open: boolean;
  onClose: () => void;
}

interface FormValues {
  name: string;
  styleNumber: string;
  season: string;
  color?: string;
  brandId?: string;
  categoryId?: string;
  status: ProductStatus;
  description?: string;
}

export function ProductCreateModal({ open, onClose }: ProductCreateModalProps) {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const navigate = useNavigate();
  const [form] = Form.useForm<FormValues>();
  const [loading, setLoading] = useState(false);
  const [optionsLoading, setOptionsLoading] = useState(false);

  // Options for selects
  const [brands, setBrands] = useState<Array<{ value: string; label: string }>>([]);
  const [categories, setCategories] = useState<Array<{ value: string; label: string }>>([]);

  // Load options
  useEffect(() => {
    if (open) {
      setOptionsLoading(true);
      Promise.all([
        brandApi.getBrands({ limit: 100 }),
        categoryApi.getCategories({ limit: 100 }),
      ])
        .then(([brandsRes, categoriesRes]) => {
          setBrands(brandsRes.data.map((b) => ({ value: b.id, label: b.name })));
          setCategories(categoriesRes.data.map((c) => ({ value: c.id, label: c.name })));
        })
        .finally(() => {
          setOptionsLoading(false);
        });

      // Reset form with default values
      form.resetFields();
      form.setFieldsValue({ status: 'draft' });
    }
  }, [open, form]);

  const handleSubmit = async () => {
    try {
      const values = await form.validateFields();
      setLoading(true);

      const createData: CreateProductParams = {
        name: values.name,
        styleNumber: values.styleNumber,
        season: values.season,
        color: values.color,
        brandId: values.brandId,
        categoryId: values.categoryId,
        status: values.status,
        description: values.description,
      };

      const result = await productApi.createProduct(createData);
      message.success(t('products.created'));
      onClose();
      // Navigate to detail page to continue adding images and SKUs
      navigate(`/products/detail/${result.product.id}`);
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

  return (
    <Modal
      title={t('products.addProduct')}
      open={open}
      onCancel={onClose}
      onOk={handleSubmit}
      confirmLoading={loading}
      okText={t('common.save')}
      cancelText={t('common.cancel')}
      destroyOnClose
      width={600}
    >
      <Spin spinning={optionsLoading}>
        <Form form={form} layout="vertical" className="mt-4">
          <Form.Item
            name="name"
            label={t('products.productName')}
            rules={[
              {
                required: true,
                message: t('validation.required', { field: t('products.productName') }),
              },
            ]}
          >
            <Input placeholder={t('products.productName')} />
          </Form.Item>

          <Row gutter={16}>
            <Col span={12}>
              <Form.Item
                name="styleNumber"
                label={t('products.styleNumber')}
                rules={[
                  {
                    required: true,
                    message: t('validation.required', { field: t('products.styleNumber') }),
                  },
                ]}
              >
                <Input placeholder="DR-2024SS-001" />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item
                name="season"
                label={t('products.season')}
                rules={[
                  {
                    required: true,
                    message: t('validation.required', { field: t('products.season') }),
                  },
                ]}
              >
                <Input placeholder="2024SS" />
              </Form.Item>
            </Col>
          </Row>

          <Row gutter={16}>
            <Col span={12}>
              <Form.Item name="brandId" label={t('products.brand')}>
                <Select
                  placeholder={t('products.selectBrand')}
                  options={brands}
                  showSearch
                  allowClear
                  optionFilterProp="label"
                />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name="categoryId" label={t('products.category')}>
                <Select
                  placeholder={t('products.selectCategory')}
                  options={categories}
                  showSearch
                  allowClear
                  optionFilterProp="label"
                />
              </Form.Item>
            </Col>
          </Row>

          <Row gutter={16}>
            <Col span={12}>
              <Form.Item name="color" label={t('products.color')}>
                <Input placeholder={t('products.color')} />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name="status" label={t('products.status')}>
                <Select
                  options={[
                    { label: t('products.statusDraft'), value: 'draft' },
                    { label: t('products.statusActive'), value: 'active' },
                    { label: t('products.statusInactive'), value: 'inactive' },
                  ]}
                />
              </Form.Item>
            </Col>
          </Row>

          <Form.Item name="description" label={t('detail.description')}>
            <Input.TextArea rows={3} placeholder={t('detail.description')} />
          </Form.Item>
        </Form>
      </Spin>
    </Modal>
  );
}
