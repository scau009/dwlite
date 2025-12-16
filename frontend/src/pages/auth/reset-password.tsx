import { Link, useSearchParams, useNavigate } from 'react-router';
import { useTranslation } from 'react-i18next';
import { ProForm, ProFormText } from '@ant-design/pro-components';
import { Card, Result, App } from 'antd';
import { LockOutlined } from '@ant-design/icons';

import { authApi } from '@/lib/auth-api';
import { validateResetPasswordForm } from '@/lib/validation';
import type { ApiError } from '@/types/auth';

export function ResetPasswordPage() {
  const { t } = useTranslation();
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const { message } = App.useApp();
  const token = searchParams.get('token') || '';

  if (!token) {
    return (
      <Card>
        <Result
          status="error"
          title="Invalid reset link"
          subTitle="This password reset link is invalid or has expired."
          extra={
            <Link to="/forgot-password" className="text-blue-500 hover:underline">
              Request a new reset link
            </Link>
          }
        />
      </Card>
    );
  }

  const handleSubmit = async (values: {
    password: string;
    confirmPassword: string;
  }) => {
    const validation = validateResetPasswordForm(
      values.password,
      values.confirmPassword
    );
    if (!validation.isValid) {
      const firstError = Object.values(validation.errors)[0];
      message.error(firstError);
      return false;
    }

    try {
      await authApi.resetPassword({ token, password: values.password });
      navigate('/login', {
        state: {
          message: 'Password reset successfully. Please log in with your new password.',
        },
      });
      return true;
    } catch (error) {
      const apiErr = error as ApiError;
      message.error(
        apiErr.error || 'Password reset failed. The link may have expired.'
      );
      return false;
    }
  };

  return (
    <Card>
      <div className="text-center mb-6">
        <h2 className="text-2xl font-semibold">{t('auth.resetPassword')}</h2>
        <p className="text-gray-500">Enter your new password below</p>
      </div>

      <ProForm
        layout="vertical"
        onFinish={handleSubmit}
        submitter={{
          searchConfig: {
            submitText: 'Reset password',
          },
          resetButtonProps: { style: { display: 'none' } },
          submitButtonProps: { block: true, size: 'large' },
        }}
      >
        <ProFormText.Password
          name="password"
          label="New Password"
          fieldProps={{
            size: 'large',
            prefix: <LockOutlined />,
          }}
          rules={[{ required: true, message: 'Please enter your new password' }]}
          extra="Min 8 characters with uppercase, lowercase, and number"
        />
        <ProFormText.Password
          name="confirmPassword"
          label="Confirm New Password"
          fieldProps={{
            size: 'large',
            prefix: <LockOutlined />,
          }}
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
    </Card>
  );
}
