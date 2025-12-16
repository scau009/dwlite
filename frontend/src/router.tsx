import { createBrowserRouter, Navigate, Outlet } from 'react-router';
import { useAuth } from '@/contexts/auth-context';

// Layouts
import { AuthLayout } from '@/layouts/auth-layout';
import { AppLayout } from '@/layouts/app-layout';

// Auth pages
import { LoginPage } from '@/pages/auth/login';
import { RegisterPage } from '@/pages/auth/register';
import { ForgotPasswordPage } from '@/pages/auth/forgot-password';
import { ResetPasswordPage } from '@/pages/auth/reset-password';
import { VerifyEmailPage } from '@/pages/auth/verify-email';

// App pages
import { DashboardPage } from '@/pages/dashboard';
import { ProfilePage } from '@/pages/profile';
import { ProductsListPage, ProductDetailPage, ProductFormPage } from '@/pages/products';

// Placeholder component for pages not yet implemented
function PlaceholderPage({ title }: { title: string }) {
  return (
    <div className="flex flex-col items-center justify-center h-[50vh] text-center">
      <h1 className="text-2xl font-bold mb-2">{title}</h1>
      <p className="text-muted-foreground">This page is under construction.</p>
    </div>
  );
}

// Protected route component
function ProtectedRoute() {
  const { isAuthenticated, isLoading } = useAuth();

  if (isLoading) {
    return (
      <div className="flex min-h-screen items-center justify-center">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary" />
      </div>
    );
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />;
  }

  return <Outlet />;
}

// Guest route component (redirect to dashboard if authenticated)
function GuestRoute() {
  const { isAuthenticated, isLoading } = useAuth();

  if (isLoading) {
    return (
      <div className="flex min-h-screen items-center justify-center">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary" />
      </div>
    );
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
          // Dashboard
          { path: '/dashboard', element: <DashboardPage /> },

          // Products
          { path: '/products', element: <ProductsListPage /> },
          { path: '/products/new', element: <ProductFormPage /> },
          { path: '/products/:id', element: <ProductDetailPage /> },
          { path: '/products/:id/edit', element: <ProductFormPage /> },
          { path: '/products/categories', element: <PlaceholderPage title="Product Categories" /> },
          { path: '/products/attributes', element: <PlaceholderPage title="Product Attributes" /> },

          // Inventory
          { path: '/inventory', element: <PlaceholderPage title="Inventory List" /> },
          { path: '/inventory/alerts', element: <PlaceholderPage title="Stock Alerts" /> },
          { path: '/inventory/logs', element: <PlaceholderPage title="Stock Logs" /> },

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

          // Data Center
          { path: '/data', element: <PlaceholderPage title="Data Overview" /> },
          { path: '/data/sales', element: <PlaceholderPage title="Sales Analysis" /> },
          { path: '/data/inventory', element: <PlaceholderPage title="Inventory Analysis" /> },
          { path: '/data/reports', element: <PlaceholderPage title="Reports" /> },

          // Settings
          { path: '/settings', element: <PlaceholderPage title="General Settings" /> },
          { path: '/settings/users', element: <PlaceholderPage title="User Management" /> },
          { path: '/settings/roles', element: <PlaceholderPage title="Role Management" /> },
          { path: '/settings/logs', element: <PlaceholderPage title="Operation Logs" /> },

          // Profile
          { path: '/profile', element: <ProfilePage /> },
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