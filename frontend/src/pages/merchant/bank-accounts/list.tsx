import { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button, Tag, App, Popconfirm, Space } from 'antd';
import { PlusOutlined, StarFilled } from '@ant-design/icons';
import { ProTable, type ActionType, type ProColumns } from '@ant-design/pro-components';

import {
  merchantBankAccountApi,
  type BankAccount,
  BANK_ACCOUNT_STATUS_LABELS,
  BANK_ACCOUNT_STATUS_COLORS,
  BANK_ACCOUNT_TYPE_LABELS,
} from '@/lib/merchant-bank-account-api';
import { BankAccountFormModal } from './components/bank-account-form-modal';

export function BankAccountsListPage() {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const actionRef = useRef<ActionType>(null);

  const [formModalOpen, setFormModalOpen] = useState(false);
  const [editingAccount, setEditingAccount] = useState<BankAccount | null>(null);

  const handleCreate = () => {
    setEditingAccount(null);
    setFormModalOpen(true);
  };

  const handleEdit = (record: BankAccount) => {
    setEditingAccount(record);
    setFormModalOpen(true);
  };

  const handleDelete = async (id: string) => {
    try {
      await merchantBankAccountApi.deleteBankAccount(id);
      message.success(t('bankAccounts.deleted'));
      actionRef.current?.reload();
    } catch {
      message.error(t('common.error'));
    }
  };

  const handleSetDefault = async (id: string) => {
    try {
      await merchantBankAccountApi.setDefaultBankAccount(id);
      message.success(t('bankAccounts.setDefaultSuccess'));
      actionRef.current?.reload();
    } catch {
      message.error(t('common.error'));
    }
  };

  const handleFormSuccess = () => {
    setFormModalOpen(false);
    actionRef.current?.reload();
  };

  const columns: ProColumns<BankAccount>[] = [
    {
      title: t('bankAccounts.bankName'),
      dataIndex: 'bankName',
      width: 150,
    },
    {
      title: t('bankAccounts.accountNumber'),
      dataIndex: 'maskedAccountNumber',
      width: 180,
      search: false,
    },
    {
      title: t('bankAccounts.accountHolder'),
      dataIndex: 'accountHolder',
      width: 120,
      search: false,
    },
    {
      title: t('bankAccounts.accountType'),
      dataIndex: 'accountType',
      width: 100,
      search: false,
      render: (_, record) => t(BANK_ACCOUNT_TYPE_LABELS[record.accountType]),
    },
    {
      title: t('bankAccounts.currency'),
      dataIndex: 'currency',
      width: 80,
      search: false,
    },
    {
      title: t('bankAccounts.status'),
      dataIndex: 'status',
      width: 100,
      valueType: 'select',
      valueEnum: {
        pending: { text: t('bankAccounts.statusPending'), status: 'Processing' },
        active: { text: t('bankAccounts.statusActive'), status: 'Success' },
        disabled: { text: t('bankAccounts.statusDisabled'), status: 'Default' },
      },
      render: (_, record) => (
        <Tag color={BANK_ACCOUNT_STATUS_COLORS[record.status]}>
          {t(BANK_ACCOUNT_STATUS_LABELS[record.status])}
        </Tag>
      ),
    },
    {
      title: t('bankAccounts.isDefault'),
      dataIndex: 'isDefault',
      width: 100,
      search: false,
      render: (_, record) =>
        record.isDefault ? (
          <Tag color="gold" icon={<StarFilled />}>
            {t('common.yes')}
          </Tag>
        ) : (
          <Tag>{t('common.no')}</Tag>
        ),
    },
    {
      title: t('common.createdAt'),
      dataIndex: 'createdAt',
      valueType: 'dateTime',
      width: 180,
      search: false,
    },
    {
      title: t('common.actions'),
      valueType: 'option',
      width: 200,
      fixed: 'right',
      render: (_, record) => (
        <Space size="small">
          {!record.isDefault && (
            <Button type="link" size="small" onClick={() => handleSetDefault(record.id)}>
              {t('bankAccounts.setDefault')}
            </Button>
          )}
          <Button type="link" size="small" onClick={() => handleEdit(record)}>
            {t('common.edit')}
          </Button>
          <Popconfirm
            title={t('bankAccounts.deleteConfirm')}
            onConfirm={() => handleDelete(record.id)}
            okText={t('common.confirm')}
            cancelText={t('common.cancel')}
          >
            <Button type="link" size="small" danger>
              {t('common.delete')}
            </Button>
          </Popconfirm>
        </Space>
      ),
    },
  ];

  return (
    <>
      <ProTable<BankAccount>
        headerTitle={t('bankAccounts.title')}
        actionRef={actionRef}
        rowKey="id"
        columns={columns}
        request={async () => {
          const result = await merchantBankAccountApi.getBankAccounts();
          return {
            data: result.data,
            total: result.total,
            success: true,
          };
        }}
        search={false}
        pagination={false}
        toolBarRender={() => [
          <Button key="create" type="primary" icon={<PlusOutlined />} onClick={handleCreate}>
            {t('bankAccounts.addAccount')}
          </Button>,
        ]}
        scroll={{ x: 1200 }}
      />

      <BankAccountFormModal
        open={formModalOpen}
        account={editingAccount}
        onCancel={() => setFormModalOpen(false)}
        onSuccess={handleFormSuccess}
      />
    </>
  );
}
