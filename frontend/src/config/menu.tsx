import type { MenuDataItem } from '@ant-design/pro-components';
import {
  DashboardOutlined,
  ShoppingOutlined,
  InboxOutlined,
  DollarOutlined,
  // ShoppingCartOutlined,
  CarOutlined,
  // BarChartOutlined,
  SettingOutlined,
  TeamOutlined,
  HomeOutlined,
  ShopOutlined,
  BulbOutlined,
  FunctionOutlined,
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
        { path: '/inventory/inbound', name: t('menu.inboundOrders'), access: 'merchant' },
        { path: '/inventory/stock', name: t('menu.stockQuery'), access: 'merchant' },
        { path: '/inventory/outbound', name: t('menu.outboundOrders'), access: 'merchant' },
        { path: '/inventory/exceptions', name: t('menu.inboundExceptions'), access: 'merchant' },
        { path: '/inventory/warehouses', name: t('menu.merchantWarehouses'), access: 'merchant' },
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
    {
      path: '/merchants',
      name: t('nav.merchants'),
      icon: <TeamOutlined />,
      access: 'admin',
      children: [
        { path: '/merchants/list', name: t('menu.merchantList') },
        { path: '/merchants/api-keys', name: t('menu.merchantApiKeys') },
      ],
    },
    {
      path: '/channels',
      name: t('nav.channels'),
      icon: <ShopOutlined />,
      access: ['admin', 'merchant'],
      children: [
        { path: '/channels/list', name: t('menu.channelList'), access: 'admin' },
        { path: '/channels/products', name: t('menu.channelProducts'), access: 'admin' },
        { path: '/channels/merchants', name: t('menu.merchantChannels'), access: 'admin' },
        { path: '/channels/available', name: t('menu.availableChannels'), access: 'merchant' },
        { path: '/channels/my-channels', name: t('menu.myChannels'), access: 'merchant' },
        { path: '/channels/listings', name: t('menu.listingManagement'), access: 'merchant' },
        { path: '/channels/rules', name: t('menu.merchantRules'), access: 'merchant' },
        { path: '/channels/listings-logs', name: t('menu.listingLogs'), access: 'merchant' },
      ],
    },
    {
      path: '/fulfillment',
      name: t('nav.fulfillment'),
      icon: <CarOutlined />,
      access: 'admin',
      children: [
        { path: '/fulfillment/orders', name: t('menu.platformOrders'), access: 'admin' },
        { path: '/fulfillment/fulfillment-orders', name: t('menu.fulfillmentOrders'), access: 'admin' },
        { path: '/fulfillment/order-exceptions', name: t('menu.orderExceptions'), access: 'admin' },
      ],
    },
    {
      path: '/admin/inbound',
      name: t('nav.inventoryManagement'),
      icon: <InboxOutlined />,
      access: 'admin',
      children: [
        { path: '/admin/inbound/orders', name: t('menu.adminInboundOrders'), access: 'admin' },
        { path: '/admin/outbound/orders', name: t('menu.adminOutboundOrders'), access: 'admin' },
      ],
    },
    {
      path: '/merchant',
      name: t('nav.merchantSettlements'),
      icon: <DollarOutlined />,
      access: 'merchant',
      children: [
        { path: '/merchant/settlements', name: t('menu.mySettlements'), access: 'merchant' },
        { path: '/merchant/payouts', name: t('menu.myPayouts'), access: 'merchant' },
        { path: '/merchant/bank-accounts', name: t('menu.bankAccounts'), access: 'merchant' },
      ],
    },
    {
      path: '/warehouses',
      name: t('nav.warehouses'),
      icon: <HomeOutlined />,
      access: 'admin',
      children: [
        { path: '/warehouses/list', name: t('menu.warehouseList') },
        { path: '/warehouses/users', name: t('menu.warehouseUsers'), access: 'admin' },
      ],
    },
    {
      path: '/settlements',
      name: t('nav.settlements'),
      icon: <DollarOutlined />,
      access: 'admin',
      children: [
        { path: '/settlements/list', name: t('menu.settlementList'), access: 'admin' },
        { path: '/settlements/payouts', name: t('menu.payoutList'), access: 'admin' },
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
    {
      path: '/platform-rules',
      name: t('nav.platformRules'),
      icon: <FunctionOutlined />,
      access: 'admin',
    },
    {
      path: '/settings',
      name: t('nav.settings'),
      icon: <SettingOutlined />,
      access: 'merchant',
      children: [
        { path: '/settings/info', name: t('menu.generalSettings'), access: 'merchant' },
        { path: '/settings/wallet', name: t('menu.walletManagement'), access: 'merchant' },
        { path: '/settings/api-keys', name: t('menu.apiKeys'), access: 'merchant' },
      ],
    },
  ];
}
