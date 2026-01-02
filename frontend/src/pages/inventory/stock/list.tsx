import { useRef, useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { ProTable, type ActionType, type ProColumns } from '@ant-design/pro-components';
import { Tag, Statistic, Card, Row, Col, Image, Select, Space, Tooltip } from 'antd';
import {
  InboxOutlined,
  ShoppingOutlined,
  ExclamationCircleOutlined,
  TruckOutlined,
  LockOutlined,
  WarningOutlined,
} from '@ant-design/icons';

import {
  merchantInventoryApi,
  type MerchantInventoryItem,
  type MerchantInventorySummary,
  type StockStatus,
  type InventoryWarehouse,
} from '@/lib/inbound-api';

export function MerchantStockListPage() {
  const { t } = useTranslation();
  const actionRef = useRef<ActionType>(null);

  const [summary, setSummary] = useState<MerchantInventorySummary | null>(null);
  const [warehouses, setWarehouses] = useState<InventoryWarehouse[]>([]);
  const [selectedWarehouse, setSelectedWarehouse] = useState<string | undefined>(undefined);
  const [selectedStatus, setSelectedStatus] = useState<StockStatus | undefined>(undefined);

  // Load summary
  const loadSummary = async () => {
    try {
      const response = await merchantInventoryApi.getInventorySummary();
      setSummary(response.data);
    } catch (error) {
      console.error('Failed to load inventory summary:', error);
    }
  };

  // Load warehouses
  const loadWarehouses = async () => {
    try {
      const response = await merchantInventoryApi.getWarehouses();
      setWarehouses(response.data);
    } catch (error) {
      console.error('Failed to load warehouses:', error);
    }
  };

  useEffect(() => {
    loadSummary();
    loadWarehouses();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Stock status options
  const stockStatusOptions = [
    { value: 'has_stock', label: t('merchantStock.statusHasStock') },
    { value: 'in_transit', label: t('merchantStock.statusInTransit') },
    { value: 'available', label: t('merchantStock.statusAvailable') },
    { value: 'reserved', label: t('merchantStock.statusReserved') },
    { value: 'damaged', label: t('merchantStock.statusDamaged') },
    { value: 'low_stock', label: t('merchantStock.statusLowStock') },
  ];

  const columns: ProColumns<MerchantInventoryItem>[] = [
    {
      title: t('merchantStock.productImage'),
      dataIndex: ['product', 'primaryImage'],
      width: 80,
      search: false,
      render: (_, record) => {
        const image = record.product?.primaryImage;
        return image ? (
          <Image src={image} width={60} height={60} style={{ objectFit: 'cover' }} />
        ) : (
          <div className="w-[60px] h-[60px] bg-gray-100 flex items-center justify-center text-gray-400">
            N/A
          </div>
        );
      },
    },
    {
      title: t('merchantStock.productName'),
      dataIndex: ['product', 'name'],
      width: 180,
      ellipsis: true,
      fieldProps: {
        placeholder: t('merchantStock.searchPlaceholder'),
      },
    },
    {
      title: t('merchantStock.styleNumber'),
      dataIndex: ['product', 'styleNumber'],
      width: 120,
      search: false,
      render: (_, record) => record.product?.styleNumber || '-',
    },
    {
      title: t('merchantStock.skuName'),
      dataIndex: ['sku', 'skuName'],
      width: 80,
      search: false,
    },
    {
      title: t('merchantStock.warehouse'),
      dataIndex: ['warehouse', 'name'],
      width: 120,
      search: false,
      render: (_, record) => (
        <Tooltip title={record.warehouse.code}>
          <Tag>{record.warehouse.name}</Tag>
        </Tooltip>
      ),
    },
    {
      title: t('merchantStock.inTransit'),
      dataIndex: 'quantityInTransit',
      width: 80,
      search: false,
      align: 'center',
      render: (_, record) => (
        <span className={record.quantityInTransit > 0 ? 'text-blue-500' : 'text-gray-400'}>
          {record.quantityInTransit}
        </span>
      ),
    },
    {
      title: t('merchantStock.available'),
      dataIndex: 'quantityAvailable',
      width: 80,
      search: false,
      align: 'center',
      render: (_, record) => (
        <span
          className={
            record.isBelowSafetyStock
              ? 'text-orange-500'
              : record.quantityAvailable > 0
                ? 'text-green-500 font-medium'
                : 'text-gray-400'
          }
        >
          {record.quantityAvailable}
          {record.isBelowSafetyStock && (
            <Tooltip title={t('merchantStock.belowSafetyStock')}>
              <WarningOutlined className="ml-1 text-orange-500" />
            </Tooltip>
          )}
        </span>
      ),
    },
    {
      title: t('merchantStock.reserved'),
      dataIndex: 'quantityReserved',
      width: 80,
      search: false,
      align: 'center',
      render: (_, record) => (
        <span className={record.quantityReserved > 0 ? 'text-purple-500' : 'text-gray-400'}>
          {record.quantityReserved}
        </span>
      ),
    },
    {
      title: t('merchantStock.damaged'),
      dataIndex: 'quantityDamaged',
      width: 80,
      search: false,
      align: 'center',
      render: (_, record) => (
        <span className={record.quantityDamaged > 0 ? 'text-red-500' : 'text-gray-400'}>
          {record.quantityDamaged}
        </span>
      ),
    },
    {
      title: t('merchantStock.averageCost'),
      dataIndex: 'averageCost',
      width: 100,
      search: false,
      align: 'right',
      render: (_, record) => {
        const value = record.averageCost ? parseFloat(record.averageCost) : 0;
        return value > 0 ? `¥${value.toFixed(2)}` : '-';
      },
    },
    {
      title: t('merchantStock.safetyStock'),
      dataIndex: 'safetyStock',
      width: 80,
      search: false,
      align: 'center',
      render: (_, record) => (record.safetyStock !== null ? record.safetyStock : '-'),
    },
    {
      title: t('merchantStock.lastInbound'),
      dataIndex: 'lastInboundAt',
      width: 140,
      search: false,
      render: (_, record) =>
        record.lastInboundAt ? new Date(record.lastInboundAt).toLocaleDateString() : '-',
    },
    {
      title: t('common.updatedAt'),
      dataIndex: 'updatedAt',
      width: 160,
      search: false,
      render: (_, record) => new Date(record.updatedAt).toLocaleString(),
    },
  ];

  return (
    <div className="space-y-4">
      <div className="mb-4">
        <h1 className="text-xl font-semibold">{t('merchantStock.title')}</h1>
        <p className="text-gray-500">{t('merchantStock.description')}</p>
      </div>

      {/* Summary Cards */}
      <Row gutter={16} className="mb-4">
        <Col xs={12} sm={6} lg={4}>
          <Card size="small">
            <Statistic
              title={t('merchantStock.totalSkuCount')}
              value={summary?.totalSkuCount || 0}
              prefix={<ShoppingOutlined />}
            />
          </Card>
        </Col>
        <Col xs={12} sm={6} lg={4}>
          <Card size="small">
            <Statistic
              title={t('merchantStock.totalInTransit')}
              value={summary?.totalInTransit || 0}
              prefix={<TruckOutlined />}
              valueStyle={{ color: '#1890ff' }}
            />
          </Card>
        </Col>
        <Col xs={12} sm={6} lg={4}>
          <Card size="small">
            <Statistic
              title={t('merchantStock.totalAvailable')}
              value={summary?.totalAvailable || 0}
              prefix={<InboxOutlined />}
              valueStyle={{ color: '#52c41a' }}
            />
          </Card>
        </Col>
        <Col xs={12} sm={6} lg={4}>
          <Card size="small">
            <Statistic
              title={t('merchantStock.totalReserved')}
              value={summary?.totalReserved || 0}
              prefix={<LockOutlined />}
              valueStyle={{ color: '#722ed1' }}
            />
          </Card>
        </Col>
        <Col xs={12} sm={6} lg={4}>
          <Card size="small">
            <Statistic
              title={t('merchantStock.totalDamaged')}
              value={summary?.totalDamaged || 0}
              prefix={<ExclamationCircleOutlined />}
              valueStyle={{ color: '#ff4d4f' }}
            />
          </Card>
        </Col>
        <Col xs={12} sm={6} lg={4}>
          <Card size="small">
            <Statistic
              title={t('merchantStock.warehouseCount')}
              value={summary?.warehouseCount || 0}
              prefix={<InboxOutlined />}
            />
          </Card>
        </Col>
      </Row>

      <ProTable<MerchantInventoryItem>
        actionRef={actionRef}
        columns={columns}
        rowKey="id"
        params={{
          warehouseId: selectedWarehouse,
          stockStatus: selectedStatus,
        }}
        request={async params => {
          try {
            const result = await merchantInventoryApi.getInventoryList({
              page: params.current,
              limit: params.pageSize,
              search: params.name,
              warehouseId: params.warehouseId,
              stockStatus: params.stockStatus as StockStatus,
            });
            return {
              data: result.data,
              success: true,
              total: result.meta.total,
            };
          } catch (error) {
            console.error('Failed to fetch inventory:', error);
            return {
              data: [],
              success: false,
              total: 0,
            };
          }
        }}
        toolBarRender={() => [
          <Space key="filters" wrap>
            <Select
              placeholder={t('merchantStock.selectWarehouse')}
              allowClear
              style={{ width: 160 }}
              value={selectedWarehouse}
              onChange={value => {
                setSelectedWarehouse(value);
                actionRef.current?.reload();
              }}
              options={warehouses.map(w => ({ value: w.id, label: w.name }))}
            />
            <Select
              placeholder={t('merchantStock.selectStatus')}
              allowClear
              style={{ width: 140 }}
              value={selectedStatus}
              onChange={value => {
                setSelectedStatus(value);
                actionRef.current?.reload();
              }}
              options={stockStatusOptions}
            />
          </Space>,
        ]}
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
        scroll={{ x: 1400 }}
      />
    </div>
  );
}
