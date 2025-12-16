import { Link, useNavigate } from 'react-router';
import { useTranslation } from 'react-i18next';
import { ProForm, ProFormText } from '@ant-design/pro-components';
import { Card, App } from 'antd';
import { UserOutlined, LockOutlined } from '@ant-design/icons';

import { useAuth } from '@/contexts/auth-context';
import { validateRegisterForm } from '@/lib/validation';
import type { ApiError } from '@/types/auth';

export function RegisterPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { register } = useAuth();
  const { message } = App.useApp();

  const handleSubmit = async (values: {
    email: string;
    password: string;
    confirmPassword: string;
  }) => {
    const validation = validateRegisterForm(
      values.email,
      values.password,
      values.confirmPassword
    );
    if (!validation.isValid) {
      const firstError = Object.values(validation.errors)[0];
      message.error(firstError);
      return false;
    }

    try {
      const result = await register({
        email: values.email,
        password: values.password,
      });
      navigate('/login', { state: { message: result.message } });
      return true;
    } catch (error) {
      const apiErr = error as ApiError;
      message.error(apiErr.error || 'Registration failed. Please try again.');
      return false;
    }
  };

  return (
    <Card>
      <div className="text-center mb-6">
        <h2 className="text-2xl font-semibold">{t('auth.register')}</h2>
        <p className="text-gray-500">Enter your details to create a new account</p>
      </div>

      <ProForm
        layout="vertical"
        onFinish={handleSubmit}
        submitter={{
          searchConfig: {
            submitText: 'Create account',
          },
          resetButtonProps: { style: { display: 'none' } },
          submitButtonProps: { block: true, size: 'large' },
        }}
      >
        <ProFormText
          name="email"
          label={t('auth.email')}
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
          label={t('auth.password')}
          fieldProps={{
            size: 'large',
            prefix: <LockOutlined />,
          }}
          placeholder={t('auth.password')}
          rules={[{ required: true, message: 'Please enter your password' }]}
          extra="Min 8 characters with uppercase, lowercase, and number"
        />
        <ProFormText.Password
          name="confirmPassword"
          label="Confirm Password"
          fieldProps={{
            size: 'large',
            prefix: <LockOutlined />,
          }}
          placeholder="Confirm password"
          rules={[
            { required: true, message: 'Please confirm your password' },
            ({ getFieldValue }) => ({
              validator(_, value) {
                if (!value || getFieldValue('password') === value) {
                  return Promise.resolve();
                }
                return Promise.reject(new Error('Passwords do not match'));
              },
            }),
          ]}
        />
      </ProForm>

      <div className="text-center mt-4">
        Already have an account?{' '}
        <Link to="/login" className="text-blue-500 hover:underline">
          {t('auth.login')}
        </Link>
      </div>
    </Card>
  );
}
