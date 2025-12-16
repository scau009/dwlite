import { Outlet } from 'react-router';
import { ShopOutlined } from '@ant-design/icons';

export function AuthLayout() {
  return (
    <div className="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8"
         style={{ backgroundColor: 'var(--ant-color-bg-layout, #f5f5f5)' }}>
      <div className="w-full max-w-md space-y-6">
        <div className="text-center">
          <div className="flex items-center justify-center gap-2 mb-2">
            <ShopOutlined style={{ fontSize: 32, color: '#6366f1' }} />
            <h1 className="text-3xl font-bold" style={{ color: 'var(--ant-color-text, #000)' }}>
              DWLite
            </h1>
          </div>
          <p style={{ color: 'var(--ant-color-text-secondary, #666)' }}>
            Admin Console
          </p>
        </div>
        <Outlet />
      </div>
    </div>
  );
}
