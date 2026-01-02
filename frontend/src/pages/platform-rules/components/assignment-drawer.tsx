import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { Drawer, Table, Button, Switch, App, Space, Tag, Empty, Popconfirm, Select, Form, InputNumber, Radio } from 'antd';
import { PlusOutlined, DeleteOutlined } from '@ant-design/icons';
import type { PlatformRule, PlatformRuleAssignment } from '@/lib/platform-rule-api';
import { platformRuleApi } from '@/lib/platform-rule-api';
import { merchantApi, type Merchant } from '@/lib/merchant-api';

interface AssignmentDrawerProps {
  open: boolean;
  rule: PlatformRule | null;
  onClose: () => void;
}

export function AssignmentDrawer({ open, rule, onClose }: AssignmentDrawerProps) {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [loading, setLoading] = useState(false);
  const [assignments, setAssignments] = useState<PlatformRuleAssignment[]>([]);
  const [merchants, setMerchants] = useState<Merchant[]>([]);
  const [addingLoading, setAddingLoading] = useState(false);
  const [toggleLoading, setToggleLoading] = useState<string | null>(null);
  const [deleteLoading, setDeleteLoading] = useState<string | null>(null);
  const [showAddForm, setShowAddForm] = useState(false);
  const [form] = Form.useForm();

  const scopeType = Form.useWatch('scopeType', form);

  // Load assignments and merchants
  useEffect(() => {
    if (open && rule) {
      setLoading(true);
      Promise.all([
        platformRuleApi.getAssignments(rule.id),
        merchantApi.getMerchants({ limit: 100, status: 'active' }),
      ])
        .then(([assignmentResult, merchantResult]) => {
          setAssignments(assignmentResult.data);
          setMerchants(merchantResult.data);
        })
        .catch((err) => {
          message.error(err.error || t('common.error'));
        })
        .finally(() => {
          setLoading(false);
        });
    }
  }, [open, rule, message, t]);

  // Get assigned merchant IDs
  const assignedMerchantIds = assignments
    .filter((a) => a.scopeType === 'merchant')
    .map((a) => a.scopeId);
  const unassignedMerchants = merchants.filter((m) => !assignedMerchantIds.includes(m.id));

  const handleAdd = async (values: { scopeType: 'merchant' | 'channel_product'; scopeId: string; priorityOverride?: number }) => {
    if (!rule) return;

    setAddingLoading(true);
    try {
      const result = await platformRuleApi.assignRule(rule.id, {
        scopeType: values.scopeType,
        scopeId: values.scopeId,
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

  const handleToggle = async (assignment: PlatformRuleAssignment) => {
    setToggleLoading(assignment.id);
    try {
      const result = await platformRuleApi.toggleAssignment(assignment.id);
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

  const handleDelete = async (assignment: PlatformRuleAssignment) => {
    setDeleteLoading(assignment.id);
    try {
      await platformRuleApi.unassignRule(assignment.id);
      setAssignments(assignments.filter((a) => a.id !== assignment.id));
      message.success(t('rules.assignmentRemoved'));
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setDeleteLoading(null);
    }
  };

  const getScopeTypeColor = (type: string) => {
    return type === 'merchant' ? 'blue' : 'purple';
  };

  const columns = [
    {
      title: t('rules.scopeType'),
      dataIndex: 'scopeType',
      width: 120,
      render: (type: string) => (
        <Tag color={getScopeTypeColor(type)}>
          {type === 'merchant' ? t('rules.scopeMerchant') : t('rules.scopeChannelProduct')}
        </Tag>
      ),
    },
    {
      title: t('rules.scopeName'),
      dataIndex: 'scopeName',
      ellipsis: true,
    },
    {
      title: t('rules.effectivePriority'),
      dataIndex: 'effectivePriority',
      width: 120,
      render: (value: number, record: PlatformRuleAssignment) => (
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
      render: (isActive: boolean, record: PlatformRuleAssignment) => (
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
      render: (_: unknown, record: PlatformRuleAssignment) => (
        <Popconfirm
          title={t('rules.confirmRemoveAssignment')}
          onConfirm={() => handleDelete(record)}
          okText={t('common.confirm')}
          cancelText={t('common.cancel')}
        >
          <Button
            type="text"
            danger
            size="small"
            icon={<DeleteOutlined />}
            loading={deleteLoading === record.id}
          />
        </Popconfirm>
      ),
    },
  ];

  return (
    <Drawer
      title={t('rules.manageAssignments')}
      open={open}
      onClose={onClose}
      width={700}
    >
      <div className="space-y-4">
        <div className="flex justify-between items-center">
          <div className="text-gray-600">
            {t('rules.assignmentFor')}: <span className="font-medium">{rule?.name}</span>
          </div>
          {!showAddForm && (
            <Button
              type="primary"
              icon={<PlusOutlined />}
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
              layout="vertical"
              onFinish={handleAdd}
              initialValues={{ scopeType: 'merchant' }}
            >
              <Form.Item
                name="scopeType"
                label={t('rules.scopeType')}
                rules={[{ required: true }]}
              >
                <Radio.Group>
                  <Radio.Button value="merchant">{t('rules.scopeMerchant')}</Radio.Button>
                  <Radio.Button value="channel_product">{t('rules.scopeChannelProduct')}</Radio.Button>
                </Radio.Group>
              </Form.Item>

              {scopeType === 'merchant' && (
                <Form.Item
                  name="scopeId"
                  label={t('rules.selectMerchant')}
                  rules={[{ required: true, message: t('rules.merchantRequired') }]}
                >
                  <Select
                    placeholder={t('rules.selectMerchant')}
                    showSearch
                    optionFilterProp="label"
                    options={unassignedMerchants.map((m) => ({
                      value: m.id,
                      label: m.companyName,
                    }))}
                  />
                </Form.Item>
              )}

              {scopeType === 'channel_product' && (
                <Form.Item
                  name="scopeId"
                  label={t('rules.channelProductId')}
                  rules={[
                    { required: true, message: t('rules.channelProductIdRequired') },
                    { len: 26, message: t('rules.channelProductIdInvalid') },
                  ]}
                >
                  <Input placeholder={t('rules.channelProductIdPlaceholder')} />
                </Form.Item>
              )}

              <Form.Item
                name="priorityOverride"
                label={t('rules.priorityOverride')}
              >
                <InputNumber
                  placeholder={t('rules.priorityOverridePlaceholder')}
                  min={0}
                  max={9999}
                  style={{ width: '100%' }}
                />
              </Form.Item>

              <Form.Item className="mb-0">
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
