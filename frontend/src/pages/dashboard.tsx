import { useNavigate } from 'react-router';
import { useTranslation } from 'react-i18next';
import { Card, Statistic, Row, Col, Table, Tag } from 'antd';
import {
  ShoppingCartOutlined,
  DollarOutlined,
  AlertOutlined,
  CarOutlined,
  ArrowUpOutlined,
} from '@ant-design/icons';

// Recent orders data
const recentOrders = [
  { id: 'ORD-001', customer: 'John Doe', amount: 299.00, status: 'completed' },
  { id: 'ORD-002', customer: 'Jane Smith', amount: 450.00, status: 'processing' },
  { id: 'ORD-003', customer: 'Bob Johnson', amount: 1299.00, status: 'pending' },
  { id: 'ORD-004', customer: 'Alice Brown', amount: 189.00, status: 'completed' },
  { id: 'ORD-005', customer: 'Charlie Wilson', amount: 129.00, status: 'shipped' },
];

// Top products data
const topProducts = [
  { id: '1', name: 'Premium Sneakers', sales: 234, revenue: 70026 },
  { id: '2', name: 'Designer Jacket', sales: 189, revenue: 85050 },
  { id: '3', name: 'Limited Edition Watch', sales: 156, revenue: 202644 },
  { id: '4', name: 'Vintage Bag', sales: 142, revenue: 26838 },
  { id: '5', name: 'Street Style Hoodie', sales: 128, revenue: 16512 },
];

export function DashboardPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'completed': return 'success';
      case 'processing': return 'processing';
      case 'pending': return 'warning';
      case 'shipped': return 'blue';
      default: return 'default';
    }
  };

  const getStatusLabel = (status: string) => {
    switch (status) {
      case 'completed': return t('orders.completed');
      case 'processing': return t('orders.processing');
      case 'pending': return t('orders.pending');
      case 'shipped': return t('orders.shipped');
      default: return status;
    }
  };

  const orderColumns = [
    {
      title: t('orders.orderNo'),
      dataIndex: 'id',
      key: 'id',
    },
    {
      title: t('orders.customer'),
      dataIndex: 'customer',
      key: 'customer',
    },
    {
      title: t('orders.amount'),
      dataIndex: 'amount',
      key: 'amount',
      render: (amount: number) => `$${amount.toFixed(2)}`,
    },
    {
      title: t('orders.status'),
      dataIndex: 'status',
      key: 'status',
      render: (status: string) => (
        <Tag color={getStatusColor(status)}>{getStatusLabel(status)}</Tag>
      ),
    },
  ];

  const productColumns = [
    {
      title: '#',
      key: 'rank',
      width: 50,
      render: (_: unknown, __: unknown, index: number) => (
        <span className="font-medium">{index + 1}</span>
      ),
    },
    {
      title: t('products.productName'),
      dataIndex: 'name',
      key: 'name',
    },
    {
      title: 'Sales',
      dataIndex: 'sales',
      key: 'sales',
    },
    {
      title: 'Revenue',
      dataIndex: 'revenue',
      key: 'revenue',
      render: (revenue: number) => `$${revenue.toLocaleString()}`,
    },
  ];

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div>
        <h1 className="text-xl font-semibold">{t('dashboard.title')}</h1>
        <p className="text-gray-500">{t('dashboard.description')}</p>
      </div>

      {/* Stats Grid */}
      <Row gutter={[16, 16]}>
        <Col xs={24} sm={12} lg={6}>
          <Card
            hoverable
            onClick={() => navigate('/orders?date=today')}
            className="h-full"
          >
            <Statistic
              title={t('dashboard.todayOrders')}
              value={128}
              prefix={<ShoppingCartOutlined />}
              suffix={
                <span className="text-sm text-green-500 ml-2">
                  <ArrowUpOutlined /> +15.2%
                </span>
              }
            />
            <p className="text-gray-400 text-sm mt-2">{t('dashboard.fromLastMonth')}</p>
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card
            hoverable
            onClick={() => navigate('/data/sales?date=today')}
            className="h-full"
          >
            <Statistic
              title={t('dashboard.todaySales')}
              value={12450}
              prefix={<DollarOutlined />}
              precision={0}
              suffix={
                <span className="text-sm text-green-500 ml-2">
                  <ArrowUpOutlined /> +20.1%
                </span>
              }
            />
            <p className="text-gray-400 text-sm mt-2">{t('dashboard.fromLastMonth')}</p>
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card
            hoverable
            onClick={() => navigate('/inventory/alerts')}
            className="h-full"
          >
            <Statistic
              title={t('dashboard.inventoryAlerts')}
              value={23}
              prefix={<AlertOutlined style={{ color: '#faad14' }} />}
            />
            <p className="text-gray-400 text-sm mt-2">{t('menu.inventoryAlerts')}</p>
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card
            hoverable
            onClick={() => navigate('/fulfillment/exceptions')}
            className="h-full"
          >
            <Statistic
              title={t('dashboard.fulfillmentExceptions')}
              value={5}
              prefix={<CarOutlined style={{ color: '#ff4d4f' }} />}
            />
            <p className="text-gray-400 text-sm mt-2">{t('menu.fulfillmentExceptions')}</p>
          </Card>
        </Col>
      </Row>

      {/* Tables Grid */}
      <Row gutter={[16, 16]}>
        <Col xs={24} lg={16}>
          <Card
            title={t('dashboard.recentOrders')}
            extra={<a onClick={() => navigate('/orders')}>{t('common.view')}</a>}
          >
            <Table
              dataSource={recentOrders}
              columns={orderColumns}
              rowKey="id"
              pagination={false}
              size="small"
              onRow={(record) => ({
                onClick: () => navigate(`/orders?id=${record.id}`),
                style: { cursor: 'pointer' },
              })}
            />
          </Card>
        </Col>
        <Col xs={24} lg={8}>
          <Card
            title={t('dashboard.topProducts')}
            extra={<a onClick={() => navigate('/products')}>{t('common.view')}</a>}
          >
            <Table
              dataSource={topProducts}
              columns={productColumns}
              rowKey="id"
              pagination={false}
              size="small"
              onRow={(record) => ({
                onClick: () => navigate(`/products/${record.id}`),
                style: { cursor: 'pointer' },
              })}
            />
          </Card>
        </Col>
      </Row>
    </div>
  );
}
