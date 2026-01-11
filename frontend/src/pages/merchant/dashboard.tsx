import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router';
import { useTranslation } from 'react-i18next';
import { Card, Statistic, Row, Col, Table, Tag, Spin, Empty, Typography } from 'antd';
import {
  InboxOutlined,
  SendOutlined,
  ExclamationCircleOutlined,
  WalletOutlined,
  DollarOutlined,
  ShoppingOutlined,
} from '@ant-design/icons';
import { Line } from '@ant-design/charts';

import {
  merchantDashboardApi,
  type MerchantDashboardData,
  type MerchantTrendItem,
  type MerchantRecentData,
} from '@/lib/merchant-dashboard-api';

const { Title } = Typography;

export default function MerchantDashboardPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [data, setData] = useState<MerchantDashboardData | null>(null);
  const [trend, setTrend] = useState<MerchantTrendItem[]>([]);
  const [recent, setRecent] = useState<MerchantRecentData | null>(null);

  useEffect(() => {
    const fetchData = async () => {
      try {
        setLoading(true);
        setError(null);
        const [dashRes, trendRes, recentRes] = await Promise.all([
          merchantDashboardApi.getDashboard(),
          merchantDashboardApi.getTrend(),
          merchantDashboardApi.getRecent(),
        ]);
        setData(dashRes);
        setTrend(trendRes.data);
        setRecent(recentRes.data);
      } catch (err) {
        console.error('Failed to load dashboard data:', err);
        setError(t('common.loadError'));
      } finally {
        setLoading(false);
      }
    };

    fetchData();
  }, [t]);

  const getInboundStatusColor = (status: string) => {
    const colors: Record<string, string> = {
      draft: 'default',
      pending: 'processing',
      shipped: 'blue',
      arrived: 'cyan',
      receiving: 'purple',
      completed: 'success',
      partial_completed: 'warning',
      cancelled: 'default',
    };
    return colors[status] || 'default';
  };

  const getOutboundStatusColor = (status: string) => {
    const colors: Record<string, string> = {
      draft: 'default',
      pending: 'processing',
      picking: 'blue',
      packing: 'cyan',
      ready: 'purple',
      shipped: 'success',
      cancelled: 'default',
    };
    return colors[status] || 'default';
  };

  const getExceptionStatusColor = (status: string) => {
    const colors: Record<string, string> = {
      pending: 'warning',
      processing: 'processing',
      resolved: 'success',
      closed: 'default',
    };
    return colors[status] || 'default';
  };

  // Helper to get status translation key (e.g., 'draft' -> 'statusDraft')
  const getStatusKey = (prefix: string, status: string) => {
    const capitalized = status.replace(/_(\w)/g, (_, c) => c.toUpperCase());
    const key = `status${capitalized.charAt(0).toUpperCase()}${capitalized.slice(1)}`;
    return `${prefix}.${key}`;
  };

  // Inbound columns
  const inboundColumns = [
    {
      title: t('inventory.orderNo'),
      dataIndex: 'orderNo',
      key: 'orderNo',
      ellipsis: true,
    },
    {
      title: t('common.quantity'),
      dataIndex: 'totalQuantity',
      key: 'totalQuantity',
    },
    {
      title: t('common.status'),
      dataIndex: 'status',
      key: 'status',
      render: (status: string) => (
        <Tag color={getInboundStatusColor(status)}>{t(getStatusKey('inventory', status))}</Tag>
      ),
    },
  ];

  // Outbound columns
  const outboundColumns = [
    {
      title: t('outbound.orderNo'),
      dataIndex: 'outboundNo',
      key: 'outboundNo',
      ellipsis: true,
    },
    {
      title: t('common.quantity'),
      dataIndex: 'totalQuantity',
      key: 'totalQuantity',
    },
    {
      title: t('common.status'),
      dataIndex: 'status',
      key: 'status',
      render: (status: string) => (
        <Tag color={getOutboundStatusColor(status)}>{t(getStatusKey('outbound', status))}</Tag>
      ),
    },
  ];

  // Helper to get exception type translation key (e.g., 'quantity_short' -> 'typeQuantityShort')
  const getExceptionTypeKey = (type: string) => {
    const parts = type.split('_');
    const capitalized = parts.map((p) => p.charAt(0).toUpperCase() + p.slice(1)).join('');
    return `inventory.type${capitalized}`;
  };

  // Helper to get exception status translation key (e.g., 'pending' -> 'exceptionStatusPending')
  const getExceptionStatusKey = (status: string) => {
    const capitalized = status.charAt(0).toUpperCase() + status.slice(1);
    return `inventory.exceptionStatus${capitalized}`;
  };

  // Exception columns
  const exceptionColumns = [
    {
      title: t('inventory.exceptionNo'),
      dataIndex: 'exceptionNo',
      key: 'exceptionNo',
      ellipsis: true,
    },
    {
      title: t('inventory.exceptionType'),
      dataIndex: 'type',
      key: 'type',
      render: (type: string) => t(getExceptionTypeKey(type)),
    },
    {
      title: t('common.status'),
      dataIndex: 'status',
      key: 'status',
      render: (status: string) => (
        <Tag color={getExceptionStatusColor(status)}>{t(getExceptionStatusKey(status))}</Tag>
      ),
    },
  ];

  if (loading) {
    return (
      <div className="flex items-center justify-center h-[50vh]">
        <Spin size="large" />
      </div>
    );
  }

  if (error) {
    return (
      <div className="flex items-center justify-center h-[50vh]">
        <Empty description={error} />
      </div>
    );
  }

  if (!data) {
    return null;
  }

  // Prepare trend chart data
  const trendChartData = trend.flatMap((item) => [
    { date: item.date, type: t('merchantDashboard.inboundCompleted'), value: item.inboundCount },
    { date: item.date, type: t('merchantDashboard.outboundShipped'), value: item.outboundCount },
  ]);

  const lineConfig = {
    data: trendChartData,
    xField: 'date',
    yField: 'value',
    colorField: 'type',
    shapeField: 'smooth',
    legend: { color: { position: 'top' as const } },
    point: { size: 4, shape: 'circle' },
    style: {
      lineWidth: 2,
    },
  };

  return (
    <div className="space-y-4">
      {/* Inventory Summary Cards */}
      <div>
        <Title level={5} className="mb-3 text-gray-600">
          {t('merchantDashboard.inventorySummary')}
        </Title>
        <Row gutter={[16, 16]}>
          <Col xs={24} sm={12} lg={6}>
            <Card hoverable onClick={() => navigate('/inventory/stock')} className="h-full">
              <Statistic
                title={t('merchantDashboard.availableStock')}
                value={data.summary.totalAvailable}
                prefix={<ShoppingOutlined style={{ color: '#52c41a' }} />}
              />
              <p className="text-gray-400 text-sm mt-2">
                {data.summary.totalSkuCount} SKUs
              </p>
            </Card>
          </Col>
          <Col xs={24} sm={12} lg={6}>
            <Card hoverable onClick={() => navigate('/inventory/stock')} className="h-full">
              <Statistic
                title={t('merchantDashboard.inTransitStock')}
                value={data.summary.totalInTransit}
                prefix={<InboxOutlined style={{ color: '#1677ff' }} />}
              />
              <p className="text-gray-400 text-sm mt-2">
                {data.summary.warehouseCount} {t('merchantDashboard.warehouses')}
              </p>
            </Card>
          </Col>
          <Col xs={24} sm={12} lg={6}>
            <Card hoverable onClick={() => navigate('/inventory/stock')} className="h-full">
              <Statistic
                title={t('merchantDashboard.reservedStock')}
                value={data.summary.totalReserved}
                prefix={<SendOutlined style={{ color: '#722ed1' }} />}
              />
            </Card>
          </Col>
          <Col xs={24} sm={12} lg={6}>
            <Card
              hoverable
              onClick={() => navigate('/inventory/inbound-exceptions')}
              className="h-full"
            >
              <Statistic
                title={t('merchantDashboard.pendingExceptions')}
                value={data.summary.pendingExceptions}
                prefix={<ExclamationCircleOutlined style={{ color: '#faad14' }} />}
              />
              <p className="text-gray-400 text-sm mt-2">{t('dashboard.needsAttention')}</p>
            </Card>
          </Col>
        </Row>
      </div>

      {/* Finance Summary Cards */}
      <div>
        <Title level={5} className="mb-3 text-gray-600">
          {t('merchantDashboard.financeSummary')}
        </Title>
        <Row gutter={[16, 16]}>
          <Col xs={24} sm={12} lg={6}>
            <Card hoverable onClick={() => navigate('/settings/merchant-wallet')} className="h-full">
              <Statistic
                title={t('merchantDashboard.availableBalance')}
                value={parseFloat(data.wallet.balance.balance)}
                prefix={<WalletOutlined style={{ color: '#52c41a' }} />}
                precision={2}
              />
              {parseFloat(data.wallet.balance.frozenAmount) > 0 && (
                <p className="text-gray-400 text-sm mt-2">
                  {t('merchantDashboard.frozen')}: {data.wallet.balance.frozenAmount}
                </p>
              )}
            </Card>
          </Col>
          <Col xs={24} sm={12} lg={6}>
            <Card hoverable onClick={() => navigate('/settings/merchant-wallet')} className="h-full">
              <Statistic
                title={t('merchantDashboard.depositBalance')}
                value={parseFloat(data.wallet.deposit.balance)}
                prefix={<DollarOutlined style={{ color: '#1677ff' }} />}
                precision={2}
              />
              {parseFloat(data.wallet.deposit.frozenAmount) > 0 && (
                <p className="text-gray-400 text-sm mt-2">
                  {t('merchantDashboard.frozen')}: {data.wallet.deposit.frozenAmount}
                </p>
              )}
            </Card>
          </Col>
          <Col xs={24} sm={12} lg={6}>
            <Card hoverable onClick={() => navigate('/merchant/settlements')} className="h-full">
              <Statistic
                title={t('merchantDashboard.pendingSettlement')}
                value={parseFloat(data.settlement.pendingAmount)}
                prefix="¥"
                precision={2}
                valueStyle={{ color: '#faad14' }}
              />
            </Card>
          </Col>
          <Col xs={24} sm={12} lg={6}>
            <Card hoverable onClick={() => navigate('/merchant/payouts')} className="h-full">
              <Statistic
                title={t('merchantDashboard.withdrawable')}
                value={parseFloat(data.payout.availableBalance)}
                prefix="¥"
                precision={2}
                valueStyle={{ color: '#52c41a' }}
              />
              {parseFloat(data.payout.processingAmount) > 0 && (
                <p className="text-gray-400 text-sm mt-2">
                  {t('merchantDashboard.processing')}: ¥{data.payout.processingAmount}
                </p>
              )}
            </Card>
          </Col>
        </Row>
      </div>

      {/* Pending Tasks */}
      <div>
        <Title level={5} className="mb-3 text-gray-600">
          {t('merchantDashboard.pendingTasks')}
        </Title>
        <Row gutter={[16, 16]}>
          <Col xs={24} sm={12}>
            <Card
              hoverable
              onClick={() => navigate('/inventory/inbound')}
            >
              <Statistic
                title={t('merchantDashboard.pendingInbounds')}
                value={data.summary.pendingInbounds}
                prefix={<InboxOutlined style={{ color: '#1677ff' }} />}
              />
            </Card>
          </Col>
          <Col xs={24} sm={12}>
            <Card
              hoverable
              onClick={() => navigate('/inventory/outbound')}
            >
              <Statistic
                title={t('merchantDashboard.pendingOutbounds')}
                value={data.summary.pendingOutbounds}
                prefix={<SendOutlined style={{ color: '#722ed1' }} />}
              />
            </Card>
          </Col>
        </Row>
      </div>

      {/* Trend Chart */}
      <Card title={t('merchantDashboard.trendTitle')} style={{ marginBottom: 16 }}>
        {trendChartData.length > 0 ? (
          <Line {...lineConfig} height={280} />
        ) : (
          <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} />
        )}
      </Card>

      {/* Recent Items Tables */}
      <Row gutter={[16, 16]}>
        <Col xs={24} lg={8}>
          <Card
            title={t('merchantDashboard.recentInbounds')}
            extra={<a onClick={() => navigate('/inventory/inbound')}>{t('dashboard.viewAll')}</a>}
            styles={{ body: { padding: '0 12px 12px' } }}
          >
            <Table
              dataSource={recent?.recentInbounds}
              columns={inboundColumns}
              rowKey="id"
              pagination={false}
              size="small"
              scroll={{ x: 'max-content' }}
              onRow={(record) => ({
                onClick: () => navigate(`/inventory/inbound/${record.id}`),
                style: { cursor: 'pointer' },
              })}
            />
          </Card>
        </Col>
        <Col xs={24} lg={8}>
          <Card
            title={t('merchantDashboard.recentOutbounds')}
            extra={<a onClick={() => navigate('/inventory/outbound')}>{t('dashboard.viewAll')}</a>}
            styles={{ body: { padding: '0 12px 12px' } }}
          >
            <Table
              dataSource={recent?.recentOutbounds}
              columns={outboundColumns}
              rowKey="id"
              pagination={false}
              size="small"
              scroll={{ x: 'max-content' }}
              onRow={(record) => ({
                onClick: () => navigate(`/inventory/outbound/${record.id}`),
                style: { cursor: 'pointer' },
              })}
            />
          </Card>
        </Col>
        <Col xs={24} lg={8}>
          <Card
            title={t('merchantDashboard.recentExceptions')}
            extra={
              <a onClick={() => navigate('/inventory/inbound-exceptions')}>
                {t('dashboard.viewAll')}
              </a>
            }
            styles={{ body: { padding: '0 12px 12px' } }}
          >
            <Table
              dataSource={recent?.recentExceptions}
              columns={exceptionColumns}
              rowKey="id"
              pagination={false}
              size="small"
              scroll={{ x: 'max-content' }}
              onRow={(record) => ({
                onClick: () => navigate(`/inventory/inbound-exceptions/${record.id}`),
                style: { cursor: 'pointer' },
              })}
            />
          </Card>
        </Col>
      </Row>
    </div>
  );
}
