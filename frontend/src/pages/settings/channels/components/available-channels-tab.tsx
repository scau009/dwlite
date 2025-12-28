import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { Card, List, Avatar, Button, Tag, Space, Empty, Spin, App } from 'antd';
import { ShopOutlined, SendOutlined } from '@ant-design/icons';

import {
  merchantChannelApi,
  type AvailableSalesChannel,
} from '@/lib/merchant-channel-api';
import { ApplyChannelModal } from './apply-channel-modal';

interface Props {
  onApplySuccess: () => void;
}

const businessTypeColorMap: Record<string, string> = {
  import: 'blue',
  export: 'green',
};

export function AvailableChannelsTab({ onApplySuccess }: Props) {
  const { t } = useTranslation();
  const { message } = App.useApp();

  const [loading, setLoading] = useState(true);
  const [channels, setChannels] = useState<AvailableSalesChannel[]>([]);
  const [applyModalOpen, setApplyModalOpen] = useState(false);
  const [selectedChannel, setSelectedChannel] =
    useState<AvailableSalesChannel | null>(null);

  const loadChannels = async () => {
    setLoading(true);
    try {
      const result = await merchantChannelApi.getAvailableChannels();
      setChannels(result.data);
    } catch {
      message.error(t('common.error'));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadChannels();
  }, []);

  const handleApply = (channel: AvailableSalesChannel) => {
    setSelectedChannel(channel);
    setApplyModalOpen(true);
  };

  const handleApplySuccess = () => {
    setApplyModalOpen(false);
    setSelectedChannel(null);
    loadChannels();
    onApplySuccess();
  };

  if (loading) {
    return (
      <div className="flex justify-center py-12">
        <Spin size="large" />
      </div>
    );
  }

  if (channels.length === 0) {
    return (
      <Empty
        description={t('myChannels.noAvailableChannels')}
        className="py-12"
      />
    );
  }

  return (
    <>
      <List
        grid={{ gutter: 16, xs: 1, sm: 2, md: 2, lg: 3, xl: 4 }}
        dataSource={channels}
        renderItem={(channel) => (
          <List.Item>
            <Card
              hoverable
              actions={[
                <Button
                  key="apply"
                  type="primary"
                  icon={<SendOutlined />}
                  onClick={() => handleApply(channel)}
                >
                  {t('myChannels.apply')}
                </Button>,
              ]}
            >
              <Card.Meta
                avatar={
                  channel.logoUrl ? (
                    <Avatar src={channel.logoUrl} size={48} shape="square" />
                  ) : (
                    <Avatar icon={<ShopOutlined />} size={48} shape="square" />
                  )
                }
                title={
                  <Space>
                    <span>{channel.name}</span>
                    <Tag color={businessTypeColorMap[channel.businessType]}>
                      {t(`channels.businessType.${channel.businessType}`)}
                    </Tag>
                  </Space>
                }
                description={
                  <div className="text-gray-500 line-clamp-2 h-10">
                    {channel.description || t('myChannels.noDescription')}
                  </div>
                }
              />
            </Card>
          </List.Item>
        )}
      />

      <ApplyChannelModal
        open={applyModalOpen}
        channel={selectedChannel}
        onClose={() => {
          setApplyModalOpen(false);
          setSelectedChannel(null);
        }}
        onSuccess={handleApplySuccess}
      />
    </>
  );
}
