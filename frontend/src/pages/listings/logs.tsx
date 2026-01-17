import { useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';
import { ProTable, type ActionType, type ProColumns } from '@ant-design/pro-components';
import { Tag, Typography, Collapse, Descriptions } from 'antd';
import dayjs from 'dayjs';

import {
  merchantListingApi,
  type ListingOperationLog,
  type OperationType,
  type OperationLogListParams,
} from '@/lib/merchant-listing-api';

const { Text } = Typography;

// Operation type to tag color mapping
const OPERATION_COLORS: Record<OperationType, string> = {
  create: 'green',
  update_price: 'blue',
  update_compare_price: 'blue',
  update_allocation: 'orange',
  update_remark: 'default',
  activate: 'success',
  pause: 'warning',
  delete: 'error',
};

// Get localized operation name
const getOperationLabel = (
  operation: OperationType,
  t: (key: string) => string
): string => {
  const labels: Record<OperationType, string> = {
    create: t('listingManagement.operationCreate'),
    update_price: t('listingManagement.operationUpdatePrice'),
    update_compare_price: t('listingManagement.operationUpdateComparePrice'),
    update_allocation: t('listingManagement.operationUpdateAllocation'),
    update_remark: t('listingManagement.operationUpdateRemark'),
    activate: t('listingManagement.operationActivate'),
    pause: t('listingManagement.operationPause'),
    delete: t('listingManagement.operationDelete'),
  };
  return labels[operation] || operation;
};

// Format change value for display
const formatChangeValue = (value: unknown): string => {
  if (value === null || value === undefined) {
    return '-';
  }
  if (typeof value === 'object') {
    return JSON.stringify(value);
  }
  return String(value);
};

export function ListingLogsPage() {
  const { t } = useTranslation();
  const actionRef = useRef<ActionType>(null);

  const columns: ProColumns<ListingOperationLog>[] = [
    {
      title: t('listingManagement.operationTime'),
      dataIndex: 'createdAt',
      width: 180,
      sorter: true,
      hideInSearch: true,
      render: (_, record) => dayjs(record.createdAt).format('YYYY-MM-DD HH:mm:ss'),
    },
    {
      title: t('listingManagement.operationType'),
      dataIndex: 'operation',
      width: 140,
      valueEnum: {
        create: { text: t('listingManagement.operationCreate') },
        update_price: { text: t('listingManagement.operationUpdatePrice') },
        update_compare_price: { text: t('listingManagement.operationUpdateComparePrice') },
        update_allocation: { text: t('listingManagement.operationUpdateAllocation') },
        update_remark: { text: t('listingManagement.operationUpdateRemark') },
        activate: { text: t('listingManagement.operationActivate') },
        pause: { text: t('listingManagement.operationPause') },
        delete: { text: t('listingManagement.operationDelete') },
      },
      render: (_, record) => (
        <Tag color={OPERATION_COLORS[record.operation]}>
          {getOperationLabel(record.operation, t)}
        </Tag>
      ),
    },
    {
      title: t('listingManagement.operator'),
      dataIndex: 'operatorEmail',
      width: 200,
      ellipsis: true,
      hideInSearch: true,
    },
    {
      title: 'Listing ID',
      dataIndex: 'listingId',
      width: 120,
      ellipsis: true,
      render: (_, record) => (
        <Link to={`/channels/listings/${record.listingId}/edit`}>
          {record.listingId.slice(0, 8)}...
        </Link>
      ),
    },
    {
      title: t('listingManagement.changeDetails'),
      dataIndex: 'changes',
      hideInSearch: true,
      render: (_, record) => {
        if (!record.changes || (!record.changes.before && !record.changes.after)) {
          return <Text type="secondary">-</Text>;
        }

        return (
          <Collapse
            ghost
            size="small"
            items={[
              {
                key: 'changes',
                label: t('listingManagement.changeDetails'),
                children: (
                  <Descriptions size="small" column={1}>
                    {record.changes.before &&
                      Object.entries(record.changes.before).map(([key, value]) => (
                        <Descriptions.Item
                          key={`before-${key}`}
                          label={
                            <span>
                              <Text type="secondary">{t('listingManagement.beforeChange')}</Text>
                              <Text className="ml-1">{key}</Text>
                            </span>
                          }
                        >
                          <Text delete type="secondary">
                            {formatChangeValue(value)}
                          </Text>
                        </Descriptions.Item>
                      ))}
                    {record.changes.after &&
                      Object.entries(record.changes.after).map(([key, value]) => (
                        <Descriptions.Item
                          key={`after-${key}`}
                          label={
                            <span>
                              <Text type="success">{t('listingManagement.afterChange')}</Text>
                              <Text className="ml-1">{key}</Text>
                            </span>
                          }
                        >
                          <Text strong>{formatChangeValue(value)}</Text>
                        </Descriptions.Item>
                      ))}
                  </Descriptions>
                ),
              },
            ]}
          />
        );
      },
    },
    {
      title: t('common.dateRange'),
      dataIndex: 'dateRange',
      valueType: 'dateRange',
      hideInTable: true,
    },
  ];

  return (
      <ProTable<ListingOperationLog>
          headerTitle={t('listingManagement.operationLogsTitle')}
          actionRef={actionRef}
          rowKey="id"
          columns={columns}
          search={{
            labelWidth: 'auto',
            className: 'mb-4',
          }}
          request={async (params) => {
            const queryParams: OperationLogListParams = {
              page: params.current,
              limit: params.pageSize,
              operation: params.operation as OperationType | undefined,
              listingId: params.listingId,
            };

            // Handle date range
            if (params.dateRange) {
              queryParams.startDate = params.dateRange[0];
              queryParams.endDate = params.dateRange[1];
            }

            const result = await merchantListingApi.getOperationLogs(queryParams);

            return {
              data: result.data,
              total: result.total,
              success: true,
            };
          }}
          pagination={{
            defaultPageSize: 20,
            showSizeChanger: true,
          }}
      />
  );
}
