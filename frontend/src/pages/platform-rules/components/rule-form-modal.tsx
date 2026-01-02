import { useEffect, useState, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal, Form, Input, InputNumber, Select, Switch, App, Tabs } from 'antd';
import type { PlatformRule, RuleVariable, RuleFunction, ValidateResult, TestResult } from '@/lib/platform-rule-api';
import { platformRuleApi } from '@/lib/platform-rule-api';
import { ExpressionEditor } from './expression-editor';
import { RuleTestPanel } from './rule-test-panel';

const { TextArea } = Input;

type PlatformRuleType = 'pricing' | 'stock_priority' | 'settlement_fee';

interface RuleFormModalProps {
  open: boolean;
  rule: PlatformRule | null;
  ruleType: PlatformRuleType;
  onClose: () => void;
  onSuccess: () => void;
}

interface FormValues {
  code: string;
  name: string;
  description?: string;
  category: 'markup' | 'discount' | 'priority' | 'fee_rate';
  expression: string;
  conditionExpression?: string;
  priority: number;
  isActive: boolean;
}

export function RuleFormModal({ open, rule, ruleType, onClose, onSuccess }: RuleFormModalProps) {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [form] = Form.useForm<FormValues>();
  const [loading, setLoading] = useState(false);
  const [detailLoading, setDetailLoading] = useState(false);
  const [variables, setVariables] = useState<RuleVariable[]>([]);
  const [functions, setFunctions] = useState<RuleFunction[]>([]);
  const [activeTab, setActiveTab] = useState('form');

  const isEdit = !!rule;
  const expression = Form.useWatch('expression', form);
  const conditionExpression = Form.useWatch('conditionExpression', form);

  // Load reference data
  useEffect(() => {
    if (open) {
      platformRuleApi.getReference(ruleType)
        .then((ref) => {
          setVariables(ref.variables);
          setFunctions(ref.functions);
        })
        .catch(console.error);
    }
  }, [open, ruleType]);

  // Load rule detail or reset form
  useEffect(() => {
    if (open) {
      if (rule) {
        setDetailLoading(true);
        platformRuleApi.getRule(rule.id)
          .then(({ data }) => {
            form.setFieldsValue({
              code: data.code,
              name: data.name,
              description: data.description || undefined,
              category: data.category,
              expression: data.expression,
              conditionExpression: data.conditionExpression || undefined,
              priority: data.priority,
              isActive: data.isActive,
            });
          })
          .catch((err) => {
            message.error(err.error || t('common.error'));
          })
          .finally(() => {
            setDetailLoading(false);
          });
      } else {
        form.resetFields();
        form.setFieldsValue({
          priority: 0,
          isActive: true,
          category: getDefaultCategory(ruleType),
        });
      }
      setActiveTab('form');
    }
  }, [open, rule, form, message, t, ruleType]);

  const getDefaultCategory = (type: PlatformRuleType): string => {
    switch (type) {
      case 'pricing':
        return 'markup';
      case 'stock_priority':
        return 'priority';
      case 'settlement_fee':
        return 'fee_rate';
      default:
        return 'markup';
    }
  };

  const handleValidate = useCallback(async (expr: string): Promise<ValidateResult> => {
    return platformRuleApi.validateExpression({
      expression: expr,
      type: ruleType,
    });
  }, [ruleType]);

  const handleTest = useCallback(async (testContext: Record<string, unknown>): Promise<TestResult> => {
    return platformRuleApi.testRule({
      expression: expression || '',
      conditionExpression: conditionExpression || undefined,
      type: ruleType,
      testContext,
    });
  }, [expression, conditionExpression, ruleType]);

  const handleSubmit = async () => {
    try {
      const values = await form.validateFields();
      setLoading(true);

      if (isEdit) {
        await platformRuleApi.updateRule(rule!.id, {
          name: values.name,
          description: values.description,
          category: values.category,
          expression: values.expression,
          conditionExpression: values.conditionExpression,
          priority: values.priority,
          isActive: values.isActive,
        });
        message.success(t('rules.updated'));
      } else {
        await platformRuleApi.createRule({
          code: values.code,
          name: values.name,
          description: values.description,
          type: ruleType,
          category: values.category,
          expression: values.expression,
          conditionExpression: values.conditionExpression,
          priority: values.priority,
          isActive: values.isActive,
        });
        message.success(t('rules.created'));
      }

      onSuccess();
    } catch (error) {
      if (error && typeof error === 'object' && 'errorFields' in error) {
        return;
      }
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setLoading(false);
    }
  };

  const getCategoryOptions = () => {
    switch (ruleType) {
      case 'pricing':
        return [
          { value: 'markup', label: t('rules.categoryMarkup') },
          { value: 'discount', label: t('rules.categoryDiscount') },
        ];
      case 'stock_priority':
        return [
          { value: 'priority', label: t('rules.categoryPriority') },
        ];
      case 'settlement_fee':
        return [
          { value: 'fee_rate', label: t('rules.categoryFeeRate') },
        ];
      default:
        return [];
    }
  };

  const getExpressionPlaceholder = () => {
    switch (ruleType) {
      case 'pricing':
        return 'markup(merchantPrice, 0.1)';
      case 'stock_priority':
        return 'value + 10';
      case 'settlement_fee':
        return 'orderAmount * 0.05';
      default:
        return '';
    }
  };

  const tabItems = [
    {
      key: 'form',
      label: t('rules.basicInfo'),
      children: (
        <Form
          form={form}
          layout="vertical"
          className="mt-4"
          disabled={detailLoading || (isEdit && rule?.isSystem)}
        >
          <Form.Item
            name="code"
            label={t('rules.code')}
            rules={[
              { required: true, message: t('rules.codeRequired') },
              { max: 100, message: t('rules.codeMaxLength') },
              { pattern: /^[a-z][a-z0-9_]*$/, message: t('rules.codeInvalid') },
            ]}
          >
            <Input
              placeholder={t('rules.codePlaceholder')}
              disabled={isEdit}
            />
          </Form.Item>

          <Form.Item
            name="name"
            label={t('rules.name')}
            rules={[
              { required: true, message: t('rules.nameRequired') },
              { max: 200, message: t('rules.nameMaxLength') },
            ]}
          >
            <Input placeholder={t('rules.namePlaceholder')} />
          </Form.Item>

          <Form.Item
            name="description"
            label={t('rules.description')}
            rules={[
              { max: 1000, message: t('rules.descriptionMaxLength') },
            ]}
          >
            <TextArea
              placeholder={t('rules.descriptionPlaceholder')}
              autoSize={{ minRows: 2, maxRows: 4 }}
            />
          </Form.Item>

          <Form.Item
            name="category"
            label={t('rules.category')}
            rules={[{ required: true, message: t('rules.categoryRequired') }]}
          >
            <Select options={getCategoryOptions()} />
          </Form.Item>

          <Form.Item
            name="expression"
            label={t('rules.expression')}
            rules={[{ required: true, message: t('rules.expressionRequired') }]}
          >
            <ExpressionEditor
              variables={variables}
              functions={functions}
              onValidate={handleValidate}
              placeholder={getExpressionPlaceholder()}
            />
          </Form.Item>

          <Form.Item
            name="conditionExpression"
            label={t('rules.conditionExpression')}
            tooltip={t('rules.conditionExpressionTooltip')}
          >
            <ExpressionEditor
              variables={variables}
              functions={functions}
              onValidate={handleValidate}
              placeholder="brand == 'Nike'"
            />
          </Form.Item>

          <div className="flex gap-4">
            <Form.Item
              name="priority"
              label={t('rules.priority')}
              tooltip={t('rules.priorityTooltip')}
              className="flex-1"
            >
              <InputNumber min={0} max={9999} style={{ width: '100%' }} />
            </Form.Item>

            <Form.Item
              name="isActive"
              label={t('rules.status')}
              valuePropName="checked"
            >
              <Switch
                checkedChildren={t('rules.statusActive')}
                unCheckedChildren={t('rules.statusInactive')}
              />
            </Form.Item>
          </div>
        </Form>
      ),
    },
    {
      key: 'test',
      label: t('rules.testTab'),
      disabled: !expression,
      children: (
        <div className="mt-4">
          <RuleTestPanel
            expression={expression || ''}
            conditionExpression={conditionExpression}
            type={ruleType}
            variables={variables}
            onTest={handleTest}
          />
        </div>
      ),
    },
  ];

  return (
    <Modal
      title={isEdit ? t('rules.editRule') : t('rules.addRule')}
      open={open}
      onCancel={onClose}
      onOk={handleSubmit}
      confirmLoading={loading}
      okText={t('common.save')}
      cancelText={t('common.cancel')}
      destroyOnClose
      width={700}
      okButtonProps={{ disabled: isEdit && rule?.isSystem }}
    >
      <Tabs
        activeKey={activeTab}
        onChange={setActiveTab}
        items={tabItems}
      />
    </Modal>
  );
}
