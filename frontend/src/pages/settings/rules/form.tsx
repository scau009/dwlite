import { useEffect, useState, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate, useParams, useSearchParams } from 'react-router';
import { Card, Form, Input, InputNumber, Radio, Switch, App, Row, Col, Collapse, Tag, Tooltip, Space, Typography, Button, Spin } from 'antd';
import { ArrowLeftOutlined, InfoCircleOutlined } from '@ant-design/icons';
import type { RuleVariable, RuleFunction, ValidateResult, TestResult } from '@/lib/merchant-rule-api';
import { merchantRuleApi } from '@/lib/merchant-rule-api';
import { ExpressionEditor } from './components/expression-editor';
import { RuleTestPanel } from './components/rule-test-panel';

const { TextArea } = Input;
const { Paragraph, Text } = Typography;

type RuleType = 'pricing' | 'stock_allocation';

interface FormValues {
  name: string;
  description?: string;
  category: 'markup' | 'discount' | 'ratio' | 'limit';
  expression: string;
  conditionExpression?: string;
  priority: number;
  isActive: boolean;
}

export function RuleFormPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { id } = useParams<{ id: string }>();
  const [searchParams] = useSearchParams();
  const { message } = App.useApp();
  const [form] = Form.useForm<FormValues>();

  const ruleType = (searchParams.get('type') || 'pricing') as RuleType;
  const isEdit = !!id;

  const [loading, setLoading] = useState(false);
  const [pageLoading, setPageLoading] = useState(false);
  const [variables, setVariables] = useState<RuleVariable[]>([]);
  const [functions, setFunctions] = useState<RuleFunction[]>([]);

  const expression = Form.useWatch('expression', form);
  const conditionExpression = Form.useWatch('conditionExpression', form);

  // Load reference data
  useEffect(() => {
    merchantRuleApi.getReference(ruleType)
      .then((ref) => {
        setVariables(ref.variables);
        setFunctions(ref.functions);
      })
      .catch(console.error);
  }, [ruleType]);

  // Load rule detail for edit mode
  useEffect(() => {
    if (isEdit && id) {
      setPageLoading(true);
      merchantRuleApi.getRule(id)
        .then(({ data }) => {
          form.setFieldsValue({
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
          navigate('/channels/rules');
        })
        .finally(() => {
          setPageLoading(false);
        });
    } else {
      form.setFieldsValue({
        priority: 0,
        isActive: true,
        category: ruleType === 'pricing' ? 'markup' : 'ratio',
      });
    }
  }, [isEdit, id, form, message, t, navigate, ruleType]);

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

      if (isEdit && id) {
        await merchantRuleApi.updateRule(id, {
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

      navigate('/channels/rules');
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

  const getRuleTypeLabel = () => {
    return ruleType === 'pricing' ? t('rules.pricingRules') : t('rules.stockAllocationRules');
  };

  if (pageLoading) {
    return (
      <div className="flex justify-center items-center h-64">
        <Spin size="large" />
      </div>
    );
  }

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-4">
          <Button
            icon={<ArrowLeftOutlined />}
            onClick={() => navigate('/channels/rules')}
          >
            {t('common.back')}
          </Button>
          <div>
            <h1 className="text-xl font-semibold m-0">
              {isEdit ? t('rules.editRule') : t('rules.addRule')}
            </h1>
            <span className="text-gray-500 text-sm">{getRuleTypeLabel()}</span>
          </div>
        </div>
        <Space>
          <Button onClick={() => navigate('/channels/rules')}>
            {t('common.cancel')}
          </Button>
          <Button type="primary" loading={loading} onClick={handleSubmit}>
            {t('common.save')}
          </Button>
        </Space>
      </div>

      {/* Form */}
      <Card>
        <Form
          form={form}
          layout="vertical"
        >
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

          {/* 分类选项展开显示 */}
          <Form.Item
            name="category"
            label={t('rules.category')}
            rules={[{ required: true, message: t('rules.categoryRequired') }]}
          >
            <Radio.Group>
              {getCategoryOptions().map((opt) => (
                <Radio.Button key={opt.value} value={opt.value}>
                  {opt.label}
                </Radio.Button>
              ))}
            </Radio.Group>
          </Form.Item>

          {/* Expression Section */}
          <div className="p-4 bg-gray-50 rounded-lg mb-4">
            <Row gutter={16}>
              <Col span={12}>
                <Form.Item
                  name="expression"
                  label={t('rules.expression')}
                  rules={[{ required: true, message: t('rules.expressionRequired') }]}
                  className="mb-3"
                >
                  <ExpressionEditor
                    variables={variables}
                    functions={functions}
                    onValidate={handleValidate}
                    placeholder={ruleType === 'pricing' ? 'markup(cost, 0.3)' : 'ratio(availableStock, 0.8)'}
                    showReference={false}
                  />
                </Form.Item>
              </Col>
              <Col span={12}>
                <Form.Item
                  name="conditionExpression"
                  label={t('rules.conditionExpression')}
                  tooltip={t('rules.conditionExpressionTooltip')}
                  className="mb-3"
                >
                  <ExpressionEditor
                    variables={variables}
                    functions={functions}
                    onValidate={handleValidate}
                    placeholder="channelCode == 'NIKE'"
                    showReference={false}
                  />
                </Form.Item>
              </Col>
            </Row>

            {/* Reference Section */}
            <Collapse
              defaultActiveKey={['variables']}
              items={[
                {
                  key: 'variables',
                  label: (
                    <Space>
                      <InfoCircleOutlined />
                      {t('rules.availableVariables')}
                    </Space>
                  ),
                  children: (
                    <div className="flex flex-wrap gap-2">
                      {variables.map((v) => (
                        <Tooltip key={v.name} title={`${t(`rules.var_${v.name}`, { defaultValue: v.description })} (${v.type})`}>
                          <Tag className="cursor-default">{v.name}</Tag>
                        </Tooltip>
                      ))}
                    </div>
                  ),
                },
                {
                  key: 'functions',
                  label: (
                    <Space>
                      <InfoCircleOutlined />
                      {t('rules.availableFunctions')}
                    </Space>
                  ),
                  children: (
                    <div className="space-y-2">
                      {functions.map((f) => (
                        <div key={f.name} className="p-2 bg-white rounded">
                          <div className="flex items-center gap-2">
                            <Tag color="blue">{f.signature}</Tag>
                          </div>
                          <Paragraph className="text-xs text-gray-500 mt-1 mb-0">
                            {t(`rules.func_${f.name}`, { defaultValue: f.description })}
                          </Paragraph>
                          <Text code className="text-xs">
                            {t('rules.example')}: {f.example}
                          </Text>
                        </div>
                      ))}
                    </div>
                  ),
                },
              ]}
              size="small"
              ghost
            />

            {/* Test Panel */}
            <div className="mt-4">
              <RuleTestPanel
                expression={expression || ''}
                conditionExpression={conditionExpression}
                type={ruleType}
                variables={variables}
                onTest={handleTest}
              />
            </div>
          </div>

          <Row gutter={16}>
            <Col span={12}>
              <Form.Item
                name="priority"
                label={t('rules.priority')}
                tooltip={t('rules.priorityTooltip')}
              >
                <InputNumber min={0} max={9999} style={{ width: '100%' }} />
              </Form.Item>
            </Col>
            <Col span={12}>
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
            </Col>
          </Row>
        </Form>
      </Card>
    </div>
  );
}
