import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {Table, Button, Tag, Switch, App, Popconfirm, Space, Card} from 'antd';
import { PlusOutlined } from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';

import { categoryApi, type CategoryTreeNode } from '@/lib/category-api';
import { CategoryFormModal } from './components/category-form-modal';

export function CategoriesListPage() {
  const { t } = useTranslation();
  const { message, modal } = App.useApp();

  const [loading, setLoading] = useState(false);
  const [treeData, setTreeData] = useState<CategoryTreeNode[]>([]);
  const [formModalOpen, setFormModalOpen] = useState(false);
  const [editingCategory, setEditingCategory] = useState<CategoryTreeNode | null>(null);
  const [parentCategory, setParentCategory] = useState<CategoryTreeNode | null>(null);
  const [statusLoading, setStatusLoading] = useState<string | null>(null);

  const fetchData = async () => {
    setLoading(true);
    try {
      const result = await categoryApi.getCategoryTree();
      setTreeData(result.data);
    } catch (error) {
      console.error('Failed to fetch categories:', error);
      message.error(t('common.error'));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, []);

  const handleStatusChange = async (category: CategoryTreeNode, isActive: boolean) => {
    setStatusLoading(category.id);
    try {
      await categoryApi.updateCategoryStatus(category.id, isActive);
      message.success(isActive ? t('categories.activated') : t('categories.deactivated'));
      fetchData();
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setStatusLoading(null);
    }
  };

  const handleAdd = (parent?: CategoryTreeNode) => {
    setEditingCategory(null);
    setParentCategory(parent || null);
    setFormModalOpen(true);
  };

  const handleEdit = (category: CategoryTreeNode) => {
    setEditingCategory(category);
    setParentCategory(null);
    setFormModalOpen(true);
  };

  const handleDelete = async (category: CategoryTreeNode) => {
    modal.confirm({
      title: t('categories.confirmDelete'),
      content: t('categories.confirmDeleteDesc', { name: category.name }),
      okText: t('common.confirm'),
      cancelText: t('common.cancel'),
      okButtonProps: { danger: true },
      onOk: async () => {
        try {
          await categoryApi.deleteCategory(category.id);
          message.success(t('categories.deleted'));
          fetchData();
        } catch (error) {
          const err = error as { error?: string; childCount?: number; productCount?: number };
          if (err.childCount) {
            message.error(t('categories.hasChildren', { count: err.childCount }));
          } else if (err.productCount) {
            message.error(t('categories.hasProducts', { count: err.productCount }));
          } else {
            message.error(err.error || t('common.error'));
          }
        }
      },
    });
  };

  const columns: ColumnsType<CategoryTreeNode> = [
    {
      title: t('categories.name'),
      dataIndex: 'name',
      key: 'name',
      width: 250,
    },
    {
      title: t('categories.slug'),
      dataIndex: 'slug',
      key: 'slug',
      width: 180,
      render: (slug: string) => (
        <code className="text-xs bg-gray-100 px-2 py-1 rounded">{slug}</code>
      ),
    },
    {
      title: t('categories.level'),
      dataIndex: 'level',
      key: 'level',
      width: 80,
      render: (level: number) => (
        <Tag color={level === 0 ? 'blue' : level === 1 ? 'green' : 'orange'}>
          {t(`categories.level${level}`)}
        </Tag>
      ),
    },
    {
      title: t('categories.sortOrder'),
      dataIndex: 'sortOrder',
      key: 'sortOrder',
      width: 80,
    },
    {
      title: t('categories.status'),
      dataIndex: 'isActive',
      key: 'isActive',
      width: 100,
      render: (isActive: boolean) => (
        <Tag color={isActive ? 'success' : 'default'}>
          {isActive ? t('categories.statusActive') : t('categories.statusInactive')}
        </Tag>
      ),
    },
    {
      title: t('categories.enableSwitch'),
      key: 'enabled',
      width: 80,
      render: (_, record) => {
        const isLoading = statusLoading === record.id;
        return (
          <Popconfirm
            title={record.isActive ? t('categories.confirmDeactivate') : t('categories.confirmActivate')}
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
      title: t('common.actions'),
      key: 'actions',
      width: 200,
      render: (_, record) => (
        <Space size="small">
          {record.level < 2 && (
            <Button
              type="link"
              size="small"
              onClick={() => handleAdd(record)}
            >
              {t('categories.addChild')}
            </Button>
          )}
          <Button
            type="link"
            size="small"
            onClick={() => handleEdit(record)}
          >
            {t('common.edit')}
          </Button>
          <Button
            type="link"
            size="small"
            danger
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
      <div className="flex justify-end">
        <Button
          type="primary"
          icon={<PlusOutlined />}
          onClick={() => handleAdd()}
        >
          {t('categories.add')}
        </Button>
      </div>

      <Card>
        <Table<CategoryTreeNode>
            columns={columns}
            dataSource={treeData}
            rowKey="id"
            loading={loading}
            pagination={false}
            expandable={{
              defaultExpandAllRows: true,
              childrenColumnName: 'children',
            }}
            size="middle"
        />
      </Card>

      <CategoryFormModal
        open={formModalOpen}
        category={editingCategory}
        parentCategory={parentCategory}
        treeData={treeData}
        onClose={() => {
          setFormModalOpen(false);
          setEditingCategory(null);
          setParentCategory(null);
        }}
        onSuccess={() => {
          setFormModalOpen(false);
          setEditingCategory(null);
          setParentCategory(null);
          fetchData();
        }}
      />
    </div>
  );
}
