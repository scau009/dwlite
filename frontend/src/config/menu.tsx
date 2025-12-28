import type { MenuDataItem } from '@ant-design/pro-components';
import {
  DashboardOutlined,
  ShoppingOutlined,
  InboxOutlined,
  // DollarOutlined,
  // ShoppingCartOutlined,
  // CarOutlined,
  // BarChartOutlined,
  // SettingOutlined,
  TeamOutlined,
  HomeOutlined,
  ShopOutlined,
  BulbOutlined,
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
      path: '/opportunities',
      name: t('nav.opportunities'),
      icon: <BulbOutlined />,
      access: 'merchant',
    },
    {
      path: '/inventory',
      name: t('nav.inventory'),
      icon: <InboxOutlined />,
      access: 'merchant',
      children: [
        { path: '/inventory/stock', name: t('menu.stockQuery'), access: 'merchant' },
        { path: '/inventory/inbound', name: t('menu.inboundOrders'), access: 'merchant' },
        { path: '/inventory/exceptions', name: t('menu.inboundExceptions'), access: 'merchant' },
      ],
    },
    {
      path: '/warehouse',
      name: t('nav.warehouseOperations'),
      icon: <InboxOutlined />,
      access: 'warehouse',
      children: [
        { path: '/warehouse/inbound', name: t('menu.warehouseInbound'), access: 'warehouse' },
        { path: '/warehouse/outbound', name: t('menu.warehouseOutbound'), access: 'warehouse' },
        { path: '/warehouse/inventory', name: t('menu.warehouseInventory'), access: 'warehouse' },
      ],
    },
    // TODO: 价格管理 - 暂时隐藏
    // {
    //   path: '/pricing',
    //   name: t('nav.pricing'),
    //   icon: <DollarOutlined />,
    //   access: ['admin', 'merchant'],
    //   children: [
    //     { path: '/pricing', name: t('menu.priceList') },
    //     { path: '/pricing/rules', name: t('menu.priceRules'), access: 'admin' },
    //     { path: '/pricing/history', name: t('menu.priceHistory') },
    //   ],
    // },
    // TODO: 订单管理 - 暂时隐藏
    // {
    //   path: '/orders',
    //   name: t('nav.orders'),
    //   icon: <ShoppingCartOutlined />,
    //   access: ['admin', 'merchant'],
    //   children: [
    //     { path: '/orders', name: t('menu.orderList') },
    //     { path: '/orders/pending', name: t('menu.orderPending') },
    //     { path: '/orders/completed', name: t('menu.orderCompleted') },
    //     { path: '/orders/refunds', name: t('menu.orderRefunds') },
    //   ],
    // },
    // TODO: 履约管理 - 暂时隐藏
    // {
    //   path: '/fulfillment',
    //   name: t('nav.fulfillment'),
    //   icon: <CarOutlined />,
    //   access: ['admin', 'merchant'],
    //   children: [
    //     { path: '/fulfillment', name: t('menu.fulfillmentList') },
    //     { path: '/fulfillment/pending', name: t('menu.fulfillmentPending') },
    //     { path: '/fulfillment/shipped', name: t('menu.fulfillmentShipped') },
    //     { path: '/fulfillment/exceptions', name: t('menu.fulfillmentExceptions') },
    //   ],
    // },
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
      path: '/channels',
      name: t('nav.channels'),
      icon: <ShopOutlined />,
      access: 'admin',
      children: [
        { path: '/channels', name: t('menu.channelList') },
        { path: '/channels/merchants', name: t('menu.merchantChannels') },
      ],
    },
    {
      path: '/warehouses',
      name: t('nav.warehouses'),
      icon: <HomeOutlined />,
      access: 'admin',
      children: [
        { path: '/warehouses', name: t('menu.warehouseList') },
        { path: '/warehouses/users', name: t('menu.warehouseUsers'), access: 'admin' },
      ],
    },
    // TODO: 数据中心 - 暂时隐藏
    // {
    //   path: '/data',
    //   name: t('nav.dataCenter'),
    //   icon: <BarChartOutlined />,
    //   access: ['admin', 'merchant'],
    //   children: [
    //     { path: '/data', name: t('menu.dataOverview') },
    //     { path: '/data/sales', name: t('menu.salesAnalysis') },
    //     { path: '/data/inventory', name: t('menu.inventoryAnalysis') },
    //     { path: '/data/reports', name: t('menu.reports'), access: 'admin' },
    //   ],
    // },
    // TODO: 系统设置 - 暂时隐藏
    // {
    //   path: '/settings',
    //   name: t('nav.settings'),
    //   icon: <SettingOutlined />,
    //   access: ['admin', 'merchant'],
    //   children: [
    //     { path: '/settings/info', name: t('menu.generalSettings'), access: 'merchant' },
    //     { path: '/settings/wallet', name: t('menu.walletManagement'), access: 'merchant' },
    //     { path: '/settings/users', name: t('menu.userManagement'), access: 'admin' },
    //     { path: '/settings/roles', name: t('menu.roleManagement'), access: 'admin' },
    //     { path: '/settings/logs', name: t('menu.operationLogs'), access: 'admin' },
    //   ],
    // },
  ];
}
