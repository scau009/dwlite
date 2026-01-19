import { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ProTable, type ActionType, type ProColumns } from '@ant-design/pro-components';
import { Button, Tag, Space, App, Popconfirm, Typography, Alert, Input, Select } from 'antd';
import {
  PlusOutlined,
  CheckCircleOutlined,
  StopOutlined,
  ExclamationCircleOutlined,
} from '@ant-design/icons';

import { adminApiKeyApi, type AdminApiKey } from '@/lib/admin-api-key-api';
import { merchantApi } from '@/lib/merchant-api';
import { CreateApiKeyModal } from './components/create-api-key-modal';
import { EditPermissionsModal } from './components/edit-permissions-modal';
import { EditIpWhitelistModal } from './components/edit-ip-whitelist-modal';

const { Text } = Typography;

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

export function AdminApiKeysListPage() {
  const { t } = useTranslation();
  const actionRef = useRef<ActionType>(null);
  const { message, modal } = App.useApp();

  const [createModalOpen, setCreateModalOpen] = useState(false);
  const [editPermissionsOpen, setEditPermissionsOpen] = useState(false);
  const [editIpWhitelistOpen, setEditIpWhitelistOpen] = useState(false);
  const [selectedApiKey, setSelectedApiKey] = useState<AdminApiKey | null>(null);
  const [newSecret, setNewSecret] = useState<string | null>(null);
  const [statusLoading, setStatusLoading] = useState<string | null>(null);

  const handleCreateSuccess = (secret: string) => {
    setCreateModalOpen(false);
    setNewSecret(secret);
    actionRef.current?.reload();
  };

  const handleStatusChange = async (apiKey: AdminApiKey, newStatus: 'active' | 'suspended') => {
    setStatusLoading(apiKey.id);
    try {
      await adminApiKeyApi.updateApiKeyStatus(apiKey.id, newStatus);
      message.success(t('adminApiKeys.statusUpdated'));
      actionRef.current?.reload();
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setStatusLoading(null);
    }
  };

  const handleDelete = async (apiKey: AdminApiKey) => {
    modal.confirm({
      title: t('adminApiKeys.confirmDelete'),
      icon: <ExclamationCircleOutlined />,
      content: t('apiKeys.deleteConfirmContent'),
      okText: t('common.confirm'),
      cancelText: t('common.cancel'),
      okButtonProps: { danger: true },
      onOk: async () => {
        try {
          await adminApiKeyApi.deleteApiKey(apiKey.id);
          message.success(t('apiKeys.deleted'));
          actionRef.current?.reload();
        } catch (error) {
          const err = error as { error?: string };
          message.error(err.error || t('common.error'));
        }
      },
    });
  };

  const handleEditPermissions = (apiKey: AdminApiKey) => {
    setSelectedApiKey(apiKey);
    setEditPermissionsOpen(true);
  };

  const handleEditIpWhitelist = (apiKey: AdminApiKey) => {
    setSelectedApiKey(apiKey);
    setEditIpWhitelistOpen(true);
  };

  const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text);
    message.success(t('common.copied'));
  };

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

  const columns: ProColumns<AdminApiKey>[] = [
    {
      title: t('apiKeys.name'),
      dataIndex: 'name',
      width: 150,
      ellipsis: true,
      search: false,
    },
    {
      title: t('apiKeys.keyId'),
      dataIndex: 'keyId',
      width: 180,
      search: false,
      render: (_, record) => (
        <Text code copyable={{ onCopy: () => copyToClipboard(record.keyId) }}>
          {record.keyId}
        </Text>
      ),
    },
    {
      title: t('adminApiKeys.owner'),
      dataIndex: ['merchant', 'name'],
      width: 150,
      ellipsis: true,
      renderFormItem: () => (
        <MerchantSearchSelect />
      ),
      search: {
        transform: (value) => ({ merchantId: value }),
      },
      render: (_, record) => record.merchant?.name || '-',
    },
    {
      title: t('apiKeys.status'),
      dataIndex: 'status',
      width: 100,
      valueType: 'select',
      valueEnum: {
        active: { text: t('apiKeys.statusActive'), status: 'Success' },
        suspended: { text: t('apiKeys.statusSuspended'), status: 'Warning' },
        revoked: { text: t('apiKeys.statusRevoked'), status: 'Error' },
      },
      render: (_, record) => getStatusTag(record.status),
    },
    {
      title: t('apiKeys.permissions'),
      dataIndex: 'permissions',
      width: 200,
      search: false,
      render: (_, record) => (
        <div className="flex flex-wrap gap-1">
          {record.permissions.slice(0, 2).map((perm) => (
            <Tag key={perm} color="blue" className="text-xs">
              {t(PERMISSION_LABELS[perm] || perm)}
            </Tag>
          ))}
          {record.permissions.length > 2 && (
            <Tag className="text-xs">+{record.permissions.length - 2}</Tag>
          )}
        </div>
      ),
    },
    {
      title: t('apiKeys.lastUsedAt'),
      dataIndex: 'lastUsedAt',
      width: 160,
      search: false,
      render: (_, record) =>
        record.lastUsedAt
          ? new Date(record.lastUsedAt).toLocaleString()
          : <Text type="secondary">{t('apiKeys.neverUsed')}</Text>,
    },
    {
      title: t('common.createdAt'),
      dataIndex: 'createdAt',
      width: 160,
      search: false,
      render: (_, record) => new Date(record.createdAt).toLocaleString(),
    },
    {
      title: t('common.actions'),
      valueType: 'option',
      width: 200,
      fixed: 'right',
      render: (_, record) => (
        <Space size="small" wrap>
          <Button
            type="link"
            size="small"
            onClick={() => handleEditPermissions(record)}
            disabled={record.status === 'revoked'}
          >
            {t('apiKeys.permissions')}
          </Button>
          <Button
            type="link"
            size="small"
            onClick={() => handleEditIpWhitelist(record)}
            disabled={record.status === 'revoked'}
          >
            {t('apiKeys.ipWhitelist')}
          </Button>
          {record.status === 'active' && (
            <Popconfirm
              title={t('adminApiKeys.confirmSuspend')}
              onConfirm={() => handleStatusChange(record, 'suspended')}
              okText={t('common.confirm')}
              cancelText={t('common.cancel')}
            >
              <Button
                type="link"
                size="small"
                loading={statusLoading === record.id}
              >
                {t('apiKeys.suspend')}
              </Button>
            </Popconfirm>
          )}
          {record.status === 'suspended' && (
            <Popconfirm
              title={t('adminApiKeys.confirmActivate')}
              onConfirm={() => handleStatusChange(record, 'active')}
              okText={t('common.confirm')}
              cancelText={t('common.cancel')}
            >
              <Button
                type="link"
                size="small"
                loading={statusLoading === record.id}
              >
                {t('apiKeys.activate')}
              </Button>
            </Popconfirm>
          )}
          <Button
            type="link"
            size="small"
            danger
            onClick={() => handleDelete(record)}
          >
            {t('common.delete')}
          </Button>
        </Space>
      ),
    },
  ];

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h2 className="text-xl font-semibold m-0">{t('adminApiKeys.title')}</h2>
          <p className="text-gray-500 dark:text-gray-400 mt-1 mb-0">{t('adminApiKeys.description')}</p>
        </div>
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

      <ProTable<AdminApiKey>
        actionRef={actionRef}
        columns={columns}
        rowKey="id"
        scroll={{ x: 1300 }}
        request={async (params) => {
          try {
            const result = await adminApiKeyApi.getApiKeys({
              page: params.current,
              limit: params.pageSize,
              type: 'merchant', // Only show merchant API keys
              status: params.status,
              merchantId: params.merchantId,
            });
            return {
              data: result.data,
              success: true,
              total: result.total,
            };
          } catch (error) {
            console.error('Failed to fetch API keys:', error);
            return {
              data: [],
              success: false,
              total: 0,
            };
          }
        }}
        search={{
          labelWidth: 'auto',
          defaultCollapsed: false,
        }}
        options={{
          density: true,
          fullScreen: true,
          reload: true,
        }}
        pagination={{
          defaultPageSize: 20,
          showSizeChanger: true,
        }}
        toolBarRender={() => [
          <Button
            key="create"
            type="primary"
            icon={<PlusOutlined />}
            onClick={() => setCreateModalOpen(true)}
          >
            {t('adminApiKeys.create')}
          </Button>,
        ]}
      />

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
            actionRef.current?.reload();
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
            actionRef.current?.reload();
          }}
        />
      )}
    </div>
  );
}

// Merchant search select component for filtering
function MerchantSearchSelect() {
  const { t } = useTranslation();
  const [options, setOptions] = useState<{ label: string; value: string }[]>([]);
  const [loading, setLoading] = useState(false);

  const handleSearch = async (value: string) => {
    if (!value || value.length < 2) {
      setOptions([]);
      return;
    }

    setLoading(true);
    try {
      const result = await merchantApi.getMerchants({ name: value, limit: 10 });
      setOptions(
        result.data.map((m) => ({
          label: m.name,
          value: m.id,
        }))
      );
    } catch {
      setOptions([]);
    } finally {
      setLoading(false);
    }
  };

  return (
    <Select
      showSearch
      allowClear
      placeholder={t('adminApiKeys.searchMerchant')}
      filterOption={false}
      onSearch={handleSearch}
      loading={loading}
      options={options}
      notFoundContent={loading ? t('common.loading') : null}
    />
  );
}
