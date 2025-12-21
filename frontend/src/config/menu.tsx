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
  HomeOutlined,
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
      access: 'admin',
      children: [
        { path: '/products/list', name: t('menu.productList') },
        { path: '/products/categories', name: t('menu.productCategories'), access: 'admin' },
        { path: '/products/brands', name: t('menu.productBrands'), access: 'admin' },
        { path: '/products/tags', name: t('menu.productTags'), access: 'admin' },
      ],
    },
    {
      path: '/inventory',
      name: t('nav.inventory'),
      icon: <InboxOutlined />,
      access: 'merchant',
      children: [
        { path: '/inventory/inbound', name: t('menu.inboundOrders'), access: 'merchant' },
        { path: '/inventory/shipments', name: t('menu.inboundShipments'), access: 'merchant' },
        { path: '/inventory/exceptions', name: t('menu.inboundExceptions'), access: 'merchant' },
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
      path: '/warehouses',
      name: t('nav.warehouses'),
      icon: <HomeOutlined />,
      access: 'admin',
      children: [
        { path: '/warehouses', name: t('menu.warehouseList') },
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
        { path: '/settings/info', name: t('menu.generalSettings'), access: 'merchant' },
        { path: '/settings/wallet', name: t('menu.walletManagement'), access: 'merchant' },
        { path: '/settings/users', name: t('menu.userManagement'), access: 'admin' },
        { path: '/settings/roles', name: t('menu.roleManagement'), access: 'admin' },
        { path: '/settings/logs', name: t('menu.operationLogs'), access: 'admin' },
      ],
    },
  ];
}
