import { useEffect, useState } from 'react';
import { Card, Col, Row, Statistic, Spin, Typography } from 'antd';
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
    height: 300,
    point: {
      shapeField: 'circle',
      sizeField: 4,
    },
    interaction: {
      tooltip: {
        marker: false,
      },
    },
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

      {/* Inbound Statistics */}
      <div className="mb-4">
        <Title level={5} className="mb-3 text-gray-600">
          {t('warehouseOps.dashboard.inboundStats')}
        </Title>
        <Row gutter={[16, 16]}>
          <Col xs={24} sm={8}>
            <Card>
              <Statistic
                title={t('warehouseOps.awaitingArrival')}
                value={inboundStats?.awaitingArrival ?? 0}
                valueStyle={{ color: '#1677ff' }}
              />
            </Card>
          </Col>
          <Col xs={24} sm={8}>
            <Card>
              <Statistic
                title={t('warehouseOps.pendingReceiving')}
                value={inboundStats?.pendingReceiving ?? 0}
                valueStyle={{ color: '#722ed1' }}
              />
            </Card>
          </Col>
          <Col xs={24} sm={8}>
            <Card>
              <Statistic
                title={t('warehouseOps.completedToday')}
                value={inboundStats?.completedToday ?? 0}
                valueStyle={{ color: '#52c41a' }}
              />
            </Card>
          </Col>
        </Row>
      </div>

      {/* Outbound Statistics */}
      <div className="mb-6">
        <Title level={5} className="mb-3 text-gray-600">
          {t('warehouseOps.dashboard.outboundStats')}
        </Title>
        <Row gutter={[16, 16]}>
          <Col xs={24} sm={6}>
            <Card>
              <Statistic
                title={t('warehouseOps.pendingPicking')}
                value={outboundStats?.pendingPicking ?? 0}
                valueStyle={{ color: '#1677ff' }}
              />
            </Card>
          </Col>
          <Col xs={24} sm={6}>
            <Card>
              <Statistic
                title={t('warehouseOps.pendingPacking')}
                value={outboundStats?.pendingPacking ?? 0}
                valueStyle={{ color: '#13c2c2' }}
              />
            </Card>
          </Col>
          <Col xs={24} sm={6}>
            <Card>
              <Statistic
                title={t('warehouseOps.readyToShip')}
                value={outboundStats?.readyToShip ?? 0}
                valueStyle={{ color: '#722ed1' }}
              />
            </Card>
          </Col>
          <Col xs={24} sm={6}>
            <Card>
              <Statistic
                title={t('warehouseOps.shippedToday')}
                value={outboundStats?.shippedToday ?? 0}
                valueStyle={{ color: '#52c41a' }}
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
