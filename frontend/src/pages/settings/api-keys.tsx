import { useState, useEffect, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import {
  Card,
  Button,
  Spin,
  App,
  Empty,
  Tag,
  Descriptions,
  Space,
  Typography,
  Alert,
  Input,
} from 'antd';
import {
  PlusOutlined,
  KeyOutlined,
  CopyOutlined,
  ReloadOutlined,
  ExclamationCircleOutlined,
  EditOutlined,
} from '@ant-design/icons';

import {
  merchantApi,
  type MerchantApiKey,
} from '@/lib/merchant-api';
import { CreateApiKeyModal } from './components/create-api-key-modal';
import { EditIpWhitelistModal } from './components/edit-ip-whitelist-modal';

const { Text } = Typography;

// Status color mapping
const statusColors: Record<string, string> = {
  active: 'success',
  suspended: 'warning',
  revoked: 'error',
};

export function ApiKeysPage() {
  const { t } = useTranslation();
  const { message, modal } = App.useApp();

  const [apiKeys, setApiKeys] = useState<MerchantApiKey[]>([]);
  const [loading, setLoading] = useState(true);
  const [createModalOpen, setCreateModalOpen] = useState(false);
  const [editIpWhitelistOpen, setEditIpWhitelistOpen] = useState(false);
  const [selectedApiKey, setSelectedApiKey] = useState<MerchantApiKey | null>(null);
  const [newSecret, setNewSecret] = useState<string | null>(null);

  const loadApiKeys = useCallback(async () => {
    setLoading(true);
    try {
      const result = await merchantApi.getApiKeys();
      setApiKeys(result.data);
    } catch {
      message.error(t('common.error'));
    } finally {
      setLoading(false);
    }
  }, [message, t]);

  useEffect(() => {
    loadApiKeys();
  }, [loadApiKeys]);

  const handleCreateSuccess = (secret: string) => {
    setCreateModalOpen(false);
    setNewSecret(secret);
    loadApiKeys();
  };

  const handleRegenerateSecret = async (apiKey: MerchantApiKey) => {
    modal.confirm({
      title: t('apiKeys.regenerateConfirmTitle'),
      icon: <ExclamationCircleOutlined />,
      content: t('apiKeys.regenerateConfirmContent'),
      okText: t('common.confirm'),
      cancelText: t('common.cancel'),
      okButtonProps: { danger: true },
      onOk: async () => {
        try {
          const result = await merchantApi.regenerateApiKeySecret(apiKey.id);
          message.success(result.message);
          setNewSecret(result.secret);
          loadApiKeys();
        } catch {
          message.error(t('common.error'));
        }
      },
    });
  };

  const handleEditIpWhitelist = (apiKey: MerchantApiKey) => {
    setSelectedApiKey(apiKey);
    setEditIpWhitelistOpen(true);
  };

  const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text);
    message.success(t('common.copied'));
  };

  const getStatusLabel = (status: string) => {
    const labels: Record<string, string> = {
      active: t('apiKeys.statusActive'),
      suspended: t('apiKeys.statusSuspended'),
      revoked: t('apiKeys.statusRevoked'),
    };
    return labels[status] || status;
  };

  const hasApiKey = apiKeys.length > 0;
  const apiKey = apiKeys[0]; // Only one API key allowed

  return (
    <div className="flex flex-col gap-4">
      {/* Header */}
      <div className="flex items-center justify-between flex-wrap gap-4">
        <div className="flex items-center gap-4">
          <span className="text-lg font-semibold">{t('apiKeys.title')}</span>
          {hasApiKey && (
            <Tag color={statusColors[apiKey.status]}>{getStatusLabel(apiKey.status)}</Tag>
          )}
        </div>
        <Space wrap>
          {hasApiKey && apiKey.status !== 'revoked' && (
            <Button
              icon={<ReloadOutlined />}
              onClick={() => handleRegenerateSecret(apiKey)}
            >
              {t('apiKeys.regenerateSecret')}
            </Button>
          )}
          {!hasApiKey && (
            <Button
              type="primary"
              icon={<PlusOutlined />}
              onClick={() => setCreateModalOpen(true)}
            >
              {t('apiKeys.create')}
            </Button>
          )}
        </Space>
      </div>

      {/* Secret Alert */}
      {newSecret && (
        <Alert
          type="warning"
          showIcon
          icon={<ExclamationCircleOutlined />}
          message={t('apiKeys.secretWarningTitle')}
          description={
            <div>
              <p className="mb-2">{t('apiKeys.secretWarningContent')}</p>
              <div className="flex items-center gap-2 bg-gray-100 dark:bg-gray-800 p-2 rounded font-mono">
                <Input.Password
                  value={newSecret}
                  readOnly
                  className="flex-1 font-mono"
                  visibilityToggle
                />
                <Button
                  icon={<CopyOutlined />}
                  onClick={() => copyToClipboard(newSecret)}
                >
                  {t('common.copy')}
                </Button>
              </div>
            </div>
          }
          closable
          onClose={() => setNewSecret(null)}
        />
      )}

      <Spin spinning={loading}>
        {hasApiKey ? (
          <div className="flex flex-col gap-4">
            {/* Basic Info Card */}
            <Card
              title={t('detail.basicInfo')}
            >
              <Descriptions column={{ xs: 1, sm: 2, md: 3 }} size="small">
                <Descriptions.Item label={t('apiKeys.name')}>
                  <Text strong>{apiKey.name}</Text>
                </Descriptions.Item>
                <Descriptions.Item label={t('apiKeys.keyId')}>
                  <Text code copyable>{apiKey.keyId}</Text>
                </Descriptions.Item>
                <Descriptions.Item label={t('common.status')}>
                  <Tag color={statusColors[apiKey.status]}>{getStatusLabel(apiKey.status)}</Tag>
                </Descriptions.Item>
                <Descriptions.Item label={t('apiKeys.createdAt')}>
                  {new Date(apiKey.createdAt).toLocaleString()}
                </Descriptions.Item>
                <Descriptions.Item label={t('apiKeys.lastUsedAt')}>
                  {apiKey.lastUsedAt
                    ? new Date(apiKey.lastUsedAt).toLocaleString()
                    : <Text type="secondary">{t('apiKeys.neverUsed')}</Text>
                  }
                </Descriptions.Item>
                <Descriptions.Item label={t('apiKeys.expiresAt')}>
                  {apiKey.expiresAt
                    ? new Date(apiKey.expiresAt).toLocaleString()
                    : <Text type="secondary">{t('apiKeys.neverExpires')}</Text>
                  }
                </Descriptions.Item>
              </Descriptions>
            </Card>

            {/* IP Whitelist Card */}
            <Card
              title={t('apiKeys.ipWhitelist')}
              extra={
                apiKey.status !== 'revoked' && (
                  <Button
                    type="link"
                    icon={<EditOutlined />}
                    onClick={() => handleEditIpWhitelist(apiKey)}
                  >
                    {t('common.edit')}
                  </Button>
                )
              }
            >
              {apiKey.ipWhitelist && apiKey.ipWhitelist.length > 0 ? (
                <div className="flex flex-wrap gap-2">
                  {apiKey.ipWhitelist.map((ip) => (
                    <Tag key={ip} className="text-sm">{ip}</Tag>
                  ))}
                </div>
              ) : (
                <div className="text-center py-4">
                  <Text type="secondary">{t('apiKeys.noIpRestriction')}</Text>
                  <br />
                  <Text type="secondary" className="text-xs">{t('apiKeys.noIpRestrictionDesc')}</Text>
                </div>
              )}
            </Card>
          </div>
        ) : (
          <Card>
            <Empty
              image={<KeyOutlined className="text-6xl text-gray-300" />}
              description={
                <div>
                  <p className="text-gray-500 dark:text-gray-400">{t('apiKeys.emptyDescription')}</p>
                </div>
              }
            >
              <Button
                type="primary"
                icon={<PlusOutlined />}
                onClick={() => setCreateModalOpen(true)}
              >
                {t('apiKeys.create')}
              </Button>
            </Empty>
          </Card>
        )}
      </Spin>

      {/* Create API Key Modal */}
      <CreateApiKeyModal
        open={createModalOpen}
        onClose={() => setCreateModalOpen(false)}
        onSuccess={handleCreateSuccess}
      />

      {/* Edit IP Whitelist Modal */}
      {selectedApiKey && (
        <EditIpWhitelistModal
          open={editIpWhitelistOpen}
          apiKey={selectedApiKey}
          onClose={() => {
            setEditIpWhitelistOpen(false);
            setSelectedApiKey(null);
          }}
          onSuccess={() => {
            setEditIpWhitelistOpen(false);
            setSelectedApiKey(null);
            loadApiKeys();
          }}
        />
      )}
    </div>
  );
}
