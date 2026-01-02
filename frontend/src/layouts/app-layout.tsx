import { useMemo, useState } from 'react';
import { Outlet, useNavigate, useLocation } from 'react-router';
import { ProLayout, PageContainer } from '@ant-design/pro-components';
import { useTranslation } from 'react-i18next';
import { ShopOutlined } from '@ant-design/icons';

import { getMenuData } from '@/config/menu';
import { HeaderRight } from '@/components/layout/header-right';
import { useAuth } from '@/contexts/auth-context';
import { useTheme } from '@/contexts/theme-context';
import { filterMenuByAccess } from '@/lib/menu-access';

export function AppLayout() {
  const { t, i18n } = useTranslation();
  const navigate = useNavigate();
  const location = useLocation();
  const [collapsed, setCollapsed] = useState(false);
  const { user } = useAuth();
  const { isDark } = useTheme();

  // eslint-disable-next-line react-hooks/preserve-manual-memoization
  const menuData = useMemo(() => {
    const allMenus = getMenuData(t);
    if (!user?.accountType) return allMenus;
    return filterMenuByAccess(allMenus, user.accountType);
  }, [t, user?.accountType]);

  return (
    <ProLayout
      title="DWLite"
      logo={<ShopOutlined style={{ fontSize: 28, color: '#6366f1' }} />}
      layout="mix"
      fixedHeader
      fixSiderbar
      collapsed={collapsed}
      onCollapse={setCollapsed}
      siderWidth={220}
      location={{ pathname: location.pathname }}
      menu={{
        params: { language: i18n.language },
        request: async () => menuData,
      }}
      menuItemRender={(item, dom) => (
        <div onClick={() => item.path && navigate(item.path)}>
          {dom}
        </div>
      )}
      subMenuItemRender={(_item, dom) => dom}
      actionsRender={() => [<HeaderRight key="header-right" />]}
      onMenuHeaderClick={() => navigate('/dashboard')}
      token={{
        header: {
          heightLayoutHeader: 56,
          colorBgHeader: isDark ? '#141414' : '#ffffff',
          colorBgRightActionsItemHover: 'transparent',
        },
        sider: {
          colorMenuBackground: isDark ? '#141414' : '#ffffff',
        },
      }}
    >
      <PageContainer
        header={{ title: false, breadcrumb: {} }}
        className="min-h-full"
      >
        <Outlet />
      </PageContainer>
    </ProLayout>
  );
}
