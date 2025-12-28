import { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ProTable, type ActionType, type ProColumns } from '@ant-design/pro-components';
import { Button, Tag, App, Popconfirm, Space, Avatar, Tooltip, Select } from 'antd';
import { PlusOutlined, EditOutlined, DeleteOutlined } from '@ant-design/icons';

import { channelApi, type SalesChannel } from '@/lib/channel-api';
import { ChannelFormModal } from './components/channel-form-modal';

const statusColorMap: Record<string, string> = {
  active: 'success',
  maintenance: 'warning',
  disabled: 'default',
};

const businessTypeColorMap: Record<string, string> = {
  import: 'blue',
  export: 'green',
};

export function ChannelsListPage() {
  const { t } = useTranslation();
  const actionRef = useRef<ActionType>(null);
  const { message, modal } = App.useApp();

  const [formModalOpen, setFormModalOpen] = useState(false);
  const [editingChannel, setEditingChannel] = useState<SalesChannel | null>(null);
  const [statusLoading, setStatusLoading] = useState<string | null>(null);

  const handleStatusChange = async (channel: SalesChannel, newStatus: 'active' | 'maintenance' | 'disabled') => {
    setStatusLoading(channel.id);
    try {
      await channelApi.updateChannelStatus(channel.id, newStatus);
      message.success(t('channels.statusUpdated'));
      actionRef.current?.reload();
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setStatusLoading(null);
    }
  };

  const handleAdd = () => {
    setEditingChannel(null);
    setFormModalOpen(true);
  };

  const handleEdit = (channel: SalesChannel) => {
    setEditingChannel(channel);
    setFormModalOpen(true);
  };

  const handleDelete = async (channel: SalesChannel) => {
    modal.confirm({
      title: t('channels.confirmDelete'),
      content: t('channels.confirmDeleteDesc', { name: channel.name }),
      okText: t('common.confirm'),
      cancelText: t('common.cancel'),
      okButtonProps: { danger: true },
      onOk: async () => {
        try {
          await channelApi.deleteChannel(channel.id);
          message.success(t('channels.deleted'));
          actionRef.current?.reload();
        } catch (error) {
          const err = error as { error?: string; merchantCount?: number };
          if (err.merchantCount) {
            message.error(t('channels.hasMerchants', { count: err.merchantCount }));
          } else {
            message.error(err.error || t('common.error'));
          }
        }
      },
    });
  };

  const columns: ProColumns<SalesChannel>[] = [
    {
      title: t('channels.logo'),
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
      title: t('channels.name'),
      dataIndex: 'name',
      width: 150,
      ellipsis: true,
      fieldProps: {
        placeholder: t('common.search') + '...',
      },
    },
    {
      title: t('channels.code'),
      dataIndex: 'code',
      width: 120,
      render: (_, record) => (
        <code className="text-xs bg-gray-100 px-2 py-1 rounded">{record.code}</code>
      ),
    },
    {
      title: t('channels.businessType'),
      dataIndex: 'businessType',
      width: 100,
      valueType: 'select',
      valueEnum: {
        import: { text: t('channels.businessTypeImport') },
        export: { text: t('channels.businessTypeExport') },
      },
      render: (_, record) => (
        <Tag color={businessTypeColorMap[record.businessType]}>
          {t(`channels.businessType${record.businessType.charAt(0).toUpperCase() + record.businessType.slice(1)}`)}
        </Tag>
      ),
    },
    {
      title: t('channels.status'),
      dataIndex: 'status',
      width: 100,
      valueType: 'select',
      valueEnum: {
        active: { text: t('channels.statusActive'), status: 'Success' },
        maintenance: { text: t('channels.statusMaintenance'), status: 'Warning' },
        disabled: { text: t('channels.statusDisabled'), status: 'Default' },
      },
      render: (_, record) => (
        <Tag color={statusColorMap[record.status]}>
          {t(`channels.status${record.status.charAt(0).toUpperCase() + record.status.slice(1)}`)}
        </Tag>
      ),
    },
    {
      title: t('channels.statusSwitch'),
      dataIndex: 'statusSwitch',
      width: 120,
      search: false,
      render: (_, record) => {
        const isLoading = statusLoading === record.id;
        return (
          <Popconfirm
            title={t('channels.confirmStatusChange')}
            description={
              <Select
                size="small"
                defaultValue={record.status}
                style={{ width: 120 }}
                options={[
                  { value: 'active', label: t('channels.statusActive') },
                  { value: 'maintenance', label: t('channels.statusMaintenance') },
                  { value: 'disabled', label: t('channels.statusDisabled') },
                ]}
                onChange={(value) => handleStatusChange(record, value)}
                disabled={isLoading}
              />
            }
            okButtonProps={{ style: { display: 'none' } }}
            cancelText={t('common.close')}
            disabled={isLoading}
          >
            <Button size="small" loading={isLoading}>
              {t('channels.changeStatus')}
            </Button>
          </Popconfirm>
        );
      },
    },
    {
      title: t('channels.sortOrder'),
      dataIndex: 'sortOrder',
      width: 80,
      search: false,
      sorter: true,
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
      width: 80,
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
          <Tooltip title={t('common.delete')}>
            <Button
              type="text"
              size="small"
              danger
              icon={<DeleteOutlined />}
              onClick={() => handleDelete(record)}
            />
          </Tooltip>
        </Space>
      ),
    },
  ];

  return (
    <div className="space-y-4">
      <div className="mb-4">
        <h1 className="text-xl font-semibold">{t('channels.title')}</h1>
        <p className="text-gray-500">{t('channels.description')}</p>
      </div>

      <ProTable<SalesChannel>
        actionRef={actionRef}
        columns={columns}
        rowKey="id"
        request={async (params) => {
          try {
            const result = await channelApi.getChannels({
              page: params.current,
              limit: params.pageSize,
              name: params.name,
              code: params.code,
              businessType: params.businessType,
              status: params.status,
            });
            return {
              data: result.data,
              success: true,
              total: result.total,
            };
          } catch (error) {
            console.error('Failed to fetch channels:', error);
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
            {t('channels.add')}
          </Button>,
        ]}
        search={{
          labelWidth: 'auto',
          defaultCollapsed: false,
          span: 6,
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

      <ChannelFormModal
        open={formModalOpen}
        channel={editingChannel}
        onClose={() => {
          setFormModalOpen(false);
          setEditingChannel(null);
        }}
        onSuccess={() => {
          setFormModalOpen(false);
          setEditingChannel(null);
          actionRef.current?.reload();
        }}
      />
    </div>
  );
}
