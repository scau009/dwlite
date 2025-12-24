import { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ProTable, type ActionType, type ProColumns } from '@ant-design/pro-components';
import { Button, Tag, Space, App, Popconfirm, Tooltip } from 'antd';
import { PlusOutlined, EditOutlined, DeleteOutlined } from '@ant-design/icons';

import {
  warehouseApi,
  type WarehouseUser,
  type Warehouse,
} from '@/lib/warehouse-api';
import { WarehouseUserFormModal } from './components/warehouse-user-form-modal';

export function WarehouseUsersListPage() {
  const { t } = useTranslation();
  const actionRef = useRef<ActionType>(null);
  const { message } = App.useApp();

  const [formModalOpen, setFormModalOpen] = useState(false);
  const [editingUser, setEditingUser] = useState<WarehouseUser | null>(null);
  const [warehouses, setWarehouses] = useState<Warehouse[]>([]);

  // Load warehouses for the form
  const loadWarehouses = async () => {
    try {
      const result = await warehouseApi.getWarehouses({ limit: 100 });
      setWarehouses(result.data);
    } catch (error) {
      console.error('Failed to load warehouses:', error);
    }
  };

  const handleCreate = async () => {
    await loadWarehouses();
    setEditingUser(null);
    setFormModalOpen(true);
  };

  const handleEdit = async (user: WarehouseUser) => {
    await loadWarehouses();
    setEditingUser(user);
    setFormModalOpen(true);
  };

  const handleDelete = async (user: WarehouseUser) => {
    try {
      await warehouseApi.deleteWarehouseUser(user.id);
      message.success(t('warehouseUsers.deleted'));
      actionRef.current?.reload();
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    }
  };

  const columns: ProColumns<WarehouseUser>[] = [
    {
      title: t('warehouseUsers.email'),
      dataIndex: 'email',
      width: 220,
      ellipsis: true,
      copyable: true,
    },
    {
      title: t('warehouseUsers.warehouse'),
      dataIndex: ['warehouse', 'name'],
      width: 200,
      search: false,
      render: (_, record) => (
        record.warehouse ? (
          <Tooltip title={record.warehouse.code}>
            <Tag color="blue">{record.warehouse.name}</Tag>
          </Tooltip>
        ) : (
          <Tag color="default">{t('warehouseUsers.noWarehouse')}</Tag>
        )
      ),
    },
    {
      title: t('warehouseUsers.verified'),
      dataIndex: 'isVerified',
      width: 100,
      search: false,
      render: (_, record) => (
        <Tag color={record.isVerified ? 'success' : 'warning'}>
          {record.isVerified ? t('common.yes') : t('common.no')}
        </Tag>
      ),
    },
    {
      title: t('common.createdAt'),
      dataIndex: 'createdAt',
      width: 160,
      search: false,
      sorter: true,
      render: (_, record) => new Date(record.createdAt).toLocaleString(),
    },
    {
      title: t('common.actions'),
      valueType: 'option',
      width: 120,
      fixed: 'right',
      render: (_, record) => (
        <Space size="small">
          <Tooltip title={t('common.edit')}>
            <Button
              type="text"
              size="small"
              icon={<EditOutlined />}
              onClick={() => handleEdit(record)}
            />
          </Tooltip>
          <Popconfirm
            title={t('warehouseUsers.confirmDelete')}
            description={t('warehouseUsers.confirmDeleteDesc')}
            onConfirm={() => handleDelete(record)}
            okText={t('common.confirm')}
            cancelText={t('common.cancel')}
          >
            <Tooltip title={t('common.delete')}>
              <Button
                type="text"
                size="small"
                danger
                icon={<DeleteOutlined />}
              />
            </Tooltip>
          </Popconfirm>
        </Space>
      ),
    },
  ];

  return (
    <div className="space-y-4">
      <div className="mb-4">
        <h1 className="text-xl font-semibold">{t('warehouseUsers.title')}</h1>
        <p className="text-gray-500">{t('warehouseUsers.description')}</p>
      </div>

      <ProTable<WarehouseUser>
        actionRef={actionRef}
        columns={columns}
        rowKey="id"
        request={async (params) => {
          try {
            const result = await warehouseApi.getWarehouseUsers({
              page: params.current,
              limit: params.pageSize,
            });
            return {
              data: result.data,
              success: true,
              total: result.meta.total,
            };
          } catch (error) {
            console.error('Failed to fetch warehouse users:', error);
            return {
              data: [],
              success: false,
              total: 0,
            };
          }
        }}
        search={false}
        options={{
          density: true,
          fullScreen: true,
          reload: true,
        }}
        pagination={{
          defaultPageSize: 20,
          showSizeChanger: true,
        }}
        toolBarRender={() => [
          <Button
            key="create"
            type="primary"
            icon={<PlusOutlined />}
            onClick={handleCreate}
          >
            {t('warehouseUsers.create')}
          </Button>,
        ]}
      />

      <WarehouseUserFormModal
        open={formModalOpen}
        user={editingUser}
        warehouses={warehouses}
        onClose={() => {
          setFormModalOpen(false);
          setEditingUser(null);
        }}
        onSuccess={() => {
          setFormModalOpen(false);
          setEditingUser(null);
          actionRef.current?.reload();
        }}
      />
    </div>
  );
}
