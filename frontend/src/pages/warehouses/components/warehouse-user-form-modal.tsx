import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal, Form, Input, Select, App } from 'antd';

import {
  warehouseApi,
  type WarehouseUser,
  type Warehouse,
  type CreateWarehouseUserRequest,
  type UpdateWarehouseUserRequest,
} from '@/lib/warehouse-api';

interface WarehouseUserFormModalProps {
  open: boolean;
  user: WarehouseUser | null;
  warehouses: Warehouse[];
  onClose: () => void;
  onSuccess: () => void;
}

export function WarehouseUserFormModal({
  open,
  user,
  warehouses,
  onClose,
  onSuccess,
}: WarehouseUserFormModalProps) {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);

  const isEdit = !!user;

  useEffect(() => {
    if (open && user) {
      form.setFieldsValue({
        email: user.email,
        warehouseId: user.warehouse?.id,
      });
    } else if (open) {
      form.resetFields();
    }
  }, [open, user, form]);

  const handleSubmit = async () => {
    try {
      const values = await form.validateFields();
      setLoading(true);

      if (isEdit) {
        const data: UpdateWarehouseUserRequest = {
          email: values.email,
          warehouseId: values.warehouseId,
        };
        if (values.password) {
          data.password = values.password;
        }
        await warehouseApi.updateWarehouseUser(user.id, data);
        message.success(t('warehouseUsers.updated'));
      } else {
        const data: CreateWarehouseUserRequest = {
          email: values.email,
          password: values.password,
          warehouseId: values.warehouseId,
        };
        await warehouseApi.createWarehouseUser(data);
        message.success(t('warehouseUsers.created'));
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

  return (
    <Modal
      title={isEdit ? t('warehouseUsers.edit') : t('warehouseUsers.create')}
      open={open}
      onCancel={onClose}
      onOk={handleSubmit}
      confirmLoading={loading}
      destroyOnClose
      width={480}
    >
      <Form
        form={form}
        layout="vertical"
        className="mt-4"
      >
        <Form.Item
          name="email"
          label={t('warehouseUsers.email')}
          rules={[
            { required: true, message: t('validation.emailRequired') },
            { type: 'email', message: t('validation.emailInvalid') },
          ]}
        >
          <Input placeholder={t('warehouseUsers.emailPlaceholder')} />
        </Form.Item>

        <Form.Item
          name="password"
          label={t('warehouseUsers.password')}
          rules={[
            { required: !isEdit, message: t('validation.passwordRequired') },
            { min: 8, message: t('validation.passwordMinLength') },
          ]}
          extra={isEdit ? t('warehouseUsers.passwordHint') : undefined}
        >
          <Input.Password placeholder={t('warehouseUsers.passwordPlaceholder')} />
        </Form.Item>

        <Form.Item
          name="warehouseId"
          label={t('warehouseUsers.warehouse')}
          rules={[{ required: true, message: t('warehouseUsers.warehouseRequired') }]}
        >
          <Select
            placeholder={t('warehouseUsers.selectWarehouse')}
            showSearch
            optionFilterProp="label"
            options={warehouses.map((w) => ({
              value: w.id,
              label: `${w.name} (${w.code})`,
            }))}
          />
        </Form.Item>
      </Form>
    </Modal>
  );
}
