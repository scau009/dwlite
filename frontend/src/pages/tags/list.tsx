import { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ProTable, type ActionType, type ProColumns } from '@ant-design/pro-components';
import { Button, Tag, Switch, App, Popconfirm, Space, Tooltip } from 'antd';
import { PlusOutlined, EditOutlined, DeleteOutlined } from '@ant-design/icons';

import { tagApi, type Tag as TagType } from '@/lib/tag-api';
import { TagFormModal } from './components/tag-form-modal';

export function TagsListPage() {
  const { t } = useTranslation();
  const actionRef = useRef<ActionType>(null);
  const { message, modal } = App.useApp();

  const [formModalOpen, setFormModalOpen] = useState(false);
  const [editingTag, setEditingTag] = useState<TagType | null>(null);
  const [statusLoading, setStatusLoading] = useState<string | null>(null);

  const handleStatusChange = async (tag: TagType, isActive: boolean) => {
    setStatusLoading(tag.id);
    try {
      await tagApi.updateTagStatus(tag.id, isActive);
      message.success(isActive ? t('tags.activated') : t('tags.deactivated'));
      actionRef.current?.reload();
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setStatusLoading(null);
    }
  };

  const handleAdd = () => {
    setEditingTag(null);
    setFormModalOpen(true);
  };

  const handleEdit = (tag: TagType) => {
    setEditingTag(tag);
    setFormModalOpen(true);
  };

  const handleDelete = async (tag: TagType) => {
    modal.confirm({
      title: t('tags.confirmDelete'),
      content: t('tags.confirmDeleteDesc', { name: tag.name }),
      okText: t('common.confirm'),
      cancelText: t('common.cancel'),
      okButtonProps: { danger: true },
      onOk: async () => {
        try {
          await tagApi.deleteTag(tag.id);
          message.success(t('tags.deleted'));
          actionRef.current?.reload();
        } catch (error) {
          const err = error as { error?: string; productCount?: number };
          if (err.productCount) {
            message.error(t('tags.hasProducts', { count: err.productCount }));
          } else {
            message.error(err.error || t('common.error'));
          }
        }
      },
    });
  };

  const columns: ProColumns<TagType>[] = [
    {
      title: t('tags.name'),
      dataIndex: 'name',
      width: 150,
      ellipsis: true,
      fieldProps: {
        placeholder: t('common.search') + '...',
      },
      render: (_, record) => (
        <Tag color={record.color || undefined}>{record.name}</Tag>
      ),
    },
    {
      title: t('tags.slug'),
      dataIndex: 'slug',
      width: 150,
      search: false,
      render: (_, record) => (
        <code className="text-xs bg-gray-100 px-2 py-1 rounded">{record.slug}</code>
      ),
    },
    {
      title: t('tags.color'),
      dataIndex: 'color',
      width: 120,
      search: false,
      render: (_, record) => (
        record.color ? (
          <div className="flex items-center gap-2">
            <div
              className="w-6 h-6 rounded border"
              style={{ backgroundColor: record.color }}
            />
            <code className="text-xs">{record.color}</code>
          </div>
        ) : (
          <span className="text-gray-400">-</span>
        )
      ),
    },
    {
      title: t('tags.sortOrder'),
      dataIndex: 'sortOrder',
      width: 100,
      search: false,
      sorter: true,
    },
    {
      title: t('tags.status'),
      dataIndex: 'isActive',
      width: 100,
      valueType: 'select',
      valueEnum: {
        true: { text: t('tags.statusActive'), status: 'Success' },
        false: { text: t('tags.statusInactive'), status: 'Default' },
      },
      render: (_, record) => (
        <Tag color={record.isActive ? 'success' : 'default'}>
          {record.isActive ? t('tags.statusActive') : t('tags.statusInactive')}
        </Tag>
      ),
    },
    {
      title: t('tags.enableSwitch'),
      dataIndex: 'enabled',
      width: 80,
      search: false,
      render: (_, record) => {
        const isLoading = statusLoading === record.id;
        return (
          <Popconfirm
            title={record.isActive ? t('tags.confirmDeactivate') : t('tags.confirmActivate')}
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
        <h1 className="text-xl font-semibold">{t('tags.title')}</h1>
        <p className="text-gray-500">{t('tags.description')}</p>
      </div>

      <ProTable<TagType>
        actionRef={actionRef}
        columns={columns}
        rowKey="id"
        request={async (params) => {
          try {
            const result = await tagApi.getTags({
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
            console.error('Failed to fetch tags:', error);
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
            {t('tags.add')}
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

      <TagFormModal
        open={formModalOpen}
        tag={editingTag}
        onClose={() => {
          setFormModalOpen(false);
          setEditingTag(null);
        }}
        onSuccess={() => {
          setFormModalOpen(false);
          setEditingTag(null);
          actionRef.current?.reload();
        }}
      />
    </div>
  );
}
