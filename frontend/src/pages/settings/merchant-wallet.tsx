import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { Card, Table, Tag, Spin, App, Statistic, Row, Col, Tabs } from 'antd';
import {
  WalletOutlined,
  SafetyCertificateOutlined,
} from '@ant-design/icons';
import type { ColumnsType, TablePaginationConfig } from 'antd/es/table';

import {
  merchantApi,
  type Wallet,
  type WalletTransaction,
  type TransactionsResponse,
} from '@/lib/merchant-api';

type WalletType = 'deposit' | 'balance';

export function MerchantWalletPage() {
  const { t } = useTranslation();
  const { message } = App.useApp();

  const [activeTab, setActiveTab] = useState<WalletType>('deposit');
  const [wallets, setWallets] = useState<{ deposit: Wallet | null; balance: Wallet | null }>({
    deposit: null,
    balance: null,
  });
  const [transactions, setTransactions] = useState<WalletTransaction[]>([]);
  const [loading, setLoading] = useState(true);
  const [tableLoading, setTableLoading] = useState(false);
  const [pagination, setPagination] = useState({
    current: 1,
    pageSize: 20,
    total: 0,
  });

  const loadWallets = async () => {
    try {
      const data = await merchantApi.getMyWallets();
      setWallets(data);
    } catch {
      message.error(t('common.error'));
    }
  };

  const loadTransactions = async (type: WalletType, page = 1, limit = 20) => {
    setTableLoading(true);
    try {
      let result: TransactionsResponse;
      if (type === 'deposit') {
        result = await merchantApi.getMyDepositTransactions({ page, limit });
      } else {
        result = await merchantApi.getMyBalanceTransactions({ page, limit });
      }
      setTransactions(result.data);
      setPagination({
        current: result.page,
        pageSize: result.limit,
        total: result.total,
      });
    } catch {
      message.error(t('common.error'));
    } finally {
      setTableLoading(false);
    }
  };

  useEffect(() => {
    const init = async () => {
      setLoading(true);
      await loadWallets();
      await loadTransactions(activeTab);
      setLoading(false);
    };
    init();
  }, []);

  const handleTabChange = (key: string) => {
    setActiveTab(key as WalletType);
    loadTransactions(key as WalletType, 1);
  };

  const handleTableChange = (paginationConfig: TablePaginationConfig) => {
    const page = paginationConfig.current || 1;
    const limit = paginationConfig.pageSize || 20;
    loadTransactions(activeTab, page, limit);
  };

  const getTypeColor = (type: string) => {
    const colors: Record<string, string> = {
      credit: 'green',
      debit: 'red',
      freeze: 'orange',
      unfreeze: 'blue',
    };
    return colors[type] || 'default';
  };

  const getTypeLabel = (type: string) => {
    const labels: Record<string, string> = {
      credit: t('merchants.txTypeCredit'),
      debit: t('merchants.txTypeDebit'),
      freeze: t('merchants.txTypeFreeze'),
      unfreeze: t('merchants.txTypeUnfreeze'),
    };
    return labels[type] || type;
  };

  const getBizTypeLabel = (bizType: string) => {
    const labels: Record<string, string> = {
      deposit_charge: t('merchants.bizdeposit_charge'),
      deposit_deduct: t('merchants.bizdeposit_deduct'),
      order_income: t('merchants.bizorder_income'),
      withdraw: t('merchants.bizwithdraw'),
      withdraw_reject: t('merchants.bizwithdraw_reject'),
      refund: t('merchants.bizrefund'),
      platform_fee: t('merchants.bizplatform_fee'),
      adjustment: t('merchants.bizadjustment'),
    };
    return labels[bizType] || bizType;
  };

  const columns: ColumnsType<WalletTransaction> = [
    {
      title: t('merchants.transactionTime'),
      dataIndex: 'createdAt',
      width: 180,
      render: (date: string) => new Date(date).toLocaleString(),
    },
    {
      title: t('merchants.transactionType'),
      dataIndex: 'type',
      width: 100,
      render: (type: string) => (
        <Tag color={getTypeColor(type)}>{getTypeLabel(type)}</Tag>
      ),
    },
    {
      title: t('merchants.bizType'),
      dataIndex: 'bizType',
      width: 120,
      render: (bizType: string) => getBizTypeLabel(bizType),
    },
    {
      title: t('merchants.amount'),
      dataIndex: 'amount',
      width: 120,
      align: 'right',
      render: (amount: string, record) => {
        const isPositive = record.type === 'credit' || record.type === 'unfreeze';
        return (
          <span className={isPositive ? 'text-green-600' : 'text-red-500'}>
            {isPositive ? '+' : '-'}¥{parseFloat(amount).toFixed(2)}
          </span>
        );
      },
    },
    {
      title: t('merchants.balanceBefore'),
      dataIndex: 'balanceBefore',
      width: 120,
      align: 'right',
      render: (amount: string) => `¥${parseFloat(amount).toFixed(2)}`,
    },
    {
      title: t('merchants.balanceAfter'),
      dataIndex: 'balanceAfter',
      width: 120,
      align: 'right',
      render: (amount: string) => `¥${parseFloat(amount).toFixed(2)}`,
    },
    {
      title: t('merchants.remark'),
      dataIndex: 'remark',
      ellipsis: true,
      render: (remark: string | null) => remark || '-',
    },
  ];

  const currentWallet = activeTab === 'deposit' ? wallets.deposit : wallets.balance;

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Spin size="large" />
      </div>
    );
  }

  const renderTransactionTable = () => (
    currentWallet ? (
      <Table
        columns={columns}
        dataSource={transactions}
        rowKey="id"
        loading={tableLoading}
        pagination={{
          ...pagination,
          showSizeChanger: true,
          showQuickJumper: true,
          showTotal: (total) => t('common.showing', { from: (pagination.current - 1) * pagination.pageSize + 1, to: Math.min(pagination.current * pagination.pageSize, total), total }),
        }}
        onChange={handleTableChange}
        scroll={{ x: 900 }}
        size="small"
      />
    ) : (
      <div className="text-center text-gray-500 py-8">
        {t('settings.walletNotInitialized')}
      </div>
    )
  );

  return (
    <div className="flex flex-col gap-4">
      {/* Header */}
      <div>
        <h1 className="text-xl font-semibold m-0">{t('settings.walletManagement')}</h1>
        <p className="text-gray-500 mt-1">{t('settings.walletManagementDesc')}</p>
      </div>

      {/* Wallet Summary Cards */}
      <Row gutter={16}>
        <Col xs={24} sm={12}>
          <Card>
            <Statistic
              title={
                <span className="flex items-center gap-2">
                  <SafetyCertificateOutlined className="text-blue-500" />
                  {t('settings.depositWallet')}
                </span>
              }
              value={parseFloat(wallets.deposit?.balance || '0').toFixed(2)}
              prefix="¥"
              valueStyle={{ color: '#1677ff' }}
            />
            {wallets.deposit && parseFloat(wallets.deposit.frozenAmount) > 0 && (
              <div className="text-gray-500 text-sm mt-2">
                {t('merchants.frozenAmount')}: ¥{parseFloat(wallets.deposit.frozenAmount).toFixed(2)}
              </div>
            )}
          </Card>
        </Col>
        <Col xs={24} sm={12}>
          <Card>
            <Statistic
              title={
                <span className="flex items-center gap-2">
                  <WalletOutlined className="text-green-500" />
                  {t('settings.balanceWallet')}
                </span>
              }
              value={parseFloat(wallets.balance?.balance || '0').toFixed(2)}
              prefix="¥"
              valueStyle={{ color: '#52c41a' }}
            />
            {wallets.balance && parseFloat(wallets.balance.frozenAmount) > 0 && (
              <div className="text-gray-500 text-sm mt-2">
                {t('merchants.frozenAmount')}: ¥{parseFloat(wallets.balance.frozenAmount).toFixed(2)}
              </div>
            )}
          </Card>
        </Col>
      </Row>

      {/* Transactions Tabs */}
      <Card>
        <Tabs
          activeKey={activeTab}
          onChange={handleTabChange}
          items={[
            {
              key: 'deposit',
              label: (
                <span className="flex items-center gap-1">
                  <SafetyCertificateOutlined />
                  {t('settings.depositTransactions')}
                </span>
              ),
              children: renderTransactionTable(),
            },
            {
              key: 'balance',
              label: (
                <span className="flex items-center gap-1">
                  <WalletOutlined />
                  {t('settings.balanceTransactions')}
                </span>
              ),
              children: renderTransactionTable(),
            },
          ]}
        />
      </Card>
    </div>
  );
}
