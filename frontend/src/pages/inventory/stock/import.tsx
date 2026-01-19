import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router';
import { useTranslation } from 'react-i18next';
import {
  Card,
  Form,
  Select,
  Button,
  Space,
  App,
  Table,
  Empty,
  Upload,
  Radio,
  Tag,
  Alert,
  Typography,
  Result,
} from 'antd';
import {
  ArrowLeftOutlined,
  UploadOutlined,
  DownloadOutlined,
  CheckCircleOutlined,
  CloseCircleOutlined,
  ExclamationCircleOutlined,
} from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';
import type { UploadFile, RcFile } from 'antd/es/upload/interface';

import {
  merchantInventoryApi,
  type MerchantWarehouse,
  type ImportInventoryPreviewItem,
  type ConflictStrategy,
} from '@/lib/inbound-api';

const { Text } = Typography;

type ImportStep = 'upload' | 'preview' | 'result';

interface ImportResult {
  imported: number;
  skipped: number;
  errors: Record<string, string>;
}

export function ImportInventoryPage() {
  const navigate = useNavigate();
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [form] = Form.useForm();

  const [step, setStep] = useState<ImportStep>('upload');
  const [warehouses, setWarehouses] = useState<MerchantWarehouse[]>([]);
  const [warehousesLoading, setWarehousesLoading] = useState(true);
  const [fileList, setFileList] = useState<UploadFile[]>([]);
  const [previewItems, setPreviewItems] = useState<ImportInventoryPreviewItem[]>([]);
  const [previewSummary, setPreviewSummary] = useState({ total: 0, valid: 0, invalid: 0 });
  const [previewLoading, setPreviewLoading] = useState(false);
  const [importing, setImporting] = useState(false);
  const [importResult, setImportResult] = useState<ImportResult | null>(null);
  const [conflictStrategy, setConflictStrategy] = useState<ConflictStrategy>('skip');

  // Load merchant warehouses
  useEffect(() => {
    const loadWarehouses = async () => {
      setWarehousesLoading(true);
      try {
        const result = await merchantInventoryApi.getMerchantWarehouses();
        setWarehouses(result.data);
        if (result.data.length === 1) {
          form.setFieldValue('warehouseId', result.data[0].id);
        }
      } catch {
        message.error(t('common.error'));
      } finally {
        setWarehousesLoading(false);
      }
    };
    loadWarehouses();
  }, [form, message, t]);

  // Download template
  const handleDownloadTemplate = async () => {
    try {
      const blob = await merchantInventoryApi.downloadImportTemplate();
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'inventory_import_template.xlsx';
      document.body.appendChild(a);
      a.click();
      window.URL.revokeObjectURL(url);
      document.body.removeChild(a);
    } catch {
      message.error(t('common.error'));
    }
  };

  // Handle file upload
  const handleBeforeUpload = (file: RcFile) => {
    const isExcel =
      file.type === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' ||
      file.type === 'application/vnd.ms-excel' ||
      file.name.endsWith('.xlsx') ||
      file.name.endsWith('.xls');

    if (!isExcel) {
      message.error(t('merchantStock.onlyExcelFiles'));
      return false;
    }
    setFileList([file]);
    return false;
  };

  // Handle preview
  const handlePreview = async () => {
    try {
      const values = await form.validateFields();
      if (fileList.length === 0) {
        message.error(t('merchantStock.pleaseUploadFile'));
        return;
      }

      setPreviewLoading(true);
      const file = fileList[0] as RcFile;
      const result = await merchantInventoryApi.previewImport(file, values.warehouseId);

      setPreviewItems(result.data.items);
      setPreviewSummary(result.data.summary);
      setStep('preview');
    } catch (error) {
      if (error && typeof error === 'object' && 'errorFields' in error) {
        return;
      }
      const err = error as Error;
      message.error(err.message || t('common.error'));
    } finally {
      setPreviewLoading(false);
    }
  };

  // Handle confirm import
  const handleConfirmImport = async () => {
    const warehouseId = form.getFieldValue('warehouseId');
    const validItems = previewItems.filter(item => item.isValid && item.productSkuId);

    if (validItems.length === 0) {
      message.error(t('merchantStock.noValidItems'));
      return;
    }

    setImporting(true);
    try {
      const result = await merchantInventoryApi.confirmImport({
        warehouseId,
        conflictStrategy,
        items: validItems.map(item => ({
          skuCode: item.skuCode,
          productSkuId: item.productSkuId!,
          quantity: item.quantity,
          unitCost: item.unitCost || undefined,
          costCurrency: item.costCurrency || 'CNY',
        })),
      });

      setImportResult(result.data);
      setStep('result');
      message.success(t('merchantStock.importSuccess'));
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setImporting(false);
    }
  };

  // Reset to start
  const handleReset = () => {
    setStep('upload');
    setFileList([]);
    setPreviewItems([]);
    setPreviewSummary({ total: 0, valid: 0, invalid: 0 });
    setImportResult(null);
    setConflictStrategy('skip');
  };

  // Preview table columns
  const previewColumns: ColumnsType<ImportInventoryPreviewItem> = [
    {
      title: t('merchantStock.row'),
      dataIndex: 'rowNumber',
      width: 60,
    },
    {
      title: t('merchantStock.skuCode'),
      dataIndex: 'skuCode',
      width: 150,
    },
    {
      title: t('merchantStock.productName'),
      dataIndex: 'productName',
      width: 200,
      render: (text) => text || <Text type="secondary">-</Text>,
    },
    {
      title: t('merchantStock.skuName'),
      dataIndex: 'skuName',
      width: 100,
      render: (text) => text || <Text type="secondary">-</Text>,
    },
    {
      title: t('merchantStock.quantity'),
      dataIndex: 'quantity',
      width: 80,
      align: 'right',
    },
    {
      title: t('merchantStock.unitCost'),
      dataIndex: 'unitCost',
      width: 100,
      align: 'right',
      render: (text, record) =>
        text ? `${text} ${record.costCurrency}` : <Text type="secondary">-</Text>,
    },
    {
      title: t('merchantStock.status'),
      key: 'status',
      width: 150,
      render: (_, record) => {
        if (!record.isValid) {
          return (
            <Space>
              <CloseCircleOutlined className="text-red-500" />
              <Text type="danger">{record.errorMessage}</Text>
            </Space>
          );
        }
        if (record.exists) {
          return (
            <Space>
              <ExclamationCircleOutlined className="text-orange-500" />
              <Tag color="orange">{t('merchantStock.existsInWarehouse')}</Tag>
            </Space>
          );
        }
        return (
          <Space>
            <CheckCircleOutlined className="text-green-500" />
            <Tag color="green">{t('merchantStock.canImport')}</Tag>
          </Space>
        );
      },
    },
  ];

  // No warehouse warning
  if (!warehousesLoading && warehouses.length === 0) {
    return (
      <div className="space-y-4">
        <Button icon={<ArrowLeftOutlined />} onClick={() => navigate('/inventory/stock')}>
          {t('common.back')}
        </Button>
        <Empty description={t('merchantStock.noMerchantWarehouse')}>
          <Button type="primary" onClick={() => navigate('/inventory/warehouses')}>
            {t('merchantStock.createWarehouse')}
          </Button>
        </Empty>
      </div>
    );
  }

  // Result view
  if (step === 'result' && importResult) {
    const hasErrors = Object.keys(importResult.errors).length > 0;

    return (
      <div className="space-y-4">
        <Button icon={<ArrowLeftOutlined />} onClick={() => navigate('/inventory/stock')}>
          {t('common.back')}
        </Button>

        <Card>
          <Result
            status={hasErrors ? 'warning' : 'success'}
            title={t('merchantStock.importCompleted')}
            subTitle={
              <div className="space-y-2 mt-4">
                <div>
                  <CheckCircleOutlined className="text-green-500 mr-2" />
                  {t('merchantStock.importedCount', { count: importResult.imported })}
                </div>
                {importResult.skipped > 0 && (
                  <div>
                    <ExclamationCircleOutlined className="text-orange-500 mr-2" />
                    {t('merchantStock.skippedCount', { count: importResult.skipped })}
                  </div>
                )}
                {hasErrors && (
                  <div>
                    <CloseCircleOutlined className="text-red-500 mr-2" />
                    {t('merchantStock.errorsCount', { count: Object.keys(importResult.errors).length })}
                  </div>
                )}
              </div>
            }
            extra={[
              <Button key="list" type="primary" onClick={() => navigate('/inventory/stock')}>
                {t('merchantStock.viewInventory')}
              </Button>,
              <Button key="again" onClick={handleReset}>
                {t('merchantStock.importAgain')}
              </Button>,
            ]}
          />
        </Card>
      </div>
    );
  }

  // Preview view
  if (step === 'preview') {
    const existsCount = previewItems.filter(item => item.isValid && item.exists).length;

    return (
      <div className="space-y-4">
        <Button icon={<ArrowLeftOutlined />} onClick={handleReset}>
          {t('common.back')}
        </Button>

        <Card title={t('merchantStock.previewImport')} loading={warehousesLoading}>
          {/* Summary */}
          <div className="mb-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
            <Space size="large">
              <Text>
                {t('merchantStock.total')}: <Text strong>{previewSummary.total}</Text>
              </Text>
              <Text>
                <CheckCircleOutlined className="text-green-500 mr-1" />
                {t('merchantStock.canImportCount')}: <Text strong type="success">{previewSummary.valid}</Text>
              </Text>
              <Text>
                <CloseCircleOutlined className="text-red-500 mr-1" />
                {t('merchantStock.errorsCountLabel')}: <Text strong type="danger">{previewSummary.invalid}</Text>
              </Text>
            </Space>
          </div>

          {/* Conflict strategy (only show if there are existing items) */}
          {existsCount > 0 && (
            <Alert
              type="warning"
              showIcon
              message={t('merchantStock.conflictWarning', { count: existsCount })}
              description={
                <Radio.Group
                  value={conflictStrategy}
                  onChange={e => setConflictStrategy(e.target.value)}
                  className="mt-2"
                >
                  <Space direction="vertical">
                    <Radio value="skip">{t('merchantStock.conflictSkip')}</Radio>
                    <Radio value="override">{t('merchantStock.conflictOverride')}</Radio>
                    <Radio value="add">{t('merchantStock.conflictAdd')}</Radio>
                  </Space>
                </Radio.Group>
              }
              className="mb-4"
            />
          )}

          {/* Preview table */}
          <Table
            columns={previewColumns}
            dataSource={previewItems}
            rowKey="rowNumber"
            pagination={{ pageSize: 20 }}
            size="small"
            scroll={{ x: 800 }}
            rowClassName={record => (!record.isValid ? 'bg-red-50 dark:bg-red-900/20' : '')}
          />

          {/* Actions */}
          <div className="mt-4 flex justify-end">
            <Space>
              <Button onClick={handleReset}>{t('common.cancel')}</Button>
              <Button
                type="primary"
                onClick={handleConfirmImport}
                loading={importing}
                disabled={previewSummary.valid === 0}
              >
                {t('merchantStock.confirmImport')} ({previewSummary.valid})
              </Button>
            </Space>
          </div>
        </Card>
      </div>
    );
  }

  // Upload view
  return (
    <div className="space-y-4">
      <Button icon={<ArrowLeftOutlined />} onClick={() => navigate('/inventory/stock')}>
        {t('common.back')}
      </Button>

      <Card title={t('merchantStock.batchImport')} loading={warehousesLoading}>
        <Form form={form} layout="vertical" className="max-w-2xl">
          {/* Warehouse Selection */}
          <Form.Item
            name="warehouseId"
            label={t('merchantStock.warehouse')}
            rules={[{ required: true, message: t('validation.required') }]}
          >
            <Select
              placeholder={t('merchantStock.selectWarehouse')}
              options={warehouses.map(w => ({
                value: w.id,
                label: w.shortName || w.name,
              }))}
            />
          </Form.Item>

          {/* File Upload */}
          <Form.Item label={t('merchantStock.uploadFile')}>
            <div className="space-y-3">
              <Upload.Dragger
                fileList={fileList}
                beforeUpload={handleBeforeUpload}
                onRemove={() => setFileList([])}
                accept=".xlsx,.xls"
                maxCount={1}
              >
                <p className="ant-upload-drag-icon">
                  <UploadOutlined />
                </p>
                <p className="ant-upload-text">{t('merchantStock.clickOrDragUpload')}</p>
                <p className="ant-upload-hint">{t('merchantStock.supportExcelFormat')}</p>
              </Upload.Dragger>

              <Button icon={<DownloadOutlined />} onClick={handleDownloadTemplate}>
                {t('merchantStock.downloadTemplate')}
              </Button>
            </div>
          </Form.Item>

          {/* Submit */}
          <Form.Item>
            <Space>
              <Button
                type="primary"
                onClick={handlePreview}
                loading={previewLoading}
                disabled={fileList.length === 0}
              >
                {t('merchantStock.previewAndImport')}
              </Button>
              <Button onClick={() => navigate('/inventory/stock')}>{t('common.cancel')}</Button>
            </Space>
          </Form.Item>
        </Form>
      </Card>
    </div>
  );
}
