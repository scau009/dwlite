import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal, Table, Tag, Spin } from 'antd';
import type { ColumnsType, TablePaginationConfig } from 'antd/es/table';

import { merchantApi, type Merchant, type WalletTransaction, type WalletInfo } from '@/lib/merchant-api';

interface TransactionsModalProps {
  open: boolean;
  merchant: Merchant | null;
  onClose: () => void;
}

export function TransactionsModal({ open, merchant, onClose }: TransactionsModalProps) {
  const { t } = useTranslation();
  const [loading, setLoading] = useState(false);
  const [transactions, setTransactions] = useState<WalletTransaction[]>([]);
  const [wallet, setWallet] = useState<WalletInfo | null>(null);
  const [pagination, setPagination] = useState<TablePaginationConfig>({
    current: 1,
    pageSize: 10,
    total: 0,
  });

  const fetchTransactions = async (page: number, pageSize: number) => {
    if (!merchant) return;

    setLoading(true);
    try {
      const result = await merchantApi.getDepositTransactions(merchant.id, {
        page,
        limit: pageSize,
      });
      setTransactions(result.data);
      setWallet(result.wallet);
      setPagination((prev) => ({
        ...prev,
        current: page,
        pageSize,
        total: result.total,
      }));
    } catch (error) {
      console.error('Failed to fetch transactions:', error);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (open && merchant) {
      fetchTransactions(1, 10);
    }
  }, [open, merchant?.id]);

  const handleTableChange = (newPagination: TablePaginationConfig) => {
    fetchTransactions(newPagination.current || 1, newPagination.pageSize || 10);
  };

  const columns: ColumnsType<WalletTransaction> = [
    {
      title: t('merchants.transactionTime'),
      dataIndex: 'createdAt',
      width: 180,
      render: (value) => new Date(value).toLocaleString(),
    },
    {
      title: t('merchants.transactionType'),
      dataIndex: 'type',
      width: 100,
      render: (type: string) => {
        const colorMap: Record<string, string> = {
          credit: 'green',
          debit: 'red',
          freeze: 'orange',
          unfreeze: 'blue',
        };
        return <Tag color={colorMap[type]}>{t(`merchants.txType${type.charAt(0).toUpperCase() + type.slice(1)}`)}</Tag>;
      },
    },
    {
      title: t('merchants.amount'),
      dataIndex: 'amount',
      width: 120,
      render: (amount: string, record) => (
        <span className={record.type === 'credit' ? 'text-green-600' : 'text-red-600'}>
          {record.type === 'credit' ? '+' : '-'}{amount}
        </span>
      ),
    },
    {
      title: t('merchants.balanceBefore'),
      dataIndex: 'balanceBefore',
      width: 120,
    },
    {
      title: t('merchants.balanceAfter'),
      dataIndex: 'balanceAfter',
      width: 120,
    },
    {
      title: t('merchants.bizType'),
      dataIndex: 'bizType',
      width: 120,
      render: (bizType: string) => t(`merchants.biz${bizType}`, bizType),
    },
    {
      title: t('merchants.remark'),
      dataIndex: 'remark',
      ellipsis: true,
      render: (value) => value || '-',
    },
  ];

  return (
    <Modal
      title={t('merchants.depositTransactions')}
      open={open}
      onCancel={onClose}
      footer={null}
      width={1000}
      destroyOnClose
    >
      {merchant && (
        <div className="mb-4 p-3 bg-gray-50 rounded flex justify-between">
          <div>
            <p className="text-sm text-gray-600">
              {t('merchants.merchantName')}: <span className="font-medium">{merchant.name}</span>
            </p>
          </div>
          {wallet && (
            <div className="text-right">
              <p className="text-sm text-gray-600">
                {t('merchants.currentBalance')}: <span className="font-medium text-green-600">{wallet.balance}</span>
              </p>
              {wallet.frozenAmount && parseFloat(wallet.frozenAmount) > 0 && (
                <p className="text-sm text-gray-600">
                  {t('merchants.frozenAmount')}: <span className="font-medium text-orange-600">{wallet.frozenAmount}</span>
                </p>
              )}
            </div>
          )}
        </div>
      )}

      <Spin spinning={loading}>
        <Table
          columns={columns}
          dataSource={transactions}
          rowKey="id"
          pagination={pagination}
          onChange={handleTableChange}
          size="small"
          scroll={{ x: 900 }}
        />
      </Spin>
    </Modal>
  );
}
