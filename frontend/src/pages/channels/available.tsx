import { useState, useRef } from 'react';
import { useNavigate } from 'react-router';
import { useTranslation } from 'react-i18next';
import { ProTable, type ActionType, type ProColumns } from '@ant-design/pro-components';
import { Avatar, Button, App } from 'antd';
import { ShopOutlined } from '@ant-design/icons';

import {
  merchantChannelApi,
  type AvailableSalesChannel,
} from '@/lib/merchant-channel-api';
import { ApplyChannelModal } from '@/pages/settings/channels/components/apply-channel-modal';

export function AvailableChannelsPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { message } = App.useApp();
  const actionRef = useRef<ActionType>(null);

  const [applyModalOpen, setApplyModalOpen] = useState(false);
  const [selectedChannel, setSelectedChannel] = useState<AvailableSalesChannel | null>(null);

  const handleApply = (channel: AvailableSalesChannel) => {
    setSelectedChannel(channel);
    setApplyModalOpen(true);
  };

  const handleApplySuccess = () => {
    setApplyModalOpen(false);
    setSelectedChannel(null);
    actionRef.current?.reload();
    navigate('/channels/my-channels');
  };

  const columns: ProColumns<AvailableSalesChannel>[] = [
    {
      title: t('myChannels.channel'),
      dataIndex: 'name',
      width: 200,
      render: (_, record) => (
        <div className="flex items-center gap-3">
          {record.logoUrl ? (
            <Avatar src={record.logoUrl} size={32} shape="square" />
          ) : (
            <Avatar icon={<ShopOutlined />} size={32} shape="square" />
          )}
          <span className="font-medium">{record.name}</span>
        </div>
      ),
    },
    {
      title: t('myChannels.columnDescription'),
      dataIndex: 'description',
      ellipsis: true,
      render: (_, record) => record.description || '-',
    },
    {
      title: t('myChannels.currency'),
      dataIndex: 'currency',
      width: 100,
    },
    {
      title: t('common.actions'),
      valueType: 'option',
      width: 100,
      render: (_, record) => (
        <Button
          type="link"
          size="small"
          onClick={() => handleApply(record)}
        >
          {t('myChannels.apply')}
        </Button>
      ),
    },
  ];

  return (
    <div className="space-y-4">
      <ProTable<AvailableSalesChannel>
        actionRef={actionRef}
        columns={columns}
        rowKey="id"
        request={async () => {
          try {
            const result = await merchantChannelApi.getAvailableChannels();
            return {
              data: result.data,
              success: true,
              total: result.data.length,
            };
          } catch {
            message.error(t('common.error'));
            return {
              data: [],
              success: false,
              total: 0,
            };
          }
        }}
        search={false}
        options={{
          density: true,
          reload: true,
        }}
        pagination={false}
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
    </div>
  );
}
