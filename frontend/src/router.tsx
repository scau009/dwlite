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
import { CategoriesListPage } from '@/pages/categories';
import { TagsListPage } from '@/pages/tags';
import {
  OpportunitiesListPage,
  InboundOrdersListPage,
  InboundOrderDetailPage,
  InboundExceptionsListPage,
  InboundExceptionDetailPage,
  MerchantStockListPage,
  OutboundOrdersListPage,
  OutboundOrderDetailPage,
} from '@/pages/inventory';
import { WarehousesListPage, WarehouseUsersListPage } from '@/pages/warehouses';
import {
  WarehouseInboundListPage,
  WarehouseInboundDetailPage,
  WarehouseOutboundListPage,
  WarehouseOutboundDetailPage,
  WarehouseInventoryListPage,
} from '@/pages/warehouse-ops';
import { MerchantProfilePage, MerchantWalletPage, MerchantChannelsPage } from '@/pages/settings';
import { MerchantRulesPage } from '@/pages/settings/rules';
import { PlatformRulesListPage } from '@/pages/platform-rules/list';

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
      {
        element: <AuthLayout />,
        children: [
          { path: '/login', element: <LoginPage /> },
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
              { path: '/fulfillment', element: <PlaceholderPage title="Fulfillment List" /> },
              { path: '/fulfillment/pending', element: <PlaceholderPage title="Pending Shipment" /> },
              { path: '/fulfillment/shipped', element: <PlaceholderPage title="Shipped" /> },
              { path: '/fulfillment/exceptions', element: <PlaceholderPage title="Fulfillment Exceptions" /> },

              // Opportunities
              { path: '/opportunities', element: <OpportunitiesListPage /> },

              // Inventory
              { path: '/inventory/stock', element: <MerchantStockListPage /> },
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
              { path: '/channels/merchants', element: <MerchantChannelsListPage /> },
              { path: '/channels/available', element: <AvailableChannelsPage /> },
              { path: '/channels/my-channels', element: <MyChannelsPage /> },
              { path: '/channels/rules', element: <MerchantRulesPage /> },

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

              // Settings
              { path: '/settings/info', element: <MerchantProfilePage /> },
              { path: '/settings/wallet', element: <MerchantWalletPage /> },
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
