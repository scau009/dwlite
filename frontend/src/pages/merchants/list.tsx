import { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ProTable, type ActionType, type ProColumns } from '@ant-design/pro-components';
import { Button, Tag, Space, Switch, App, Popconfirm } from 'antd';
import { WalletOutlined, HistoryOutlined, PlusCircleOutlined, AuditOutlined } from '@ant-design/icons';

import { merchantApi, type Merchant } from '@/lib/merchant-api';
import { ChargeModal } from './components/charge-modal';
import { TransactionsModal } from './components/transactions-modal';
import { ReviewModal } from './components/review-modal';

export function MerchantsListPage() {
  const { t } = useTranslation();
  const actionRef = useRef<ActionType>(null);
  const { message } = App.useApp();

  const [chargeModalOpen, setChargeModalOpen] = useState(false);
  const [transactionsModalOpen, setTransactionsModalOpen] = useState(false);
  const [reviewModalOpen, setReviewModalOpen] = useState(false);
  const [selectedMerchant, setSelectedMerchant] = useState<Merchant | null>(null);
  const [statusLoading, setStatusLoading] = useState<string | null>(null);

  const handleStatusChange = async (merchant: Merchant, enabled: boolean) => {
    setStatusLoading(merchant.id);
    try {
      await merchantApi.updateMerchantStatus(merchant.id, enabled);
      message.success(enabled ? t('merchants.enabled') : t('merchants.disabled'));
      actionRef.current?.reload();
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setStatusLoading(null);
    }
  };

  const handleInitWallets = async (merchant: Merchant) => {
    try {
      await merchantApi.initMerchantWallets(merchant.id);
      message.success(t('merchants.walletsInitialized'));
      actionRef.current?.reload();
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    }
  };

  const handleOpenCharge = (merchant: Merchant) => {
    setSelectedMerchant(merchant);
    setChargeModalOpen(true);
  };

  const handleOpenTransactions = (merchant: Merchant) => {
    setSelectedMerchant(merchant);
    setTransactionsModalOpen(true);
  };

  const handleOpenReview = (merchant: Merchant) => {
    setSelectedMerchant(merchant);
    setReviewModalOpen(true);
  };

  const columns: ProColumns<Merchant>[] = [
    {
      title: t('merchants.name'),
      dataIndex: 'name',
      ellipsis: true,
      fieldProps: {
        placeholder: t('common.search') + '...',
      },
    },
    {
      title: t('merchants.email'),
      dataIndex: 'email',
      ellipsis: true,
      width: 180,
      fieldProps: {
        placeholder: t('common.search') + '...',
      },
    },
    {
      title: t('merchants.contactName'),
      dataIndex: 'contactName',
      width: 100,
      search: false,
    },
    {
      title: t('merchants.contactPhone'),
      dataIndex: 'contactPhone',
      width: 130,
      search: false,
    },
    {
      title: t('merchants.depositBalance'),
      dataIndex: 'depositBalance',
      width: 120,
      search: false,
      render: (_, record) => (
        <span className={parseFloat(record.depositBalance) > 0 ? 'text-green-600' : ''}>
          {record.depositBalance}
        </span>
      ),
    },
    {
      title: t('merchants.status'),
      dataIndex: 'status',
      width: 100,
      valueType: 'select',
      valueEnum: {
        pending: { text: t('merchants.statusPending'), status: 'Warning' },
        approved: { text: t('merchants.statusApproved'), status: 'Success' },
        rejected: { text: t('merchants.statusRejected'), status: 'Error' },
        disabled: { text: t('merchants.statusDisabled'), status: 'Default' },
      },
      render: (_, record) => {
        const colorMap: Record<string, string> = {
          pending: 'warning',
          approved: 'success',
          rejected: 'error',
          disabled: 'default',
        };
        return (
          <Tag color={colorMap[record.status]}>
            {t(`merchants.status${record.status.charAt(0).toUpperCase() + record.status.slice(1)}`)}
          </Tag>
        );
      },
    },
    {
      title: t('merchants.enableSwitch'),
      dataIndex: 'enabled',
      width: 100,
      search: false,
      render: (_, record) => {
        const isEnabled = record.status === 'approved';
        const isDisabled = record.status === 'pending' || record.status === 'rejected';
        const isLoading = statusLoading === record.id;

        return (
          <Popconfirm
            title={isEnabled ? t('merchants.confirmDisable') : t('merchants.confirmEnable')}
            description={isEnabled ? t('merchants.confirmDisableDesc') : t('merchants.confirmEnableDesc')}
            onConfirm={() => handleStatusChange(record, !isEnabled)}
            okText={t('common.confirm')}
            cancelText={t('common.cancel')}
            disabled={isDisabled || isLoading}
          >
            <Switch
              checked={isEnabled}
              disabled={isDisabled}
              loading={isLoading}
              size="small"
            />
          </Popconfirm>
        );
      },
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
      width: 220,
      render: (_, record) => (
        <Space size="small">
          {record.status === 'pending' && (
            <Button
              type="primary"
              size="small"
              icon={<AuditOutlined />}
              onClick={() => handleOpenReview(record)}
            >
              {t('merchants.review')}
            </Button>
          )}
          {record.status !== 'pending' && !record.hasWallets && (
            <Button
              type="link"
              size="small"
              icon={<PlusCircleOutlined />}
              onClick={() => handleInitWallets(record)}
            >
              {t('merchants.initWallets')}
            </Button>
          )}
          {record.status !== 'pending' && record.hasWallets && (
            <>
              <Button
                type="link"
                size="small"
                icon={<WalletOutlined />}
                onClick={() => handleOpenCharge(record)}
              >
                {t('merchants.charge')}
              </Button>
              <Button
                type="link"
                size="small"
                icon={<HistoryOutlined />}
                onClick={() => handleOpenTransactions(record)}
              >
                {t('merchants.transactions')}
              </Button>
            </>
          )}
        </Space>
      ),
    },
  ];

  return (
    <div className="space-y-4">
      <div className="mb-4">
        <h1 className="text-xl font-semibold">{t('merchants.title')}</h1>
        <p className="text-gray-500">{t('merchants.description')}</p>
      </div>

      <ProTable<Merchant>
        actionRef={actionRef}
        columns={columns}
        rowKey="id"
        request={async (params) => {
          try {
            const result = await merchantApi.getMerchants({
              page: params.current,
              limit: params.pageSize,
              status: params.status,
              name: params.name,
              email: params.email,
            });
            return {
              data: result.data,
              success: true,
              total: result.total,
            };
          } catch (error) {
            console.error('Failed to fetch merchants:', error);
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
      />

      <ChargeModal
        open={chargeModalOpen}
        merchant={selectedMerchant}
        onClose={() => {
          setChargeModalOpen(false);
          setSelectedMerchant(null);
        }}
        onSuccess={() => {
          setChargeModalOpen(false);
          setSelectedMerchant(null);
          actionRef.current?.reload();
        }}
      />

      <TransactionsModal
        open={transactionsModalOpen}
        merchant={selectedMerchant}
        onClose={() => {
          setTransactionsModalOpen(false);
          setSelectedMerchant(null);
        }}
      />

      <ReviewModal
        open={reviewModalOpen}
        merchant={selectedMerchant}
        onClose={() => {
          setReviewModalOpen(false);
          setSelectedMerchant(null);
        }}
        onSuccess={() => {
          setReviewModalOpen(false);
          setSelectedMerchant(null);
          actionRef.current?.reload();
        }}
      />
    </div>
  );
}
