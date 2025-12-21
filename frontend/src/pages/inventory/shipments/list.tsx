import { useRef } from 'react';
import { useNavigate } from 'react-router';
import { useTranslation } from 'react-i18next';
import { ProTable, type ActionType, type ProColumns } from '@ant-design/pro-components';
import { Button, Tag, Tooltip } from 'antd';
import { EyeOutlined } from '@ant-design/icons';

import { inboundApi, type InboundShipment, type InboundShipmentStatus } from '@/lib/inbound-api';

// Status color mapping
const statusColors: Record<InboundShipmentStatus, string> = {
  pending: 'default',
  picked: 'processing',
  in_transit: 'cyan',
  delivered: 'success',
  exception: 'error',
};

export function InboundShipmentsListPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const actionRef = useRef<ActionType>(null);

  // Get status label
  const getStatusLabel = (status: InboundShipmentStatus) => {
    const labels: Record<InboundShipmentStatus, string> = {
      pending: t('inventory.shipmentStatusPending'),
      picked: t('inventory.shipmentStatusPicked'),
      in_transit: t('inventory.shipmentStatusInTransit'),
      delivered: t('inventory.shipmentStatusDelivered'),
      exception: t('inventory.shipmentStatusException'),
    };
    return labels[status] || status;
  };

  // Handle view detail
  const handleView = (shipment: InboundShipment) => {
    navigate(`/inventory/shipments/detail/${shipment.id}`);
  };

  const columns: ProColumns<InboundShipment>[] = [
    {
      title: t('inventory.trackingNumber'),
      dataIndex: 'trackingNumber',
      width: 180,
      ellipsis: true,
      copyable: true,
      fieldProps: {
        placeholder: t('common.search') + '...',
      },
      render: (_, record) => (
        <a onClick={() => handleView(record)} className="font-medium font-mono">
          {record.trackingNumber}
        </a>
      ),
    },
    {
      title: t('inventory.carrierName'),
      dataIndex: 'carrierName',
      width: 120,
      search: false,
      render: (_, record) => record.carrierName || record.carrierCode,
    },
    {
      title: t('common.status'),
      dataIndex: 'status',
      width: 100,
      valueType: 'select',
      valueEnum: {
        pending: { text: t('inventory.shipmentStatusPending') },
        picked: { text: t('inventory.shipmentStatusPicked') },
        in_transit: { text: t('inventory.shipmentStatusInTransit') },
        delivered: { text: t('inventory.shipmentStatusDelivered') },
        exception: { text: t('inventory.shipmentStatusException') },
      },
      render: (_, record) => (
        <Tag color={statusColors[record.status]}>{getStatusLabel(record.status)}</Tag>
      ),
    },
    {
      title: t('inventory.senderName'),
      dataIndex: 'senderName',
      width: 100,
      search: false,
      ellipsis: true,
    },
    {
      title: t('inventory.senderPhone'),
      dataIndex: 'senderPhone',
      width: 120,
      search: false,
    },
    {
      title: t('inventory.boxCount'),
      dataIndex: 'boxCount',
      width: 80,
      search: false,
      align: 'center',
    },
    {
      title: t('inventory.totalWeight'),
      dataIndex: 'totalWeight',
      width: 100,
      search: false,
      render: (_, record) => (record.totalWeight ? `${record.totalWeight} kg` : '-'),
    },
    {
      title: t('inventory.shippedAt'),
      dataIndex: 'shippedAt',
      width: 160,
      search: false,
      sorter: true,
      defaultSortOrder: 'descend',
      render: (_, record) => new Date(record.shippedAt).toLocaleString(),
    },
    {
      title: t('inventory.estimatedArrivalDate'),
      dataIndex: 'estimatedArrivalDate',
      width: 120,
      search: false,
      render: (_, record) =>
        record.estimatedArrivalDate
          ? new Date(record.estimatedArrivalDate).toLocaleDateString()
          : '-',
    },
    {
      title: t('inventory.deliveredAt'),
      dataIndex: 'deliveredAt',
      width: 160,
      search: false,
      render: (_, record) =>
        record.deliveredAt ? new Date(record.deliveredAt).toLocaleString() : '-',
    },
    {
      title: t('common.actions'),
      valueType: 'option',
      width: 80,
      fixed: 'right',
      render: (_, record) => (
        <Tooltip title={t('common.view')}>
          <Button
            type="text"
            size="small"
            icon={<EyeOutlined />}
            onClick={() => handleView(record)}
          />
        </Tooltip>
      ),
    },
  ];

  return (
    <div className="space-y-4">
      <div className="mb-4">
        <h1 className="text-xl font-semibold">{t('inventory.shipmentsTitle')}</h1>
        <p className="text-gray-500">{t('inventory.shipmentsDescription')}</p>
      </div>

      <ProTable<InboundShipment>
        actionRef={actionRef}
        columns={columns}
        rowKey="id"
        request={async (params) => {
          try {
            const result = await inboundApi.getInboundShipments({
              page: params.current,
              limit: params.pageSize,
              status: params.status,
              search: params.trackingNumber,
            });
            return {
              data: result.data,
              success: true,
              total: result.total,
            };
          } catch (error) {
            console.error('Failed to fetch shipments:', error);
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
