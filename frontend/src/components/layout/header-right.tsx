import { Dropdown, Switch, Space, Avatar, type MenuProps } from 'antd';
import {
  GlobalOutlined,
  SunOutlined,
  MoonOutlined,
  UserOutlined,
  LogoutOutlined,
} from '@ant-design/icons';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';

import { useTheme } from '@/contexts/theme-context';
import { useAuth } from '@/contexts/auth-context';

export function HeaderRight() {
  const { t, i18n } = useTranslation();
  const { isDark, toggleTheme } = useTheme();
  const { user, logout } = useAuth();
  const navigate = useNavigate();

  const languageItems: MenuProps['items'] = [
    {
      key: 'zh',
      label: '中文',
      onClick: () => i18n.changeLanguage('zh'),
    },
    {
      key: 'en',
      label: 'English',
      onClick: () => i18n.changeLanguage('en'),
    },
  ];

  const userItems: MenuProps['items'] = [
    {
      key: 'profile',
      icon: <UserOutlined />,
      label: t('header.profile'),
      onClick: () => navigate('/profile'),
    },
    {
      type: 'divider',
    },
    {
      key: 'logout',
      icon: <LogoutOutlined />,
      label: t('header.logout'),
      onClick: () => {
        logout();
        navigate('/login');
      },
    },
  ];

  return (
    <Space size="middle">
      {/* Language Switcher */}
      <Dropdown menu={{ items: languageItems }} placement="bottomRight">
        <span className="cursor-pointer flex items-center gap-1">
          <GlobalOutlined />
          <span className="hidden sm:inline">{i18n.language === 'zh' ? '中文' : 'EN'}</span>
        </span>
      </Dropdown>

      {/* Theme Toggle */}
      <Switch
        checked={isDark}
        onChange={toggleTheme}
        checkedChildren={<MoonOutlined />}
        unCheckedChildren={<SunOutlined />}
      />

      {/* User Dropdown */}
      <Dropdown menu={{ items: userItems }} placement="bottomRight">
        <span className="cursor-pointer flex items-center gap-2">
          <Avatar size="small" icon={<UserOutlined />} />
          <span className="hidden sm:inline">{user?.email || 'User'}</span>
        </span>
      </Dropdown>
    </Space>
  );
}
