import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal, Form, Select, InputNumber, Input, App, Radio } from 'antd';
import { channelWarehouseApi, type Warehouse } from '@/lib/channel-warehouse-api';

interface AddWarehouseModalProps {
  open: boolean;
  channelId: string | null;
  onClose: () => void;
  onSuccess: () => void;
}

export function AddWarehouseModal({ open, channelId, onClose, onSuccess }: AddWarehouseModalProps) {
  const { t } = useTranslation();
  const [form] = Form.useForm();
  const { message } = App.useApp();

  const [loading, setLoading] = useState(false);
  const [availableWarehouses, setAvailableWarehouses] = useState<Warehouse[]>([]);
  const [fetchingWarehouses, setFetchingWarehouses] = useState(false);
  const [priorityMode, setPriorityMode] = useState<'auto' | 'manual'>('auto');

  useEffect(() => {
    if (open && channelId) {
      form.resetFields();
      setPriorityMode('auto');
      loadAvailableWarehouses();
    }
  }, [open, channelId, form]);

  const loadAvailableWarehouses = async () => {
    if (!channelId) return;

    setFetchingWarehouses(true);
    try {
      const result = await channelWarehouseApi.getAvailableWarehouses(channelId);
      setAvailableWarehouses(result.data);
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setFetchingWarehouses(false);
    }
  };

  const handleSubmit = async () => {
    if (!channelId) return;

    try {
      const values = await form.validateFields();
      setLoading(true);

      await channelWarehouseApi.addWarehouse(channelId, {
        warehouseId: values.warehouseId,
        priority: priorityMode === 'manual' ? values.priority : undefined,
        remark: values.remark,
      });

      message.success(t('channels.warehouses.added'));
      form.resetFields();
      onSuccess();
    } catch (error) {
      const err = error as { error?: string };
      if (err.error) {
        message.error(err.error);
      }
    } finally {
      setLoading(false);
    }
  };

  const handleCancel = () => {
    form.resetFields();
    onClose();
  };

  return (
    <Modal
      title={t('channels.warehouses.addWarehouse')}
      open={open}
      onOk={handleSubmit}
      onCancel={handleCancel}
      confirmLoading={loading}
      width={560}
      destroyOnClose
    >
      <Form
        form={form}
        layout="vertical"
        initialValues={{
          priority: 0,
        }}
      >
        <Form.Item
          name="warehouseId"
          label={t('channels.warehouses.warehouseName')}
          rules={[{ required: true, message: t('channels.warehouses.warehouseRequired') }]}
        >
          <Select
            placeholder={t('channels.warehouses.selectWarehouse')}
            loading={fetchingWarehouses}
            options={availableWarehouses.map((warehouse) => ({
              value: warehouse.id,
              label: `${warehouse.name} (${warehouse.code})`,
            }))}
            showSearch
            filterOption={(input, option) =>
              (option?.label ?? '').toLowerCase().includes(input.toLowerCase())
            }
          />
        </Form.Item>

        <Form.Item label={t('channels.warehouses.priority')}>
          <Radio.Group value={priorityMode} onChange={(e) => setPriorityMode(e.target.value)}>
            <Radio value="auto">{t('channels.warehouses.autoPriority')}</Radio>
            <Radio value="manual">{t('channels.warehouses.manualPriority')}</Radio>
          </Radio.Group>
        </Form.Item>

        {priorityMode === 'manual' && (
          <Form.Item
            name="priority"
            label=" "
            colon={false}
            tooltip={t('channels.warehouses.priorityTooltip')}
            rules={[
              { required: true, message: t('common.required') },
              { type: 'number', min: 0, message: t('validation.priority_positive') },
            ]}
          >
            <InputNumber
              style={{ width: '100%' }}
              min={0}
              placeholder="0"
            />
          </Form.Item>
        )}

        <Form.Item name="remark" label={t('channels.warehouses.remark')}>
          <Input.TextArea
            rows={3}
            placeholder={t('common.optional')}
            maxLength={255}
            showCount
          />
        </Form.Item>
      </Form>
    </Modal>
  );
}
