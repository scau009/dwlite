import { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ProTable, type ActionType, type ProColumns } from '@ant-design/pro-components';
import { Image, Switch, Space } from 'antd';

import {
  warehouseOpsApi,
  type WarehouseInventoryItem,
} from '@/lib/warehouse-operations-api';

export function WarehouseInventoryListPage() {
  const { t } = useTranslation();
  const actionRef = useRef<ActionType>(null);
  const [hasStockOnly, setHasStockOnly] = useState(false);

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
      dataIndex: 'styleNumber',
      width: 120,
      fieldProps: {
        placeholder: t('common.search') + '...',
      },
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
              styleNumber: params.styleNumber,
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
        scroll={{ x: 1000 }}
      />
    </div>
  );
}