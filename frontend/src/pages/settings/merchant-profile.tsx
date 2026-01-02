import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import {
  Card,
  Form,
  Input,
  Button,
  Spin,
  App,
  Descriptions,
  Tag,
  Space,
  Divider,
} from 'antd';
import { EditOutlined, SaveOutlined, CloseOutlined } from '@ant-design/icons';

import {
  merchantApi,
  type MerchantProfile,
  type UpdateMerchantProfileRequest,
} from '@/lib/merchant-api';

const { TextArea } = Input;

export function MerchantProfilePage() {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [form] = Form.useForm();

  const [profile, setProfile] = useState<MerchantProfile | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [editing, setEditing] = useState(false);

  const loadProfile = async () => {
    setLoading(true);
    try {
      const data = await merchantApi.getMyProfile();
      setProfile(data);
      form.setFieldsValue(data);
    } catch {
      message.error(t('common.error'));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadProfile();
  }, []);

  const handleEdit = () => {
    setEditing(true);
    form.setFieldsValue(profile);
  };

  const handleCancel = () => {
    setEditing(false);
    form.setFieldsValue(profile);
  };

  const handleSave = async () => {
    try {
      const values = await form.validateFields();
      setSaving(true);

      const updateData: UpdateMerchantProfileRequest = {
        name: values.name,
        description: values.description || null,
        contactName: values.contactName,
        contactPhone: values.contactPhone,
        province: values.province || null,
        city: values.city || null,
        district: values.district || null,
        address: values.address || null,
      };

      const result = await merchantApi.updateMyProfile(updateData);
      setProfile(result.merchant);
      setEditing(false);
      message.success(t('settings.profileUpdated'));
    } catch (error) {
      const err = error as { error?: string };
      if (err.error) {
        message.error(err.error);
      }
    } finally {
      setSaving(false);
    }
  };

  const getStatusColor = (status: string) => {
    const colors: Record<string, string> = {
      pending: 'warning',
      approved: 'success',
      rejected: 'error',
      disabled: 'default',
    };
    return colors[status] || 'default';
  };

  const getStatusLabel = (status: string) => {
    const labels: Record<string, string> = {
      pending: t('merchants.statusPending'),
      approved: t('merchants.statusApproved'),
      rejected: t('merchants.statusRejected'),
      disabled: t('merchants.statusDisabled'),
    };
    return labels[status] || status;
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Spin size="large" />
      </div>
    );
  }

  if (!profile) {
    return (
      <Card>
        <div className="text-center text-gray-500">{t('settings.merchantNotFound')}</div>
      </Card>
    );
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex justify-end">
        {!editing ? (
          <Button type="primary" icon={<EditOutlined />} onClick={handleEdit}>
            {t('common.edit')}
          </Button>
        ) : (
          <Space>
            <Button icon={<CloseOutlined />} onClick={handleCancel}>
              {t('common.cancel')}
            </Button>
            <Button
              type="primary"
              icon={<SaveOutlined />}
              loading={saving}
              onClick={handleSave}
            >
              {t('common.save')}
            </Button>
          </Space>
        )}
      </div>

      {/* Account Status */}
      <Card title={t('settings.accountStatus')}>
        <Descriptions column={{ xs: 1, sm: 2, md: 3 }}>
          <Descriptions.Item label={t('merchants.email')}>
            {profile.email}
          </Descriptions.Item>
          <Descriptions.Item label={t('common.status')}>
            <Tag color={getStatusColor(profile.status)}>
              {getStatusLabel(profile.status)}
            </Tag>
          </Descriptions.Item>
          <Descriptions.Item label={t('common.createdAt')}>
            {new Date(profile.createdAt).toLocaleString()}
          </Descriptions.Item>
          {profile.approvedAt && (
            <Descriptions.Item label={t('settings.approvedAt')}>
              {new Date(profile.approvedAt).toLocaleString()}
            </Descriptions.Item>
          )}
          {profile.rejectedReason && (
            <Descriptions.Item label={t('settings.rejectedReason')} span={2}>
              <span className="text-red-500">{profile.rejectedReason}</span>
            </Descriptions.Item>
          )}
        </Descriptions>
      </Card>

      {/* Profile Form */}
      <Card title={t('settings.basicInfo')}>
        <Form
          form={form}
          layout="vertical"
          disabled={!editing}
          initialValues={profile}
        >
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <Form.Item
              name="name"
              label={t('merchants.name')}
              rules={[{ required: true, message: t('validation.required', { field: t('merchants.name') }) }]}
            >
              <Input placeholder={t('settings.enterMerchantName')} maxLength={100} />
            </Form.Item>

            <Form.Item name="description" label={t('settings.description')}>
              <TextArea rows={1} placeholder={t('settings.enterDescription')} maxLength={255} />
            </Form.Item>
          </div>

          <Divider>{t('settings.contactInfo')}</Divider>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <Form.Item
              name="contactName"
              label={t('merchants.contactName')}
              rules={[{ required: true, message: t('validation.required', { field: t('merchants.contactName') }) }]}
            >
              <Input placeholder={t('settings.enterContactName')} maxLength={50} />
            </Form.Item>

            <Form.Item
              name="contactPhone"
              label={t('merchants.contactPhone')}
              rules={[{ required: true, message: t('validation.required', { field: t('merchants.contactPhone') }) }]}
            >
              <Input placeholder={t('settings.enterContactPhone')} maxLength={20} />
            </Form.Item>
          </div>

          <Divider>{t('settings.addressInfo')}</Divider>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <Form.Item name="province" label={t('warehouses.province')}>
              <Input placeholder={t('warehouses.provincePlaceholder')} maxLength={50} />
            </Form.Item>

            <Form.Item name="city" label={t('warehouses.city')}>
              <Input placeholder={t('warehouses.cityPlaceholder')} maxLength={50} />
            </Form.Item>

            <Form.Item name="district" label={t('warehouses.district')}>
              <Input placeholder={t('warehouses.districtPlaceholder')} maxLength={50} />
            </Form.Item>
          </div>

          <Form.Item name="address" label={t('warehouses.address')}>
            <TextArea rows={2} placeholder={t('warehouses.addressPlaceholder')} maxLength={255} />
          </Form.Item>
        </Form>
      </Card>

    </div>
  );
}
