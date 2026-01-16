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
  Popconfirm,
  Typography,
  Alert,
  Modal,
  Input,
} from 'antd';
import {
  PlusOutlined,
  KeyOutlined,
  CopyOutlined,
  ReloadOutlined,
  DeleteOutlined,
  ExclamationCircleOutlined,
  CheckCircleOutlined,
  StopOutlined,
  PlayCircleOutlined,
} from '@ant-design/icons';

import {
  merchantApi,
  type MerchantApiKey,
} from '@/lib/merchant-api';
import { CreateApiKeyModal } from './components/create-api-key-modal';
import { EditPermissionsModal } from './components/edit-permissions-modal';
import { EditIpWhitelistModal } from './components/edit-ip-whitelist-modal';

const { Text, Paragraph } = Typography;

// Permission labels mapping
const PERMISSION_LABELS: Record<string, string> = {
  'merchant_inventory:read': 'apiKeys.perm.inventoryRead',
  'merchant_inventory:write': 'apiKeys.perm.inventoryWrite',
  'merchant_inbound:read': 'apiKeys.perm.inboundRead',
  'merchant_inbound:write': 'apiKeys.perm.inboundWrite',
  'fulfillment:read': 'apiKeys.perm.fulfillmentRead',
  'fulfillment:write': 'apiKeys.perm.fulfillmentWrite',
  'settlement:read': 'apiKeys.perm.settlementRead',
  'listing:read': 'apiKeys.perm.listingRead',
  'listing:write': 'apiKeys.perm.listingWrite',
  'webhook:manage': 'apiKeys.perm.webhookManage',
};

export function ApiKeysPage() {
  const { t } = useTranslation();
  const { message, modal } = App.useApp();

  const [apiKeys, setApiKeys] = useState<MerchantApiKey[]>([]);
  const [loading, setLoading] = useState(true);
  const [createModalOpen, setCreateModalOpen] = useState(false);
  const [editPermissionsOpen, setEditPermissionsOpen] = useState(false);
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

  const handleDelete = async (apiKey: MerchantApiKey) => {
    modal.confirm({
      title: t('apiKeys.deleteConfirmTitle'),
      icon: <ExclamationCircleOutlined />,
      content: t('apiKeys.deleteConfirmContent'),
      okText: t('common.confirm'),
      cancelText: t('common.cancel'),
      okButtonProps: { danger: true },
      onOk: async () => {
        try {
          await merchantApi.deleteApiKey(apiKey.id);
          message.success(t('apiKeys.deleted'));
          loadApiKeys();
        } catch {
          message.error(t('common.error'));
        }
      },
    });
  };

  const handleSuspend = async (apiKey: MerchantApiKey) => {
    try {
      await merchantApi.suspendApiKey(apiKey.id);
      message.success(t('apiKeys.suspended'));
      loadApiKeys();
    } catch {
      message.error(t('common.error'));
    }
  };

  const handleActivate = async (apiKey: MerchantApiKey) => {
    try {
      await merchantApi.activateApiKey(apiKey.id);
      message.success(t('apiKeys.activated'));
      loadApiKeys();
    } catch {
      message.error(t('common.error'));
    }
  };

  const handleEditPermissions = (apiKey: MerchantApiKey) => {
    setSelectedApiKey(apiKey);
    setEditPermissionsOpen(true);
  };

  const handleEditIpWhitelist = (apiKey: MerchantApiKey) => {
    setSelectedApiKey(apiKey);
    setEditIpWhitelistOpen(true);
  };

  const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text);
    message.success(t('common.copied'));
  };

  const hasApiKey = apiKeys.length > 0;
  const apiKey = apiKeys[0]; // Only one API key allowed

  const getStatusTag = (status: string) => {
    switch (status) {
      case 'active':
        return <Tag icon={<CheckCircleOutlined />} color="success">{t('apiKeys.statusActive')}</Tag>;
      case 'suspended':
        return <Tag icon={<StopOutlined />} color="warning">{t('apiKeys.statusSuspended')}</Tag>;
      case 'revoked':
        return <Tag icon={<StopOutlined />} color="error">{t('apiKeys.statusRevoked')}</Tag>;
      default:
        return <Tag>{status}</Tag>;
    }
  };

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h2 className="text-xl font-semibold m-0">{t('apiKeys.title')}</h2>
          <p className="text-gray-500 dark:text-gray-400 mt-1 mb-0">{t('apiKeys.description')}</p>
        </div>
        {!hasApiKey && (
          <Button
            type="primary"
            icon={<PlusOutlined />}
            onClick={() => setCreateModalOpen(true)}
          >
            {t('apiKeys.create')}
          </Button>
        )}
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
          className="!my-4"
        />
      )}

      <Spin spinning={loading}>
        {hasApiKey ? (
          <Card>
            {/* API Key Info */}
            <Descriptions
              column={{ xs: 1, sm: 2, md: 2 }}
              bordered
              size="small"
            >
              <Descriptions.Item label={t('apiKeys.name')} span={2}>
                <Space>
                  <KeyOutlined />
                  <Text strong>{apiKey.name}</Text>
                  {getStatusTag(apiKey.status)}
                </Space>
              </Descriptions.Item>
              <Descriptions.Item label={t('apiKeys.keyId')} span={2}>
                <Space>
                  <Text code copyable>{apiKey.keyId}</Text>
                </Space>
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
            </Descriptions>

            {/* Permissions */}
            <div className="mt-4">
              <div className="flex justify-between items-center mb-2">
                <Text strong>{t('apiKeys.permissions')}</Text>
                <Button
                  type="link"
                  size="small"
                  onClick={() => handleEditPermissions(apiKey)}
                  disabled={apiKey.status === 'revoked'}
                >
                  {t('common.edit')}
                </Button>
              </div>
              <div className="flex flex-wrap gap-2">
                {apiKey.permissions.map((perm) => (
                  <Tag key={perm} color="blue">
                    {t(PERMISSION_LABELS[perm] || perm)}
                  </Tag>
                ))}
              </div>
            </div>

            {/* IP Whitelist */}
            <div className="mt-4">
              <div className="flex justify-between items-center mb-2">
                <Text strong>{t('apiKeys.ipWhitelist')}</Text>
                <Button
                  type="link"
                  size="small"
                  onClick={() => handleEditIpWhitelist(apiKey)}
                  disabled={apiKey.status === 'revoked'}
                >
                  {t('common.edit')}
                </Button>
              </div>
              {apiKey.ipWhitelist && apiKey.ipWhitelist.length > 0 ? (
                <div className="flex flex-wrap gap-2">
                  {apiKey.ipWhitelist.map((ip) => (
                    <Tag key={ip}>{ip}</Tag>
                  ))}
                </div>
              ) : (
                <Text type="secondary">{t('apiKeys.noIpRestriction')}</Text>
              )}
            </div>

            {/* Actions */}
            <div className="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
              <Space wrap>
                {apiKey.status === 'active' && (
                  <Button
                    icon={<StopOutlined />}
                    onClick={() => handleSuspend(apiKey)}
                  >
                    {t('apiKeys.suspend')}
                  </Button>
                )}
                {apiKey.status === 'suspended' && (
                  <Button
                    icon={<PlayCircleOutlined />}
                    onClick={() => handleActivate(apiKey)}
                  >
                    {t('apiKeys.activate')}
                  </Button>
                )}
                <Popconfirm
                  title={t('apiKeys.regenerateConfirmTitle')}
                  description={t('apiKeys.regenerateConfirmContent')}
                  onConfirm={() => handleRegenerateSecret(apiKey)}
                  okText={t('common.confirm')}
                  cancelText={t('common.cancel')}
                  disabled={apiKey.status === 'revoked'}
                >
                  <Button
                    icon={<ReloadOutlined />}
                    disabled={apiKey.status === 'revoked'}
                  >
                    {t('apiKeys.regenerateSecret')}
                  </Button>
                </Popconfirm>
                <Button
                  danger
                  icon={<DeleteOutlined />}
                  onClick={() => handleDelete(apiKey)}
                >
                  {t('apiKeys.delete')}
                </Button>
              </Space>
            </div>
          </Card>
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

      {/* Edit Permissions Modal */}
      {selectedApiKey && (
        <EditPermissionsModal
          open={editPermissionsOpen}
          apiKey={selectedApiKey}
          onClose={() => {
            setEditPermissionsOpen(false);
            setSelectedApiKey(null);
          }}
          onSuccess={() => {
            setEditPermissionsOpen(false);
            setSelectedApiKey(null);
            loadApiKeys();
          }}
        />
      )}

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
