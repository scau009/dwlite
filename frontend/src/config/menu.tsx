import type { MenuDataItem } from '@ant-design/pro-components';
import {
  DashboardOutlined,
  ShoppingOutlined,
  InboxOutlined,
  DollarOutlined,
  ShoppingCartOutlined,
  CarOutlined,
  BarChartOutlined,
  SettingOutlined,
  TeamOutlined,
} from '@ant-design/icons';
import type { TFunction } from 'i18next';
import type { AccountType } from '@/types/auth';

// Extended menu item with access control
export interface AccessMenuDataItem extends MenuDataItem {
  access?: AccountType | AccountType[];
  children?: AccessMenuDataItem[];
}

export function getMenuData(t: TFunction): AccessMenuDataItem[] {
  return [
    {
      path: '/dashboard',
      name: t('nav.dashboard'),
      icon: <DashboardOutlined />,
    },
    {
      path: '/products',
      name: t('nav.products'),
      icon: <ShoppingOutlined />,
      children: [
        { path: '/products', name: t('menu.productList') },
        { path: '/products/categories', name: t('menu.productCategories'), access: 'admin' },
        { path: '/products/attributes', name: t('menu.productAttributes'), access: 'admin' },
      ],
    },
    {
      path: '/inventory',
      name: t('nav.inventory'),
      icon: <InboxOutlined />,
      children: [
        { path: '/inventory', name: t('menu.inventoryList') },
        { path: '/inventory/alerts', name: t('menu.inventoryAlerts') },
        { path: '/inventory/logs', name: t('menu.inventoryLogs') },
      ],
    },
    {
      path: '/pricing',
      name: t('nav.pricing'),
      icon: <DollarOutlined />,
      children: [
        { path: '/pricing', name: t('menu.priceList') },
        { path: '/pricing/rules', name: t('menu.priceRules'), access: 'admin' },
        { path: '/pricing/history', name: t('menu.priceHistory') },
      ],
    },
    {
      path: '/orders',
      name: t('nav.orders'),
      icon: <ShoppingCartOutlined />,
      children: [
        { path: '/orders', name: t('menu.orderList') },
        { path: '/orders/pending', name: t('menu.orderPending') },
        { path: '/orders/completed', name: t('menu.orderCompleted') },
        { path: '/orders/refunds', name: t('menu.orderRefunds') },
      ],
    },
    {
      path: '/fulfillment',
      name: t('nav.fulfillment'),
      icon: <CarOutlined />,
      children: [
        { path: '/fulfillment', name: t('menu.fulfillmentList') },
        { path: '/fulfillment/pending', name: t('menu.fulfillmentPending') },
        { path: '/fulfillment/shipped', name: t('menu.fulfillmentShipped') },
        { path: '/fulfillment/exceptions', name: t('menu.fulfillmentExceptions') },
      ],
    },
    {
      path: '/merchants',
      name: t('nav.merchants'),
      icon: <TeamOutlined />,
      access: 'admin',
      children: [
        { path: '/merchants', name: t('menu.merchantList') },
      ],
    },
    {
      path: '/data',
      name: t('nav.dataCenter'),
      icon: <BarChartOutlined />,
      children: [
        { path: '/data', name: t('menu.dataOverview') },
        { path: '/data/sales', name: t('menu.salesAnalysis') },
        { path: '/data/inventory', name: t('menu.inventoryAnalysis') },
        { path: '/data/reports', name: t('menu.reports'), access: 'admin' },
      ],
    },
    {
      path: '/settings',
      name: t('nav.settings'),
      icon: <SettingOutlined />,
      children: [
        { path: '/settings', name: t('menu.generalSettings') },
        { path: '/settings/users', name: t('menu.userManagement'), access: 'admin' },
        { path: '/settings/roles', name: t('menu.roleManagement'), access: 'admin' },
        { path: '/settings/logs', name: t('menu.operationLogs'), access: 'admin' },
      ],
    },
  ];
}
