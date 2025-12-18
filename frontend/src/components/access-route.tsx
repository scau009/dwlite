import { Outlet, useLocation } from 'react-router';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { Result, Button } from 'antd';

import { useAuth } from '@/contexts/auth-context';
import { getMenuData } from '@/config/menu';
import { getAccessMap, canAccessPath } from '@/lib/menu-access';

/**
 * Route-level access control component
 * Checks if the current user can access the current route
 * Redirects to dashboard or shows 403 page if not authorized
 */
export function AccessRoute() {
  const { user } = useAuth();
  const location = useLocation();
  const { t } = useTranslation();

  const accessMap = useMemo(() => {
    const menus = getMenuData(t);
    return getAccessMap(menus);
  }, [t]);

  const hasAccess = useMemo(() => {
    if (!user?.accountType) return true;
    return canAccessPath(location.pathname, user.accountType, accessMap);
  }, [user?.accountType, location.pathname, accessMap]);

  if (!hasAccess) {
    return (
      <Result
        status="403"
        title="403"
        subTitle={t('error.noPermission', 'Sorry, you do not have permission to access this page.')}
        extra={
          <Button type="primary" onClick={() => window.history.back()}>
            {t('action.goBack', 'Go Back')}
          </Button>
        }
      />
    );
  }

  return <Outlet />;
}