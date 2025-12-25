import { useAuth } from '@/contexts/auth-context';
import { DashboardPage } from '@/pages/dashboard';
import { WarehouseDashboardPage } from '@/pages/warehouse-ops';

/**
 * Role-based dashboard that shows different content based on user account type.
 */
export function RoleBasedDashboard() {
  const { user } = useAuth();

  if (user?.accountType === 'warehouse') {
    return <WarehouseDashboardPage />;
  }

  // Default to admin/merchant dashboard
  return <DashboardPage />;
}
