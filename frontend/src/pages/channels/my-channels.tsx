import { useRef } from 'react';
import type { ActionType } from '@ant-design/pro-components';

import { MyChannelsTab } from '@/pages/settings/channels/components/my-channels-tab';

export function MyChannelsPage() {
  const actionRef = useRef<ActionType>(null);

  return (
    <div className="flex flex-col gap-4">
      <MyChannelsTab actionRef={actionRef} />
    </div>
  );
}
