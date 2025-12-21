import { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ProTable, type ActionType, type ProColumns } from '@ant-design/pro-components';
import { Button, Tag, Space, App, Popconfirm, Tooltip } from 'antd';
import { PlusOutlined, EditOutlined, DeleteOutlined } from '@ant-design/icons';

import {
  warehouseApi,
  type Warehouse,
  type WarehouseType,
  type WarehouseCategory,
  type WarehouseStatus,
} from '@/lib/warehouse-api';
import { WarehouseFormModal } from './components/warehouse-form-modal';

// Status color mapping
const statusColors: Record<WarehouseStatus, string> = {
  active: 'success',
  maintenance: 'warning',
  disabled: 'default',
};

// Type color mapping
const typeColors: Record<WarehouseType, string> = {
  self: 'blue',
  third_party: 'cyan',
  bonded: 'purple',
  overseas: 'orange',
};

// Category color mapping
const categoryColors: Record<WarehouseCategory, string> = {
  platform: 'green',
  merchant: 'gold',
};

export function WarehousesListPage() {
  const { t } = useTranslation();
  const actionRef = useRef<ActionType>(null);
  const { message } = App.useApp();

  const [formModalOpen, setFormModalOpen] = useState(false);
  const [editingWarehouse, setEditingWarehouse] = useState<Warehouse | null>(null);

  const handleCreate = () => {
    setEditingWarehouse(null);
    setFormModalOpen(true);
  };

  const handleEdit = (warehouse: Warehouse) => {
    setEditingWarehouse(warehouse);
    setFormModalOpen(true);
  };

  const handleDelete = async (warehouse: Warehouse) => {
    try {
      await warehouseApi.deleteWarehouse(warehouse.id);
      message.success(t('warehouses.deleted'));
      actionRef.current?.reload();
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    }
  };

  const getStatusLabel = (status: WarehouseStatus) => {
    const labels: Record<WarehouseStatus, string> = {
      active: t('warehouses.statusActive'),
      maintenance: t('warehouses.statusMaintenance'),
      disabled: t('warehouses.statusDisabled'),
    };
    return labels[status] || status;
  };

  const getTypeLabel = (type: WarehouseType) => {
    const labels: Record<WarehouseType, string> = {
      self: t('warehouses.typeSelf'),
      third_party: t('warehouses.typeThirdParty'),
      bonded: t('warehouses.typeBonded'),
      overseas: t('warehouses.typeOverseas'),
    };
    return labels[type] || type;
  };

  const getCategoryLabel = (category: WarehouseCategory) => {
    const labels: Record<WarehouseCategory, string> = {
      platform: t('warehouses.categoryPlatform'),
      merchant: t('warehouses.categoryMerchant'),
    };
    return labels[category] || category;
  };

  const columns: ProColumns<Warehouse>[] = [
    {
      title: t('warehouses.code'),
      dataIndex: 'code',
      width: 120,
      ellipsis: true,
      copyable: true,
      fieldProps: {
        placeholder: t('common.search') + '...',
      },
    },
    {
      title: t('warehouses.name'),
      dataIndex: 'name',
      width: 180,
      ellipsis: true,
      fieldProps: {
        placeholder: t('common.search') + '...',
      },
      render: (_, record) => (
        <Tooltip title={record.fullAddress}>
          <span className="font-medium">{record.name}</span>
        </Tooltip>
      ),
    },
    {
      title: t('warehouses.type'),
      dataIndex: 'type',
      width: 100,
      valueType: 'select',
      valueEnum: {
        self: { text: t('warehouses.typeSelf') },
        third_party: { text: t('warehouses.typeThirdParty') },
        bonded: { text: t('warehouses.typeBonded') },
        overseas: { text: t('warehouses.typeOverseas') },
      },
      render: (_, record) => (
        <Tag color={typeColors[record.type]}>{getTypeLabel(record.type)}</Tag>
      ),
    },
    {
      title: t('warehouses.category'),
      dataIndex: 'category',
      width: 100,
      valueType: 'select',
      valueEnum: {
        platform: { text: t('warehouses.categoryPlatform') },
        merchant: { text: t('warehouses.categoryMerchant') },
      },
      render: (_, record) => (
        <Tag color={categoryColors[record.category]}>{getCategoryLabel(record.category)}</Tag>
      ),
    },
    {
      title: t('warehouses.location'),
      dataIndex: 'province',
      width: 150,
      search: false,
      render: (_, record) => (
        <span>
          {[record.province, record.city].filter(Boolean).join(' / ') || '-'}
        </span>
      ),
    },
    {
      title: t('warehouses.contactName'),
      dataIndex: 'contactName',
      width: 100,
      search: false,
    },
    {
      title: t('warehouses.contactPhone'),
      dataIndex: 'contactPhone',
      width: 130,
      search: false,
    },
    {
      title: t('common.status'),
      dataIndex: 'status',
      width: 100,
      valueType: 'select',
      valueEnum: {
        active: { text: t('warehouses.statusActive') },
        maintenance: { text: t('warehouses.statusMaintenance') },
        disabled: { text: t('warehouses.statusDisabled') },
      },
      render: (_, record) => (
        <Tag color={statusColors[record.status]}>{getStatusLabel(record.status)}</Tag>
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
            title={t('warehouses.confirmDelete')}
            description={t('warehouses.confirmDeleteDesc')}
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
        <h1 className="text-xl font-semibold">{t('warehouses.title')}</h1>
        <p className="text-gray-500">{t('warehouses.description')}</p>
      </div>

      <ProTable<Warehouse>
        actionRef={actionRef}
        columns={columns}
        rowKey="id"
        request={async (params) => {
          try {
            const result = await warehouseApi.getWarehouses({
              page: params.current,
              limit: params.pageSize,
              name: params.name,
              code: params.code,
              type: params.type,
              category: params.category,
              status: params.status,
            });
            return {
              data: result.data,
              success: true,
              total: result.total,
            };
          } catch (error) {
            console.error('Failed to fetch warehouses:', error);
            return {
              data: [],
              success: false,
              total: 0,
            };
          }
        }}
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
        scroll={{ x: 1400 }}
        toolBarRender={() => [
          <Button
            key="create"
            type="primary"
            icon={<PlusOutlined />}
            onClick={handleCreate}
          >
            {t('warehouses.create')}
          </Button>,
        ]}
      />

      <WarehouseFormModal
        open={formModalOpen}
        warehouse={editingWarehouse}
        onClose={() => {
          setFormModalOpen(false);
          setEditingWarehouse(null);
        }}
        onSuccess={() => {
          setFormModalOpen(false);
          setEditingWarehouse(null);
          actionRef.current?.reload();
        }}
      />
    </div>
  );
}
