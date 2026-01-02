import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal, Transfer, App, Empty } from 'antd';
import type { TransferProps } from 'antd';
import { channelWarehouseApi, type Warehouse } from '@/lib/channel-warehouse-api';

interface BatchAddWarehouseModalProps {
  open: boolean;
  channelId: string | null;
  onClose: () => void;
  onSuccess: () => void;
}

type TransferItem = Warehouse & { key: string };

export function BatchAddWarehouseModal({
  open,
  channelId,
  onClose,
  onSuccess,
}: BatchAddWarehouseModalProps) {
  const { t } = useTranslation();
  const { message } = App.useApp();

  const [loading, setLoading] = useState(false);
  const [availableWarehouses, setAvailableWarehouses] = useState<TransferItem[]>([]);
  const [fetchingWarehouses, setFetchingWarehouses] = useState(false);
  const [selectedKeys, setSelectedKeys] = useState<string[]>([]);
  const [targetKeys, setTargetKeys] = useState<string[]>([]);

  useEffect(() => {
    if (open && channelId) {
      setSelectedKeys([]);
      setTargetKeys([]);
      loadAvailableWarehouses();
    }
  }, [open, channelId]);

  const loadAvailableWarehouses = async () => {
    if (!channelId) return;

    setFetchingWarehouses(true);
    try {
      const result = await channelWarehouseApi.getAvailableWarehouses(channelId);
      const items: TransferItem[] = result.data.map((warehouse) => ({
        ...warehouse,
        key: warehouse.id,
      }));
      setAvailableWarehouses(items);
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setFetchingWarehouses(false);
    }
  };

  const handleChange: TransferProps['onChange'] = (nextTargetKeys) => {
    setTargetKeys(nextTargetKeys as string[]);
  };

  const handleSelectChange: TransferProps['onSelectChange'] = (
    sourceSelectedKeys,
    targetSelectedKeys
  ) => {
    setSelectedKeys([...sourceSelectedKeys, ...targetSelectedKeys] as string[]);
  };

  const handleSubmit = async () => {
    if (!channelId) return;

    if (targetKeys.length === 0) {
      message.warning(t('channels.warehouses.warehouseRequired'));
      return;
    }

    try {
      setLoading(true);

      const result = await channelWarehouseApi.batchAddWarehouses(channelId, {
        warehouseIds: targetKeys,
      });

      message.success(
        t('channels.warehouses.batchAddSuccess', {
          added: result.added,
          skipped: result.skipped,
        })
      );
      setSelectedKeys([]);
      setTargetKeys([]);
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
    setSelectedKeys([]);
    setTargetKeys([]);
    onClose();
  };

  return (
    <Modal
      title={t('channels.warehouses.batchAdd')}
      open={open}
      onOk={handleSubmit}
      onCancel={handleCancel}
      confirmLoading={loading}
      width={750}
      destroyOnClose
    >
      <div className="my-4">
        {availableWarehouses.length === 0 && !fetchingWarehouses ? (
          <Empty
            description={t('channels.warehouses.available')}
            className="my-8"
          />
        ) : (
          <Transfer
            dataSource={availableWarehouses}
            titles={[t('channels.warehouses.available'), t('channels.warehouses.selectWarehouses')]}
            targetKeys={targetKeys}
            selectedKeys={selectedKeys}
            onChange={handleChange}
            onSelectChange={handleSelectChange}
            render={(item) => `${item.name} (${item.code})`}
            listStyle={{
              width: 300,
              height: 400,
            }}
            showSearch
            filterOption={(inputValue, item) => {
              const searchText = inputValue.toLowerCase();
              return (
                item.name.toLowerCase().includes(searchText) ||
                item.code.toLowerCase().includes(searchText)
              );
            }}
            locale={{
              itemUnit: t('common.items'),
              itemsUnit: t('common.items'),
              searchPlaceholder: t('common.search'),
              notFoundContent: t('common.noData'),
            }}
          />
        )}
      </div>
    </Modal>
  );
}
