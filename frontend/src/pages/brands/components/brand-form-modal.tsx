import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal, Form, Input, InputNumber, Switch, App } from 'antd';

import { brandApi, type Brand, type BrandDetail } from '@/lib/brand-api';

interface BrandFormModalProps {
  open: boolean;
  brand: Brand | null;
  onClose: () => void;
  onSuccess: () => void;
}

interface FormValues {
  name: string;
  slug?: string;
  logoUrl?: string;
  description?: string;
  sortOrder: number;
  isActive: boolean;
}

export function BrandFormModal({ open, brand, onClose, onSuccess }: BrandFormModalProps) {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [form] = Form.useForm<FormValues>();
  const [loading, setLoading] = useState(false);
  const [detailLoading, setDetailLoading] = useState(false);

  const isEdit = !!brand;

  useEffect(() => {
    if (open) {
      if (brand) {
        // 编辑模式：加载详情
        setDetailLoading(true);
        brandApi.getBrand(brand.id)
          .then((detail: BrandDetail) => {
            form.setFieldsValue({
              name: detail.name,
              slug: detail.slug,
              logoUrl: detail.logoUrl || undefined,
              description: detail.description || undefined,
              sortOrder: detail.sortOrder,
              isActive: detail.isActive,
            });
          })
          .catch((err) => {
            message.error(err.error || t('common.error'));
          })
          .finally(() => {
            setDetailLoading(false);
          });
      } else {
        // 新增模式：重置表单
        form.resetFields();
        form.setFieldsValue({
          sortOrder: 0,
          isActive: true,
        });
      }
    }
  }, [open, brand, form, message, t]);

  const handleSubmit = async () => {
    try {
      const values = await form.validateFields();
      setLoading(true);

      if (isEdit) {
        await brandApi.updateBrand(brand!.id, values);
        message.success(t('brands.updated'));
      } else {
        await brandApi.createBrand(values);
        message.success(t('brands.created'));
      }

      onSuccess();
    } catch (error) {
      if (error && typeof error === 'object' && 'errorFields' in error) {
        // Form validation error
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
      title={isEdit ? t('brands.edit') : t('brands.add')}
      open={open}
      onCancel={onClose}
      onOk={handleSubmit}
      confirmLoading={loading}
      okText={t('common.save')}
      cancelText={t('common.cancel')}
      destroyOnClose
      width={500}
    >
      <Form
        form={form}
        layout="vertical"
        className="mt-4"
        disabled={detailLoading}
      >
        <Form.Item
          name="name"
          label={t('brands.name')}
          rules={[
            { required: true, message: t('brands.nameRequired') },
            { max: 100, message: t('brands.nameMaxLength') },
          ]}
        >
          <Input placeholder={t('brands.namePlaceholder')} />
        </Form.Item>

        <Form.Item
          name="slug"
          label={t('brands.slug')}
          tooltip={t('brands.slugTooltip')}
          rules={[
            { pattern: /^[a-z0-9-]+$/, message: t('brands.slugInvalid') },
            { max: 100, message: t('brands.slugMaxLength') },
          ]}
        >
          <Input placeholder={t('brands.slugPlaceholder')} />
        </Form.Item>

        <Form.Item
          name="logoUrl"
          label={t('brands.logoUrl')}
          rules={[
            { type: 'url', message: t('brands.logoUrlInvalid') },
          ]}
        >
          <Input placeholder={t('brands.logoUrlPlaceholder')} />
        </Form.Item>

        <Form.Item
          name="description"
          label={t('brands.descriptionLabel')}
          rules={[
            { max: 500, message: t('brands.descriptionMaxLength') },
          ]}
        >
          <Input.TextArea
            rows={3}
            placeholder={t('brands.descriptionPlaceholder')}
          />
        </Form.Item>

        <Form.Item
          name="sortOrder"
          label={t('brands.sortOrder')}
          tooltip={t('brands.sortOrderTooltip')}
        >
          <InputNumber min={0} max={9999} style={{ width: '100%' }} />
        </Form.Item>

        <Form.Item
          name="isActive"
          label={t('brands.status')}
          valuePropName="checked"
        >
          <Switch checkedChildren={t('brands.statusActive')} unCheckedChildren={t('brands.statusInactive')} />
        </Form.Item>
      </Form>
    </Modal>
  );
}