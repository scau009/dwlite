import { Button, Card, Result, Typography } from 'antd';
import {
  ClockCircleOutlined,
  CloseCircleOutlined,
  StopOutlined,
  LogoutOutlined,
  EditOutlined,
} from '@ant-design/icons';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';

import { useAuth } from '@/contexts/auth-context';
import type { MerchantStatus } from '@/types/auth';

const { Paragraph, Text } = Typography;

export function MerchantPendingApprovalPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { user, logout } = useAuth();

  const merchantStatus = user?.merchantStatus as MerchantStatus | null;

  const handleLogout = async () => {
    await logout();
    navigate('/login');
  };

  const handleEditProfile = () => {
    navigate('/settings/profile');
  };

  const getStatusConfig = () => {
    switch (merchantStatus) {
      case 'pending':
        return {
          icon: <ClockCircleOutlined style={{ fontSize: 72, color: '#faad14' }} />,
          status: 'warning' as const,
          title: t('merchant.approval.pendingTitle'),
          subTitle: t('merchant.approval.pendingDescription'),
          showEditProfile: true,
        };
      case 'rejected':
        return {
          icon: <CloseCircleOutlined style={{ fontSize: 72, color: '#ff4d4f' }} />,
          status: 'error' as const,
          title: t('merchant.approval.rejectedTitle'),
          subTitle: t('merchant.approval.rejectedDescription'),
          showEditProfile: true,
        };
      case 'disabled':
        return {
          icon: <StopOutlined style={{ fontSize: 72, color: '#8c8c8c' }} />,
          status: 'error' as const,
          title: t('merchant.approval.disabledTitle'),
          subTitle: t('merchant.approval.disabledDescription'),
          showEditProfile: false,
        };
      default:
        return {
          icon: <ClockCircleOutlined style={{ fontSize: 72, color: '#faad14' }} />,
          status: 'warning' as const,
          title: t('merchant.approval.pendingTitle'),
          subTitle: t('merchant.approval.pendingDescription'),
          showEditProfile: true,
        };
    }
  };

  const config = getStatusConfig();

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-900 p-4">
      <Card className="w-full max-w-lg">
        <Result
          icon={config.icon}
          title={config.title}
          subTitle={config.subTitle}
          extra={
            <div className="flex flex-col gap-3">
              {merchantStatus === 'rejected' && user?.merchantRejectedReason && (
                <div className="text-left bg-red-50 dark:bg-red-900/20 p-4 rounded-lg mb-4">
                  <Text strong>{t('merchant.approval.rejectedReason')}:</Text>
                  <Paragraph className="mt-2 mb-0">
                    {user.merchantRejectedReason}
                  </Paragraph>
                </div>
              )}
              <div className="flex justify-center gap-3">
                {config.showEditProfile && (
                  <Button
                    type="primary"
                    icon={<EditOutlined />}
                    onClick={handleEditProfile}
                  >
                    {t('merchant.approval.editProfile')}
                  </Button>
                )}
                <Button icon={<LogoutOutlined />} onClick={handleLogout}>
                  {t('auth.logout')}
                </Button>
              </div>
            </div>
          }
        />
      </Card>
    </div>
  );
}
