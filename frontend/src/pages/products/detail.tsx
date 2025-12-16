import { useParams, useNavigate } from 'react-router';
import { useTranslation } from 'react-i18next';
import { ProDescriptions } from '@ant-design/pro-components';
import { Card, Button, Tabs, Tag, Space, Table, Statistic, Row, Col, App } from 'antd';
import { ArrowLeftOutlined, EditOutlined, DeleteOutlined } from '@ant-design/icons';

// Mock product data
const mockProduct = {
  id: '1',
  name: 'Premium Sneakers',
  sku: 'SKU-001',
  category: 'Footwear',
  price: 299,
  originalPrice: 399,
  stock: 150,
  status: 'on_sale' as const,
  description: 'High-quality premium sneakers with exceptional comfort and style.',
  brand: 'Nike',
  weight: '0.8kg',
  dimensions: '30 x 20 x 12 cm',
  createdAt: '2024-01-01 10:00:00',
  updatedAt: '2024-01-15 10:30:00',
};

// Mock inventory logs
const inventoryLogs = [
  { id: '1', type: 'in', quantity: 100, operator: 'Admin', time: '2024-01-15 09:00', remark: 'Initial stock' },
  { id: '2', type: 'out', quantity: 20, operator: 'System', time: '2024-01-14 15:30', remark: 'Order fulfillment' },
  { id: '3', type: 'in', quantity: 50, operator: 'Admin', time: '2024-01-13 10:00', remark: 'Restocking' },
  { id: '4', type: 'out', quantity: 30, operator: 'System', time: '2024-01-12 14:20', remark: 'Order fulfillment' },
];

// Mock price history
const priceHistory = [
  { id: '1', oldPrice: 399, newPrice: 299, operator: 'Admin', time: '2024-01-10 10:00', remark: 'Promotion' },
  { id: '2', oldPrice: 349, newPrice: 399, operator: 'Admin', time: '2024-01-01 09:00', remark: 'New season pricing' },
];

// Mock operation logs
const operationLogs = [
  { id: '1', action: 'Update', field: 'stock', oldValue: '100', newValue: '150', operator: 'Admin', time: '2024-01-15 10:30' },
  { id: '2', action: 'Update', field: 'price', oldValue: '399', newValue: '299', operator: 'Admin', time: '2024-01-10 10:00' },
  { id: '3', action: 'Update', field: 'status', oldValue: 'off_sale', newValue: 'on_sale', operator: 'Admin', time: '2024-01-05 14:00' },
];

export function ProductDetailPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { t } = useTranslation();
  const { modal, message } = App.useApp();
  const product = mockProduct;

  const handleDelete = () => {
    modal.confirm({
      title: t('confirm.deleteTitle'),
      content: t('confirm.deleteMessage'),
      okButtonProps: { danger: true },
      onOk: () => {
        console.log('Delete product:', id);
        message.success(t('common.success'));
        navigate('/products');
      },
    });
  };

  const tabItems = [
    {
      key: 'basic',
      label: t('detail.basicInfo'),
      children: (
        <ProDescriptions
          column={2}
          dataSource={product}
          columns={[
            { title: t('products.productName'), dataIndex: 'name' },
            { title: t('products.sku'), dataIndex: 'sku' },
            { title: t('products.category'), dataIndex: 'category' },
            { title: t('detail.brand'), dataIndex: 'brand' },
            { title: t('detail.weight'), dataIndex: 'weight' },
            { title: t('detail.dimensions'), dataIndex: 'dimensions' },
            { title: t('detail.description'), dataIndex: 'description', span: 2 },
            { title: t('common.createdAt'), dataIndex: 'createdAt' },
            { title: t('common.updatedAt'), dataIndex: 'updatedAt' },
          ]}
        />
      ),
    },
    {
      key: 'inventory',
      label: t('nav.inventory'),
      children: (
        <div className="space-y-4">
          <Card>
            <Statistic title={t('detail.currentStock')} value={product.stock} />
          </Card>
          <Card title={t('detail.stockLogs')}>
            <Table
              dataSource={inventoryLogs}
              rowKey="id"
              pagination={false}
              columns={[
                {
                  title: t('common.operation'),
                  dataIndex: 'type',
                  render: (type: string, record: typeof inventoryLogs[0]) => (
                    <Tag color={type === 'in' ? 'success' : 'warning'}>
                      {type === 'in' ? '+' : '-'}{record.quantity}
                    </Tag>
                  ),
                },
                { title: 'Remark', dataIndex: 'remark' },
                { title: 'Operator', dataIndex: 'operator' },
                { title: 'Time', dataIndex: 'time' },
              ]}
            />
          </Card>
        </div>
      ),
    },
    {
      key: 'pricing',
      label: t('nav.pricing'),
      children: (
        <div className="space-y-4">
          <Card>
            <Row gutter={24}>
              <Col span={12}>
                <Statistic
                  title={t('detail.currentPrice')}
                  value={product.price}
                  prefix="$"
                />
              </Col>
              <Col span={12}>
                <Statistic
                  title={t('form.originalPrice')}
                  value={product.originalPrice}
                  prefix="$"
                  valueStyle={{ textDecoration: 'line-through', color: '#999' }}
                />
              </Col>
            </Row>
          </Card>
          <Card title={t('menu.priceHistory')}>
            <Table
              dataSource={priceHistory}
              rowKey="id"
              pagination={false}
              columns={[
                {
                  title: 'Price Change',
                  render: (_, record: typeof priceHistory[0]) => (
                    <span>${record.oldPrice} → ${record.newPrice}</span>
                  ),
                },
                { title: 'Remark', dataIndex: 'remark' },
                { title: 'Operator', dataIndex: 'operator' },
                { title: 'Time', dataIndex: 'time' },
              ]}
            />
          </Card>
        </div>
      ),
    },
    {
      key: 'logs',
      label: t('menu.operationLogs'),
      children: (
        <Card>
          <Table
            dataSource={operationLogs}
            rowKey="id"
            pagination={false}
            columns={[
              {
                title: 'Action',
                dataIndex: 'action',
                render: (action: string) => <Tag color="blue">{action}</Tag>,
              },
              { title: 'Field', dataIndex: 'field' },
              {
                title: 'Change',
                render: (_, record: typeof operationLogs[0]) => (
                  <span>{record.oldValue} → {record.newValue}</span>
                ),
              },
              { title: 'Operator', dataIndex: 'operator' },
              { title: 'Time', dataIndex: 'time' },
            ]}
          />
        </Card>
      ),
    },
  ];

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex items-center justify-between">
        <a
          onClick={() => navigate('/products')}
          className="flex items-center gap-1 text-gray-500 hover:text-gray-700 cursor-pointer"
        >
          <ArrowLeftOutlined />
          <span>{t('common.back')}</span>
        </a>
        <Space>
          <Button
            icon={<EditOutlined />}
            onClick={() => navigate(`/products/${id}/edit`)}
          >
            {t('common.edit')}
          </Button>
          <Button
            danger
            icon={<DeleteOutlined />}
            onClick={handleDelete}
          >
            {t('common.delete')}
          </Button>
        </Space>
      </div>

      {/* Core Info Card */}
      <Card>
        <div className="flex items-start justify-between mb-4">
          <div>
            <h1 className="text-2xl font-bold">{product.name}</h1>
            <p className="text-gray-500">{product.sku}</p>
          </div>
          <Tag color={product.status === 'on_sale' ? 'success' : 'default'}>
            {product.status === 'on_sale' ? t('products.onSale') : t('products.offSale')}
          </Tag>
        </div>
        <Row gutter={24}>
          <Col span={8}>
            <Statistic title={t('products.price')} value={product.price} prefix="$" />
          </Col>
          <Col span={8}>
            <Statistic title={t('products.stock')} value={product.stock} />
          </Col>
          <Col span={8}>
            <Statistic title={t('products.category')} value={product.category} />
          </Col>
        </Row>
      </Card>

      {/* Tabs for detailed info */}
      <Card>
        <Tabs items={tabItems} />
      </Card>
    </div>
  );
}
