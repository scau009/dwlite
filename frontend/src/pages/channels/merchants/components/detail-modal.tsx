import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal, Descriptions, Tag, Spin, App } from 'antd';

import { channelApi, type MerchantChannel } from '@/lib/channel-api';

interface MerchantChannelDetailModalProps {
  open: boolean;
  merchantChannel: MerchantChannel | null;
  onClose: () => void;
}

const statusColorMap: Record<string, string> = {
  pending: 'processing',
  active: 'success',
  suspended: 'warning',
  disabled: 'default',
};

export function MerchantChannelDetailModal({ open, merchantChannel, onClose }: MerchantChannelDetailModalProps) {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [loading, setLoading] = useState(false);
  const [detail, setDetail] = useState<MerchantChannel | null>(null);

  useEffect(() => {
    if (open && merchantChannel) {
      // eslint-disable-next-line react-hooks/set-state-in-effect
      setLoading(true);
      channelApi.getMerchantChannel(merchantChannel.id)
        .then((data) => {
          setDetail(data);
        })
        .catch((err) => {
          message.error(err.error || t('common.error'));
        })
        .finally(() => {
          setLoading(false);
        });
    } else {
      setDetail(null);
    }
  }, [open, merchantChannel, message, t]);

  return (
    <Modal
      title={t('merchantChannels.detailTitle')}
      open={open}
      onCancel={onClose}
      footer={null}
      width={600}
    >
      {loading ? (
        <div className="flex justify-center py-8">
          <Spin />
        </div>
      ) : detail ? (
        <Descriptions column={1} bordered size="small">
          <Descriptions.Item label={t('merchantChannels.merchant')}>
            {detail.merchant.name}
          </Descriptions.Item>
          {detail.merchant.contactName && (
            <Descriptions.Item label={t('merchantChannels.contactName')}>
              {detail.merchant.contactName}
            </Descriptions.Item>
          )}
          {detail.merchant.contactPhone && (
            <Descriptions.Item label={t('merchantChannels.contactPhone')}>
              {detail.merchant.contactPhone}
            </Descriptions.Item>
          )}
          <Descriptions.Item label={t('merchantChannels.channel')}>
            {detail.salesChannel.name} ({detail.salesChannel.code})
          </Descriptions.Item>
          <Descriptions.Item label={t('merchantChannels.status')}>
            <Tag color={statusColorMap[detail.status]}>
              {t(`merchantChannels.status${detail.status.charAt(0).toUpperCase() + detail.status.slice(1)}`)}
            </Tag>
          </Descriptions.Item>
          {detail.remark && (
            <Descriptions.Item label={t('merchantChannels.remark')}>
              {detail.remark}
            </Descriptions.Item>
          )}
          {detail.approvedAt && (
            <Descriptions.Item label={t('merchantChannels.approvedAt')}>
              {new Date(detail.approvedAt).toLocaleString()}
            </Descriptions.Item>
          )}
          <Descriptions.Item label={t('common.createdAt')}>
            {new Date(detail.createdAt).toLocaleString()}
          </Descriptions.Item>
          <Descriptions.Item label={t('common.updatedAt')}>
            {new Date(detail.updatedAt).toLocaleString()}
          </Descriptions.Item>
          {detail.config && Object.keys(detail.config).length > 0 && (
            <Descriptions.Item label={t('merchantChannels.config')}>
              <pre className="text-xs bg-gray-50 p-2 rounded overflow-auto max-h-40">
                {JSON.stringify(detail.config, null, 2)}
              </pre>
            </Descriptions.Item>
          )}
        </Descriptions>
      ) : null}
    </Modal>
  );
}
