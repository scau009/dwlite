import { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ProTable, type ActionType, type ProColumns } from '@ant-design/pro-components';
import { Button, Tag, Space, App, Popconfirm } from 'antd';
import { PlusOutlined } from '@ant-design/icons';

import {
  merchantWarehouseApi,
  type MerchantWarehouse,
} from '@/lib/merchant-warehouse-api';
import { WarehouseFormModal } from './components/warehouse-form-modal';

type WarehouseType = 'self' | 'third_party' | 'bonded' | 'overseas';
type WarehouseStatus = 'active' | 'maintenance' | 'disabled';

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

export function MerchantWarehousesListPage() {
  const { t } = useTranslation();
  const actionRef = useRef<ActionType>(null);
  const { message } = App.useApp();

  const [formModalOpen, setFormModalOpen] = useState(false);
  const [editingWarehouse, setEditingWarehouse] = useState<MerchantWarehouse | null>(null);

  const handleCreate = () => {
    setEditingWarehouse(null);
    setFormModalOpen(true);
  };

  const handleEdit = (warehouse: MerchantWarehouse) => {
    setEditingWarehouse(warehouse);
    setFormModalOpen(true);
  };

  const handleDelete = async (warehouse: MerchantWarehouse) => {
    try {
      await merchantWarehouseApi.deleteWarehouse(warehouse.id);
      message.success(t('merchantWarehouses.deleted'));
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

  const columns: ProColumns<MerchantWarehouse>[] = [
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
        <span className="font-medium">{record.name}</span>
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
      title: t('warehouses.location'),
      dataIndex: 'province',
      width: 150,
      search: false,
      render: (_, record) => {
        const parts = [record.province, record.city].filter(Boolean);
        return parts.length > 0 ? parts.join(' / ') : '-';
      },
    },
    {
      title: t('warehouses.contact'),
      dataIndex: 'contactName',
      width: 150,
      search: false,
      render: (_, record) => (
        <div>
          {record.contactName && <div>{record.contactName}</div>}
          {record.contactPhone && (
            <div className="text-xs text-gray-500">{record.contactPhone}</div>
          )}
          {!record.contactName && !record.contactPhone && '-'}
        </div>
      ),
    },
    {
      title: t('common.status'),
      dataIndex: 'status',
      width: 100,
      valueType: 'select',
      valueEnum: {
        active: { text: t('warehouses.statusActive'), status: 'Success' },
        maintenance: { text: t('warehouses.statusMaintenance'), status: 'Warning' },
        disabled: { text: t('warehouses.statusDisabled'), status: 'Default' },
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
      render: (_, record) => new Date(record.createdAt).toLocaleString(),
    },
    {
      title: t('common.actions'),
      valueType: 'option',
      width: 120,
      fixed: 'right',
      render: (_, record) => (
        <Space size="small">
          <Button
            type="link"
            size="small"
            onClick={() => handleEdit(record)}
          >
            {t('common.edit')}
          </Button>
          <Popconfirm
            title={t('merchantWarehouses.confirmDelete')}
            onConfirm={() => handleDelete(record)}
            okText={t('common.confirm')}
            cancelText={t('common.cancel')}
          >
            <Button type="link" size="small" danger>
              {t('common.delete')}
            </Button>
          </Popconfirm>
        </Space>
      ),
    },
  ];

  return (
    <div>
      <ProTable<MerchantWarehouse>
        headerTitle={t('merchantWarehouses.title')}
        actionRef={actionRef}
        columns={columns}
        rowKey="id"
        request={async (params) => {
          try {
            const result = await merchantWarehouseApi.getWarehouses({
              page: params.current,
              limit: params.pageSize,
              name: params.name,
              code: params.code,
              type: params.type,
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
        toolBarRender={() => [
          <Button
            key="create"
            type="primary"
            icon={<PlusOutlined />}
            onClick={handleCreate}
          >
            {t('merchantWarehouses.create')}
          </Button>,
        ]}
        search={{
          labelWidth: 'auto',
          defaultCollapsed: true,
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
        scroll={{ x: 1100 }}
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
