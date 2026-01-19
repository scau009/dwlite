import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router';
import { Card, Col, Row, Statistic, Spin, Typography } from 'antd';
import {
  InboxOutlined,
  CheckCircleOutlined,
  CarOutlined,
  SendOutlined,
} from '@ant-design/icons';
import { Line } from '@ant-design/charts';
import { useTranslation } from 'react-i18next';
import {
  warehouseOpsApi,
  type WarehouseInboundStats,
  type WarehouseOutboundStats,
  type WarehouseDashboardTrendItem,
} from '@/lib/warehouse-operations-api';

const { Title } = Typography;

export default function WarehouseDashboardPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const [loading, setLoading] = useState(true);
  const [inboundStats, setInboundStats] = useState<WarehouseInboundStats | null>(null);
  const [outboundStats, setOutboundStats] = useState<WarehouseOutboundStats | null>(null);
  const [trendData, setTrendData] = useState<WarehouseDashboardTrendItem[]>([]);

  useEffect(() => {
    const fetchData = async () => {
      setLoading(true);
      try {
        const [inboundRes, outboundRes, trendRes] = await Promise.all([
          warehouseOpsApi.getInboundStats(),
          warehouseOpsApi.getOutboundStats(),
          warehouseOpsApi.getDashboardTrend(),
        ]);
        setInboundStats(inboundRes.data);
        setOutboundStats(outboundRes.data);
        setTrendData(trendRes.data);
      } catch (error) {
        console.error('Failed to fetch dashboard data:', error);
      } finally {
        setLoading(false);
      }
    };
    fetchData();
  }, []);

  // Transform trend data for chart
  const chartData = trendData.flatMap((item) => [
    {
      date: item.date,
      value: item.inboundCount,
      category: t('warehouseOps.dashboard.inboundCompleted'),
    },
    {
      date: item.date,
      value: item.outboundCount,
      category: t('warehouseOps.dashboard.outboundCompleted'),
    },
  ]);

  const chartConfig = {
    data: chartData,
    xField: 'date',
    yField: 'value',
    colorField: 'category',
    shapeField: 'smooth',
    height: 300,
    legend: { color: { position: 'top' as const } },
    point: { size: 4, shape: 'circle' },
    style: {
      lineWidth: 2,
    },
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Spin size="large" />
      </div>
    );
  }

  return (
    <div className="p-6">
      <Title level={4} className="mb-6">
        {t('warehouseOps.dashboard.title')}
      </Title>

      {/* Combined Inbound & Outbound Statistics */}
      <div className="mb-6">
        <Title level={5} className="mb-3 text-gray-600">
          {t('warehouseOps.dashboard.inOutStats')}
        </Title>
        <Row gutter={[16, 16]}>
          <Col xs={24} sm={12} md={6}>
            <Card
              hoverable
              onClick={() => navigate('/warehouse/inbound?status=shipped')}
              className="cursor-pointer"
            >
              <Statistic
                title={t('warehouseOps.dashboard.pendingReceiving')}
                value={inboundStats?.awaitingArrival ?? 0}
                prefix={<InboxOutlined style={{ color: '#1677ff' }} />}
              />
            </Card>
          </Col>
          <Col xs={24} sm={12} md={6}>
            <Card
              hoverable
              onClick={() => navigate('/warehouse/outbound?status=ready')}
              className="cursor-pointer"
            >
              <Statistic
                title={t('warehouseOps.dashboard.pendingShipment')}
                value={outboundStats?.readyToShip ?? 0}
                prefix={<CarOutlined style={{ color: '#722ed1' }} />}
              />
            </Card>
          </Col>
          <Col xs={24} sm={12} md={6}>
            <Card>
              <Statistic
                title={t('warehouseOps.dashboard.inboundToday')}
                value={inboundStats?.completedToday ?? 0}
                prefix={<CheckCircleOutlined style={{ color: '#52c41a' }} />}
              />
            </Card>
          </Col>
          <Col xs={24} sm={12} md={6}>
            <Card>
              <Statistic
                title={t('warehouseOps.dashboard.outboundToday')}
                value={outboundStats?.shippedToday ?? 0}
                prefix={<SendOutlined style={{ color: '#52c41a' }} />}
              />
            </Card>
          </Col>
        </Row>
      </div>

      {/* Trend Chart */}
      <Card title={t('warehouseOps.dashboard.trendTitle')}>
        {chartData.length > 0 ? (
          <Line {...chartConfig} />
        ) : (
          <div className="flex items-center justify-center h-64 text-gray-400">
            {t('warehouseOps.dashboard.noData')}
          </div>
        )}
      </Card>
    </div>
  );
}
