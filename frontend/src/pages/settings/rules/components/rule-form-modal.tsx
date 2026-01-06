import { useEffect, useState, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal, Form, Input, InputNumber, Select, Switch, App, Tabs } from 'antd';
import type { MerchantRule, RuleVariable, RuleFunction, ValidateResult, TestResult } from '@/lib/merchant-rule-api';
import { merchantRuleApi } from '@/lib/merchant-rule-api';
import { ExpressionEditor } from './expression-editor';
import { RuleTestPanel } from './rule-test-panel';

const { TextArea } = Input;

interface RuleFormModalProps {
  open: boolean;
  rule: MerchantRule | null;
  ruleType: 'pricing' | 'stock_allocation';
  onClose: () => void;
  onSuccess: () => void;
}

interface FormValues {
  code: string;
  name: string;
  description?: string;
  category: 'markup' | 'discount' | 'ratio' | 'limit';
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
      merchantRuleApi.getReference(ruleType)
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
        merchantRuleApi.getRule(rule.id)
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
          category: ruleType === 'pricing' ? 'markup' : 'ratio',
        });
      }
      setActiveTab('form');
    }
  }, [open, rule, form, message, t, ruleType]);

  const handleValidate = useCallback(async (expr: string): Promise<ValidateResult> => {
    return merchantRuleApi.validateExpression({
      expression: expr,
      type: ruleType,
    });
  }, [ruleType]);

  const handleTest = useCallback(async (testContext: Record<string, unknown>): Promise<TestResult> => {
    return merchantRuleApi.testRule({
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
        await merchantRuleApi.updateRule(rule!.id, {
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
        await merchantRuleApi.createRule({
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
    if (ruleType === 'pricing') {
      return [
        { value: 'markup', label: t('rules.categoryMarkup') },
        { value: 'discount', label: t('rules.categoryDiscount') },
      ];
    }
    return [
      { value: 'ratio', label: t('rules.categoryRatio') },
      { value: 'limit', label: t('rules.categoryLimit') },
    ];
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
          disabled={detailLoading}
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
              placeholder={ruleType === 'pricing' ? 'markup(cost, 0.3)' : 'ratio(availableStock, 0.8)'}
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
              placeholder="channelCode == 'NIKE'"
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
    >
      <Tabs
        activeKey={activeTab}
        onChange={setActiveTab}
        items={tabItems}
      />
    </Modal>
  );
}
