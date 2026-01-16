import { createBrowserRouter, Navigate, Outlet } from 'react-router';
import { Empty, Spin } from 'antd';
import { LoadingOutlined } from '@ant-design/icons';

import { useAuth } from '@/contexts/auth-context';

// Layouts
import { AuthLayout } from '@/layouts/auth-layout';
import { AppLayout } from '@/layouts/app-layout';

// Components
import { AccessRoute } from '@/components/access-route';

// Auth pages
import { LoginPage } from '@/pages/auth/login';
import { RegisterPage } from '@/pages/auth/register';
import { ForgotPasswordPage } from '@/pages/auth/forgot-password';
import { ResetPasswordPage } from '@/pages/auth/reset-password';
import { VerifyEmailPage } from '@/pages/auth/verify-email';

// App pages
import { RoleBasedDashboard } from '@/components/role-based-dashboard';
import { ProfilePage } from '@/pages/profile';
import { ProductsListPage, ProductDetailPage } from '@/pages/products';
import { MerchantsListPage } from '@/pages/merchants';
import { BrandsListPage } from '@/pages/brands';
import { ChannelsListPage, MerchantChannelsListPage, AvailableChannelsPage, MyChannelsPage } from '@/pages/channels';
import { ChannelProductsListPage } from '@/pages/channels/products/list';
import { ChannelProductDetailPage } from '@/pages/channels/products/detail';
import { ListingsListPage, CreateListingPage, EditListingPage, ListingLogsPage } from '@/pages/listings';
import { CategoriesListPage } from '@/pages/categories';
import { TagsListPage } from '@/pages/tags';
import {
  OpportunitiesListPage,
  InboundOrdersListPage,
  InboundOrderDetailPage,
  InboundExceptionsListPage,
  InboundExceptionDetailPage,
  MerchantStockListPage,
  AddInventoryPage,
  ImportInventoryPage,
  OutboundOrdersListPage,
  OutboundOrderDetailPage,
  MerchantWarehousesListPage,
} from '@/pages/inventory';
import { WarehousesListPage, WarehouseUsersListPage } from '@/pages/warehouses';
import {
  WarehouseInboundListPage,
  WarehouseInboundDetailPage,
  WarehouseOutboundListPage,
  WarehouseOutboundDetailPage,
  WarehouseInventoryListPage,
} from '@/pages/warehouse-ops';
import { MerchantProfilePage, MerchantWalletPage, ApiKeysPage } from '@/pages/settings';
import { MerchantRulesPage } from '@/pages/settings/rules';
import { RuleFormPage } from '@/pages/settings/rules/form';
import { PlatformRulesListPage, PlatformRuleFormPage } from '@/pages/platform-rules';
import { OrderExceptionsListPage, OrderExceptionDetailPage } from '@/pages/fulfillment/order-exceptions';
import { PlatformOrdersListPage, PlatformOrderDetailPage } from '@/pages/fulfillment/orders';
import { FulfillmentOrdersListPage, FulfillmentOrderDetailPage } from '@/pages/fulfillment/fulfillment-orders';
import { SettlementsListPage, SettlementDetailPage } from '@/pages/settlements';
import { PayoutsListPage, PayoutDetailPage } from '@/pages/settlements/payouts';
import { BankAccountsListPage } from '@/pages/merchant/bank-accounts';
import { MerchantSettlementsListPage, MerchantSettlementDetailPage } from '@/pages/merchant/settlements';
import { MerchantPayoutsListPage } from '@/pages/merchant/payouts';

// Placeholder component for pages not yet implemented
// eslint-disable-next-line react-refresh/only-export-components
function PlaceholderPage({ title }: { title: string }) {
  return (
    <div className="flex flex-col items-center justify-center h-[50vh]">
      <Empty
        description={
          <div>
            <h1 className="text-xl font-semibold mb-2">{title}</h1>
            <p className="text-gray-500">This page is under construction.</p>
          </div>
        }
      />
    </div>
  );
}

// Loading component
// eslint-disable-next-line react-refresh/only-export-components
function LoadingScreen() {
  return (
    <div className="flex min-h-screen items-center justify-center">
      <Spin indicator={<LoadingOutlined style={{ fontSize: 32 }} spin />} />
    </div>
  );
}

// Protected route component
// eslint-disable-next-line react-refresh/only-export-components
function ProtectedRoute() {
  const { isAuthenticated, isLoading } = useAuth();

  if (isLoading) {
    return <LoadingScreen />;
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />;
  }

  return <Outlet />;
}

// Guest route component (redirect to dashboard if authenticated)
// eslint-disable-next-line react-refresh/only-export-components
function GuestRoute() {
  const { isAuthenticated, isLoading } = useAuth();

  if (isLoading) {
    return <LoadingScreen />;
  }

  if (isAuthenticated) {
    return <Navigate to="/dashboard" replace />;
  }

  return <Outlet />;
}

export const router = createBrowserRouter([
  // Guest routes (login, register, etc.)
  {
    element: <GuestRoute />,
    children: [
      // Login page has its own full layout with shoe wall
      { path: '/login', element: <LoginPage /> },
      // Other auth pages use the standard AuthLayout
      {
        element: <AuthLayout />,
        children: [
          { path: '/register', element: <RegisterPage /> },
          { path: '/forgot-password', element: <ForgotPasswordPage /> },
          { path: '/reset-password', element: <ResetPasswordPage /> },
          { path: '/verify-email', element: <VerifyEmailPage /> },
        ],
      },
    ],
  },
  // Protected routes (app)
  {
    element: <ProtectedRoute />,
    children: [
      {
        element: <AppLayout />,
        children: [
          {
            element: <AccessRoute />,
            children: [
              // Dashboard
              { path: '/dashboard', element: <RoleBasedDashboard /> },

              // Products
              { path: '/products/list', element: <ProductsListPage /> },
              { path: '/products/detail/:id', element: <ProductDetailPage /> },
              { path: '/products/categories', element: <CategoriesListPage /> },
              { path: '/products/brands', element: <BrandsListPage /> },
              { path: '/products/tags', element: <TagsListPage /> },

              // Pricing
              { path: '/pricing', element: <PlaceholderPage title="Price List" /> },
              { path: '/pricing/rules', element: <PlaceholderPage title="Price Rules" /> },
              { path: '/pricing/history', element: <PlaceholderPage title="Price History" /> },

              // Orders
              { path: '/orders', element: <PlaceholderPage title="Order List" /> },
              { path: '/orders/pending', element: <PlaceholderPage title="Pending Orders" /> },
              { path: '/orders/completed', element: <PlaceholderPage title="Completed Orders" /> },
              { path: '/orders/refunds', element: <PlaceholderPage title="Refunds" /> },

              // Fulfillment
              { path: '/fulfillment', element: <Navigate to="/fulfillment/orders" replace /> },
              { path: '/fulfillment/orders', element: <PlatformOrdersListPage /> },
              { path: '/fulfillment/orders/:id', element: <PlatformOrderDetailPage /> },
              { path: '/fulfillment/fulfillment-orders', element: <FulfillmentOrdersListPage /> },
              { path: '/fulfillment/fulfillment-orders/:id', element: <FulfillmentOrderDetailPage /> },
              { path: '/fulfillment/order-exceptions', element: <OrderExceptionsListPage /> },
              { path: '/fulfillment/order-exceptions/:id', element: <OrderExceptionDetailPage /> },

              // Settlements
              { path: '/settlements', element: <Navigate to="/settlements/list" replace /> },
              { path: '/settlements/list', element: <SettlementsListPage /> },
              { path: '/settlements/detail/:id', element: <SettlementDetailPage /> },
              { path: '/settlements/payouts', element: <PayoutsListPage /> },
              { path: '/settlements/payouts/:id', element: <PayoutDetailPage /> },

              // Opportunities
              { path: '/opportunities', element: <OpportunitiesListPage /> },

              // Inventory
              { path: '/inventory/warehouses', element: <MerchantWarehousesListPage /> },
              { path: '/inventory/stock', element: <MerchantStockListPage /> },
              { path: '/inventory/stock/add', element: <AddInventoryPage /> },
              { path: '/inventory/stock/import', element: <ImportInventoryPage /> },
              { path: '/inventory/inbound', element: <InboundOrdersListPage /> },
              { path: '/inventory/inbound/detail/:id', element: <InboundOrderDetailPage /> },
              { path: '/inventory/outbound', element: <OutboundOrdersListPage /> },
              { path: '/inventory/outbound/detail/:id', element: <OutboundOrderDetailPage /> },
              { path: '/inventory/exceptions', element: <InboundExceptionsListPage /> },
              { path: '/inventory/exceptions/detail/:id', element: <InboundExceptionDetailPage /> },

              // Merchants
              { path: '/merchants', element: <MerchantsListPage /> },

              // Channels
              { path: '/channels', element: <Navigate to="/channels/list" replace /> },
              { path: '/channels/list', element: <ChannelsListPage /> },
              { path: '/channels/products', element: <ChannelProductsListPage /> },
              { path: '/channels/products/:id', element: <ChannelProductDetailPage /> },
              { path: '/channels/merchants', element: <MerchantChannelsListPage /> },
              { path: '/channels/available', element: <AvailableChannelsPage /> },
              { path: '/channels/my-channels', element: <MyChannelsPage /> },
              { path: '/channels/listings', element: <ListingsListPage /> },
              { path: '/channels/listings/create', element: <CreateListingPage /> },
              { path: '/channels/listings/:id/edit', element: <EditListingPage /> },
              { path: '/channels/listings-logs', element: <ListingLogsPage /> },
              { path: '/channels/rules', element: <MerchantRulesPage /> },
              { path: '/channels/rules/create', element: <RuleFormPage /> },
              { path: '/channels/rules/:id/edit', element: <RuleFormPage /> },

              // Warehouses (Admin)
              { path: '/warehouses', element: <Navigate to="/warehouses/list" replace /> },
              { path: '/warehouses/list', element: <WarehousesListPage /> },
              { path: '/warehouses/users', element: <WarehouseUsersListPage /> },

              // Warehouse Operations (Warehouse users)
              { path: '/warehouse/inbound', element: <WarehouseInboundListPage /> },
              { path: '/warehouse/inbound/:id', element: <WarehouseInboundDetailPage /> },
              { path: '/warehouse/outbound', element: <WarehouseOutboundListPage /> },
              { path: '/warehouse/outbound/:id', element: <WarehouseOutboundDetailPage /> },
              { path: '/warehouse/inventory', element: <WarehouseInventoryListPage /> },

              // Data Center
              { path: '/data', element: <PlaceholderPage title="Data Overview" /> },
              { path: '/data/sales', element: <PlaceholderPage title="Sales Analysis" /> },
              { path: '/data/inventory', element: <PlaceholderPage title="Inventory Analysis" /> },
              { path: '/data/reports', element: <PlaceholderPage title="Reports" /> },

              // Platform Rules (Admin)
              { path: '/platform-rules', element: <PlatformRulesListPage /> },
              { path: '/platform-rules/create', element: <PlatformRuleFormPage /> },
              { path: '/platform-rules/:id/edit', element: <PlatformRuleFormPage /> },

              // Merchant Settlement Center
              { path: '/merchant', element: <Navigate to="/merchant/settlements" replace /> },
              { path: '/merchant/settlements', element: <MerchantSettlementsListPage /> },
              { path: '/merchant/settlements/:id', element: <MerchantSettlementDetailPage /> },
              { path: '/merchant/payouts', element: <MerchantPayoutsListPage /> },
              { path: '/merchant/bank-accounts', element: <BankAccountsListPage /> },

              // Settings
              { path: '/settings/info', element: <MerchantProfilePage /> },
              { path: '/settings/wallet', element: <MerchantWalletPage /> },
              { path: '/settings/api-keys', element: <ApiKeysPage /> },
              { path: '/settings/users', element: <PlaceholderPage title="User Management" /> },
              { path: '/settings/roles', element: <PlaceholderPage title="Role Management" /> },
              { path: '/settings/logs', element: <PlaceholderPage title="Operation Logs" /> },

              // Profile
              { path: '/profile', element: <ProfilePage /> },
            ],
          },
        ],
      },
    ],
  },
  // Root redirect
  {
    path: '/',
    element: <Navigate to="/dashboard" replace />,
  },
  // Catch-all redirect
  {
    path: '*',
    element: <Navigate to="/dashboard" replace />,
  },
]);
