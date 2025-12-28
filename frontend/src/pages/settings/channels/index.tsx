import { useState, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { Tabs, Card } from 'antd';
import type { ActionType } from '@ant-design/pro-components';

import { AvailableChannelsTab } from './components/available-channels-tab';
import { MyChannelsTab } from './components/my-channels-tab';

export function MerchantChannelsPage() {
  const { t } = useTranslation();
  const [activeTab, setActiveTab] = useState('available');
  const myChannelsRef = useRef<ActionType>(null);

  const handleApplySuccess = () => {
    // 切换到我的渠道 Tab 并刷新
    setActiveTab('my');
    myChannelsRef.current?.reload();
  };

  const items = [
    {
      key: 'available',
      label: t('myChannels.availableChannels'),
      children: <AvailableChannelsTab onApplySuccess={handleApplySuccess} />,
    },
    {
      key: 'my',
      label: t('myChannels.myChannels'),
      children: <MyChannelsTab actionRef={myChannelsRef} />,
    },
  ];

  return (
    <div className="flex flex-col gap-4">
      <div>
        <h1 className="text-xl font-semibold m-0">{t('myChannels.title')}</h1>
        <p className="text-gray-500 mt-1">{t('myChannels.description')}</p>
      </div>

      <Card>
        <Tabs activeKey={activeTab} onChange={setActiveTab} items={items} />
      </Card>
    </div>
  );
}
