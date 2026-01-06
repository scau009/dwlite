import { Link, useNavigate, useLocation } from 'react-router';
import { useTranslation } from 'react-i18next';
import { LoginForm, ProFormText } from '@ant-design/pro-components';
import { Alert, App } from 'antd';
import { UserOutlined, LockOutlined, ShopOutlined } from '@ant-design/icons';

import { useAuth } from '@/contexts/auth-context';
import { validateLoginForm } from '@/lib/validation';
import type { ApiError } from '@/types/auth';
import { ShoeWall } from './components/shoe-wall';

export function LoginPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const location = useLocation();
  const { login } = useAuth();
  const { message } = App.useApp();

  // Get success message from registration or email verification
  const successMessage = (location.state as { message?: string })?.message;

  const handleSubmit = async (values: { email: string; password: string }) => {
    const validation = validateLoginForm(values.email, values.password);
    if (!validation.isValid) {
      const firstError = Object.values(validation.errors)[0];
      message.error(firstError);
      return false;
    }

    try {
      await login(values);
      navigate('/dashboard');
      return true;
    } catch (error) {
      const apiErr = error as ApiError;
      message.error(apiErr.error || 'Login failed. Please try again.');
      return false;
    }
  };

  return (
    <div className="h-screen flex overflow-hidden">
      {/* Left side - Shoe Wall (hidden on mobile) */}
      <div className="hidden lg:flex lg:w-1/2 xl:w-[55%] h-full">
        <ShoeWall />
      </div>

      {/* Right side - Login Form */}
      <div
        className="flex-1 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8"
        style={{ backgroundColor: 'var(--ant-color-bg-layout, #f5f5f5)' }}
      >
        <div className="w-full max-w-md space-y-6">
          {/* Logo and branding */}
          <div className="text-center">
            <div className="flex items-center justify-center gap-2 mb-2">
              <ShopOutlined style={{ fontSize: 32, color: '#6366f1' }} />
              <h1
                className="text-3xl font-bold"
                style={{ color: 'var(--ant-color-text, #000)' }}
              >
                DWLite
              </h1>
            </div>
            <p style={{ color: 'var(--ant-color-text-secondary, #666)' }}>
              {t('auth.loginSubtitle', 'Sign in to your account')}
            </p>
          </div>

          {/* Success message alert */}
          {successMessage && (
            <Alert
              message={successMessage}
              type="success"
              showIcon
              className="mb-4"
            />
          )}

          {/* Login form */}
          <LoginForm
            title=""
            subTitle=""
            onFinish={handleSubmit}
            submitter={{
              searchConfig: {
                submitText: t('auth.login'),
              },
            }}
          >
            <ProFormText
              name="email"
              fieldProps={{
                size: 'large',
                prefix: <UserOutlined />,
              }}
              placeholder="you@example.com"
              rules={[
                { required: true, message: 'Please enter your email' },
                { type: 'email', message: 'Please enter a valid email' },
              ]}
            />
            <ProFormText.Password
              name="password"
              fieldProps={{
                size: 'large',
                prefix: <LockOutlined />,
              }}
              placeholder={t('auth.password')}
              rules={[{ required: true, message: 'Please enter your password' }]}
            />

            <div className="mb-4">
              <Link to="/forgot-password" className="text-blue-500 hover:underline">
                {t('auth.forgotPassword')}
              </Link>
            </div>
          </LoginForm>

          <div className="text-center mt-4">
            <span style={{ color: 'var(--ant-color-text-secondary, #666)' }}>
              {t('auth.noAccount', "Don't have an account?")}{' '}
            </span>
            <Link to="/register" className="text-blue-500 hover:underline">
              {t('auth.register')}
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
}
