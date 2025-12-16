import { Link, useNavigate, useLocation } from 'react-router';
import { useTranslation } from 'react-i18next';
import { LoginForm, ProFormText } from '@ant-design/pro-components';
import { Alert, App } from 'antd';
import { UserOutlined, LockOutlined } from '@ant-design/icons';

import { useAuth } from '@/contexts/auth-context';
import { validateLoginForm } from '@/lib/validation';
import type { ApiError } from '@/types/auth';

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
    <div>
      {successMessage && (
        <Alert
          message={successMessage}
          type="success"
          showIcon
          className="mb-4"
        />
      )}

      <LoginForm
        title={t('auth.login')}
        subTitle="Enter your email and password to access your account"
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
        Don't have an account?{' '}
        <Link to="/register" className="text-blue-500 hover:underline">
          {t('auth.register')}
        </Link>
      </div>
    </div>
  );
}
