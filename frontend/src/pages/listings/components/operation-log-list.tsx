import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Timeline, Card, Tag, Empty, Spin, Typography, Collapse, Descriptions } from 'antd';
import dayjs from 'dayjs';

import {
  merchantListingApi,
  type ListingOperationLog,
  type OperationType,
} from '@/lib/merchant-listing-api';

const { Text } = Typography;

interface OperationLogListProps {
  listingId: string;
  limit?: number;
}

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

export function OperationLogList({ listingId, limit = 10 }: OperationLogListProps) {
  const { t } = useTranslation();
  const [logs, setLogs] = useState<ListingOperationLog[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchLogs = async () => {
      try {
        setLoading(true);
        const response = await merchantListingApi.getListingLogs(listingId, limit);
        setLogs(response.data);
      } catch {
        // Silent fail - just show empty state
      } finally {
        setLoading(false);
      }
    };

    fetchLogs();
  }, [listingId, limit]);

  if (loading) {
    return (
      <div className="flex justify-center py-8">
        <Spin />
      </div>
    );
  }

  if (logs.length === 0) {
    return <Empty description={t('listingManagement.noLogs')} />;
  }

  return (
    <Timeline
      items={logs.map((log) => ({
        color: OPERATION_COLORS[log.operation],
        children: (
          <Card size="small" className="mb-2">
            <div className="flex items-start justify-between">
              <div className="flex items-center gap-2">
                <Tag color={OPERATION_COLORS[log.operation]}>
                  {getOperationLabel(log.operation, t)}
                </Tag>
                <Text type="secondary">{log.operatorEmail}</Text>
              </div>
              <Text type="secondary" className="text-xs">
                {dayjs(log.createdAt).format('YYYY-MM-DD HH:mm:ss')}
              </Text>
            </div>

            {log.changes && (log.changes.before || log.changes.after) && (
              <Collapse
                ghost
                size="small"
                className="mt-2"
                items={[
                  {
                    key: 'changes',
                    label: t('listingManagement.changeDetails'),
                    children: (
                      <Descriptions size="small" column={1} bordered>
                        {log.changes.before &&
                          Object.entries(log.changes.before).map(([key, value]) => (
                            <Descriptions.Item
                              key={`before-${key}`}
                              label={
                                <span>
                                  <Text type="secondary">
                                    {t('listingManagement.beforeChange')}
                                  </Text>
                                  <Text className="ml-1">{key}</Text>
                                </span>
                              }
                            >
                              <Text delete type="secondary">
                                {formatChangeValue(value)}
                              </Text>
                            </Descriptions.Item>
                          ))}
                        {log.changes.after &&
                          Object.entries(log.changes.after).map(([key, value]) => (
                            <Descriptions.Item
                              key={`after-${key}`}
                              label={
                                <span>
                                  <Text type="success">
                                    {t('listingManagement.afterChange')}
                                  </Text>
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
            )}
          </Card>
        ),
      }))}
    />
  );
}
