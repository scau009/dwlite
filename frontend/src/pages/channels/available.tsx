import { useNavigate } from 'react-router';

import { AvailableChannelsTab } from '@/pages/settings/channels/components/available-channels-tab';

export function AvailableChannelsPage() {
  const navigate = useNavigate();

  const handleApplySuccess = () => {
    // 跳转到我的渠道页面
    navigate('/channels/my-channels');
  };

  return (
    <div className="flex flex-col gap-4">
      <AvailableChannelsTab onApplySuccess={handleApplySuccess} />
    </div>
  );
}
