import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal, Form, Input, InputNumber, Switch, App, ColorPicker } from 'antd';
import type { Color } from 'antd/es/color-picker';

import { tagApi, type Tag, type TagDetail } from '@/lib/tag-api';

interface TagFormModalProps {
  open: boolean;
  tag: Tag | null;
  onClose: () => void;
  onSuccess: () => void;
}

interface FormValues {
  name: string;
  slug?: string;
  color?: string;
  sortOrder: number;
  isActive: boolean;
}

export function TagFormModal({ open, tag, onClose, onSuccess }: TagFormModalProps) {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [form] = Form.useForm<FormValues>();
  const [loading, setLoading] = useState(false);
  const [detailLoading, setDetailLoading] = useState(false);

  const isEdit = !!tag;
  const colorValue = Form.useWatch('color', form);

  useEffect(() => {
    if (open) {
      if (tag) {
        // 编辑模式：加载详情
        setDetailLoading(true);
        tagApi.getTag(tag.id)
          .then((detail: TagDetail) => {
            form.setFieldsValue({
              name: detail.name,
              slug: detail.slug,
              color: detail.color || undefined,
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
  }, [open, tag, form, message, t]);

  const handleSubmit = async () => {
    try {
      const values = await form.validateFields();
      setLoading(true);

      if (isEdit) {
        await tagApi.updateTag(tag!.id, values);
        message.success(t('tags.updated'));
      } else {
        await tagApi.createTag(values);
        message.success(t('tags.created'));
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

  const handleColorChange = (color: Color) => {
    form.setFieldsValue({ color: color.toHexString() });
  };

  return (
    <Modal
      title={isEdit ? t('tags.edit') : t('tags.add')}
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
          label={t('tags.name')}
          rules={[
            { required: true, message: t('tags.nameRequired') },
            { max: 50, message: t('tags.nameMaxLength') },
          ]}
        >
          <Input placeholder={t('tags.namePlaceholder')} />
        </Form.Item>

        <Form.Item
          name="slug"
          label={t('tags.slug')}
          tooltip={t('tags.slugTooltip')}
          rules={[
            { pattern: /^[a-z0-9-]+$/, message: t('tags.slugInvalid') },
            { max: 60, message: t('tags.slugMaxLength') },
          ]}
        >
          <Input placeholder={t('tags.slugPlaceholder')} />
        </Form.Item>

        <Form.Item
          name="color"
          label={t('tags.color')}
          tooltip={t('tags.colorTooltip')}
        >
          <div className="flex items-center gap-2">
            <ColorPicker
              value={colorValue}
              onChange={handleColorChange}
              showText
              format="hex"
            />
            <Input
              placeholder="#FF5733"
              value={colorValue}
              onChange={(e) => form.setFieldsValue({ color: e.target.value })}
              style={{ width: 120 }}
            />
          </div>
        </Form.Item>

        <Form.Item
          name="sortOrder"
          label={t('tags.sortOrder')}
          tooltip={t('tags.sortOrderTooltip')}
        >
          <InputNumber min={0} max={9999} style={{ width: '100%' }} />
        </Form.Item>

        <Form.Item
          name="isActive"
          label={t('tags.status')}
          valuePropName="checked"
        >
          <Switch checkedChildren={t('tags.statusActive')} unCheckedChildren={t('tags.statusInactive')} />
        </Form.Item>
      </Form>
    </Modal>
  );
}
