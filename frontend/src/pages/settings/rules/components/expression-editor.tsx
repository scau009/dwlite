import { useState, useEffect, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { Input, Card, Typography, Collapse, Tag, Tooltip, Space, Alert } from 'antd';
import { InfoCircleOutlined, CheckCircleOutlined, CloseCircleOutlined } from '@ant-design/icons';
import type { RuleVariable, RuleFunction, ValidateResult } from '@/lib/merchant-rule-api';

const { TextArea } = Input;
const { Text, Paragraph } = Typography;

interface ExpressionEditorProps {
  value?: string;
  onChange?: (value: string) => void;
  variables: RuleVariable[];
  functions: RuleFunction[];
  onValidate?: (expression: string) => Promise<ValidateResult>;
  placeholder?: string;
  disabled?: boolean;
}

export function ExpressionEditor({
  value = '',
  onChange,
  variables,
  functions,
  onValidate,
  placeholder,
  disabled,
}: ExpressionEditorProps) {
  const { t } = useTranslation();
  const [validationResult, setValidationResult] = useState<ValidateResult | null>(null);
  const [validating, setValidating] = useState(false);

  const validateExpression = useCallback(async (expr: string) => {
    if (!expr.trim() || !onValidate) {
      setValidationResult(null);
      return;
    }

    setValidating(true);
    try {
      const result = await onValidate(expr);
      setValidationResult(result);
    } catch {
      setValidationResult({ valid: false, error: t('rules.validationFailed') });
    } finally {
      setValidating(false);
    }
  }, [onValidate, t]);

  useEffect(() => {
    const timer = setTimeout(() => {
      validateExpression(value);
    }, 500);
    return () => clearTimeout(timer);
  }, [value, validateExpression]);

  const handleChange = (e: React.ChangeEvent<HTMLTextAreaElement>) => {
    onChange?.(e.target.value);
  };

  const insertText = (text: string) => {
    const newValue = value + text;
    onChange?.(newValue);
  };

  const variableItems = [
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
            <Tooltip key={v.name} title={`${v.description} (${v.type})`}>
              <Tag
                className="cursor-pointer hover:bg-blue-100"
                onClick={() => insertText(v.name)}
              >
                {v.name}
              </Tag>
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
            <div key={f.name} className="p-2 bg-gray-50 rounded">
              <div className="flex items-center gap-2">
                <Tag
                  color="blue"
                  className="cursor-pointer hover:opacity-80"
                  onClick={() => insertText(f.name + '(')}
                >
                  {f.signature}
                </Tag>
              </div>
              <Paragraph className="text-xs text-gray-500 mt-1 mb-0">
                {f.description}
              </Paragraph>
              <Text code className="text-xs">
                {t('rules.example')}: {f.example}
              </Text>
            </div>
          ))}
        </div>
      ),
    },
  ];

  return (
    <Card size="small" className="expression-editor">
      <div className="space-y-3">
        <div>
          <TextArea
            value={value}
            onChange={handleChange}
            placeholder={placeholder || t('rules.expressionPlaceholder')}
            disabled={disabled}
            autoSize={{ minRows: 3, maxRows: 8 }}
            className="font-mono"
          />
          {validationResult && (
            <div className="mt-2">
              {validationResult.valid ? (
                <Alert
                  message={t('rules.expressionValid')}
                  type="success"
                  showIcon
                  icon={<CheckCircleOutlined />}
                />
              ) : (
                <Alert
                  message={validationResult.error}
                  type="error"
                  showIcon
                  icon={<CloseCircleOutlined />}
                />
              )}
            </div>
          )}
          {validating && (
            <Text type="secondary" className="text-xs">
              {t('rules.validating')}...
            </Text>
          )}
        </div>

        <Collapse
          items={variableItems}
          size="small"
          ghost
          className="bg-gray-50 rounded"
        />
      </div>
    </Card>
  );
}
