import { useRef, useState, useEffect } from 'react';
import { useNavigate } from 'react-router';
import { useTranslation } from 'react-i18next';
import { ProTable, type ActionType, type ProColumns } from '@ant-design/pro-components';
import { Tag, Statistic, Card, Row, Col, Image, Tooltip, Button } from 'antd';
import {
  InboxOutlined,
  ShoppingOutlined,
  ExclamationCircleOutlined,
  TruckOutlined,
  LockOutlined,
  PlusOutlined,
  EditOutlined,
  UploadOutlined,
} from '@ant-design/icons';

import {
  merchantInventoryApi,
  type MerchantInventoryItem,
  type MerchantInventorySummary,
  type StockStatus,
  type InventoryWarehouse,
  type MerchantWarehouse,
} from '@/lib/inbound-api';
import { getCurrencySymbol } from '@/lib/merchant-listing-api';
import { AdjustInventoryModal } from './components/adjust-inventory-modal';

export function MerchantStockListPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const actionRef = useRef<ActionType>(null);

  const [summary, setSummary] = useState<MerchantInventorySummary | null>(null);
  const [warehouses, setWarehouses] = useState<InventoryWarehouse[]>([]);
  const [merchantWarehouses, setMerchantWarehouses] = useState<MerchantWarehouse[]>([]);
  const [merchantWarehouseIds, setMerchantWarehouseIds] = useState<Set<string>>(new Set());

  // Adjust modal state
  const [adjustModalOpen, setAdjustModalOpen] = useState(false);
  const [selectedInventory, setSelectedInventory] = useState<MerchantInventoryItem | null>(null);

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

  // Load merchant warehouses (for determining which inventory can be adjusted)
  const loadMerchantWarehouses = async () => {
    try {
      const response = await merchantInventoryApi.getMerchantWarehouses();
      setMerchantWarehouses(response.data);
      setMerchantWarehouseIds(new Set(response.data.map(w => w.id)));
    } catch (error) {
      console.error('Failed to load merchant warehouses:', error);
    }
  };

  // Check if inventory is from a merchant warehouse (can be adjusted)
  const canAdjustInventory = (inventory: MerchantInventoryItem) => {
    return merchantWarehouseIds.has(inventory.warehouse?.id || '');
  };

  // Handle adjust click
  const handleAdjust = (inventory: MerchantInventoryItem) => {
    setSelectedInventory(inventory);
    setAdjustModalOpen(true);
  };

  // Handle adjust modal close
  const handleAdjustModalClose = () => {
    setAdjustModalOpen(false);
    setSelectedInventory(null);
  };

  // Handle adjust success
  const handleAdjustSuccess = () => {
    setAdjustModalOpen(false);
    setSelectedInventory(null);
    actionRef.current?.reload();
    loadSummary();
  };

  useEffect(() => {
    loadSummary();
    loadWarehouses();
    loadMerchantWarehouses();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const columns: ProColumns<MerchantInventoryItem>[] = [
    {
      title: t('merchantStock.productInfo'),
      dataIndex: 'name',
      width: 280,
      ellipsis: true,
      fieldProps: {
        placeholder: t('merchantStock.searchPlaceholder'),
      },
      render: (_, record) => {
        const image = record.product?.primaryImage;
        return (
          <div className="flex gap-3">
            {image ? (
              <div className="w-12 h-12 flex-shrink-0 flex items-center justify-center bg-gray-100 rounded">
                <Image
                  src={image}
                  style={{ maxWidth: 48, maxHeight: 48, objectFit: 'contain' }}
                  preview={false}
                />
              </div>
            ) : (
              <div className="w-12 h-12 flex-shrink-0 bg-gray-100 flex items-center justify-center text-gray-400 rounded text-xs">
                N/A
              </div>
            )}
            <div className="flex-1 min-w-0">
              <div className="font-medium text-sm truncate">{record.product?.name || '-'}</div>
              <div className="text-xs text-gray-500 mt-1">
                <span className="font-mono">{record.product?.styleNumber || '-'}</span>
              </div>
            </div>
          </div>
        );
      },
    },
    {
      title: t('merchantStock.skuName'),
      key: 'skuName',
      width: 100,
      search: false,
      render: (_, record) => (
        <span className="text-sm">
          {record.sku?.sizeUnit && record.sku?.sizeValue
            ? `${record.sku.sizeUnit} ${record.sku.sizeValue}`
            : record.sku?.skuName || '-'}
        </span>
      ),
    },
    {
      title: t('merchantStock.stockStatus'),
      dataIndex: 'stockStatus',
      width: 120,
      valueType: 'select',
      valueEnum: {
        has_stock: { text: t('merchantStock.statusHasStock') },
        in_transit: { text: t('merchantStock.statusInTransit') },
        available: { text: t('merchantStock.statusAvailable') },
        reserved: { text: t('merchantStock.statusReserved') },
        damaged: { text: t('merchantStock.statusDamaged') },
      },
      hideInTable: true,
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
            record.quantityAvailable > 0
              ? 'text-green-500 font-medium'
              : 'text-gray-400'
          }
        >
          {record.quantityAvailable}
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
      title: t('merchantStock.warehouse'),
      dataIndex: 'warehouseId',
      width: 120,
      valueType: 'select',
      fieldProps: {
        placeholder: t('merchantStock.selectWarehouse'),
        options: warehouses.map(w => ({ value: w.id, label: w.name })),
      },
      render: (_, record) => {
        if (!record.warehouse) return '-';
        return (
          <Tooltip title={record.warehouse.code}>
            <Tag>{record.warehouse.name}</Tag>
          </Tooltip>
        );
      },
    },
    {
      title: t('merchantStock.averageCost'),
      dataIndex: 'averageCost',
      width: 100,
      search: false,
      align: 'right',
      render: (_, record) => (
        <span className={record.averageCost ? 'font-mono' : 'text-gray-400'}>
          {record.averageCost ? `${getCurrencySymbol(record.currency)}${record.averageCost}` : '-'}
        </span>
      ),
    },
    {
      title: t('merchantStock.barcode'),
      dataIndex: ['sku', 'barcode'],
      width: 140,
      search: false,
      ellipsis: true,
      render: (_, record) => (
        <span className={record.sku?.barcode ? 'font-mono text-xs' : 'text-gray-400'}>
          {record.sku?.barcode || '-'}
        </span>
      ),
    },
    {
      title: t('common.updatedAt'),
      dataIndex: 'updatedAt',
      width: 160,
      search: false,
      render: (_, record) => new Date(record.updatedAt).toLocaleString(),
    },
    {
      title: t('common.actions'),
      key: 'actions',
      width: 100,
      fixed: 'right',
      search: false,
      render: (_, record) => {
        if (!canAdjustInventory(record)) {
          return null;
        }
        return (
          <Button
            type="link"
            size="small"
            icon={<EditOutlined />}
            onClick={() => handleAdjust(record)}
          >
            {t('merchantStock.adjust')}
          </Button>
        );
      },
    },
  ];

  return (
    <div className="space-y-4">
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
        search={{
          labelWidth: 'auto',
          defaultCollapsed: false,
        }}
        toolBarRender={() => [
          merchantWarehouses.length > 0 && (
            <Button
              key="import"
              icon={<UploadOutlined />}
              onClick={() => navigate('/inventory/stock/import')}
            >
              {t('merchantStock.batchImport')}
            </Button>
          ),
          merchantWarehouses.length > 0 && (
            <Button
              key="add"
              type="primary"
              icon={<PlusOutlined />}
              onClick={() => navigate('/inventory/stock/add')}
            >
              {t('merchantStock.addInventory')}
            </Button>
          ),
        ]}
        options={{
          density: true,
          fullScreen: true,
          reload: true,
        }}
        pagination={{
          defaultPageSize: 20,
          showSizeChanger: true,
        }}
        scroll={{ x: 1500 }}
      />

      {/* Adjust Inventory Modal */}
      <AdjustInventoryModal
        open={adjustModalOpen}
        inventory={selectedInventory}
        onClose={handleAdjustModalClose}
        onSuccess={handleAdjustSuccess}
      />
    </div>
  );
}
