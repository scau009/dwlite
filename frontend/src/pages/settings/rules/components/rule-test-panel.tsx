import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Card, Form, InputNumber, Button, Space, Alert, Descriptions, Typography } from 'antd';
import { PlayCircleOutlined, CheckCircleOutlined, CloseCircleOutlined } from '@ant-design/icons';
import type { TestResult, RuleVariable } from '@/lib/merchant-rule-api';

const { Text } = Typography;

interface RuleTestPanelProps {
  expression: string;
  conditionExpression?: string;
  type: 'pricing' | 'stock_allocation';
  variables: RuleVariable[];
  onTest: (testContext: Record<string, unknown>) => Promise<TestResult>;
}

export function RuleTestPanel({
  expression,
  conditionExpression,
  type,
  variables,
  onTest,
}: RuleTestPanelProps) {
  const { t } = useTranslation();
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);
  const [result, setResult] = useState<TestResult | null>(null);

  const handleTest = async () => {
    if (!expression.trim()) {
      return;
    }

    setLoading(true);
    try {
      const values = form.getFieldsValue();
      const testContext: Record<string, unknown> = {};

      // Build test context from form values
      variables.forEach((v) => {
        if (values[v.name] !== undefined && values[v.name] !== null) {
          testContext[v.name] = values[v.name];
        }
      });

      const testResult = await onTest(testContext);
      setResult(testResult);
    } catch (error) {
      const err = error as { error?: string };
      setResult({
        conditionResult: false,
        result: null,
        context: {},
        error: err.error || t('rules.testFailed'),
      });
    } finally {
      setLoading(false);
    }
  };

  // Get numeric variables for form
  const numericVariables = variables.filter(
    (v) => v.type === 'float' || v.type === 'int' || v.type === 'number'
  );

  // Default values based on type
  const getDefaultValue = (name: string): number => {
    if (name === 'value') return type === 'pricing' ? 100 : 50;
    if (name === 'cost') return 80;
    if (name === 'referencePrice') return 120;
    if (name === 'availableStock') return 100;
    return 0;
  };

  return (
    <Card
      size="small"
      title={
        <Space>
          <PlayCircleOutlined />
          {t('rules.testExecution')}
        </Space>
      }
      className="rule-test-panel"
    >
      <div className="space-y-4">
        <Form
          form={form}
          layout="inline"
          className="flex-wrap gap-2"
          initialValues={Object.fromEntries(
            numericVariables.map((v) => [v.name, getDefaultValue(v.name)])
          )}
        >
          {numericVariables.map((v) => (
            <Form.Item
              key={v.name}
              name={v.name}
              label={v.name}
              tooltip={v.description}
              className="mb-2"
            >
              <InputNumber
                placeholder={v.name}
                style={{ width: 120 }}
                precision={v.type === 'int' ? 0 : 2}
              />
            </Form.Item>
          ))}
          <Form.Item className="mb-2">
            <Button
              type="primary"
              icon={<PlayCircleOutlined />}
              onClick={handleTest}
              loading={loading}
              disabled={!expression.trim()}
            >
              {t('rules.runTest')}
            </Button>
          </Form.Item>
        </Form>

        {result && (
          <div className="space-y-2">
            {result.error ? (
              <Alert
                message={t('rules.testError')}
                description={result.error}
                type="error"
                showIcon
                icon={<CloseCircleOutlined />}
              />
            ) : (
              <>
                {conditionExpression && (
                  <Alert
                    message={t('rules.conditionResult')}
                    description={
                      <Space>
                        {result.conditionResult ? (
                          <>
                            <CheckCircleOutlined className="text-green-500" />
                            <Text type="success">{t('rules.conditionPassed')}</Text>
                          </>
                        ) : (
                          <>
                            <CloseCircleOutlined className="text-red-500" />
                            <Text type="danger">{t('rules.conditionFailed')}</Text>
                          </>
                        )}
                      </Space>
                    }
                    type={result.conditionResult ? 'success' : 'warning'}
                    showIcon
                  />
                )}
                <Alert
                  message={t('rules.executionResult')}
                  description={
                    <Descriptions size="small" column={1}>
                      <Descriptions.Item label={t('rules.output')}>
                        <Text code className="text-lg">
                          {String(result.result)}
                        </Text>
                      </Descriptions.Item>
                    </Descriptions>
                  }
                  type="success"
                  showIcon
                  icon={<CheckCircleOutlined />}
                />
              </>
            )}
          </div>
        )}
      </div>
    </Card>
  );
}
