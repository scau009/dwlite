import { useEffect, useState, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal, Form, Input, InputNumber, Switch, TreeSelect, App } from 'antd';

import { categoryApi, type CategoryTreeNode, type CategoryDetail } from '@/lib/category-api';

interface CategoryFormModalProps {
  open: boolean;
  category: CategoryTreeNode | null;
  parentCategory: CategoryTreeNode | null;
  treeData: CategoryTreeNode[];
  onClose: () => void;
  onSuccess: () => void;
}

interface FormValues {
  name: string;
  slug?: string;
  parentId?: string;
  description?: string;
  sortOrder: number;
  isActive: boolean;
}

interface TreeSelectNode {
  title: string;
  value: string;
  disabled?: boolean;
  children?: TreeSelectNode[];
}

export function CategoryFormModal({
  open,
  category,
  parentCategory,
  treeData,
  onClose,
  onSuccess,
}: CategoryFormModalProps) {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [form] = Form.useForm<FormValues>();
  const [loading, setLoading] = useState(false);
  const [detailLoading, setDetailLoading] = useState(false);

  const isEdit = !!category;

  // 转换树形数据为 TreeSelect 格式
  const treeSelectData = useMemo(() => {
    const convert = (nodes: CategoryTreeNode[], editingId?: string): TreeSelectNode[] => {
      return nodes
        .filter(node => node.id !== editingId) // 编辑时排除自己
        .filter(node => node.level < 2) // 只允许选择前两级作为父级
        .map(node => ({
          title: node.name,
          value: node.id,
          disabled: !node.isActive, // 禁用未启用的分类
          children: node.children?.length
            ? convert(node.children, editingId)
            : undefined,
        }));
    };
    return convert(treeData, category?.id);
  }, [treeData, category?.id]);

  useEffect(() => {
    if (open) {
      if (category) {
        // 编辑模式：加载详情
        setDetailLoading(true);
        categoryApi.getCategory(category.id)
          .then((detail: CategoryDetail) => {
            form.setFieldsValue({
              name: detail.name,
              slug: detail.slug,
              parentId: detail.parentId || undefined,
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
          parentId: parentCategory?.id,
        });
      }
    }
  }, [open, category, parentCategory, form, message, t]);

  const handleSubmit = async () => {
    try {
      const values = await form.validateFields();
      setLoading(true);

      const data = {
        ...values,
        parentId: values.parentId || undefined,
      };

      if (isEdit) {
        await categoryApi.updateCategory(category!.id, data);
        message.success(t('categories.updated'));
      } else {
        await categoryApi.createCategory(data);
        message.success(t('categories.created'));
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
      title={isEdit ? t('categories.edit') : (parentCategory ? t('categories.addChild') : t('categories.add'))}
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
          name="parentId"
          label={t('categories.parent')}
        >
          <TreeSelect
            treeData={treeSelectData}
            placeholder={t('categories.parentPlaceholder')}
            allowClear
            treeDefaultExpandAll
            showSearch
            treeNodeFilterProp="title"
          />
        </Form.Item>

        <Form.Item
          name="name"
          label={t('categories.name')}
          rules={[
            { required: true, message: t('categories.nameRequired') },
            { max: 100, message: t('categories.nameMaxLength') },
          ]}
        >
          <Input placeholder={t('categories.namePlaceholder')} />
        </Form.Item>

        <Form.Item
          name="slug"
          label={t('categories.slug')}
          tooltip={t('categories.slugTooltip')}
          rules={[
            { pattern: /^[a-z0-9-]+$/, message: t('categories.slugInvalid') },
            { max: 120, message: t('categories.slugMaxLength') },
          ]}
        >
          <Input placeholder={t('categories.slugPlaceholder')} />
        </Form.Item>

        <Form.Item
          name="description"
          label={t('categories.descriptionLabel')}
          rules={[
            { max: 500, message: t('categories.descriptionMaxLength') },
          ]}
        >
          <Input.TextArea
            rows={3}
            placeholder={t('categories.descriptionPlaceholder')}
          />
        </Form.Item>

        <Form.Item
          name="sortOrder"
          label={t('categories.sortOrder')}
          tooltip={t('categories.sortOrderTooltip')}
        >
          <InputNumber min={0} max={9999} style={{ width: '100%' }} />
        </Form.Item>

        <Form.Item
          name="isActive"
          label={t('categories.status')}
          valuePropName="checked"
        >
          <Switch
            checkedChildren={t('categories.statusActive')}
            unCheckedChildren={t('categories.statusInactive')}
          />
        </Form.Item>
      </Form>
    </Modal>
  );
}
