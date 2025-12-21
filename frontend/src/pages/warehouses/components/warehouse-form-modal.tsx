import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import {
  Modal,
  Form,
  Input,
  Select,
  InputNumber,
  App,
  Tabs,
  Row,
  Col,
} from 'antd';

import {
  warehouseApi,
  type Warehouse,
  type WarehouseDetail,
  type CreateWarehouseRequest,
} from '@/lib/warehouse-api';

interface WarehouseFormModalProps {
  open: boolean;
  warehouse: Warehouse | null;
  onClose: () => void;
  onSuccess: () => void;
}

export function WarehouseFormModal({
  open,
  warehouse,
  onClose,
  onSuccess,
}: WarehouseFormModalProps) {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);
  const [detailLoading, setDetailLoading] = useState(false);

  const isEdit = !!warehouse;

  // Load warehouse detail when editing
  useEffect(() => {
    if (open && warehouse) {
      setDetailLoading(true);
      warehouseApi
        .getWarehouse(warehouse.id)
        .then((detail: WarehouseDetail) => {
          form.setFieldsValue({
            ...detail,
          });
        })
        .catch(() => {
          message.error(t('common.error'));
        })
        .finally(() => {
          setDetailLoading(false);
        });
    } else if (open) {
      form.resetFields();
      form.setFieldsValue({
        type: 'third_party',
        category: 'platform',
        countryCode: 'CN',
        status: 'active',
        sortOrder: 0,
      });
    }
  }, [open, warehouse, form, message, t]);

  const handleSubmit = async () => {
    try {
      const values = await form.validateFields();
      setLoading(true);

      const data: CreateWarehouseRequest = {
        ...values,
      };

      if (isEdit) {
        await warehouseApi.updateWarehouse(warehouse.id, data);
        message.success(t('warehouses.updated'));
      } else {
        await warehouseApi.createWarehouse(data);
        message.success(t('warehouses.created'));
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

  const tabItems = [
    {
      key: 'basic',
      label: t('warehouses.tabBasic'),
      children: (
        <Row gutter={16}>
          <Col span={12}>
            <Form.Item
              name="code"
              label={t('warehouses.code')}
              rules={[{ required: true, message: t('validation.required') }]}
            >
              <Input placeholder="WH-SH-001" disabled={isEdit} />
            </Form.Item>
          </Col>
          <Col span={12}>
            <Form.Item
              name="name"
              label={t('warehouses.name')}
              rules={[{ required: true, message: t('validation.required') }]}
            >
              <Input placeholder={t('warehouses.namePlaceholder')} />
            </Form.Item>
          </Col>
          <Col span={12}>
            <Form.Item name="shortName" label={t('warehouses.shortName')}>
              <Input placeholder={t('warehouses.shortNamePlaceholder')} />
            </Form.Item>
          </Col>
          <Col span={12}>
            <Form.Item
              name="type"
              label={t('warehouses.type')}
              rules={[{ required: true, message: t('validation.required') }]}
            >
              <Select>
                <Select.Option value="self">{t('warehouses.typeSelf')}</Select.Option>
                <Select.Option value="third_party">{t('warehouses.typeThirdParty')}</Select.Option>
                <Select.Option value="bonded">{t('warehouses.typeBonded')}</Select.Option>
                <Select.Option value="overseas">{t('warehouses.typeOverseas')}</Select.Option>
              </Select>
            </Form.Item>
          </Col>
          <Col span={12}>
            <Form.Item
              name="category"
              label={t('warehouses.category')}
              rules={[{ required: true, message: t('validation.required') }]}
            >
              <Select>
                <Select.Option value="platform">{t('warehouses.categoryPlatform')}</Select.Option>
                <Select.Option value="merchant">{t('warehouses.categoryMerchant')}</Select.Option>
              </Select>
            </Form.Item>
          </Col>
          <Col span={12}>
            <Form.Item
              name="status"
              label={t('common.status')}
              rules={[{ required: true, message: t('validation.required') }]}
            >
              <Select>
                <Select.Option value="active">{t('warehouses.statusActive')}</Select.Option>
                <Select.Option value="maintenance">{t('warehouses.statusMaintenance')}</Select.Option>
                <Select.Option value="disabled">{t('warehouses.statusDisabled')}</Select.Option>
              </Select>
            </Form.Item>
          </Col>
          <Col span={12}>
            <Form.Item name="sortOrder" label={t('warehouses.sortOrder')}>
              <InputNumber
                placeholder="0"
                className="w-full"
                min={0}
              />
            </Form.Item>
          </Col>
          <Col span={24}>
            <Form.Item name="description" label={t('warehouses.descriptionLabel')}>
              <Input.TextArea rows={3} placeholder={t('warehouses.descriptionPlaceholder')} />
            </Form.Item>
          </Col>
        </Row>
      ),
    },
    {
      key: 'location',
      label: t('warehouses.tabLocation'),
      children: (
        <Row gutter={16}>
          <Col span={8}>
            <Form.Item name="countryCode" label={t('warehouses.countryCode')}>
              <Input placeholder="CN" maxLength={2} />
            </Form.Item>
          </Col>
          <Col span={8}>
            <Form.Item name="province" label={t('warehouses.province')}>
              <Input placeholder={t('warehouses.provincePlaceholder')} />
            </Form.Item>
          </Col>
          <Col span={8}>
            <Form.Item name="city" label={t('warehouses.city')}>
              <Input placeholder={t('warehouses.cityPlaceholder')} />
            </Form.Item>
          </Col>
          <Col span={8}>
            <Form.Item name="district" label={t('warehouses.district')}>
              <Input placeholder={t('warehouses.districtPlaceholder')} />
            </Form.Item>
          </Col>
          <Col span={16}>
            <Form.Item name="address" label={t('warehouses.address')}>
              <Input placeholder={t('warehouses.addressPlaceholder')} />
            </Form.Item>
          </Col>
          <Col span={8}>
            <Form.Item name="postalCode" label={t('warehouses.postalCode')}>
              <Input placeholder="200000" />
            </Form.Item>
          </Col>
          <Col span={8}>
            <Form.Item name="longitude" label={t('warehouses.longitude')}>
              <Input placeholder="121.4737021" />
            </Form.Item>
          </Col>
          <Col span={8}>
            <Form.Item name="latitude" label={t('warehouses.latitude')}>
              <Input placeholder="31.2303904" />
            </Form.Item>
          </Col>
          <Col span={8}>
            <Form.Item name="timezone" label={t('warehouses.timezone')}>
              <Input placeholder="Asia/Shanghai" />
            </Form.Item>
          </Col>
        </Row>
      ),
    },
    {
      key: 'contact',
      label: t('warehouses.tabContact'),
      children: (
        <Row gutter={16}>
          <Col span={12}>
            <Form.Item
              name="contactName"
              label={t('warehouses.contactName')}
            >
              <Input placeholder={t('warehouses.contactNamePlaceholder')} />
            </Form.Item>
          </Col>
          <Col span={12}>
            <Form.Item
              name="contactPhone"
              label={t('warehouses.contactPhone')}
            >
              <Input placeholder={t('warehouses.contactPhonePlaceholder')} />
            </Form.Item>
          </Col>
          <Col span={12}>
            <Form.Item name="contactEmail" label={t('warehouses.contactEmail')}>
              <Input placeholder={t('warehouses.contactEmailPlaceholder')} />
            </Form.Item>
          </Col>
        </Row>
      ),
    },
    {
      key: 'notes',
      label: t('warehouses.tabNotes'),
      children: (
        <Row gutter={16}>
          <Col span={24}>
            <Form.Item name="internalNotes" label={t('warehouses.internalNotes')}>
              <Input.TextArea rows={6} placeholder={t('warehouses.internalNotesPlaceholder')} />
            </Form.Item>
          </Col>
        </Row>
      ),
    },
  ];

  return (
    <Modal
      title={isEdit ? t('warehouses.edit') : t('warehouses.create')}
      open={open}
      onCancel={onClose}
      onOk={handleSubmit}
      confirmLoading={loading}
      destroyOnClose
      width={800}
    >
      <Form form={form} layout="vertical" className="mt-4" disabled={detailLoading}>
        <Tabs items={tabItems} />
      </Form>
    </Modal>
  );
}
