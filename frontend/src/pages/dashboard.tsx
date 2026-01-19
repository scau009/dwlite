import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router';
import { useTranslation } from 'react-i18next';
import { Card, Statistic, Row, Col, Table, Tag, Spin, Empty } from 'antd';
import {
  ShoppingCartOutlined,
  DollarOutlined,
  ExclamationCircleOutlined,
  WarningOutlined,
  ArrowUpOutlined,
  ArrowDownOutlined,
} from '@ant-design/icons';
import { Line } from '@ant-design/charts';

import {
  adminDashboardApi,
  type DashboardData,
  type TrendItem,
  type RecentData,
} from '@/lib/admin-dashboard-api';

export function DashboardPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [data, setData] = useState<DashboardData | null>(null);
  const [trend, setTrend] = useState<TrendItem[]>([]);
  const [recent, setRecent] = useState<RecentData | null>(null);

  useEffect(() => {
    const fetchData = async () => {
      try {
        setLoading(true);
        setError(null);
        const [dashRes, trendRes, recentRes] = await Promise.all([
          adminDashboardApi.getDashboard(),
          adminDashboardApi.getTrend(),
          adminDashboardApi.getRecent(),
        ]);
        setData(dashRes.data);
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

  const getStatusColor = (status: string) => {
    const colors: Record<string, string> = {
      pending: 'warning',
      allocating: 'processing',
      allocated: 'cyan',
      allocation_failed: 'error',
      fulfilling: 'blue',
      shipped: 'geekblue',
      delivered: 'purple',
      completed: 'success',
      cancelled: 'default',
      processing: 'processing',
      rejected: 'error',
      expired: 'orange',
    };
    return colors[status] || 'default';
  };

  const getExceptionTypeLabel = (type: string) => {
    const labels: Record<string, string> = {
      inventory_insufficient: t('orderException.typeInventoryInsufficient'),
      allocation_failed: t('orderException.typeAllocationFailed'),
      fulfillment_rejected: t('orderException.typeFulfillmentRejected'),
      fulfillment_expired: t('orderException.typeFulfillmentExpired'),
    };
    return labels[type] || type;
  };

  // Order columns for recent orders table
  const orderColumns = [
    {
      title: t('orders.orderNo'),
      dataIndex: 'orderNo',
      key: 'orderNo',
      ellipsis: true,
    },
    {
      title: t('common.channel'),
      dataIndex: 'channelName',
      key: 'channelName',
      render: (v: string | null) => v || '-',
    },
    {
      title: t('orders.amount'),
      dataIndex: 'totalAmount',
      key: 'totalAmount',
      render: (v: string) => `¥${parseFloat(v).toFixed(2)}`,
    },
    {
      title: t('orders.status'),
      dataIndex: 'status',
      key: 'status',
      render: (status: string) => <Tag color={getStatusColor(status)}>{status}</Tag>,
    },
  ];

  // Exception columns
  const exceptionColumns = [
    {
      title: t('orderException.exceptionNo'),
      dataIndex: 'exceptionNo',
      key: 'exceptionNo',
      ellipsis: true,
    },
    {
      title: t('orderException.type'),
      dataIndex: 'type',
      key: 'type',
      render: (type: string) => getExceptionTypeLabel(type),
    },
    {
      title: t('orders.status'),
      dataIndex: 'status',
      key: 'status',
      render: (status: string) => <Tag color={getStatusColor(status)}>{status}</Tag>,
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
    { date: item.date, type: t('dashboard.orders'), value: item.orderCount },
    { date: item.date, type: t('dashboard.fulfillments'), value: item.fulfillmentCount },
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

  const GrowthIndicator = ({ value }: { value: number }) => {
    if (value === 0) return null;
    const isPositive = value > 0;
    return (
      <span className={`text-sm ml-2 ${isPositive ? 'text-green-500' : 'text-red-500'}`}>
        {isPositive ? <ArrowUpOutlined /> : <ArrowDownOutlined />} {Math.abs(value)}%
      </span>
    );
  };

  return (
    <div className="space-y-4">
      {/* KPI Summary Cards */}
      <Row gutter={[16, 16]}>
        <Col xs={24} sm={12} lg={6}>
          <Card hoverable onClick={() => navigate('/fulfillment/orders')} className="h-full">
            <Statistic
              title={t('dashboard.todayOrders')}
              value={data.summary.todayOrders}
              prefix={<ShoppingCartOutlined />}
              suffix={<GrowthIndicator value={data.summary.todayOrdersGrowth} />}
            />
            <p className="text-gray-400 text-sm mt-2">{t('dashboard.vsYesterday')}</p>
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card hoverable onClick={() => navigate('/fulfillment/orders')} className="h-full">
            <Statistic
              title={t('dashboard.todayRevenue')}
              value={parseFloat(data.summary.todayRevenue)}
              prefix={<DollarOutlined />}
              precision={2}
              suffix={<GrowthIndicator value={data.summary.todayRevenueGrowth} />}
            />
            <p className="text-gray-400 text-sm mt-2">{t('dashboard.vsYesterday')}</p>
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card
            hoverable
            onClick={() => navigate('/fulfillment/order-exceptions')}
            className="h-full"
          >
            <Statistic
              title={t('dashboard.pendingExceptions')}
              value={data.summary.pendingExceptions}
              prefix={<ExclamationCircleOutlined style={{ color: '#faad14' }} />}
            />
            <p className="text-gray-400 text-sm mt-2">{t('dashboard.needsAttention')}</p>
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card
            hoverable
            onClick={() => navigate('/fulfillment/orders?status=allocation_failed')}
            className="h-full"
          >
            <Statistic
              title={t('dashboard.allocationFailed')}
              value={data.summary.allocationFailed}
              prefix={<WarningOutlined style={{ color: '#ff4d4f' }} />}
            />
            <p className="text-gray-400 text-sm mt-2">{t('dashboard.needsAttention')}</p>
          </Card>
        </Col>
      </Row>

      {/* Trend Chart */}
      <Card title={t('dashboard.trendTitle')} style={{ marginBottom: 16 }}>
        {trendChartData.length > 0 ? (
          <Line {...lineConfig} height={280} />
        ) : (
          <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} />
        )}
      </Card>

      {/* Recent Items Tables */}
      <Row gutter={[16, 16]}>
        <Col xs={24} lg={12}>
          <Card
            title={t('dashboard.recentOrders')}
            extra={<a onClick={() => navigate('/fulfillment/orders')}>{t('dashboard.viewAll')}</a>}
          >
            <Table
              dataSource={recent?.recentOrders}
              columns={orderColumns}
              rowKey="id"
              pagination={false}
              size="small"
              onRow={(record) => ({
                onClick: () => navigate(`/fulfillment/orders/${record.id}`),
                style: { cursor: 'pointer' },
              })}
            />
          </Card>
        </Col>
        <Col xs={24} lg={12}>
          <Card
            title={t('dashboard.recentExceptions')}
            extra={
              <a onClick={() => navigate('/fulfillment/order-exceptions')}>
                {t('dashboard.viewAll')}
              </a>
            }
          >
            <Table
              dataSource={recent?.recentExceptions}
              columns={exceptionColumns}
              rowKey="id"
              pagination={false}
              size="small"
              onRow={(record) => ({
                onClick: () => navigate(`/fulfillment/order-exceptions/${record.id}`),
                style: { cursor: 'pointer' },
              })}
            />
          </Card>
        </Col>
      </Row>
    </div>
  );
}
