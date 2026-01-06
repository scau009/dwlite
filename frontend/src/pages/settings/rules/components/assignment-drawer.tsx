import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { Drawer, Table, Button, Switch, App, Space, Tag, Empty, Popconfirm, Select, Form, InputNumber } from 'antd';
import type { MerchantRule, MerchantRuleAssignment } from '@/lib/merchant-rule-api';
import { merchantRuleApi } from '@/lib/merchant-rule-api';
import { merchantChannelApi, type MyMerchantChannel } from '@/lib/merchant-channel-api';

interface AssignmentDrawerProps {
  open: boolean;
  rule: MerchantRule | null;
  onClose: () => void;
}

export function AssignmentDrawer({ open, rule, onClose }: AssignmentDrawerProps) {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [loading, setLoading] = useState(false);
  const [assignments, setAssignments] = useState<MerchantRuleAssignment[]>([]);
  const [channels, setChannels] = useState<MyMerchantChannel[]>([]);
  const [addingLoading, setAddingLoading] = useState(false);
  const [toggleLoading, setToggleLoading] = useState<string | null>(null);
  const [deleteLoading, setDeleteLoading] = useState<string | null>(null);
  const [showAddForm, setShowAddForm] = useState(false);
  const [form] = Form.useForm();

  // Load assignments and available channels
  useEffect(() => {
    if (open && rule) {
      setLoading(true);
      Promise.all([
        merchantRuleApi.getAssignments(rule.id),
        merchantChannelApi.getMyChannels({ status: 'active', limit: 100 }),
      ])
        .then(([assignmentResult, channelResult]) => {
          setAssignments(assignmentResult.data);
          setChannels(channelResult.data);
        })
        .catch((err) => {
          message.error(err.error || t('common.error'));
        })
        .finally(() => {
          setLoading(false);
        });
    }
  }, [open, rule, message, t]);

  // Get unassigned channels
  const assignedChannelIds = assignments.map((a) => a.merchantSalesChannel.id);
  const unassignedChannels = channels.filter((c) => !assignedChannelIds.includes(c.id));

  const handleAdd = async (values: { channelId: string; priorityOverride?: number }) => {
    if (!rule) return;

    setAddingLoading(true);
    try {
      const result = await merchantRuleApi.assignRule(rule.id, {
        merchantSalesChannelId: values.channelId,
        priorityOverride: values.priorityOverride,
        isActive: true,
      });
      setAssignments([...assignments, result.data]);
      message.success(t('rules.assignmentAdded'));
      setShowAddForm(false);
      form.resetFields();
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setAddingLoading(false);
    }
  };

  const handleToggle = async (assignment: MerchantRuleAssignment) => {
    setToggleLoading(assignment.id);
    try {
      const result = await merchantRuleApi.toggleAssignment(assignment.id);
      setAssignments(
        assignments.map((a) => (a.id === assignment.id ? result.data : a))
      );
      message.success(result.data.isActive ? t('rules.assignmentEnabled') : t('rules.assignmentDisabled'));
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setToggleLoading(null);
    }
  };

  const handleDelete = async (assignment: MerchantRuleAssignment) => {
    setDeleteLoading(assignment.id);
    try {
      await merchantRuleApi.unassignRule(assignment.id);
      setAssignments(assignments.filter((a) => a.id !== assignment.id));
      message.success(t('rules.assignmentRemoved'));
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setDeleteLoading(null);
    }
  };

  const columns = [
    {
      title: t('rules.channel'),
      key: 'channel',
      render: (_: unknown, record: MerchantRuleAssignment) => (
        <div>
          <div className="font-medium">{record.merchantSalesChannel.salesChannel.name}</div>
          <div className="text-xs text-gray-500">{record.merchantSalesChannel.salesChannel.code}</div>
        </div>
      ),
    },
    {
      title: t('rules.effectivePriority'),
      dataIndex: 'effectivePriority',
      width: 120,
      render: (value: number, record: MerchantRuleAssignment) => (
        <Space>
          <span>{value}</span>
          {record.priorityOverride !== null && (
            <Tag color="blue" className="text-xs">
              {t('rules.overridden')}
            </Tag>
          )}
        </Space>
      ),
    },
    {
      title: t('rules.status'),
      dataIndex: 'isActive',
      width: 100,
      render: (isActive: boolean, record: MerchantRuleAssignment) => (
        <Switch
          checked={isActive}
          loading={toggleLoading === record.id}
          onChange={() => handleToggle(record)}
          size="small"
        />
      ),
    },
    {
      title: t('common.actions'),
      key: 'actions',
      width: 80,
      render: (_: unknown, record: MerchantRuleAssignment) => (
        <Popconfirm
          title={t('rules.confirmRemoveAssignment')}
          onConfirm={() => handleDelete(record)}
          okText={t('common.confirm')}
          cancelText={t('common.cancel')}
        >
          <Button
            type="link"
            danger
            size="small"
            loading={deleteLoading === record.id}
          >
            {t('common.delete')}
          </Button>
        </Popconfirm>
      ),
    },
  ];

  return (
    <Drawer
      title={t('rules.manageAssignments')}
      open={open}
      onClose={onClose}
      width={600}
    >
      <div className="space-y-4">
        <div className="flex justify-between items-center">
          <div className="text-gray-600">
            {t('rules.assignmentFor')}: <span className="font-medium">{rule?.name}</span>
          </div>
          {!showAddForm && unassignedChannels.length > 0 && (
            <Button
              type="primary"
              onClick={() => setShowAddForm(true)}
            >
              {t('rules.addAssignment')}
            </Button>
          )}
        </div>

        {showAddForm && (
          <div className="p-4 bg-gray-50 rounded">
            <Form
              form={form}
              layout="inline"
              onFinish={handleAdd}
              className="flex-wrap gap-2"
            >
              <Form.Item
                name="channelId"
                rules={[{ required: true, message: t('rules.channelRequired') }]}
                className="flex-1 min-w-[200px] mb-2"
              >
                <Select
                  placeholder={t('rules.selectChannel')}
                  options={unassignedChannels.map((c) => ({
                    value: c.id,
                    label: `${c.salesChannel.name} (${c.salesChannel.code})`,
                  }))}
                />
              </Form.Item>
              <Form.Item
                name="priorityOverride"
                className="mb-2"
              >
                <InputNumber
                  placeholder={t('rules.priorityOverride')}
                  min={0}
                  max={9999}
                  style={{ width: 150 }}
                />
              </Form.Item>
              <Form.Item className="mb-2">
                <Space>
                  <Button
                    type="primary"
                    htmlType="submit"
                    loading={addingLoading}
                  >
                    {t('common.add')}
                  </Button>
                  <Button onClick={() => {
                    setShowAddForm(false);
                    form.resetFields();
                  }}>
                    {t('common.cancel')}
                  </Button>
                </Space>
              </Form.Item>
            </Form>
          </div>
        )}

        {assignments.length === 0 && !loading ? (
          <Empty description={t('rules.noAssignments')} />
        ) : (
          <Table
            dataSource={assignments}
            columns={columns}
            rowKey="id"
            loading={loading}
            pagination={false}
            size="small"
          />
        )}
      </div>
    </Drawer>
  );
}
