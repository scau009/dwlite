import { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ProTable, type ActionType, type ProColumns } from '@ant-design/pro-components';
import { Button, Tag, Switch, App, Popconfirm, Space, Avatar } from 'antd';
import { PlusOutlined, EditOutlined, DeleteOutlined } from '@ant-design/icons';

import { brandApi, type Brand } from '@/lib/brand-api';
import { BrandFormModal } from './components/brand-form-modal';

export function BrandsListPage() {
  const { t } = useTranslation();
  const actionRef = useRef<ActionType>(null);
  const { message, modal } = App.useApp();

  const [formModalOpen, setFormModalOpen] = useState(false);
  const [editingBrand, setEditingBrand] = useState<Brand | null>(null);
  const [statusLoading, setStatusLoading] = useState<string | null>(null);

  const handleStatusChange = async (brand: Brand, isActive: boolean) => {
    setStatusLoading(brand.id);
    try {
      await brandApi.updateBrandStatus(brand.id, isActive);
      message.success(isActive ? t('brands.activated') : t('brands.deactivated'));
      actionRef.current?.reload();
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setStatusLoading(null);
    }
  };

  const handleAdd = () => {
    setEditingBrand(null);
    setFormModalOpen(true);
  };

  const handleEdit = (brand: Brand) => {
    setEditingBrand(brand);
    setFormModalOpen(true);
  };

  const handleDelete = async (brand: Brand) => {
    modal.confirm({
      title: t('brands.confirmDelete'),
      content: t('brands.confirmDeleteDesc', { name: brand.name }),
      okText: t('common.confirm'),
      cancelText: t('common.cancel'),
      okButtonProps: { danger: true },
      onOk: async () => {
        try {
          await brandApi.deleteBrand(brand.id);
          message.success(t('brands.deleted'));
          actionRef.current?.reload();
        } catch (error) {
          const err = error as { error?: string; productCount?: number };
          if (err.productCount) {
            message.error(t('brands.hasProducts', { count: err.productCount }));
          } else {
            message.error(err.error || t('common.error'));
          }
        }
      },
    });
  };

  const columns: ProColumns<Brand>[] = [
    {
      title: t('brands.logo'),
      dataIndex: 'logoUrl',
      width: 80,
      search: false,
      render: (_, record) => (
        record.logoUrl ? (
          <Avatar src={record.logoUrl} shape="square" size={40} />
        ) : (
          <Avatar shape="square" size={40}>{record.name.charAt(0).toUpperCase()}</Avatar>
        )
      ),
    },
    {
      title: t('brands.name'),
      dataIndex: 'name',
      ellipsis: true,
      fieldProps: {
        placeholder: t('common.search') + '...',
      },
    },
    {
      title: t('brands.slug'),
      dataIndex: 'slug',
      width: 150,
      search: false,
      render: (_, record) => (
        <code className="text-xs bg-gray-100 px-2 py-1 rounded">{record.slug}</code>
      ),
    },
    {
      title: t('brands.sortOrder'),
      dataIndex: 'sortOrder',
      width: 100,
      search: false,
      sorter: true,
    },
    {
      title: t('brands.status'),
      dataIndex: 'isActive',
      width: 100,
      valueType: 'select',
      valueEnum: {
        true: { text: t('brands.statusActive'), status: 'Success' },
        false: { text: t('brands.statusInactive'), status: 'Default' },
      },
      render: (_, record) => (
        <Tag color={record.isActive ? 'success' : 'default'}>
          {record.isActive ? t('brands.statusActive') : t('brands.statusInactive')}
        </Tag>
      ),
    },
    {
      title: t('brands.enableSwitch'),
      dataIndex: 'enabled',
      width: 80,
      search: false,
      render: (_, record) => {
        const isLoading = statusLoading === record.id;
        return (
          <Popconfirm
            title={record.isActive ? t('brands.confirmDeactivate') : t('brands.confirmActivate')}
            onConfirm={() => handleStatusChange(record, !record.isActive)}
            okText={t('common.confirm')}
            cancelText={t('common.cancel')}
            disabled={isLoading}
          >
            <Switch
              checked={record.isActive}
              loading={isLoading}
              size="small"
            />
          </Popconfirm>
        );
      },
    },
    {
      title: t('common.createdAt'),
      dataIndex: 'createdAt',
      width: 160,
      search: false,
      render: (_, record) => new Date(record.createdAt).toLocaleString(),
    },
    {
      title: t('common.actions'),
      valueType: 'option',
      width: 120,
      render: (_, record) => (
        <Space size="small">
          <Button
            type="link"
            size="small"
            icon={<EditOutlined />}
            onClick={() => handleEdit(record)}
          >
            {t('common.edit')}
          </Button>
          <Button
            type="link"
            size="small"
            danger
            icon={<DeleteOutlined />}
            onClick={() => handleDelete(record)}
          >
            {t('common.delete')}
          </Button>
        </Space>
      ),
    },
  ];

  return (
    <div className="space-y-4">
      <div className="mb-4">
        <h1 className="text-xl font-semibold">{t('brands.title')}</h1>
        <p className="text-gray-500">{t('brands.description')}</p>
      </div>

      <ProTable<Brand>
        actionRef={actionRef}
        columns={columns}
        rowKey="id"
        request={async (params, sort) => {
          try {
            const result = await brandApi.getBrands({
              page: params.current,
              limit: params.pageSize,
              name: params.name,
              isActive: params.isActive === 'true' ? true : params.isActive === 'false' ? false : undefined,
            });
            return {
              data: result.data,
              success: true,
              total: result.total,
            };
          } catch (error) {
            console.error('Failed to fetch brands:', error);
            return {
              data: [],
              success: false,
              total: 0,
            };
          }
        }}
        toolBarRender={() => [
          <Button
            key="add"
            type="primary"
            icon={<PlusOutlined />}
            onClick={handleAdd}
          >
            {t('brands.add')}
          </Button>,
        ]}
        search={{
          labelWidth: 'auto',
          defaultCollapsed: false,
        }}
        options={{
          density: true,
          fullScreen: true,
          reload: true,
        }}
        pagination={{
          defaultPageSize: 20,
          showSizeChanger: true,
        }}
      />

      <BrandFormModal
        open={formModalOpen}
        brand={editingBrand}
        onClose={() => {
          setFormModalOpen(false);
          setEditingBrand(null);
        }}
        onSuccess={() => {
          setFormModalOpen(false);
          setEditingBrand(null);
          actionRef.current?.reload();
        }}
      />
    </div>
  );
}