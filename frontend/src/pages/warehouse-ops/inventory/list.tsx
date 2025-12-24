import { useRef, useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { ProTable, type ActionType, type ProColumns } from '@ant-design/pro-components';
import { Tag, Statistic, Card, Row, Col, Image, Switch, Space } from 'antd';
import {
  InboxOutlined,
  ShoppingOutlined,
  ExclamationCircleOutlined,
  TruckOutlined,
} from '@ant-design/icons';

import {
  warehouseOpsApi,
  type WarehouseInventoryItem,
  type WarehouseInventorySummary,
} from '@/lib/warehouse-operations-api';

export function WarehouseInventoryListPage() {
  const { t } = useTranslation();
  const actionRef = useRef<ActionType>(null);

  const [summary, setSummary] = useState<WarehouseInventorySummary | null>(null);
  const [hasStockOnly, setHasStockOnly] = useState(false);

  // Load summary
  const loadSummary = async () => {
    try {
      const response = await warehouseOpsApi.getInventorySummary();
      setSummary(response.data);
    } catch (error) {
      console.error('Failed to load inventory summary:', error);
    }
  };

  useEffect(() => {
    loadSummary();
  }, []);

  const columns: ProColumns<WarehouseInventoryItem>[] = [
    {
      title: t('warehouseOps.productImage'),
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
      title: t('warehouseOps.productName'),
      dataIndex: ['product', 'name'],
      width: 180,
      ellipsis: true,
      fieldProps: {
        placeholder: t('common.search') + '...',
      },
    },
    {
      title: t('warehouseOps.styleNumber'),
      dataIndex: ['product', 'styleNumber'],
      width: 120,
      search: false,
      render: (_, record) => record.product?.styleNumber || '-',
    },
    {
      title: t('warehouseOps.skuName'),
      dataIndex: ['sku', 'skuName'],
      width: 80,
      search: false,
      render: (_, record) => record.sku?.skuName || '-',
    },
    {
      title: t('warehouseOps.color'),
      dataIndex: ['product', 'color'],
      width: 80,
      search: false,
      render: (_, record) => record.product?.color || '-',
    },
    {
      title: t('warehouseOps.merchant'),
      dataIndex: ['merchant', 'companyName'],
      width: 120,
      ellipsis: true,
      search: false,
    },
    {
      title: t('warehouseOps.inTransit'),
      dataIndex: 'quantityInTransit',
      width: 80,
      search: false,
      align: 'center',
      render: (_, record) => (
        <span className={record.quantityInTransit > 0 ? 'text-blue-500' : ''}>
          {record.quantityInTransit}
        </span>
      ),
    },
    {
      title: t('warehouseOps.available'),
      dataIndex: 'quantityAvailable',
      width: 80,
      search: false,
      align: 'center',
      render: (_, record) => (
        <span className={record.quantityAvailable > 0 ? 'text-green-500 font-medium' : 'text-gray-400'}>
          {record.quantityAvailable}
        </span>
      ),
    },
    {
      title: t('warehouseOps.reserved'),
      dataIndex: 'quantityReserved',
      width: 80,
      search: false,
      align: 'center',
      render: (_, record) => (
        <span className={record.quantityReserved > 0 ? 'text-orange-500' : ''}>
          {record.quantityReserved}
        </span>
      ),
    },
    {
      title: t('warehouseOps.damaged'),
      dataIndex: 'quantityDamaged',
      width: 80,
      search: false,
      align: 'center',
      render: (_, record) => (
        <span className={record.quantityDamaged > 0 ? 'text-red-500' : ''}>
          {record.quantityDamaged}
        </span>
      ),
    },
    {
      title: t('warehouseOps.averageCost'),
      dataIndex: 'averageCost',
      width: 100,
      search: false,
      align: 'right',
      render: (_, record) => {
        const value = parseFloat(record.averageCost);
        return value > 0 ? `¥${value.toFixed(2)}` : '-';
      },
    },
    {
      title: t('warehouseOps.updatedAt'),
      dataIndex: 'updatedAt',
      width: 160,
      search: false,
      render: (_, record) => new Date(record.updatedAt).toLocaleString(),
    },
  ];

  return (
    <div className="space-y-4">
      <div className="mb-4">
        <h1 className="text-xl font-semibold">{t('warehouseOps.inventoryTitle')}</h1>
        <p className="text-gray-500">{t('warehouseOps.inventoryDescription')}</p>
      </div>

      {/* Summary Cards */}
      <Row gutter={16} className="mb-4">
        <Col xs={12} sm={6}>
          <Card size="small">
            <Statistic
              title={t('warehouseOps.totalSkuCount')}
              value={summary?.totalSkuCount || 0}
              prefix={<ShoppingOutlined />}
            />
          </Card>
        </Col>
        <Col xs={12} sm={6}>
          <Card size="small">
            <Statistic
              title={t('warehouseOps.totalInTransit')}
              value={summary?.totalInTransit || 0}
              prefix={<TruckOutlined />}
              valueStyle={{ color: '#1890ff' }}
            />
          </Card>
        </Col>
        <Col xs={12} sm={6}>
          <Card size="small">
            <Statistic
              title={t('warehouseOps.totalAvailable')}
              value={summary?.totalAvailable || 0}
              prefix={<InboxOutlined />}
              valueStyle={{ color: '#52c41a' }}
            />
          </Card>
        </Col>
        <Col xs={12} sm={6}>
          <Card size="small">
            <Statistic
              title={t('warehouseOps.totalDamaged')}
              value={summary?.totalDamaged || 0}
              prefix={<ExclamationCircleOutlined />}
              valueStyle={{ color: '#ff4d4f' }}
            />
          </Card>
        </Col>
      </Row>

      {/* Warehouse Info */}
      {summary?.warehouse && (
        <Card size="small" className="mb-4">
          <div className="flex items-center gap-4">
            <Tag color="blue">{summary.warehouse.code}</Tag>
            <span className="font-medium">{summary.warehouse.name}</span>
          </div>
        </Card>
      )}

      <ProTable<WarehouseInventoryItem>
        actionRef={actionRef}
        columns={columns}
        rowKey="id"
        params={{ hasStock: hasStockOnly }}
        request={async params => {
          try {
            const result = await warehouseOpsApi.getInventory({
              page: params.current,
              limit: params.pageSize,
              search: params.name,
              hasStock: params.hasStock,
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
          <Space key="filter" align="center">
            <span>{t('warehouseOps.hasStockOnly')}</span>
            <Switch
              checked={hasStockOnly}
              onChange={checked => {
                setHasStockOnly(checked);
                actionRef.current?.reload();
              }}
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
        scroll={{ x: 1300 }}
      />
    </div>
  );
}
