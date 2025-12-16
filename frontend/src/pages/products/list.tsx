import { useRef, useState } from 'react';
import { useNavigate } from 'react-router';
import { useTranslation } from 'react-i18next';
import { ProTable, type ActionType, type ProColumns } from '@ant-design/pro-components';
import { Button, Tag, Space, App } from 'antd';
import { PlusOutlined, UpOutlined, DownOutlined, ExportOutlined } from '@ant-design/icons';

// Product type
interface Product {
  id: string;
  name: string;
  sku: string;
  category: string;
  price: number;
  stock: number;
  status: 'on_sale' | 'off_sale';
  updatedAt: string;
}

// Mock data
const mockProducts: Product[] = [
  { id: '1', name: 'Premium Sneakers', sku: 'SKU-001', category: 'Footwear', price: 299, stock: 150, status: 'on_sale', updatedAt: '2024-01-15 10:30' },
  { id: '2', name: 'Designer Jacket', sku: 'SKU-002', category: 'Apparel', price: 450, stock: 80, status: 'on_sale', updatedAt: '2024-01-14 14:20' },
  { id: '3', name: 'Limited Edition Watch', sku: 'SKU-003', category: 'Accessories', price: 1299, stock: 25, status: 'on_sale', updatedAt: '2024-01-13 09:15' },
  { id: '4', name: 'Vintage Bag', sku: 'SKU-004', category: 'Accessories', price: 189, stock: 200, status: 'on_sale', updatedAt: '2024-01-12 16:45' },
  { id: '5', name: 'Street Style Hoodie', sku: 'SKU-005', category: 'Apparel', price: 129, stock: 0, status: 'off_sale', updatedAt: '2024-01-11 11:00' },
  { id: '6', name: 'Classic Sunglasses', sku: 'SKU-006', category: 'Accessories', price: 79, stock: 300, status: 'on_sale', updatedAt: '2024-01-10 08:30' },
  { id: '7', name: 'Running Shoes', sku: 'SKU-007', category: 'Footwear', price: 199, stock: 120, status: 'on_sale', updatedAt: '2024-01-09 13:20' },
  { id: '8', name: 'Casual T-Shirt', sku: 'SKU-008', category: 'Apparel', price: 39, stock: 500, status: 'on_sale', updatedAt: '2024-01-08 15:10' },
  { id: '9', name: 'Leather Wallet', sku: 'SKU-009', category: 'Accessories', price: 89, stock: 180, status: 'on_sale', updatedAt: '2024-01-07 10:00' },
  { id: '10', name: 'Winter Coat', sku: 'SKU-010', category: 'Apparel', price: 599, stock: 45, status: 'off_sale', updatedAt: '2024-01-06 09:45' },
];

export function ProductsListPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const actionRef = useRef<ActionType>(null);
  const { message, modal } = App.useApp();
  const [selectedRowKeys, setSelectedRowKeys] = useState<React.Key[]>([]);

  const columns: ProColumns<Product>[] = [
    {
      title: t('products.productName'),
      dataIndex: 'name',
      ellipsis: true,
      formItemProps: {
        label: t('products.productName') + ' / ' + t('products.sku'),
      },
      fieldProps: {
        placeholder: t('common.search') + '...',
      },
    },
    {
      title: t('products.sku'),
      dataIndex: 'sku',
      width: 120,
      search: false,
    },
    {
      title: t('products.category'),
      dataIndex: 'category',
      width: 120,
      valueType: 'select',
      valueEnum: {
        Footwear: { text: 'Footwear' },
        Apparel: { text: 'Apparel' },
        Accessories: { text: 'Accessories' },
      },
    },
    {
      title: t('products.price'),
      dataIndex: 'price',
      width: 100,
      search: false,
      sorter: true,
      render: (_, record) => `$${record.price.toFixed(2)}`,
    },
    {
      title: t('products.stock'),
      dataIndex: 'stock',
      width: 100,
      search: false,
      sorter: true,
      render: (_, record) => (
        <span style={{ color: record.stock === 0 ? '#ff4d4f' : undefined }}>
          {record.stock}
        </span>
      ),
    },
    {
      title: t('products.status'),
      dataIndex: 'status',
      width: 100,
      valueType: 'select',
      valueEnum: {
        on_sale: { text: t('products.onSale'), status: 'Success' },
        off_sale: { text: t('products.offSale'), status: 'Default' },
      },
      render: (_, record) => (
        <Tag color={record.status === 'on_sale' ? 'success' : 'default'}>
          {record.status === 'on_sale' ? t('products.onSale') : t('products.offSale')}
        </Tag>
      ),
    },
    {
      title: t('common.updatedAt'),
      dataIndex: 'updatedAt',
      width: 160,
      search: false,
      sorter: true,
    },
    {
      title: t('common.actions'),
      valueType: 'option',
      width: 120,
      render: (_, record) => [
        <a key="view" onClick={() => navigate(`/products/${record.id}`)}>
          {t('common.view')}
        </a>,
        <a key="edit" onClick={() => navigate(`/products/${record.id}/edit`)}>
          {t('common.edit')}
        </a>,
      ],
    },
  ];

  const handleBatchAction = (action: 'on_sale' | 'off_sale' | 'delete') => {
    modal.confirm({
      title: t('confirm.batchTitle'),
      content: t('confirm.batchMessage', { count: selectedRowKeys.length }),
      okButtonProps: { danger: action === 'delete' },
      onOk: () => {
        console.log(`Batch ${action}:`, selectedRowKeys);
        message.success(t('common.success'));
        setSelectedRowKeys([]);
        actionRef.current?.reload();
      },
    });
  };

  return (
    <div className="space-y-4">
      <div className="mb-4">
        <h1 className="text-xl font-semibold">{t('products.title')}</h1>
        <p className="text-gray-500">{t('products.description')}</p>
      </div>

      <ProTable<Product>
        actionRef={actionRef}
        columns={columns}
        rowKey="id"
        rowSelection={{
          selectedRowKeys,
          onChange: setSelectedRowKeys,
        }}
        request={async (params, sort) => {
          console.log('Query params:', params, sort);
          // Simulate API delay
          await new Promise((resolve) => setTimeout(resolve, 300));

          let data = [...mockProducts];

          // Filter
          if (params.name) {
            data = data.filter(
              (item) =>
                item.name.toLowerCase().includes(params.name.toLowerCase()) ||
                item.sku.toLowerCase().includes(params.name.toLowerCase())
            );
          }
          if (params.category) {
            data = data.filter((item) => item.category === params.category);
          }
          if (params.status) {
            data = data.filter((item) => item.status === params.status);
          }

          return {
            data,
            success: true,
            total: data.length,
          };
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
          defaultPageSize: 10,
          showSizeChanger: true,
        }}
        toolBarRender={() => [
          <Button
            key="add"
            type="primary"
            icon={<PlusOutlined />}
            onClick={() => navigate('/products/new')}
          >
            {t('products.addProduct')}
          </Button>,
          <Button key="export" icon={<ExportOutlined />}>
            {t('common.export')}
          </Button>,
        ]}
        tableAlertRender={({ selectedRowKeys }) => (
          <Space>
            {t('common.selected', { count: selectedRowKeys.length })}
          </Space>
        )}
        tableAlertOptionRender={() => (
          <Space>
            <Button
              size="small"
              icon={<UpOutlined />}
              onClick={() => handleBatchAction('on_sale')}
            >
              {t('products.batchOnSale')}
            </Button>
            <Button
              size="small"
              icon={<DownOutlined />}
              onClick={() => handleBatchAction('off_sale')}
            >
              {t('products.batchOffSale')}
            </Button>
          </Space>
        )}
      />
    </div>
  );
}
