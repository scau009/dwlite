import { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ProTable, type ActionType, type ProColumns } from '@ant-design/pro-components';
import { Button, Tag, Switch, App, Popconfirm, Space, Tabs, Typography } from 'antd';
import { PlusOutlined, SettingOutlined } from '@ant-design/icons';

import { merchantRuleApi, type MerchantRule } from '@/lib/merchant-rule-api';
import { RuleFormModal } from './components/rule-form-modal';
import { AssignmentDrawer } from './components/assignment-drawer';

const { Paragraph } = Typography;

type RuleType = 'pricing' | 'stock_allocation';

export function MerchantRulesPage() {
  const { t } = useTranslation();
  const actionRef = useRef<ActionType>(null);
  const { message, modal } = App.useApp();

  const [ruleType, setRuleType] = useState<RuleType>('pricing');
  const [formModalOpen, setFormModalOpen] = useState(false);
  const [editingRule, setEditingRule] = useState<MerchantRule | null>(null);
  const [assignmentDrawerOpen, setAssignmentDrawerOpen] = useState(false);
  const [selectedRule, setSelectedRule] = useState<MerchantRule | null>(null);
  const [statusLoading, setStatusLoading] = useState<string | null>(null);

  const handleStatusChange = async (rule: MerchantRule, isActive: boolean) => {
    setStatusLoading(rule.id);
    try {
      await merchantRuleApi.updateRule(rule.id, { isActive });
      message.success(isActive ? t('rules.activated') : t('rules.deactivated'));
      actionRef.current?.reload();
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setStatusLoading(null);
    }
  };

  const handleAdd = () => {
    setEditingRule(null);
    setFormModalOpen(true);
  };

  const handleEdit = (rule: MerchantRule) => {
    setEditingRule(rule);
    setFormModalOpen(true);
  };

  const handleManageAssignments = (rule: MerchantRule) => {
    setSelectedRule(rule);
    setAssignmentDrawerOpen(true);
  };

  const handleDelete = async (rule: MerchantRule) => {
    modal.confirm({
      title: t('rules.confirmDelete'),
      content: t('rules.confirmDeleteDesc', { name: rule.name }),
      okText: t('common.confirm'),
      cancelText: t('common.cancel'),
      okButtonProps: { danger: true },
      onOk: async () => {
        try {
          await merchantRuleApi.deleteRule(rule.id);
          message.success(t('rules.deleted'));
          actionRef.current?.reload();
        } catch (error) {
          const err = error as { error?: string };
          message.error(err.error || t('common.error'));
        }
      },
    });
  };

  const getCategoryColor = (category: string) => {
    switch (category) {
      case 'markup':
        return 'blue';
      case 'discount':
        return 'green';
      case 'ratio':
        return 'purple';
      case 'limit':
        return 'orange';
      default:
        return 'default';
    }
  };

  const columns: ProColumns<MerchantRule>[] = [
    {
      title: t('rules.code'),
      dataIndex: 'code',
      width: 150,
      ellipsis: true,
      render: (_, record) => (
        <code className="text-xs bg-gray-100 px-2 py-1 rounded">{record.code}</code>
      ),
    },
    {
      title: t('rules.name'),
      dataIndex: 'name',
      width: 200,
      ellipsis: true,
      fieldProps: {
        placeholder: t('common.search') + '...',
      },
    },
    {
      title: t('rules.category'),
      dataIndex: 'category',
      width: 100,
      search: false,
      render: (_, record) => (
        <Tag color={getCategoryColor(record.category)}>
          {t(`rules.category${record.category.charAt(0).toUpperCase() + record.category.slice(1)}`)}
        </Tag>
      ),
    },
    {
      title: t('rules.expression'),
      dataIndex: 'expression',
      width: 250,
      search: false,
      ellipsis: true,
      render: (_, record) => (
        <Paragraph
          className="mb-0 font-mono text-xs"
          ellipsis={{ rows: 2, tooltip: record.expression }}
        >
          {record.expression}
        </Paragraph>
      ),
    },
    {
      title: t('rules.priority'),
      dataIndex: 'priority',
      width: 80,
      search: false,
      sorter: true,
    },
    {
      title: t('rules.status'),
      dataIndex: 'isActive',
      width: 100,
      valueType: 'select',
      valueEnum: {
        true: { text: t('rules.statusActive'), status: 'Success' },
        false: { text: t('rules.statusInactive'), status: 'Default' },
      },
      render: (_, record) => (
        <Tag color={record.isActive ? 'success' : 'default'}>
          {record.isActive ? t('rules.statusActive') : t('rules.statusInactive')}
        </Tag>
      ),
    },
    {
      title: t('rules.enableSwitch'),
      dataIndex: 'enabled',
      width: 80,
      search: false,
      render: (_, record) => {
        const isLoading = statusLoading === record.id;
        return (
          <Popconfirm
            title={record.isActive ? t('rules.confirmDeactivate') : t('rules.confirmActivate')}
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
      width: 180,
      fixed: 'right',
      render: (_, record) => (
        <Space size="small">
          <Button
            type="link"
            size="small"
            icon={<SettingOutlined />}
            onClick={() => handleManageAssignments(record)}
          >
            {t('rules.assignments')}
          </Button>
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

  const tabItems = [
    {
      key: 'pricing',
      label: t('rules.pricingRules'),
    },
    {
      key: 'stock_allocation',
      label: t('rules.stockAllocationRules'),
    },
  ];

  return (
    <div className="space-y-4">
      <Tabs
        activeKey={ruleType}
        onChange={(key) => {
          setRuleType(key as RuleType);
          actionRef.current?.reload();
        }}
        items={tabItems}
      />

      <ProTable<MerchantRule>
        actionRef={actionRef}
        columns={columns}
        rowKey="id"
        request={async (params) => {
          try {
            const result = await merchantRuleApi.getRules({
              page: params.current,
              limit: params.pageSize,
              type: ruleType,
              search: params.name,
            });
            return {
              data: result.data,
              success: true,
              total: result.total,
            };
          } catch (error) {
            console.error('Failed to fetch rules:', error);
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
            {t('rules.addRule')}
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
        scroll={{ x: 1200 }}
      />

      <RuleFormModal
        open={formModalOpen}
        rule={editingRule}
        ruleType={ruleType}
        onClose={() => {
          setFormModalOpen(false);
          setEditingRule(null);
        }}
        onSuccess={() => {
          setFormModalOpen(false);
          setEditingRule(null);
          actionRef.current?.reload();
        }}
      />

      <AssignmentDrawer
        open={assignmentDrawerOpen}
        rule={selectedRule}
        onClose={() => {
          setAssignmentDrawerOpen(false);
          setSelectedRule(null);
        }}
      />
    </div>
  );
}
