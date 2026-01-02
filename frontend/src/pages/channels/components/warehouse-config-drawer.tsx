import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
  Drawer,
  Button,
  Space,
  App,
  Tag,
  Switch,
  InputNumber,
  Popconfirm,
} from 'antd';
import { ProTable, type ProColumns } from '@ant-design/pro-components';
import { PlusOutlined } from '@ant-design/icons';
import {
  channelWarehouseApi,
  type ChannelWarehouse,
} from '@/lib/channel-warehouse-api';
import type { SalesChannel } from '@/lib/channel-api';
import { AddWarehouseModal } from './add-warehouse-modal';
import { BatchAddWarehouseModal } from './batch-add-warehouse-modal';

interface WarehouseConfigDrawerProps {
  open: boolean;
  channel: SalesChannel | null;
  onClose: () => void;
}

const warehouseTypeColorMap: Record<string, string> = {
  self: 'blue',
  third_party: 'default',
  bonded: 'purple',
  overseas: 'orange',
};

const warehouseTypeI18nKeyMap: Record<string, string> = {
  self: 'channels.warehouses.typeSelf',
  third_party: 'channels.warehouses.typeThirdParty',
  bonded: 'channels.warehouses.typeBonded',
  overseas: 'channels.warehouses.typeOverseas',
};

export function WarehouseConfigDrawer({ open, channel, onClose }: WarehouseConfigDrawerProps) {
  const { t } = useTranslation();
  const { message } = App.useApp();

  const [warehouses, setWarehouses] = useState<ChannelWarehouse[]>([]);
  const [loading, setLoading] = useState(false);
  const [addModalOpen, setAddModalOpen] = useState(false);
  const [batchAddModalOpen, setBatchAddModalOpen] = useState(false);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [editingPriority, setEditingPriority] = useState<number>(0);

  useEffect(() => {
    if (open && channel) {
      loadWarehouses();
    }
  }, [open, channel]);

  const loadWarehouses = async () => {
    if (!channel) return;

    setLoading(true);
    try {
      const result = await channelWarehouseApi.getChannelWarehouses(channel.id);
      setWarehouses(result.data);
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setLoading(false);
    }
  };

  const handleStatusChange = async (record: ChannelWarehouse, checked: boolean) => {
    if (!channel) return;

    try {
      await channelWarehouseApi.updateWarehouse(channel.id, record.id, {
        status: checked ? 'active' : 'disabled',
      });
      message.success(t('channels.warehouses.updated'));
      await loadWarehouses();
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    }
  };

  const handlePriorityChange = async (record: ChannelWarehouse, priority: number | null) => {
    if (!channel || priority === null) return;

    try {
      await channelWarehouseApi.updateWarehouse(channel.id, record.id, {
        priority,
      });
      message.success(t('channels.warehouses.updated'));
      await loadWarehouses();
      setEditingId(null);
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
      setEditingId(null);
    }
  };

  const handleRemove = async (record: ChannelWarehouse) => {
    if (!channel) return;

    try {
      await channelWarehouseApi.removeWarehouse(channel.id, record.id);
      message.success(t('channels.warehouses.removed'));
      await loadWarehouses();
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    }
  };

  const columns: ProColumns<ChannelWarehouse>[] = [
    {
      title: t('channels.warehouses.warehouseName'),
      dataIndex: ['warehouse', 'name'],
      width: 200,
      render: (_, record) => (
        <div>
          <div className="font-medium">{record.warehouse.name}</div>
          <div className="text-xs text-gray-500">{record.warehouse.code}</div>
        </div>
      ),
    },
    {
      title: t('channels.warehouses.type'),
      dataIndex: ['warehouse', 'type'],
      width: 100,
      render: (_, record) => {
        const type = record.warehouse.type;
        const color = warehouseTypeColorMap[type] || warehouseTypeColorMap.third_party;
        const labelKey = warehouseTypeI18nKeyMap[type] || warehouseTypeI18nKeyMap.third_party;
        return <Tag color={color}>{t(labelKey)}</Tag>;
      },
    },
    {
      title: t('channels.warehouses.country'),
      dataIndex: ['warehouse', 'countryCode'],
      width: 80,
      render: (_, record) => <span className="uppercase">{record.warehouse.countryCode}</span>,
    },
    {
      title: t('channels.warehouses.priority'),
      dataIndex: 'priority',
      width: 100,
      render: (_, record) => {
        if (editingId === record.id) {
          return (
            <InputNumber
              size="small"
              min={0}
              defaultValue={record.priority}
              onChange={(value) => setEditingPriority(value || 0)}
              onPressEnter={() => handlePriorityChange(record, editingPriority)}
              onBlur={() => setEditingId(null)}
              autoFocus
            />
          );
        }
        return (
          <span
            className="cursor-pointer hover:text-blue-500"
            onClick={() => {
              setEditingId(record.id);
              setEditingPriority(record.priority);
            }}
          >
            {record.priority}
          </span>
        );
      },
    },
    {
      title: t('channels.warehouses.status'),
      dataIndex: 'status',
      width: 100,
      render: (_, record) => (
        <Switch
          checked={record.status === 'active'}
          onChange={(checked) => handleStatusChange(record, checked)}
          checkedChildren={t('common.active')}
          unCheckedChildren={t('common.inactive')}
        />
      ),
    },
    {
      title: t('common.actions'),
      width: 80,
      fixed: 'right',
      render: (_, record) => (
        <Popconfirm
          title={t('channels.warehouses.confirmRemove')}
          description={t('channels.warehouses.confirmRemoveDesc', { name: record.warehouse.name })}
          onConfirm={() => handleRemove(record)}
          okText={t('common.confirm')}
          cancelText={t('common.cancel')}
        >
          <Button type="link" danger size="small">
            {t('common.delete')}
          </Button>
        </Popconfirm>
      ),
    },
  ];

  return (
    <>
      <Drawer
        title={t('channels.warehouses.configTitle', { name: channel?.name || '' })}
        width={1000}
        open={open}
        onClose={onClose}
        extra={
          <Space>
            <Button onClick={() => setBatchAddModalOpen(true)}>
              {t('channels.warehouses.batchAdd')}
            </Button>
            <Button type="primary" icon={<PlusOutlined />} onClick={() => setAddModalOpen(true)}>
              {t('channels.warehouses.addWarehouse')}
            </Button>
          </Space>
        }
      >
        <ProTable<ChannelWarehouse>
          columns={columns}
          dataSource={warehouses}
          rowKey="id"
          search={false}
          toolBarRender={false}
          pagination={false}
          loading={loading}
          options={false}
          cardProps={{ bodyStyle: { padding: 0 } }}
        />

        {warehouses.length === 0 && !loading && (
          <div className="text-center text-gray-400 py-8">
            {t('common.noData')}
          </div>
        )}
      </Drawer>

      <AddWarehouseModal
        open={addModalOpen}
        channelId={channel?.id || null}
        onClose={() => setAddModalOpen(false)}
        onSuccess={() => {
          setAddModalOpen(false);
          loadWarehouses();
        }}
      />

      <BatchAddWarehouseModal
        open={batchAddModalOpen}
        channelId={channel?.id || null}
        onClose={() => setBatchAddModalOpen(false)}
        onSuccess={() => {
          setBatchAddModalOpen(false);
          loadWarehouses();
        }}
      />
    </>
  );
}
