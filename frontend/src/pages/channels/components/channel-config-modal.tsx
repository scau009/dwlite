import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal, Input, Button, Checkbox, App, Empty, Popconfirm } from 'antd';
import { PlusOutlined, DeleteOutlined, EyeOutlined, EyeInvisibleOutlined } from '@ant-design/icons';
import { channelApi, type SalesChannel, type SalesChannelDetail } from '@/lib/channel-api';

interface ConfigItem {
  key: string;
  value: string;
  isSecret: boolean;
}

interface ChannelConfigModalProps {
  open: boolean;
  channel: SalesChannel | null;
  onClose: () => void;
  onSuccess: () => void;
}

export function ChannelConfigModal({
  open,
  channel,
  onClose,
  onSuccess,
}: ChannelConfigModalProps) {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [configItems, setConfigItems] = useState<ConfigItem[]>([]);
  const [visibleSecrets, setVisibleSecrets] = useState<Set<number>>(new Set());

  // Load channel detail to get config
  useEffect(() => {
    const loadChannelConfig = async () => {
      if (!channel) return;
      setLoading(true);
      try {
        const detail: SalesChannelDetail = await channelApi.getChannel(channel.id);
        const config = detail.config as Record<string, { value: string; isSecret: boolean }> | null;

        if (config && typeof config === 'object') {
          const items: ConfigItem[] = Object.entries(config).map(([key, val]) => ({
            key,
            value: typeof val === 'object' && val !== null ? val.value || '' : String(val || ''),
            isSecret: typeof val === 'object' && val !== null ? val.isSecret || false : false,
          }));
          setConfigItems(items);
        } else {
          setConfigItems([]);
        }
      } catch (error) {
        console.error('Failed to load channel config:', error);
        message.error(t('common.error'));
      } finally {
        setLoading(false);
      }
    };

    if (open && channel) {
      loadChannelConfig();
    }
  }, [open, channel, message, t]);

  const handleAddItem = () => {
    setConfigItems([...configItems, { key: '', value: '', isSecret: false }]);
  };

  const handleRemoveItem = (index: number) => {
    const newItems = [...configItems];
    newItems.splice(index, 1);
    setConfigItems(newItems);
    // Remove from visible secrets if it was visible
    const newVisible = new Set(visibleSecrets);
    newVisible.delete(index);
    setVisibleSecrets(newVisible);
  };

  const handleItemChange = (index: number, field: keyof ConfigItem, value: string | boolean) => {
    const newItems = [...configItems];
    newItems[index] = { ...newItems[index], [field]: value };
    setConfigItems(newItems);
  };

  const toggleSecretVisibility = (index: number) => {
    const newVisible = new Set(visibleSecrets);
    if (newVisible.has(index)) {
      newVisible.delete(index);
    } else {
      newVisible.add(index);
    }
    setVisibleSecrets(newVisible);
  };

  const handleSave = async () => {
    if (!channel) return;

    // Validate: check for empty keys
    const hasEmptyKey = configItems.some(item => !item.key.trim());
    if (hasEmptyKey) {
      message.warning(t('channels.config.keyRequired'));
      return;
    }

    // Check for duplicate keys
    const keys = configItems.map(item => item.key.trim());
    const uniqueKeys = new Set(keys);
    if (keys.length !== uniqueKeys.size) {
      message.warning(t('channels.config.duplicateKey'));
      return;
    }

    setSaving(true);
    try {
      // Convert to config object with isSecret metadata
      const config: Record<string, { value: string; isSecret: boolean }> = {};
      configItems.forEach(item => {
        config[item.key.trim()] = {
          value: item.value,
          isSecret: item.isSecret,
        };
      });

      await channelApi.updateChannel(channel.id, { config });
      message.success(t('channels.config.saved'));
      onSuccess();
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setSaving(false);
    }
  };

  const handleClose = () => {
    setConfigItems([]);
    setVisibleSecrets(new Set());
    onClose();
  };

  return (
    <Modal
      title={t('channels.config.title', { name: channel?.name || '' })}
      open={open}
      onCancel={handleClose}
      onOk={handleSave}
      confirmLoading={saving}
      okText={t('common.save')}
      cancelText={t('common.cancel')}
      width={640}
      destroyOnClose
    >
      <div className="py-4">
        <div className="mb-4 text-gray-500 text-sm">
          {t('channels.config.hint')}
        </div>

        {loading ? (
          <div className="text-center py-8 text-gray-400">{t('common.loading')}</div>
        ) : configItems.length === 0 ? (
          <Empty
            image={Empty.PRESENTED_IMAGE_SIMPLE}
            description={t('channels.config.noConfig')}
          >
            <Button type="primary" icon={<PlusOutlined />} onClick={handleAddItem}>
              {t('channels.config.addItem')}
            </Button>
          </Empty>
        ) : (
          <div className="space-y-3">
            {/* Header */}
            <div className="grid grid-cols-12 gap-2 text-sm text-gray-500 font-medium px-1">
              <div className="col-span-4">{t('channels.config.configKey')}</div>
              <div className="col-span-5">{t('channels.config.configValue')}</div>
              <div className="col-span-2">{t('channels.config.isSecret')}</div>
              <div className="col-span-1"></div>
            </div>

            {/* Config Items */}
            {configItems.map((item, index) => (
              <div key={index} className="grid grid-cols-12 gap-2 items-center">
                <div className="col-span-4">
                  <Input
                    placeholder={t('channels.config.keyPlaceholder')}
                    value={item.key}
                    onChange={(e) => handleItemChange(index, 'key', e.target.value)}
                    maxLength={50}
                  />
                </div>
                <div className="col-span-5">
                  <Input.Password
                    placeholder={t('channels.config.valuePlaceholder')}
                    value={item.value}
                    onChange={(e) => handleItemChange(index, 'value', e.target.value)}
                    visibilityToggle={{
                      visible: !item.isSecret || visibleSecrets.has(index),
                      onVisibleChange: () => {
                        if (item.isSecret) {
                          toggleSecretVisibility(index);
                        }
                      },
                    }}
                    iconRender={(visible) =>
                      item.isSecret ? (visible ? <EyeOutlined /> : <EyeInvisibleOutlined />) : null
                    }
                    maxLength={500}
                  />
                </div>
                <div className="col-span-2 flex justify-center">
                  <Checkbox
                    checked={item.isSecret}
                    onChange={(e) => handleItemChange(index, 'isSecret', e.target.checked)}
                  />
                </div>
                <div className="col-span-1 flex justify-center">
                  <Popconfirm
                    title={t('channels.config.confirmRemove')}
                    onConfirm={() => handleRemoveItem(index)}
                    okText={t('common.confirm')}
                    cancelText={t('common.cancel')}
                  >
                    <Button
                      type="text"
                      danger
                      icon={<DeleteOutlined />}
                      size="small"
                    />
                  </Popconfirm>
                </div>
              </div>
            ))}

            {/* Add Button */}
            <Button
              type="dashed"
              onClick={handleAddItem}
              icon={<PlusOutlined />}
              className="w-full"
            >
              {t('channels.config.addItem')}
            </Button>
          </div>
        )}
      </div>
    </Modal>
  );
}
